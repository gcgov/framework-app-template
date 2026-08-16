# Running this app in Docker

This template ships a Linux container stack — **Nginx + PHP-FPM** (plus an optional MongoDB
for development) — that replaces the old Windows/IIS hosting. Secrets are injected from the
environment or Docker secrets via the framework's `%env(...)%` config resolver, so nothing
sensitive lives in the config files.

> **Prerequisite:** the images install `gcgov/framework` from Packagist. The release that ships
> the `%env()` config resolver must be tagged and referenced by `composer.json` before
> `composer install` (and therefore `docker build`) can succeed. See `composer.json`.

---

## Quick start (local development)

```bash
cp .env.example .env          # fill in any real values you have; blanks are fine for dev
docker compose --profile dev up --build
# → API on http://localhost:8080
```

The `dev` profile also starts a throwaway MongoDB at `mongodb:27017`, which the default
`environment-local.json` points at (`%env(default:mongodb://mongodb:27017:MONGO_URI)%`).

Run framework CLI routes and tooling inside the PHP container:

```bash
docker compose exec php vendor/bin/gf cli:list
docker compose exec php vendor/bin/gf cli /your/cli/route
docker compose exec php composer ci
```

Before first run you still scaffold the identity/URL placeholders with `gf setup` (it replaces
the `{app_*}` / `{prod_app_*}` tokens in the config, nginx, and compose files). Environment
selection is unchanged: `gf env local` / `gf env prod` copy the matching
`environment-{name}.json` into place.

---

## Securely setting environment variables in Docker

The framework reads secrets through `%env(...)%`, so **how** you supply those variables is what
keeps them safe. In order of preference:

### 1. Prefer file-based secrets (Docker / Swarm / Kubernetes secrets)

A secret mounted as a file never appears in the process environment, so it is **not** exposed by
`docker inspect` and does not leak into child processes. Mount it and read it with the `file`
processor (the leading `trim:` strips the trailing newline):

```jsonc
// environment-prod.json
"uri": "%env(trim:file:MONGO_URI_FILE)%"
```

```yaml
# compose / swarm
services:
  php:
    environment:
      MONGO_URI_FILE: /run/secrets/mongo_uri
    secrets:
      - mongo_uri
secrets:
  mongo_uri:
    external: true          # `docker secret create mongo_uri ./mongo_uri`
```

Kubernetes — mount the secret as a file and point the `*_FILE` variable at it:

```yaml
env:
  - name: MONGO_URI_FILE
    value: /run/secrets/mongo_uri
volumeMounts:
  - name: mongo-uri
    mountPath: /run/secrets
    readOnly: true
volumes:
  - name: mongo-uri
    secret:
      secretName: mongo-uri
```

### 2. Process environment variables (acceptable, less private)

Fine for non-secret config and local development; readable via `docker inspect` and the
container's `/proc`, so avoid for high-value secrets.

```bash
docker run --env-file .env …                 # a gitignored env file
```

```yaml
services:
  php:
    env_file: [.env]                          # what this template's compose uses
```

Kubernetes can inject individual values from a Secret without a file:

```yaml
env:
  - name: MICROSOFT_CLIENT_SECRET
    valueFrom:
      secretKeyRef: { name: app-secrets, key: microsoft-client-secret }
```

### Rules — do not break these

- **Keep `.env` out of git.** It is gitignored here; commit only `.env.example` with blank/dummy
  values.
- **Never bake secrets into an image.** `ENV`, `ARG`, and `COPY` all persist in image layers and
  in `docker history` — anyone who can pull the image can read them. Inject secrets at **run**
  time, never build time.
- **Never put real secret values in `docker-compose.yml`** (it is committed). Reference `${VAR}`
  and keep the values in `.env` or a secrets manager.
- **Rotation is a restart, not a rebuild.** Because secrets are injected at runtime, rotating a
  credential means updating the secret/`.env` and restarting the container — the image is
  unchanged.

---

## TLS and the forwarded scheme

TLS is **not** terminated inside the container. Terminate it at your edge (reverse proxy, load
balancer, ingress) and forward the original scheme:

```
proxy_set_header X-Forwarded-Proto $scheme;   # or the ingress equivalent
```

The bundled nginx config maps `X-Forwarded-Proto` to the `HTTPS` / `REQUEST_SCHEME` FastCGI
params, so PHP sees the real client scheme (used for absolute URLs, secure cookies, redirects).
There is deliberately no in-container HTTP→HTTPS redirect.

---

## What the nginx config does

`docker/nginx/default.conf.template` reproduces the five behaviors the IIS `web.config` provided
(scheme from the edge, trailing-slash strip, static pass-through for `theme/` and `favicon.ico`,
front-controller routing to `index.php` with `REQUEST_URI` preserved, and CORS with an origin
allowlist + preflight). The CORS origins come from `CORS_ORIGIN_APP`, `CORS_ORIGIN_FRONTEND`, and
`CORS_ORIGIN_SWAGGER`; only those exact origins receive `Access-Control-Allow-Origin`.

---

## Production image

```bash
docker build --target prod -t your-app:latest .
```

The `prod` target installs `--no-dev` dependencies and runs PHP-FPM. Serve it behind an nginx
container using `docker/nginx/default.conf.template` (both containers need the application files —
bake them in or share a volume — because nginx serves the static assets and PHP-FPM executes
`index.php`). Commit `composer.lock` for reproducible builds.
