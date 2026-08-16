# syntax=docker/dockerfile:1
#
# Multi-stage build for a gcgov/framework API running on PHP-FPM behind Nginx.
# Targets:
#   dev   — full (incl. dev) dependencies, for local docker-compose development
#   prod  — minimal runtime image (default), no dev dependencies
#
# NOTE: `composer install` resolves gcgov/framework from Packagist, so the
# framework release that ships the %env() config resolver must be tagged and
# the composer.json constraint set to it (see composer.json). Commit
# composer.lock for reproducible images.

# ---- base: PHP-FPM + required extensions ----
FROM php:8.3-fpm AS base
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip libsodium-dev libzip-dev; \
    docker-php-ext-install -j"$(nproc)" sodium zip; \
    pecl install mongodb; \
    docker-php-ext-enable mongodb; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/app

# ---- vendor: production dependencies only ----
FROM base AS vendor
# composer.lock is optional here (the `*` glob) but SHOULD be committed.
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

# ---- dev: full dependencies for local development ----
FROM base AS dev
COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress
COPY . /var/www/app
RUN set -eux; \
    mkdir -p srv/tmp/tmp srv/tmp/sessions srv/tmp/files srv/tmp/opcache srv/tmp/soaptmp srv/profile logs; \
    chown -R www-data:www-data /var/www/app
USER www-data
CMD ["php-fpm"]

# ---- prod: minimal runtime image (default target) ----
FROM base AS prod
COPY --from=vendor /var/www/app/vendor ./vendor
COPY . /var/www/app
RUN set -eux; \
    mkdir -p srv/tmp/tmp srv/tmp/sessions srv/tmp/files srv/tmp/opcache srv/tmp/soaptmp srv/profile logs; \
    chown -R www-data:www-data /var/www/app
USER www-data
CMD ["php-fpm"]
