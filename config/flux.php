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
];
