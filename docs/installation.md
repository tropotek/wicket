# Installation

You need Docker with the Compose plugin. Copy [`docker-compose-example.yml`](https://github.com/tropotek/wicket/blob/main/docker-compose-example.yml)
into an empty directory (e.g. `/srv/wicket`) as `docker-compose-live.yml`, and add a `.env` next to it:

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

## The image

Releases are published to GitHub's container registry as `ghcr.io/tropotek/wicket`, tagged with
the version (`1.2.0`) and `latest`. `docker-compose-live.yml` uses `latest`; set `WICKET_TAG` in
`.env` to pin a version, so an upgrade only happens when you change it:

```ini
WICKET_TAG=1.2.0
```

Versions are listed on the [package page](https://github.com/tropotek/wicket/pkgs/container/wicket).

## Your own compose file

To run Wicket alongside other containers, add it as a service in your own `docker-compose.yml`.
This example puts Caddy in front for HTTPS, so Wicket publishes no port of its own:

```yaml
services:
  wicket:
    image: ghcr.io/tropotek/wicket:1.2.0
    restart: unless-stopped
    environment:
      APP_URL: https://wiki.example.org
      WICKET_ADMIN_EMAIL: you@example.org
      TRUSTED_PROXIES: "*"
      SESSION_SECURE_COOKIE: "true"
    volumes:
      - ./wicket-data:/app/data
    healthcheck:
      test: ["CMD", "curl", "-fsS", "http://127.0.0.1:8080/up"]
      interval: 30s
      timeout: 5s
      retries: 3
      start_period: 40s

  caddy:
    image: caddy:2
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data

volumes:
  caddy_data:
```

```caddyfile
wiki.example.org {
    reverse_proxy wicket:8080
}
```

- Mount a directory at `/app/data` and nowhere else; it holds everything Wicket keeps (see below).
  The container makes it writable for its app user on start.
- Wicket serves plain HTTP on port 8080 inside the network. Other containers reach it as
  `wicket:8080`; add `ports: ["8080:8080"]` only if something outside Docker needs it.
- Any variable from [Configuration](configuration.md) can go under `environment:` or in an
  `env_file:`.
- Behind nginx, raise `client_max_body_size` (default 1 MB) to at least `WICKET_UPLOAD_MAX_KB`, or
  larger uploads fail before they reach Wicket.

Run artisan commands through the `artisan` wrapper, which runs them as the app user:

```sh
docker compose -f docker-compose-live.yml exec wicket artisan list wicket
```

## What's in `data/`

| Path | Holds |
|---|---|
| `data/db/wiki.sqlite` | the database |
| `data/media/` | uploads and the media library, served at `/data/media/` |
| `data/public/` | the site logo and profile photos, served at `/data/public/` |
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

Migrations run on start. Take a [backup](backup-restore.md#backup) first. With a pinned version,
change `WICKET_TAG` (or the `image:` tag in your own compose file) to the new version, then pull.
