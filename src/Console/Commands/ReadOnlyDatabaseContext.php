<?php

declare(strict_types=1);

namespace Flux\Console\Commands;

use Flux\Broker\VirtualHost;
use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\ConnectionConfig;
use Flux\Persistence\Postgres\VirtualHostRepository;
use RuntimeException;
use Throwable;

final readonly class ReadOnlyDatabaseContext
{
    public function __construct(
        private ConnectionConfig $config
    ) {
    }

    public function connect(): Connection
    {
        return Connection::fromConfig($this->config);
    }

    public function defaultVirtualHost(Connection $connection): VirtualHost
    {
        $virtualHost = (new VirtualHostRepository($connection))->findByName('/');

        if ($virtualHost === null) {
            throw new RuntimeException('Default virtual host "/" was not found.');
        }

        return $virtualHost;
    }

    public function safeError(Throwable $exception): string
    {
        return $this->config->redact($exception->getMessage());
    }
}
