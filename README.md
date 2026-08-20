# Flux

Flux is a lightweight unified message broker written from scratch in vanilla PHP with PostgreSQL planned for persistence.

Flux is currently pre-MVP. Broker behavior, database schema, migrations, AMQP 0-9-1 compatibility, and other protocol adapters have not been implemented yet.

## Requirements

- PHP 8.4 or newer
- Composer

## Installation

```bash
composer install
```

## CLI

The main command entry point is `bin/flux`:

```bash
php bin/flux
php bin/flux help
php bin/flux --version
```

The intended command format is:

```bash
flux <command>
```

Future commands will live under `src/Console/Commands/`.

## Tests

```bash
composer test
```

Unit tests live in `tests/Unit/`. Integration tests live in `tests/Integration/` and will later use a real PostgreSQL test database.

## Directory Structure

- `bin/` - executable entry points
- `config/` - Flux configuration
- `database/migrations/` - future database migrations
- `src/Broker/` - protocol-neutral broker core
- `src/Console/` - CLI application and commands
- `src/Persistence/` - persistence abstractions and implementations
- `src/Persistence/Postgres/` - future PostgreSQL persistence implementation
- `src/Protocol/` - protocol adapters
- `src/Protocol/Amqp/` - future minimal AMQP 0-9-1 adapter
- `src/Support/` - small shared infrastructure
- `tests/` - unit, integration, and fixture files
- `var/` - runtime logs and process files
