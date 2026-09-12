# Wasted Youth Tracker

Wasted Youth Tracker is a flexible parental control system for Windows.

Read the [full documentation](https://zieren.de/software/wasted-youth-tracker).

## Server development

From `server/`, install dependencies and build the frontend assets:

```sh
composer install
npm ci
npm run build:assets
```

Configure `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`, then initialize the schema with:

```sh
php bin/migrate.php
```

Run checks with `vendor/bin/phpstan analyse` and `vendor/bin/phpunit`.
