# Migrate from tkwiki

`wicket:import` copies a tkwiki site into a fresh Wicket install: users (with their passwords),
pages with every revision, the menu, secrets, settings and media. The tkwiki site is only read.

| tkwiki | Wicket |
|---|---|
| admin or sysadmin permission | admin |
| staff | editor |
| member | member |
| page permission public / user / staff / private | visibility public / members / editors / private |
| secret permission user / staff / private | visibility members / editors / private (a public secret becomes members) |
| `page://` links and secret placeholders | Wicket page links and secret blocks |

A user who could manage members becomes an editor or member by type; promote them in
**Admin → Users** if needed. The import lists every change like this as a warning.

1. **Start Wicket** as in [Installation](installation.md), with no pages yet. Add tkwiki's hostnames to `.env`
   so absolute links to the old site become paths, then restart:

   ```ini
   WICKET_SITE_HOSTS=wiki.example.org,old-wiki.example.org
   ```

2. **Copy tkwiki's media** into the data directory, where the container can read it:

   ```sh
   cp -a /path/to/tkwiki/data/media data/tkwiki-media
   ```

3. **Dry run.** It shows what would be imported and any warnings, and changes nothing. The
   tkwiki database has to be reachable from the container: use the host's LAN address, not
   `localhost`.

   ```sh
   docker compose -f docker-compose-live.yml exec wicket artisan wicket:import \
       --from='mysql://user:password@192.168.1.10:3306/tkwiki' \
       --media-src=/app/data/tkwiki-media --dry-run
   ```

   URL-encode special characters in the password (`@` → `%40`, `:` → `%3A`).

4. **Import.** The same command with `--force` instead of `--dry-run`. `--force` empties
   Wicket's users, pages, menu, secrets and settings first, which on a fresh install removes the
   first admin the container created; tkwiki's users replace it. It asks for tkwiki's encryption key
   (`system.encrypt` in tkwiki's `config.php`) to bring the secrets over; leave it empty to skip
   them. A wrong key stops the import before anything is written. Everything goes in one
   transaction, so a failure leaves Wicket empty.

5. **Check and clean up.** Sign in as a tkwiki admin, look over a few pages and secrets, then:

   ```sh
   rm -rf data/tkwiki-media
   ```

Without `--force` the import refuses to run when Wicket has any users or pages. Running it again
with `--force` redoes the import; it never empties `data/media`, and copied files are overwritten.

Users keep their tkwiki passwords. A user with no password in tkwiki gets a random one and a
warning: they sign in through **Forgot password** (needs mail set up), or an admin sets one in
**Admin → Users**. Media files of types Wicket
doesn't serve (scripts, keys and the like) are skipped with a warning.
