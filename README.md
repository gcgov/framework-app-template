# Framework App Template

Template for a new [gcgov/framework](https://github.com/gcgov/framework) application. It builds two
container images — nginx and PHP-FPM — and keeps every secret out of the committed files: the one
`config.json` references them with `%env(...)%`, and production supplies them as provisioned files.

## Getting started

1. [Use this template](https://github.com/gcgov/framework-app-template/generate) to generate a new
   repository for your application.

2. Bootstrap it. Docker and git are the only prerequisites — PHP on the host is optional:
   ```bash
   cp .env.example .env                 # the variables docker compose itself needs
   docker compose build php
   docker compose run --rm php vendor/bin/gf init --title="Permits API"
   ```
   `gf init` writes the title and a freshly minted guid into `config.json`, appends the variables
   `config.json` references to `.env`, generates JWT signing keypairs, and installs
   chrome-headless-shell. It is non-interactive, so it also works from a scaffolding script or a
   devcontainer. The working tree is bind-mounted, so all of that lands on your host.

3. Fill in `.env`, then check it:
   ```bash
   docker compose run --rm php vendor/bin/gf env    # does it resolve? names the first thing missing
   ```
   Every reference in `config.json` is **required** — there are no defaults, and a blank value
   counts as missing. That is deliberate: a half-configured application should refuse to start
   rather than run in some unintended posture. `gf env --list` shows the whole list.

   One `.env`, not two: docker compose interpolates its own variables from `.env` and never reads
   `.env.local`, so the two halves share a file.

4. Run it, and create the account you sign in as:
   ```bash
   docker compose up --build
   # → http://localhost:8080/health

   docker compose exec php vendor/bin/gf user:create \
     --email=dev@example.test --roles="User.Read,User.Write,Widget.Read,Widget.Write"
   ```
   Every route below requires a token, and `blockNewUsers` defaults to true, so this step is how
   an application gets its first user — there is no way in from outside.

5. Try the `widget` module, then write your own models, controllers, and routes.

To work against real data rather than an empty database, put a `mongodump` in
`db/backup/{DatabaseName}/` and run `docker compose run --rm mongo-restore`. It restores over the
compose network, so your host needs no MongoDB tools, and it can write to no database but this
stack's.

**[LOCAL-DEVELOPMENT.md](LOCAL-DEVELOPMENT.md) is the full walkthrough**, through signing in and
writing a document, with a troubleshooting table.

## Adding what you need

`config.json` ships with eight variables and nothing else — no Microsoft, PayJunction, or SMTP
block. Add the section for an integration when the application actually uses one; a section that
is absent hydrates to its defaults. Keeping unused credentials out of the file means the
application never has to be handed a value it does not use in order to boot.

## Framework Services

Framework Services are part of the framework; you switch one on by giving it a block in the
`services` section. Presence enables — a block that is absent means the service is off, a block
that is present (even `{}`) means it is on, and its contents are that service's settings.

```json
"services": {
    "auth": { "provider": "oauth" },
    "userCrud": {},
    "documentation": {}
}
```

- **`auth`** — one service, two providers. `"oauth"` is a full OAuth server (password,
  third-party and authorization-code grants, plus MFA); `"msFront"` exchanges a Microsoft token
  the front end already holds for an application token. Either way you get
  `/.well-known/jwks.json`, `/auth/fileToken`, and a JWT guard over every route marked
  `authentication: true`. Two providers cannot both be active — there is one `provider` key.
- **`userCrud`** — `/user` CRUD over the resolved user model, gated on `User.Read` / `User.Write`.
- **`documentation`** — `GET /documentation.yaml`, generated from the annotations in this
  application and the framework.

`auth` takes two optional settings, omitted here so they keep their defaults. Add them when the
application wants a different answer:

```json
"auth": { "provider": "oauth", "blockNewUsers": false, "defaultNewUserRoles": [ "Widget.Read" ] }
```

`blockNewUsers` defaults to `true`, so only users already in the database may sign in. Set it
false to provision a user on first successful authentication, carrying `defaultNewUserRoles`.

Routes that declare `authentication: true` need something to guard them. If no auth service is
enabled and `\app\router::providesAuthentication()` returns false, the framework refuses to
boot rather than serve routes that look protected and are not.

## Documentation

- **[LOCAL-DEVELOPMENT.md](LOCAL-DEVELOPMENT.md)** — running this application on a development
  computer: the commands for this stack, end to end, and what each failure means. The rules behind
  them live in the framework, at
  [readme/local-development.md](https://github.com/gcgov/framework/blob/main/readme/local-development.md) — that copy stays current, this one
  is frozen at Scaffold time.
- **[DOCKER.md](DOCKER.md)** — the images, secrets as provisioned files, health checks, TLS, and
  how a Release reaches a host.
- The `gf` CLI: `vendor/bin/gf` (`gf init`, `gf env`, `gf cli`, `gf db:run`, `gf migrate`, …).
- Framework docs: [environment variables](https://github.com/gcgov/framework/blob/main/readme/environment-variables.md)
  · [the gf CLI](https://github.com/gcgov/framework/blob/main/readme/gf.md).

## Running the CI checks (phpstan + phpunit)

`composer.json` requires `gcgov/framework: ^7.0@RC` and `composer.lock` pins the exact release.
The `@RC` stability flag is there because v7 is currently a release candidate; at `v7.0.0` it
becomes plain `^7.0`:

```bash
scripts/adopt-framework-release.sh '^7.0'
```

To run the checks:

```bash
composer install --prefer-dist          # add --ignore-platform-req=ext-mongodb if the extension isn't loaded
composer ci                             # = composer phpstan && composer test
```

The tests shim `ext-mongodb` when it is absent (`tests/bootstrap.php`), so the suite runs without
a live MongoDB.

`composer.lock` is resolved for PHP 8.4.0 (`config.platform.php`), which is what the production
image runs — without that pin, resolving on a newer PHP locks packages that will not install in
the image. Keep the pin, the `php` constraint, and the Dockerfile's base image in step — and
never use blanket `--ignore-platform-reqs`, which discards the pin along with the extension
checks.

## Local development without Docker

You can still run the app under any PHP 8.4+ SAPI with `ext-mongodb`. Point the web root at
`/www/`, resolve config secrets through your shell environment or a `.env` file at the project
root, and use `vendor/bin/gf` for CLI tasks. The Docker stack is the supported, reproducible path.

The MongoDB you point it at must be a **replica set** — a `mongod` started with no arguments will
not do. Every write the framework makes runs in a transaction, so a standalone serves every read
and fails every write. One member is enough: `mongod --replSet rs0`, then `rs.initiate()`.
