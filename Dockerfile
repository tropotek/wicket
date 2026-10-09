FROM dunglas/frankenphp:1.13-php8.4-bookworm

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
