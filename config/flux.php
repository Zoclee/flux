<?php

declare(strict_types=1);

return [
    'database' => [
        'host' => getenv('FLUX_DB_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('FLUX_DB_PORT') ?: 5432),
        'name' => getenv('FLUX_DB_NAME') ?: 'flux',
        'user' => getenv('FLUX_DB_USER') ?: 'flux',
        'password' => getenv('FLUX_DB_PASSWORD') ?: null,
    ],
    'amqp' => [
        'host' => getenv('FLUX_AMQP_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('FLUX_AMQP_PORT') ?: 5672),
        'heartbeat' => (int) (getenv('FLUX_AMQP_HEARTBEAT') === false ? 60 : getenv('FLUX_AMQP_HEARTBEAT')),
    ],
    'diagnostics' => [
        'host' => getenv('FLUX_DIAGNOSTICS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('FLUX_DIAGNOSTICS_PORT') ?: 5673),
    ],
];
