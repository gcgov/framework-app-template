# Framework App Template

Template for a new [gcgov/framework](https://github.com/gcgov/framework) application. It builds two
container images — nginx and PHP-FPM — and keeps every secret out of the committed files: the one
`config.json` references them with `%env(...)%`, and production supplies them as provisioned files.

## Getting started

1. [Use this template](https://github.com/gcgov/framework-app-template/generate) to generate a new
   repository for your application.

2. Bootstrap it:
   ```bash
   composer install
   vendor/bin/gf init --title="Permits API"
   ```
   `gf init` writes the title and a freshly minted guid into `config.json`, writes a `.env`
   skeleton from the variables `config.json` references, generates JWT signing keypairs, and
   installs chrome-headless-shell. It is non-interactive, so it also works from a scaffolding
   script or a devcontainer.

3. Fill in `.env`, and add the compose variables:
   ```bash
   cp .env.example .env.local
   vendor/bin/gf env              # does it resolve? names the first thing missing
   ```
   Every reference in `config.json` is **required** — there are no defaults, and a blank value
   counts as missing. That is deliberate: a half-configured application should refuse to start
   rather than run in some unintended posture. `gf env --list` shows the whole list.

4. Run it:
   ```bash
   docker compose up --build
   # → http://localhost:8080
   ```

5. Try the `widget` module, then write your own models, controllers, and routes.

## Adding what you need

`config.json` ships with five variables and nothing else — no Microsoft, PayJunction, or SMTP
block. Add the section for an integration when the application actually uses one; a section that
is absent hydrates to its defaults. Keeping unused credentials out of the file means the
application never has to be handed a value it does not use in order to boot.

## Documentation

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
