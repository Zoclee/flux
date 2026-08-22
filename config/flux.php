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
    'limits' => [
        'max_connections' => (int) (getenv('FLUX_MAX_CONNECTIONS') === false ? 1000 : getenv('FLUX_MAX_CONNECTIONS')),
        'max_channels_per_connection' => (int) (getenv('FLUX_MAX_CHANNELS_PER_CONNECTION') === false ? 256 : getenv('FLUX_MAX_CHANNELS_PER_CONNECTION')),
        'max_consumers_per_connection' => (int) (getenv('FLUX_MAX_CONSUMERS_PER_CONNECTION') === false ? 256 : getenv('FLUX_MAX_CONSUMERS_PER_CONNECTION')),
        'max_consumers_per_channel' => (int) (getenv('FLUX_MAX_CONSUMERS_PER_CHANNEL') === false ? 64 : getenv('FLUX_MAX_CONSUMERS_PER_CHANNEL')),
        'amqp_max_frame_size' => (int) (getenv('FLUX_AMQP_MAX_FRAME_SIZE') === false ? 1048576 : getenv('FLUX_AMQP_MAX_FRAME_SIZE')),
        'max_message_size' => (int) (getenv('FLUX_MAX_MESSAGE_SIZE') === false ? 16777216 : getenv('FLUX_MAX_MESSAGE_SIZE')),
        'max_queues_per_virtual_host' => (int) (getenv('FLUX_MAX_QUEUES_PER_VHOST') === false ? 10000 : getenv('FLUX_MAX_QUEUES_PER_VHOST')),
        'max_queue_depth' => (int) (getenv('FLUX_MAX_QUEUE_DEPTH') === false ? 1000000 : getenv('FLUX_MAX_QUEUE_DEPTH')),
    ],
    'amqp' => [
        'enabled' => filter_var(getenv('FLUX_AMQP_ENABLED') === false ? true : getenv('FLUX_AMQP_ENABLED'), FILTER_VALIDATE_BOOL),
        'host' => getenv('FLUX_AMQP_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('FLUX_AMQP_PORT') ?: 5672),
        'heartbeat' => (int) (getenv('FLUX_AMQP_HEARTBEAT') === false ? 60 : getenv('FLUX_AMQP_HEARTBEAT')),
        'tls' => [
            'enabled' => filter_var(getenv('FLUX_AMQP_TLS_ENABLED') === false ? false : getenv('FLUX_AMQP_TLS_ENABLED'), FILTER_VALIDATE_BOOL),
            'host' => getenv('FLUX_AMQP_TLS_HOST') ?: '0.0.0.0',
            'port' => (int) (getenv('FLUX_AMQP_TLS_PORT') ?: 5671),
            'cert' => getenv('FLUX_AMQP_TLS_CERT') ?: '',
            'key' => getenv('FLUX_AMQP_TLS_KEY') ?: '',
            'ca' => getenv('FLUX_AMQP_TLS_CA') ?: null,
        ],
    ],
    'diagnostics' => [
        'host' => getenv('FLUX_DIAGNOSTICS_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('FLUX_DIAGNOSTICS_PORT') ?: 5673),
    ],
    'shutdown' => [
        'drain_timeout' => (int) (getenv('FLUX_SHUTDOWN_DRAIN_TIMEOUT') === false ? 30 : getenv('FLUX_SHUTDOWN_DRAIN_TIMEOUT')),
    ],
];
