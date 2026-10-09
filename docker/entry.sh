#!/bin/sh
# Container entrypoint for the shipped image: prepare /app/data, keep APP_KEY, migrate, cache,
# start the scheduler, then exec the server as the unprivileged `app` user.
set -eu
cd /app

as_app() { su-exec app "$@"; }

DATA=/app/data
mkdir -p "$DATA/db" "$DATA/media" "$DATA/private" "$DATA/logs"
# Bind mounts arrive owned by the host user; fix once (skip the walk when already right).
if [ "$(stat -c %U "$DATA")" != app ] || [ "$(stat -c %U "$DATA/private")" != app ]; then
    chown -R app:app "$DATA"
fi
chmod 700 "$DATA/private"

if [ "${DB_CONNECTION:-sqlite}" = sqlite ] && [ ! -f "$DB_DATABASE" ]; then
    as_app touch "$DB_DATABASE"
fi

# APP_KEY encrypts sessions and every stored secret: env wins, else the persisted key, else make one.
KEY_FILE="$DATA/private/.app-key"
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$KEY_FILE" ]; then
        as_app sh -c "umask 077 && php -r 'echo \"base64:\".base64_encode(random_bytes(32));' > '$KEY_FILE'"
        echo "wicket: generated APP_KEY in $KEY_FILE — back it up with the database; secrets can't be read without it."
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

as_app php artisan migrate --force --no-interaction
as_app php artisan optimize --no-interaction >/dev/null
as_app php artisan wicket:first-admin --no-interaction

# Scheduler (heartbeat, hourly housekeeping, nightly reindex); restarted if it ever exits.
as_app sh -c 'while true; do php artisan schedule:work --no-interaction || true; sleep 5; done' &

exec su-exec app "$@"
