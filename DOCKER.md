# Running and deploying this application

This template builds two container images from one context — **php** (PHP-FPM running the
application) and **nginx** (static assets, proxying everything else to php). They are tagged
together, so the pair can never disagree about which release it is serving.

A **Release** is one immutable image identified by content digest. Deploying and rolling back are
the same operation: point a host at a different digest and restart. Nothing is built, resolved, or
updated on a production host.

> Production topology — compose files, the Traefik stack, and secrets — lives in the **ops
> repository**, `gcgov/deploy`, not here. This repository builds Releases; the ops repository
> decides what runs.

---

## Local development

```bash
vendor/bin/gf env --init      # write .env from what config.json references
cp .env.example .env.local    # and the variables docker compose needs
# fill in the blanks in .env, then:
docker compose up --build
open http://localhost:8080
```

The working tree is bind-mounted into the php container and opcache revalidates on every request
(`docker/php/conf.d/dev.ini`), so edits are live.

```bash
docker compose exec php vendor/bin/gf env         # does the configuration resolve?
docker compose exec php vendor/bin/gf cli:list
docker compose exec php composer ci
```

---

## Configuration

One committed `config.json`. Every `%env(...)%` reference in it is **required** — there are no
defaults, and a variable set to the empty string counts as unset. A production container that
forgets a variable fails to start and names it, rather than booting in some half-configured state.

```bash
vendor/bin/gf env --list      # what this application needs, and which are secrets
vendor/bin/gf env             # resolve it against the current environment
```

The full reference is `readme/environment-variables.md` in the framework.

---

## Secrets

Secrets reach a container as **files**, never as environment variables:

```jsonc
// config.json — the same file in every environment
"uri": "%env(secret:MONGO_URI)%"
```

`%env(secret:MONGO_URI)%` reads the file named by `MONGO_URI_FILE` when that variable is set, and
falls back to `MONGO_URI` otherwise. A developer sets `MONGO_URI` in `.env`; production sets
`MONGO_URI_FILE=/run/secrets/<app>/mongo_uri` and mounts the file. One config file, both worlds.

A `_FILE` variable naming a missing file is a **hard error**. It does not fall back to the plain
variable — that would silently substitute a stale environment value for a secret that failed to
mount, which is the failure you would least want to happen quietly.

Why files rather than environment variables: a value in the environment is visible in
`docker inspect`, readable from `/proc/<pid>/environ`, and inherited by every child process. A
mounted file is none of those things.

**Where the values come from.** Encrypted with SOPS in `gcgov/deploy`, against a GCP KMS key per
Zone plus an offline break-glass key. An operator decrypts on their own workstation and provisions
the files to the host — a step deliberately separate from deploying, so no host holds a decryption
key and CI never sees a secret. See `docs/adr/0003-secrets-never-decrypt-in-ci-or-on-hosts.md`.

### JWT signing keys

These are secrets too, and they are gitignored — so they are **not in the build context and not in
the image**. `config.json` reads the directory from `%env(APP_JWT_KEY_PATH)%`, which every
environment must set — the two halves are only useful together, and mounting the keys without
pointing the application at them is the failure that looks like success:

| | `APP_JWT_KEY_PATH` |
|---|---|
| local dev — `.env`, read by the host gf CLI | `srv/jwtCertificates/` — relative to the application root; where `vendor/bin/gf cert:generate-auth` writes |
| local dev — the container | `/var/www/app/srv/jwtCertificates/` — set in `docker-compose.yml` itself, because `.env` is shared with the host CLI and one value cannot be right on both filesystems; the bind mount exposes the same directory |
| a Zone | `/run/secrets/<app>/jwt/` — the directory `bin/provision` fills, mounted read-only |

Get it wrong and `/api/health/ready` fails: when `services.auth` is enabled, readiness checks
that the key directory holds usable signing keys, precisely so an unmounted or empty key mount
stops a deploy at the health gate instead of surfacing as a `configException` the moment
someone tries to sign in. Plain `/api/health` stays I/O-free and keeps the container alive —
missing keys are a readiness problem, not a liveness one.

Every replica must have the same keys; regenerating them signs every user out.

### Rules

- **Never bake a secret into an image.** `ENV`, `ARG` and `COPY` all persist in layers and in
  `docker history`. Anyone who can pull the image can read them.
- **Never put a real value in a compose file.** They are committed.
- **Rotation is a provision plus a restart, not a rebuild.** The image does not change.

---

## Health

| Route | Checks | Used by |
|---|---|---|
| `GET {basePath}/health` | Configuration resolved. No I/O. | Container `HEALTHCHECK`, Traefik |
| `GET {basePath}/health/ready` | Pings every configured database. 503 when one is down. | The deploy gate |

They are separate on purpose. If the container's healthcheck also pinged the database, a brief
database outage would fail the probe, restart every replica, and turn a dependency blip into a
crash loop.

Both come from the framework, so every application has them — a deploy pipeline cannot gate on an
endpoint an application might have skipped. `/health` reports the deployed version from
`APP_VERSION`, baked in at build time, which is how you confirm a deploy actually landed.

The nginx image's healthcheck URL is `HEALTH_URL`, defaulting to `http://localhost/health`. Set it
to match when the application is not served at the domain root.

---

## Building

```bash
docker build --target php   -t ghcr.io/gcgov/<app>/php   --build-arg APP_VERSION=$(git rev-parse --short HEAD) .
docker build --target nginx -t ghcr.io/gcgov/<app>/nginx .
```

In practice `.github/workflows/release.yml` does this: a push to `main` builds and publishes, a
`v*` tag also deploys. Both call a reusable workflow in `gcgov/deploy`, so changing how deployment
works is one pull request rather than one per application.

`composer.lock` is committed and `config.platform.php` pins resolution to the runtime the image
runs. Without that pin, resolving on a newer PHP locks packages that will not install in the image.

---

## TLS and the forwarded scheme

TLS terminates at Traefik, one instance per Zone, with certificates from Let's Encrypt over DNS-01
— the only challenge type that works for a Zone with no inbound path from the internet, which is why
all three Zones use it.

The container never terminates TLS and never redirects to HTTPS. It needs the original scheme
forwarded:

```
proxy_set_header X-Forwarded-Proto $scheme;
```

The bundled nginx config maps that to the `HTTPS` and `REQUEST_SCHEME` FastCGI params, so PHP sees
the real client scheme for absolute URLs, secure cookies, and redirects.

---

## What the nginx config does

`docker/nginx/default.conf.template` reproduces the five behaviours the IIS `web.config` provided:
the scheme from the edge, trailing-slash stripping, static pass-through for `theme/` and
`favicon.ico`, front-controller routing to `index.php` with `REQUEST_URI` preserved, and CORS with
an origin allowlist and preflight handling.

The CORS origins come from `CORS_ORIGIN_APP`, `CORS_ORIGIN_FRONTEND` and `CORS_ORIGIN_SWAGGER`,
substituted at container start. **Keep all three distinct** — they become keys in an nginx `map`,
and a duplicate key stops nginx from starting.

---

## What is deliberately not here

- **A production compose file.** It is in `gcgov/deploy`, under the Zone that runs this application.
- **Swarm or Kubernetes manifests.** The deployment target is Docker on three Ubuntu hosts.
- **Anything that writes to the container filesystem and expects it to survive.** Logs go to stderr;
  uploads and sessions under `srv/tmp` are scratch space that a deploy discards.
