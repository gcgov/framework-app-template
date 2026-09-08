# syntax=docker/dockerfile:1
#
# Two images, one build context.
#
#   php    — PHP-FPM running the application (default target)
#   nginx  — nginx serving the application's static assets and proxying to php
#   dev    — php plus dev dependencies, for the local compose stack
#
# nginx needs the application's www/ directory to serve theme assets, so it is built
# from the same context and tagged in lockstep with php. Two images that can never
# disagree about which release they are serving.
#
# Build both:
#   docker build --target php   -t ghcr.io/gcgov/<app>/php   --build-arg APP_VERSION=$(git rev-parse HEAD) .
#   docker build --target nginx -t ghcr.io/gcgov/<app>/nginx .

# ---- base: PHP-FPM + the extensions the framework requires ----
FROM php:8.4-fpm AS base
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git unzip libsodium-dev libzip-dev; \
    docker-php-ext-install -j"$(nproc)" sodium zip opcache; \
    pecl install mongodb; \
    docker-php-ext-enable mongodb; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# The base image ships no php.ini and a pool config that assumes a root master.
# Replace both — see docker/php/php-fpm.d/zz-app.conf for why www.conf goes.
COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-fpm.d/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf
RUN rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf.default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/app

# ---- vendor: production dependencies, resolved from the committed lock ----
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

# ---- dev: full dependencies for the local compose stack ----
FROM base AS dev
COPY docker/php/conf.d/dev.ini /usr/local/etc/php/conf.d/zzz-dev.ini
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist --no-progress
COPY . /var/www/app
RUN set -eux; \
    mkdir -p srv/tmp/tmp srv/tmp/sessions srv/tmp/files srv/tmp/opcache srv/tmp/soaptmp srv/profile logs; \
    chown -R www-data:www-data /var/www/app
USER www-data
CMD ["php-fpm"]

# ---- php: the production application image ----
FROM base AS php

# The deployed release, surfaced by GET {basePath}/health so a deploy can be verified
# rather than assumed. Passed by the release workflow; "unknown" locally.
ARG APP_VERSION=unknown
ENV APP_VERSION=${APP_VERSION}

COPY --from=vendor /var/www/app/vendor ./vendor
COPY . /var/www/app
RUN set -eux; \
    mkdir -p srv/tmp/tmp srv/tmp/sessions srv/tmp/files srv/tmp/opcache srv/tmp/soaptmp srv/profile logs; \
    chown -R www-data:www-data /var/www/app

# Nothing in the image needs to be writable by the application except srv/tmp.
# The JWT signing keys and every other secret are provisioned at runtime, never baked
# in — they are gitignored, so they are not in this build context at all.
USER www-data
EXPOSE 9000
CMD ["php-fpm"]

# ---- nginx: serves static assets, proxies everything else to php ----
FROM nginx:1.27-alpine AS nginx
COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
# Only the web root: nginx has no business being able to read the application's PHP.
COPY www /var/www/app/www

# The framework serves health at {basePath}/health, so the URL depends on the
# application's base path — set HEALTH_URL to match when it is not the domain root.
ENV HEALTH_URL="http://localhost/health"

# Exercises the whole path — nginx, FPM, PHP, and the framework's route table — rather
# than asking whether a process is running. Liveness only: /health does no I/O, so a
# database blip cannot put the container into a restart loop. Readiness
# ({basePath}/health/ready, which pings Mongo) is what the deploy gate gets to decide on.
HEALTHCHECK --interval=15s --timeout=3s --start-period=20s --retries=3 \
    CMD wget --quiet --tries=1 --spider "$HEALTH_URL" || exit 1
