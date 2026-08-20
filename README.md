# Flux

Flux is a lightweight unified message broker written from scratch in vanilla PHP with PostgreSQL persistence.

Flux is currently pre-MVP. Broker behavior, AMQP 0-9-1 compatibility, and other protocol adapters have not been implemented yet.

## Requirements

- PHP 8.4 or newer
- Composer
- PostgreSQL for integration tests and persistence work

## Installation

```bash
composer install
```

## CLI

The repository command entry point is `flux` at the project root:

```bash
php flux
php flux help
php flux --version
php flux db:status
php flux migrate
```

The intended Composer-installed command format is:

```bash
flux <command>
```

Future commands will live under `src/Console/Commands/`.

### Database Migrations

Flux verifies PostgreSQL connectivity and reports migration status without applying migrations with:

```bash
php flux db:status
```

Flux applies PostgreSQL migrations with:

```bash
php flux migrate
```

After Composer installation, the equivalent command is `flux migrate`.

Database configuration is read from normal environment variables:

```text
FLUX_DB_HOST
FLUX_DB_PORT
FLUX_DB_NAME
FLUX_DB_USER
FLUX_DB_PASSWORD
```

The current defaults are defined in `config/flux.php`.

For local development, Flux also loads a `.env` file from the project root before reading configuration:

```text
FLUX_DB_HOST=127.0.0.1
FLUX_DB_PORT=5432
FLUX_DB_NAME=flux
FLUX_DB_USER=flux
FLUX_DB_PASSWORD=secret
```

Values already present in the process environment take precedence over `.env`.

## Tests

```bash
composer test
```

Unit tests live in `tests/Unit/`. Integration tests live in `tests/Integration/` and use a real PostgreSQL test database when `FLUX_TEST_DATABASE_URL` is set.

Example:

```bash
FLUX_TEST_DATABASE_URL="pgsql:host=127.0.0.1;port=5432;dbname=flux_test;user=flux;password=secret" composer test
```

## PostgreSQL Persistence Model

The initial schema lives in plain SQL migrations under `database/migrations/`. It establishes the Phase 1 persistence foundation only:

Migration filenames use `yyyymmdd_hhmmss_description.sql` so lexical order is execution order. If multiple migrations are created at the same time, increment the timestamp by one second for each subsequent file. Migrations must be idempotent and safe to apply more than once.

```text
virtual_hosts
    |
    +-- destinations
    |      |
    |      +-- bindings
    |      |
    |      +-- message_routes
    |               |
    |               +-- deliveries
    |
    +-- subscriptions

messages
    |
    +-- message_routes
```

Flux uses `destinations` instead of making queues the fundamental abstraction so the core schema stays protocol-neutral. A queue is the first supported destination type, but future protocol adapters should not force MQTT topics, Kafka topics, or AMQP exchanges into queue-specific tables.

`messages` store payload bytes and message metadata once. `message_routes` associate one stored payload with one or more destinations, allowing fan-out without duplicating binary payload data. `deliveries` are separate because reservation, acknowledgement, rejection, retries, and attempts have their own lifecycle independent of message storage.

Live consumers, TCP connections, channels, sockets, and runtime statistics are not persisted. Durable consumption relationships are represented by `subscriptions`; active consumers remain runtime concepts for later broker phases.

## Directory Structure

- `flux` - project-root CLI entry point
- `config/` - Flux configuration
- `database/migrations/` - PostgreSQL schema migrations
- `src/Broker/` - protocol-neutral broker core
- `src/Console/` - CLI application commands
- `src/Persistence/` - persistence abstractions and implementations
- `src/Persistence/Postgres/` - future PostgreSQL persistence implementation
- `src/Protocol/` - protocol adapters
- `src/Protocol/Amqp/` - future minimal AMQP 0-9-1 adapter
- `src/Support/` - small shared infrastructure
- `tests/` - unit, integration, and fixture files
- `var/` - runtime logs and process files
