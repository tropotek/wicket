# syntax=docker/dockerfile:1
#
# Targets:
#   dev   — local development image (docker-compose.yml): bookworm + Composer + Node, code bind-mounted.
#   prod  — the shipped image (default): Alpine, no build tools, everything persistent under /app/data.

ARG FRANKENPHP=1.13-php8.4

############################ dev ############################
FROM dunglas/frankenphp:${FRANKENPHP}-bookworm AS dev

RUN install-php-extensions pdo_sqlite intl zip pcntl gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app \
 && useradd -u ${UID} -g app -m app \
 && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
 && chown -R app:app /config/caddy /data/caddy

USER app
WORKDIR /app

############################ prod: shared runtime base ############################
FROM dunglas/frankenphp:${FRANKENPHP}-alpine AS base

RUN install-php-extensions pdo_sqlite intl zip pcntl gd opcache

WORKDIR /app

############################ prod: PHP dependencies ############################
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-autoloader --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
 && cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php

############################ prod: front-end build ############################
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json vite.config.js ./
COPY resources ./resources
RUN npm ci --no-audit --no-fund && npm run build

############################ prod: runtime ############################
FROM base AS prod

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-wicket.ini"
COPY docker/opcache.ini "$PHP_INI_DIR/conf.d/zz-opcache.ini"

RUN addgroup -g 1000 app && adduser -D -u 1000 -G app app

COPY --from=vendor --chown=app:app /app /app
COPY --from=assets --chown=app:app /app/public/build /app/public/build

# Persistent state lives in /app/data (one volume): db/, media/, private/.
RUN mkdir -p data/db data/media data/private public/data storage/app/dompdf storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && ln -s /app/data/media public/data/media \
 && ln -s /app/storage/app/public public/storage \
 && chown -R app:app /app/data /app/storage /app/bootstrap/cache /app/public /config/caddy /data/caddy

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    CACHE_STORE=file \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/data/db/wiki.sqlite \
    OCTANE_SERVER=frankenphp \
    APP_PORT=8080

USER app
EXPOSE 8080
VOLUME ["/app/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${APP_PORT}/up" || exit 1

CMD ["sh", "-c", "exec php artisan octane:frankenphp --caddyfile=docker/Caddyfile --host=0.0.0.0 --port=${APP_PORT} --workers=auto --max-requests=500"]
