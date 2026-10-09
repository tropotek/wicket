# Backup and restore

## Backup

`wicket:backup` writes a consistent snapshot of the database to `data/backups/` while the site
runs, and keeps the newest 7 (`--keep=N`). Then archive the whole `data/` directory, which holds
the snapshot, the media and the app key. A nightly host crontab entry:

```cron
15 2 * * *  cd /srv/wicket && docker compose -f docker-compose-live.yml exec -T wicket artisan wicket:backup && tar -czf backups/wicket-$(date +\%F).tgz data
```

Copy the archives off the machine.

## Restore

From a `data/` archive (a fresh machine, or replacing everything):

```sh
cd /srv/wicket
docker compose -f docker-compose-live.yml down
mv data data.old                      # keep until the restore checks out
tar -xzf backups/wicket-2026-10-10.tgz
docker compose -f docker-compose-live.yml up -d
```

To roll back only the database to a snapshot, keeping the current media and key:

```sh
docker compose -f docker-compose-live.yml down
cp data/db/wiki.sqlite data/db/wiki.sqlite.old
cp data/backups/wiki-<timestamp>.sqlite data/db/wiki.sqlite
docker compose -f docker-compose-live.yml up -d
```

If `APP_KEY` is set in `.env`, restore it too: it has to be the key the backup's secrets were
encrypted with.
