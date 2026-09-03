# Running this application on a development computer

The commands, for this stack. Every *why* behind them — the replica set, the required Config
References, the signing keys, the Bootstrap User — is in the framework's
**[local-development.md](https://github.com/gcgov/framework/blob/main/readme/local-development.md)**, which reaches you through Composer and
so stays current; this page is a copy frozen when your application was scaffolded, and is yours to
edit as your stack changes.

> `DOCKER.md` covers the images, secrets and deployment. `README.md` is the short version of this
> page.

---

## 1. Prerequisites

**Docker Desktop** (or Docker Engine plus the Compose plugin) and **git**. That is the whole list.

A host PHP toolchain is optional — PHP 8.4 with `ext-mongodb`, `ext-sodium`, `ext-zip` and
`ext-openssl` gives you a faster inner loop and a `vendor/bin/gf` you can run without a container,
but nothing below needs it.

The stack publishes **8080** (the API) and **27017** (MongoDB). Change either in `.env`.

## 2. Bootstrap

```bash
git clone https://github.com/<org>/<your-app>.git && cd <your-app>
cp .env.example .env                  # the variables docker compose itself needs
docker compose build php
docker compose run --rm php vendor/bin/gf init --title="My API"
```

`gf init` writes the title and a minted guid into `config.json`, appends the variables
`config.json` references to `.env`, generates JWT signing keypairs into `srv/jwtCertificates/`, and
installs chrome-headless-shell (`--skip-chrome` to skip ~150 MB). The working tree is bind-mounted,
so all of it lands on your host. It is idempotent — re-run it as your `config.json` grows.

**One `.env`, not two.** Docker compose interpolates `${HTTP_PORT}` and the CORS origins from
`.env` and never reads `.env.local`, so a compose variable put there is ignored with no warning.
`gf env --init` appends to the same file rather than replacing it.

## 3. Fill in `.env`

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

```bash
docker compose run --rm php vendor/bin/gf env         # resolve it, or name the first thing missing
docker compose run --rm php vendor/bin/gf env --list  # every variable, and whether it is set
```

`APP_BASE_PATH` is `/` at the domain root, **not blank** — blank counts as unset and is a startup
failure. Set it to `/api` when the application is served under a prefix, and change the nginx
healthcheck in `docker-compose.yml` to match.

### The two variables that differ by side

`.env` is read by the host `gf` CLI *and* by the php container, and these two cannot hold one value
correct for both — so `docker-compose.yml` pins the container's value in `environment:`, which beats
`env_file:`, and `.env` carries the host's. ([Why](https://github.com/gcgov/framework/blob/main/readme/local-development.md#variables-whose-correct-value-depends-on-who-is-reading))

| Variable | In `.env` (the host) | In the container |
|---|---|---|
| `APP_JWT_KEY_PATH` | `srv/jwtCertificates/` | `/var/www/app/srv/jwtCertificates/` — the same directory through the bind mount |
| `MONGO_URI` | `mongodb://localhost:27017/?directConnection=true` | `mongodb://mongodb:27017/?replicaSet=rs0` |

`directConnection=true` is not optional from the host: the replica set advertises its member as
`mongodb:27017`, a name only the compose network resolves. In a Zone both variables come from
provisioned files instead — see `DOCKER.md`.

## 4. Start it

```bash
docker compose up --build
curl -s localhost:8080/health        # liveness, no I/O
curl -s localhost:8080/health/ready  # every dependency, one line each
```

Three containers: **nginx** on 8080, **php**, and **mongodb**. Mongo takes a few seconds longer than
a plain image would — its healthcheck initiates the replica set, and php waits for it.

```json
{ "status": "ok", "version": "unknown", "checks": { "mongo:myapp": "ok", "jwtKeys": "ok" } }
```

**Why a replica set:** every write the framework makes runs in a transaction, which MongoDB offers
only on a replica set or a sharded cluster — so a standalone serves every read and fails every
write. That is the failure this stack exists to prevent, and it is worth recognising on sight.
([Detail](https://github.com/gcgov/framework/blob/main/readme/local-development.md#1-the-database-must-be-a-replica-set) ·
[ADR](https://github.com/gcgov/framework/blob/main/docs/adr/0008-writes-are-transactional-so-mongodb-is-a-replica-set.md))

## 5. Create the Bootstrap User

```bash
docker compose exec php vendor/bin/gf user:create \
  --email=dev@example.test \
  --roles="User.Read,User.Write,Widget.Read,Widget.Write"
```

It prints a generated password once; pass `--password=…` to choose your own. You cannot skip this
and there is no way in from outside — `blockNewUsers` defaults true, every `/user` route needs
`User.Write`, and a hand-written `mongosh` document has no usable password.
([Why](https://github.com/gcgov/framework/blob/main/readme/local-development.md#4-the-bootstrap-user))

This application's roles are in `app/constants.php`. Adding one later is the same command with
`--force`, which leaves the password alone.

## 6. Sign in and call a route

Every route this template ships requires authentication and a role. `client_id` is `app.guid` from
`config.json`.

```bash
CLIENT_ID=$(python3 -c "import json;print(json.load(open('config.json'))['app']['guid'])")

TOKEN=$(curl -s -X POST localhost:8080/auth/authorize \
  -H 'Content-Type: application/json' \
  -d "{\"grant_type\":\"password\",\"scope\":\"login\",\"client_id\":\"$CLIENT_ID\",\"username\":\"dev@example.test\",\"password\":\"…\"}" \
  | python3 -c "import json,sys;print(json.load(sys.stdin)['access_token'])")

curl -s localhost:8080/widgets -H "Authorization: Bearer $TOKEN"

curl -s -X POST localhost:8080/widgets/new \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"First widget"}'
```

That last call is the one that proves the replica set is doing its job. `grant_type=password` and
`scope=login` are checked exactly, and `client_id` must equal `app.guid`.

Also available: `GET /.well-known/jwks.json`, `GET /documentation.yaml`, `GET /user`.

## 7. Restore a backup into the development database

A `mongodump` of another database goes in `db/backup/`, in a directory named for the database it
came from. One command restores it:

```bash
mongodump --uri="<source uri>" --db=myapp --out=db/backup   # writes db/backup/myapp/
docker compose run --rm mongo-restore
```

`mongo-restore` is a one-shot container from the same `mongo:7` image as the database, so your host
needs no MongoDB tools at all. A compose profile keeps it out of `docker compose up`, so a restore
is never a side effect of a boot.

| | |
|---|---|
| Reads | `db/backup/${MONGO_DATABASE}`, mounted read-only |
| Writes | the `mongodb` service in this stack, and nothing else |
| Replaces | every collection the backup holds; a collection it does not hold stays as it is |
| Restores into | `MONGO_DATABASE`, whatever the backup directory is called |

That third row is worth reading twice: a restore is not a reset. `docker compose down -v` is the
reset.

Point it at a directory named for a different database with `MONGO_RESTORE_FROM`. Anything after
the service name reaches `mongorestore` unchanged:

```bash
docker compose run --rm -e MONGO_RESTORE_FROM=myapp-prod mongo-restore
docker compose run --rm mongo-restore --numParallelCollections=1
```

**The connection is not `MONGO_URI`.** `docker/mongodb/restore.sh` builds it from the compose
service name, so the only database a restore can write to is the one beside it. That is deliberate.
A workstation that could reach another Environment's database is what retired `gf db:restore` in
v7, and the backup file is the seam that replaced it.

**A restored account keeps its own password hash**, so you can sign in as a user whose password you
already know — and as nobody else. Set a password on an account you want to use:

```bash
docker compose exec php vendor/bin/gf user:create --force --email=dev@example.test --roles="…"
```

`db/backup/` is git-ignored, and a backup holds whatever the source database holds. Treat the
directory the way you treat that database.

## 8. The day-to-day loop

Edits are live — the working tree is bind-mounted and `docker/php/conf.d/dev.ini` turns opcache
timestamp validation back on. Rebuild only when `composer.json`, the `Dockerfile` or `docker/`
changes.

```bash
docker compose logs -f php                       # logs go to stderr, not logs/*.log
docker compose exec php vendor/bin/gf cli:list
docker compose exec php vendor/bin/gf cli /cli/widgets
docker compose exec php composer ci
docker compose exec php vendor/bin/gf env --init # after adding a %env() reference to config.json
docker compose exec mongodb mongosh myapp
```

`docker compose down` keeps the data; `down -v` drops the Mongo volume and starts over — the replica
set re-initiates on the next boot and you create the Bootstrap User again.

## Running without Docker

Serve `www/` with any PHP 8.4 SAPI that has `ext-mongodb`, and use `vendor/bin/gf` directly. The
MongoDB you point it at must still be a replica set:

```bash
mongod --replSet rs0 --dbpath /path/to/data
mongosh --eval 'rs.initiate()'
```

With mongo under its own name on the host, drop `directConnection=true` and use
`MONGO_URI=mongodb://localhost:27017/?replicaSet=rs0`.

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| `Transaction numbers are only allowed on a replica set member or mongos` | MongoDB is a standalone. Reads work, writes cannot. §4. |
| **`gf init` fails with "Failed writing config.json"** — *suspected, on Linux hosts only* | The `dev` image runs as `www-data` (uid 33) and cannot write files a bind mount says you own. Docker Desktop masks this on macOS and Windows. Workaround: `docker compose run --rm --user "$(id -u):$(id -g)" php …`. Please report it if you hit this — it has not yet been confirmed on a real Linux host. |
| A write hangs, then a server-selection timeout | `MONGO_URI` names `replicaSet=rs0` from the host. Use `directConnection=true` instead. §3. |
| `configException` naming a variable, at startup | That Config Reference has no value. Every one is required and blank counts as unset. `gf env --list`. |
| `configException` about `APP_BASE_PATH`, which you did set | You set it to nothing. At the domain root it is `/`, not blank. §3. |
| `HTTP_PORT` / a CORS origin appears to do nothing | It is in `.env.local`. Compose interpolates only from `.env`. §2. |
| `/health/ready` 503 on `jwtKeys` | The key directory is empty, or `APP_JWT_KEY_PATH` points at the wrong side. §3. Run `gf cert:generate-auth`. |
| `/health/ready` 503 on `mongo:*` | Mongo is not up or `MONGO_URI` is wrong. `docker compose ps` shows whether its healthcheck went green. |
| 401 on every application route | No token, or no user. §5 and §6. |
| `mongo-restore` reports "No backup at db/backup/…" | The directory is named for the database the dump came from, and `mongodump --out=db/backup` names it for you. §7. |
| `mongorestore` does not know what to do with file `.gitignore` | Expected. It reads `db/backup` as a dump root, and that file is what keeps the directory in git. §7. |
| Sign-in fails for a user the restore brought in | A restore does not hand you anyone's password, only their hash. Sign in as an account you know, or `gf user:create --force`. §7. |
| 401 "Invalid client id" | `client_id` does not equal `app.guid`. |
| 403 after authenticating fine | The user lacks the role the route declares. `user:create --force --roles="…"`. |
| Sign-in returns an MFA challenge and a token with no roles | `settings.forceMfaForPasswordUsers` is on. Complete `POST /auth/verifyMfaSecret` then `POST /auth/verifyMfaCode`. |
| nginx exits immediately | Two of the three `CORS_ORIGIN_*` are equal. They become keys in an nginx `map`; a duplicate key is fatal. |
| `docker compose` errors that `.env` is missing | Both env files are `required: false`, so this is a Compose older than 2.24. Upgrade, or `touch .env .env.local`. |
| The framework refuses to boot over authenticated routes | `services.auth` is absent while routes declare `authentication: true`. |
| `composer install` complains about `ext-mongodb` on the host | Use `--ignore-platform-req=ext-mongodb`, never blanket `--ignore-platform-reqs` — that also discards the `config.platform.php` pin. |

`db/local-createuser.js` creates a **MongoDB account**, not an application user. The compose stack
runs `mongod` without authentication, so you do not need it locally.

---

## Where to look next

- **[The framework's local-development guide](https://github.com/gcgov/framework/blob/main/readme/local-development.md)** — the rules behind
  every step above, and the version that stays current.
- **[README.md](README.md)** · **[DOCKER.md](DOCKER.md)**
- **[the gf CLI](https://github.com/gcgov/framework/blob/main/readme/gf.md)** · **[environment variables](https://github.com/gcgov/framework/blob/main/readme/environment-variables.md)** ·
  **[MongoDB](https://github.com/gcgov/framework/blob/main/readme/mongodb.md)**
