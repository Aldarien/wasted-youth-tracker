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

For local development, copy `server/.env.example` to `server/.env` and fill in the
database credentials. The application loads that file automatically without
overriding variables already supplied by the environment. In production, inject
these values through the process environment or a secret manager instead.

Initialize the schema with:

```sh
php bin/migrate.php
```

Application logs are emitted as JSON to stderr and written to rotating files such as
`logs/application-YYYY-MM-DD.log`. Production defaults are `LOG_LEVEL=INFO`,
`LOG_MAX_FILES=14`, `LOG_STDERR=true`, and `LOG_SLOW_REQUEST_MS=1000`;
`LOG_DIRECTORY` customizes the file location. Sensitive log context is redacted,
`/health` provides a lightweight liveness check and `/ready` verifies database
availability for deployment readiness checks.

Run checks with `vendor/bin/phpstan analyse` and `vendor/bin/phpunit`.

To review the UI locally through the development Nginx proxy:

```sh
docker compose --profile ui up -d --build
docker compose run --rm php php bin/migrate.php
```

Open `http://localhost:8081/` (or the port configured with `UI_PORT`). If a local Traefik instance is connected to the
`global-proxy` network, open `http://wyt.localhost/` instead. Set
`TRAEFIK_NETWORK` if your Traefik network has another name.

### Production operations

Run the application as a non-root user, inject secrets through the process
environment or a secret manager, and terminate TLS at the application or a
trusted reverse proxy. Send the JSON logs from stderr to the deployment
platform's log collector; retain file rotation only as a local fallback.

Recommended alerts are sustained HTTP 5xx responses, readiness failures,
slow-request warnings, database connection errors, and low disk space. Back up
the database separately and verify restore procedures.
