# Framework App Template

App template repository to scaffold a new [gcgov/framework](https://github.com/gcgov/framework)
application. It runs in Docker (Nginx + PHP-FPM) and keeps secrets out of the config files by
resolving them from environment variables / Docker secrets via the framework's `%env(...)%`
syntax.

## Getting started

1. [Use this template](https://github.com/gcgov/framework-app-template/generate) to generate a
   new repository for your app.
2. Scaffold the identity/URL placeholders. `gf setup` replaces the `{app_*}` and `{prod_app_*}`
   tokens across the config, nginx, and compose files:
   ```bash
   composer install
   vendor/bin/gf setup
   ```
   Tokens you provide include: `{app_guid}` (generate at https://www.guidgenerator.com/),
   `{app_title}`, `{app_root_url}`, `{app_base_path}`, `{app_redirect_after_login}`,
   `{app_redirect_after_logout}`, the `{app_microsoft_*}` client id/tenant/drive id, and the
   matching `{prod_app_*}` values for production.
3. Provide secrets and per-environment values as **environment variables**, not tokens. The
   committed `app/config/environment.json` references them with `%env(...)%` — e.g.
   `"uri": "%env(MONGO_URI)%"`, `"clientSecret": "%env(MICROSOFT_CLIENT_SECRET)%"`,
   `"basePath": "%env(default:...:APP_BASE_PATH)%"`. Whichever values the process environment
   supplies *are* the environment — there is nothing to activate. Copy `.env.example` to `.env`
   for local development; use container env / Docker/Kubernetes secrets in production. See
   **[DOCKER.md](DOCKER.md)** and the framework's
   [environment-variables guide](https://github.com/gcgov/framework/blob/main/readme/environment-variables.md).
4. Run it:
   ```bash
   cp .env.example .env
   docker compose --profile dev up --build
   # → http://localhost:8080
   ```
5. Test the `widget` module, then create your own models, controllers, and services.

### Working with production data

Copy `app/config/prod.env.example` to `app/config/prod.env` (gitignored) and fill in the prod
values; validate it with `vendor/bin/gf env prod`. Then `gf db:restore --from=prod` and
`gf db:run --env=prod` resolve `environment.json` with that overlay — no prod config file ever
lives in the repo.

## Documentation

- **[DOCKER.md](DOCKER.md)** — running in Docker, and how to set environment variables securely
  (Docker/Swarm/Kubernetes secrets, TLS at the edge, the CLI).
- The `gf` CLI: `vendor/bin/gf` (`gf setup`, `gf env`, `gf cli`, `gf db:*`, …).

## Local development without Docker

You can still run the app under any PHP 8.3+ SAPI with `ext-mongodb`. Point the web root at
`/www/`, resolve config secrets through your shell environment or a `.env` file at the project
root, and use `vendor/bin/gf` for CLI tasks. The Docker stack is the supported, reproducible path.
