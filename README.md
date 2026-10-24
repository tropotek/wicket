# Wicket

[![Docs](https://img.shields.io/badge/docs-tropotek.github.io%2Fwicket-2f6feb)](https://tropotek.github.io/wicket/)

A small, self-hosted wiki: pages with full revision history, a menu, a media library, encrypted
secrets (passwords, OTP codes, keys) and full-text search. Built on Laravel, shipped as one Docker
image (FrankenPHP + SQLite) with everything it stores in a single `data/` directory.

## Quick start

Copy [`docker-compose-example.yml`](docker-compose-example.yml) into an empty directory as `docker-compose-live.yml`, then:

```sh
echo "WICKET_ADMIN_EMAIL=you@example.org" > .env
docker compose -f docker-compose-live.yml up -d
docker compose -f docker-compose-live.yml logs wicket   # the admin's generated password
```

Open <http://localhost:8080>.

## Documentation

Full docs at **<https://tropotek.github.io/wicket/>**:

| Guide | For |
|---|---|
| [Installation](docs/installation.md) | Running Wicket with Docker Compose, upgrading |
| [Configuration](docs/configuration.md) | Environment variables |
| [Backup and restore](docs/backup-restore.md) | Nightly backups, restoring a site or a database |
| [Migrate from tkwiki](docs/migrate-from-tkwiki.md) | Importing an existing tkwiki site |
| [Development](docs/development.md) | Working on Wicket, running tests, releasing |

## Licence

See [LICENSE](LICENSE).
