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

## Appearance

Under **Admin → Settings → Appearance**:

- **Accent colour**: buttons, links and highlights, in light and dark mode. The browser-tab icon
  (favicon) is drawn in the same colour. Browsers that can't show an SVG favicon fall back to a
  fixed blue `favicon.ico`.
- **Default mode**: light, dark or the visitor's system setting, until a visitor picks their own.
- **Corners** and **Font**.
- **Logo**: a PNG, JPG, GIF or WebP up to 1 MB, shown 32 px tall to the left of the site name in
  the navbar. Turn off **Show the site name beside the logo** when the logo already contains the
  name. SVG isn't accepted: uploads are served as files from the site, where an SVG could run
  script.

Users add a **profile photo** on their Profile page (up to 4 MB). It is cropped to a square,
re-saved as WebP with camera metadata such as location removed, and replaces their initials in
the navbar. Admins see it on the user's edit page and can remove it.

The logo and profile photos are kept in `data/public/`, apart from the media library in
`data/media/`. Backups of `data/` include them.

## Logs

The container logs to stderr, shown by `docker compose -f docker-compose-live.yml logs wicket`,
and to daily files in `data/logs/`. Docker drops a container's own log when the container is
replaced (every upgrade); the files in `data/logs/` stay, and go into the `data/` backups.

