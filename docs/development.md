# Development

```sh
cp .env.example .env
docker compose up -d                       # app on APP_PORT (8777), Vite on VITE_PORT (5777)
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
```

The tkwiki importer's tests are left out of the default run:
`docker compose exec app php artisan test --group=import`.

## Release

Tests run locally, not in CI. From a clean `main`:

```sh
bin/release v1.2.3
```

It runs Pint and the test suite (and the importer tests when the importer changed since the last
tag), tags the commit, and offers to push the tag. A pushed `v*` tag makes GitHub Actions build
the image for amd64 and publish `ghcr.io/tropotek/wicket:1.2.3` and `:latest`.
