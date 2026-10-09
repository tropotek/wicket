# Wicket

Wicket is a small, self-hosted wiki: pages with full revision history, a menu, a media library,
encrypted secrets (passwords, OTP codes, keys) and full-text search. It's built on Laravel and
ships as one Docker image (FrankenPHP + SQLite), with everything it stores in a single `data/`
directory.

## What it does

- **Pages and history.** Edit in TinyMCE; every save is a revision you can compare and restore.
  Pages are public, members-only, editors-only or private.
- **Wiki links.** Link pages by name; links to missing pages show up as new, and renamed pages
  keep their old addresses working.
- **Secrets.** Store logins, OTP seeds and keys encrypted, and drop them into pages as blocks
  that reveal their values only to people allowed to see them.
- **Media.** Upload images and files, browse them in a media library, pick them from the editor.
- **Search and PDF.** Full-text search over every page; any page exports to PDF.
- **One container.** Migrations, the scheduler and the first admin are handled on start. Backups
  are one command and one directory.

## Documentation

| Guide | For |
|---|---|
| [Installation](installation.md) | Running Wicket with Docker Compose, upgrading |
| [Configuration](configuration.md) | Environment variables |
| [Backup and restore](backup-restore.md) | Nightly backups, restoring a site or a database |
| [Migrate from tkwiki](migrate-from-tkwiki.md) | Importing an existing tkwiki site |
| [Development](development.md) | Working on Wicket, running tests, releasing |
