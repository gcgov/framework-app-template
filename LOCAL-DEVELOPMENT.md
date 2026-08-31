# Running this application on a development computer

From an empty checkout to a signed-in request against your own data. Nine steps, all of which
should succeed; if one does not, [Troubleshooting](#troubleshooting) names the cause.

> This is the local story. `DOCKER.md` covers the images, secrets and deployment;
> `README.md` is the short version of this page.

---

## 1. Prerequisites

- **Docker Desktop** (or Docker Engine plus the Compose plugin) and **git**. That is the whole list.
- **A host PHP toolchain is optional.** PHP 8.4 with `ext-mongodb`, `ext-sodium`, `ext-zip` and
  `ext-openssl` gives you a faster inner loop and a `vendor/bin/gf` you can run without a container,
  but nothing below requires it — every command runs inside the stack. Installing PHP on Windows
  used to be the bulk of setting this up, and it no longer has to be.

The stack publishes two ports on the host: **8080** (the API) and **27017** (MongoDB). Change either
in `.env` if something already has them.

---

## 2. Get the code and the compose variables

```bash
git clone https://github.com/<org>/<your-app>.git
cd <your-app>
cp .env.example .env
```

`.env` is gitignored and holds every local value. It has two halves: the compose variables you just
copied, and the application variables step 3 appends. They share one file because **docker compose
interpolates `${HTTP_PORT}` and the CORS origins from `.env` and from nothing else** — it never
reads `.env.local`, so a compose variable put there is ignored with no warning.

---

## 3. Bootstrap

```bash
docker compose build php
docker compose run --rm php vendor/bin/gf init --title="My API"
```

`gf init` is non-interactive and does four things:

| | |
|---|---|
| **Identity** | Writes `app.title` and a freshly minted `app.guid` into `config.json`. The guid is the OAuth `client_id`; re-running `init` keeps an existing one rather than invalidating every registered client. |
| **`.env`** | Appends every variable `config.json` references, derived from `config.json` itself so the list cannot drift. Values are left blank for you. |
| **JWT keys** | Generates 5 RSA keypairs plus `guids.json` into `srv/jwtCertificates/`. Gitignored — they are secrets, and they never enter a build context or an image. |
| **chrome-headless-shell** | Downloads it into `srv/chrome/`. Add `--skip-chrome` if the application does not render PDFs; it is ~150 MB. |

The working tree is bind-mounted, so all of that lands on your host filesystem even though the
command ran in a container.

---

## 4. Fill in `.env`

Open it. The bottom half is what `gf init` appended — eight variables, all required. There are no
defaults and a variable set to the empty string counts as unset, deliberately: a half-configured
application should refuse to start rather than run in some posture nobody chose.

```bash
APP_TYPE=local
APP_ROOT_URL=http://localhost:8080
APP_BASE_PATH=/
APP_REDIRECT_AFTER_LOGIN=http://localhost:5173
APP_REDIRECT_AFTER_LOGOUT=http://localhost:5173
APP_JWT_KEY_PATH=srv/jwtCertificates/
MONGO_DATABASE=myapp
MONGO_URI=mongodb://localhost:27017/?directConnection=true
```

`APP_BASE_PATH` is `/` at the domain root, **not blank** — blank counts as unset and is a startup
failure like any other missing reference. Set it to `/api` when the application is served under a
prefix, and change the nginx healthcheck in `docker-compose.yml` to match.

Then check it:

```bash
docker compose run --rm php vendor/bin/gf env         # resolve it, or name the first thing missing
docker compose run --rm php vendor/bin/gf env --list  # every variable, and whether it is set
```

### The two variables that differ by side

`.env` is read by both the host `gf` CLI and the container, and two of its variables cannot hold one
value that is right for both. Those two are **pinned in `docker-compose.yml`**, where `environment:`
overrides `env_file:`, so the value in `.env` is the host's and the container quietly gets its own:

| Variable | In `.env` (the host) | In the container | Why |
|---|---|---|---|
| `APP_JWT_KEY_PATH` | `srv/jwtCertificates/` | `/var/www/app/srv/jwtCertificates/` | Different filesystems, same directory through the bind mount. `gf cert:generate-auth` writes on the host. |
| `MONGO_URI` | `mongodb://localhost:27017/?directConnection=true` | `mongodb://mongodb:27017/?replicaSet=rs0` | Different networks. The host sees a published port; the container resolves the service name. |

`directConnection=true` is not optional from the host. The replica set advertises its one member as
`mongodb:27017` — a name only the compose network resolves — so without it the driver discovers that
member and then cannot reach it. In a Zone, `APP_JWT_KEY_PATH` is a mounted secret directory and
`MONGO_URI` comes from a file; see `DOCKER.md`.

---

## 5. Start it

```bash
docker compose up --build
```

Three containers come up: **nginx** on 8080, **php** (PHP-FPM), and **mongodb**. Mongo takes a few
seconds longer than it used to — its healthcheck initiates the replica set, and php waits for it.

```bash
curl -s localhost:8080/health        # {"status":"ok","version":"unknown"}
curl -s localhost:8080/health/ready  # every dependency, one line each
```

The two are deliberately different. `/health` is liveness and does no I/O, so a database blip cannot
restart every container. `/health/ready` is readiness: it pings each configured database and, when
`services.auth` is enabled, checks that the key directory holds usable signing keys. It answers
`503` with the failing check named, which is a much better first stop than the application logs:

```json
{ "status": "ok", "version": "unknown", "checks": { "mongo:myapp": "ok", "jwtKeys": "ok" } }
```

---

## 6. Why MongoDB is a replica set

`docker-compose.yml` starts mongo with `--replSet rs0` and initiates a single-member set. That is a
correctness requirement, not production fidelity.

Every write the framework makes runs in a transaction — `save()` alone is several writes (the
document, its auto-increment counters, and the embedded copies pushed into other collections), and a
half-applied save is a corrupt denormalisation rather than a failed request. MongoDB offers
transactions only on a replica set or a sharded cluster.

A standalone `mongod` therefore serves every **read** perfectly and fails every **write** with:

```
Transaction numbers are only allowed on a replica set member or mongos
```

which is exactly the shape of bug that survives a smoke test: the list endpoints work and only
saving fails.

---

## 7. Create the first user

```bash
docker compose exec php vendor/bin/gf user:create \
  --email=dev@example.test \
  --roles="User.Read,User.Write,Widget.Read,Widget.Write"
```

It prints a generated password once — pass `--password=…` if you would rather choose it.

You cannot skip this, and there is no way around it from outside. `config.json` enables
`services.auth`, whose `blockNewUsers` defaults to true, so only users already in the database may
sign in. Every `/user` route requires a caller already holding `User.Write`. Nothing can
authenticate, so nothing can create the first user. Inserting the document with `mongosh` does not
help either: the user model hashes the password as it writes, so a hand-written document has no
password anyone can sign in with. `gf user:create` saves through that same model, which is what
makes the account usable.

Roles are plain strings that routes compare against — nothing validates them. Give the first user
whatever its routes name in `requiredRoles` (this template ships `Widget.Read` / `Widget.Write` in
`app/constants.php`), plus `User.Read` and `User.Write` to administer other users through
`services.userCrud`.

Adding a role later is the same command with `--force`, which updates in place and leaves the
password alone:

```bash
docker compose exec php vendor/bin/gf user:create --email=dev@example.test --force --roles="User.Read,Widget.Read"
```

---

## 8. Sign in and call a route

Every route this template ships requires authentication and a role, so you need a token. Take
`client_id` from `app.guid` in `config.json` — the value `gf init` minted.

```bash
CLIENT_ID=$(python3 -c "import json;print(json.load(open('config.json'))['app']['guid'])")

TOKEN=$(curl -s -X POST localhost:8080/auth/authorize \
  -H 'Content-Type: application/json' \
  -d "{\"grant_type\":\"password\",\"scope\":\"login\",\"client_id\":\"$CLIENT_ID\",\"username\":\"dev@example.test\",\"password\":\"…\"}" \
  | python3 -c "import json,sys;print(json.load(sys.stdin)['access_token'])")

curl -s localhost:8080/widgets -H "Authorization: Bearer $TOKEN"
```

`grant_type=password` and `scope=login` are both checked exactly, and `client_id` must equal
`app.guid` — a mismatch is a 401 reading "Invalid client id", not a hint that the guid is wrong.

Then write something, which is the step that proves the replica set is doing its job:

```bash
curl -s -X POST localhost:8080/widgets/new \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"First widget"}'
```

Other routes worth knowing: `GET /.well-known/jwks.json` (the public keys, unauthenticated),
`GET /documentation.yaml` (OpenAPI, generated from the annotations in this application and the
framework), and `GET /user` (`services.userCrud`).

---

## 9. The day-to-day loop

**Edits are live.** The working tree is bind-mounted into the php container and
`docker/php/conf.d/dev.ini` turns opcache timestamp validation back on, so a saved file is picked up
on the next request. No restart, no rebuild. Rebuild only when `composer.json`, the `Dockerfile` or
anything under `docker/` changes.

```bash
docker compose logs -f php                       # logs go to stderr, not logs/*.log
docker compose exec php vendor/bin/gf cli:list   # the application's CLI routes
docker compose exec php vendor/bin/gf cli /cli/widgets
docker compose exec php composer ci              # phpstan + phpunit, the same checks CI runs
docker compose exec php vendor/bin/gf env        # after editing config.json
docker compose exec mongodb mongosh myapp        # a shell on the database
```

Set `logging.lifecycle: true` in `config.json` to trace routing and the auth guard end to end when a
request is answered by something you did not expect.

`docker compose down` stops the stack and keeps the data; `docker compose down -v` also drops the
Mongo volume, which is how you start over (the replica set re-initiates on the next boot, and you
create the first user again).

### Adding a configuration variable

Add the `%env(...)%` reference to `config.json`, then:

```bash
docker compose run --rm php vendor/bin/gf env --init   # appends only what .env lacks
```

It leaves every value you have filled in, and every variable `config.json` knows nothing about,
alone. `--force` rewrites from `config.json` and discards both — you rarely want it.

---

## Running without Docker

The stack is the supported path, but nothing requires it. Serve `www/` with any PHP 8.4 SAPI that
has `ext-mongodb`, point `MONGO_URI` at a MongoDB you run yourself, and use `vendor/bin/gf` directly.

The one thing that is easy to get wrong: **that MongoDB must also be a replica set** (§6). A
`mongod` started with no arguments will not do.

```bash
mongod --replSet rs0 --dbpath /path/to/data
mongosh --eval 'rs.initiate()'
```

With mongo on the host under its own name, `directConnection=true` is unnecessary — set
`MONGO_URI=mongodb://localhost:27017/?replicaSet=rs0`.

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| `Transaction numbers are only allowed on a replica set member or mongos` | MongoDB is a standalone. Reads work, writes cannot. See §6. |
| A write hangs, then fails with a server-selection timeout | `MONGO_URI` names `replicaSet=rs0` from the host. The set advertises `mongodb:27017`, which the host cannot resolve — use `directConnection=true` instead (§4). |
| `configException` naming a variable, at startup | That `%env()` reference has no value. Every one is required and blank counts as unset. `gf env --list` shows the whole list. |
| `configException` about `APP_BASE_PATH`, which you did set | You set it to nothing. At the domain root it is `/`, not blank (§4). |
| `HTTP_PORT` / a CORS origin appears to do nothing | It is in `.env.local`. Docker compose interpolates only from `.env` (§2). |
| `/health/ready` returns 503 on `jwtKeys` | The key directory is empty or `APP_JWT_KEY_PATH` points at the wrong side (§4). Run `gf cert:generate-auth`. |
| `/health/ready` returns 503 on `mongo:*` | Mongo is not up or `MONGO_URI` is wrong. `docker compose ps` shows whether its healthcheck ever went green. |
| 401 on every application route | No token, or no user. Steps 7 and 8. |
| 401 "Invalid client id" | `client_id` does not equal `app.guid` in `config.json`. |
| 403 on a route that authenticated fine | The user lacks the role the route declares. Re-run `user:create --force --roles="…"`. |
| nginx exits immediately at startup | Two of `CORS_ORIGIN_APP` / `_FRONTEND` / `_SWAGGER` are equal. They become keys in an nginx `map`, and a duplicate key is fatal. |
| `docker compose` errors that `.env` is missing | Both env files are declared `required: false`, so this means a Compose too old to understand that (pre-2.24). Upgrade, or `touch .env .env.local` and carry on. |
| The framework refuses to boot over authenticated routes | `services.auth` is absent from `config.json` while routes declare `authentication: true`. That combination would serve them to anyone. |
| `composer install` complains about `ext-mongodb` on the host | Use `--ignore-platform-req=ext-mongodb`, never blanket `--ignore-platform-reqs` — that also discards the `config.platform.php` pin and locks packages the production image cannot install. |

`db/local-createuser.js` creates a **MongoDB account**, not an application user. The compose stack
runs `mongod` without authentication, so you do not need it locally.

---

## Where to look next

- **[README.md](README.md)** — the short bootstrap, and how Framework Services are enabled.
- **[DOCKER.md](DOCKER.md)** — the two images, secrets as provisioned files, TLS, and how a Release
  reaches a host.
- **[the gf CLI](https://github.com/gcgov/framework/blob/main/readme/gf.md)** ·
  **[environment variables](https://github.com/gcgov/framework/blob/main/readme/environment-variables.md)** ·
  **[MongoDB](https://github.com/gcgov/framework/blob/main/readme/mongodb.md)**
