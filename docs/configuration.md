# Configuration

Everything goes in `.env`. Most site settings (name, home page, registration, custom CSS,
maintenance mode) are edited under **Admin → Settings** instead.

| Variable | Default | |
|---|---|---|
| `APP_URL` | `http://localhost:8080` | public address of the site |
| `APP_NAME` | `Wicket` | name before Settings has one |
| `WICKET_PORT` | `8080` | host port |
| `WICKET_TAG` | `latest` | image tag, e.g. `1.0.0` to pin a version |
| `WICKET_ADMIN_USERNAME` / `_EMAIL` / `_PASSWORD` | `admin` / – / generated | first admin, used once on a fresh install |
| `APP_KEY` | generated into `data/private/.app-key` | set it to manage the key yourself |
| `TRUSTED_PROXIES` | – | `*` or a comma list, when behind a reverse proxy |
| `SESSION_SECURE_COOKIE` | `false` | `true` when served over HTTPS |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | – | SMTP for password resets and the contact form |
| `WICKET_SITE_HOSTS` | – | other hostnames this wiki uses or used, comma-separated; links to them are stored as paths |
| `WICKET_REGISTRATION` | `false` | allow sign-ups (also in Settings) |
| `WICKET_SECRETS` | `true` | the secrets module (also in Settings) |
| `WICKET_UPLOAD_MAX_KB` | `10240` | largest upload |
| `WICKET_LOGIN_MAX_ATTEMPTS` / `WICKET_LOGIN_LOCKOUT_MINUTES` | `5` / `15` | login throttling |
| `WICKET_PAGE_LOCK_SECONDS` | `120` | how long an open editor holds a page |
| `LOG_LEVEL` | `debug` | least severe message logged (`info`, `warning`, `error`…) |
| `LOG_DAILY_DAYS` | `14` | days of log files kept in `data/logs/` |

To rotate `APP_KEY`, put the old key in `APP_PREVIOUS_KEYS`, or stored secrets become unreadable.

## Logs

The container logs to stderr, shown by `docker compose -f docker-compose-live.yml logs wicket`,
and to daily files in `data/logs/`. Docker drops a container's own log when the container is
replaced (every upgrade); the files in `data/logs/` stay, and go into the `data/` backups.

