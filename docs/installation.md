# Installation

You need Docker with the Compose plugin. Copy [`docker-compose-live.yml`](https://github.com/tropotek/wicket/blob/main/docker-compose-live.yml)
into an empty directory (e.g. `/srv/wicket`) and add a `.env` next to it:

```ini
APP_URL=https://wiki.example.org
WICKET_ADMIN_EMAIL=you@example.org
```

```sh
docker compose -f docker-compose-live.yml up -d
docker compose -f docker-compose-live.yml logs wicket   # first start: the admin's generated password
```

The site listens on port 8080 (`WICKET_PORT` changes the host port). On every start the container
creates `data/`, generates an app key if there is none, runs the database migrations and starts
the scheduler. On the first start it also creates the admin from `WICKET_ADMIN_*`; with no
`WICKET_ADMIN_PASSWORD` a password is generated and printed once in the log.

Serve it over HTTPS from a reverse proxy (Caddy, nginx, Traefik) and add:

```ini
TRUSTED_PROXIES=*
SESSION_SECURE_COOKIE=true
```

Run artisan commands through the `artisan` wrapper, which runs them as the app user:

```sh
docker compose -f docker-compose-live.yml exec wicket artisan list wicket
```

## What's in `data/`

| Path | Holds |
|---|---|
| `data/db/wiki.sqlite` | the database |
| `data/media/` | uploads and the media library, served at `/data/media/` |
| `data/private/.app-key` | the generated app key (unless `APP_KEY` is set) |
| `data/backups/` | `wicket:backup` snapshots |
| `data/logs/` | daily log files (`wicket-YYYY-MM-DD.log`), kept 14 days |

The app key encrypts every stored secret. Without it, a restored database still works, but its
secrets cannot be read. Keep it with your backups.

## Upgrade

```sh
docker compose -f docker-compose-live.yml pull
docker compose -f docker-compose-live.yml up -d
```

Migrations run on start. Take a [backup](backup-restore.md#backup) first. To stay on a version, set `WICKET_TAG`.
