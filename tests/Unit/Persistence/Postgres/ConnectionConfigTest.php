<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Persistence\Postgres;

use Flux\Persistence\Postgres\ConnectionConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConnectionConfigTest extends TestCase
{
    public function testItBuildsPostgreSQLDsnWithoutPassword(): void
    {
        $config = new ConnectionConfig('127.0.0.1', 5432, 'flux', 'flux', 'secret');

        self::assertSame('pgsql:host=127.0.0.1;port=5432;dbname=flux', $config->dsn());
    }

    public function testItRedactsPasswordFromMessages(): void
    {
        $config = new ConnectionConfig('127.0.0.1', 5432, 'flux', 'flux', 'secret');

        self::assertSame('password=[redacted]', $config->redact('password=secret'));
    }

    public function testItFailsClearlyForMissingDatabaseName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FLUX_DB_NAME is not configured.');

        new ConnectionConfig('127.0.0.1', 5432, '', 'flux', null);
    }
}
