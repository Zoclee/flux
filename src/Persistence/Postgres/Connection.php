<?php

declare(strict_types=1);

namespace Flux\Persistence\Postgres;

use PDO;
use PDOException;
use RuntimeException;

final readonly class Connection
{
    /**
     * @param array{
     *     host?: string,
     *     port?: int,
     *     name?: string,
     *     user?: string,
     *     password?: string|null
     * } $config
     */
    public static function fromConfig(array $config): PDO
    {
        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('The pdo_pgsql extension is required to connect to PostgreSQL.');
        }

        $host = self::requiredString($config, 'host');
        $port = (int) ($config['port'] ?? 0);
        $name = self::requiredString($config, 'name');
        $user = self::requiredString($config, 'user');
        $password = $config['password'] ?? null;

        if ($port <= 0) {
            throw new RuntimeException('PostgreSQL configuration value "port" must be a positive integer.');
        }

        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $name);

        return self::create($dsn, $user, $password);
    }

    public static function fromDsn(string $dsn): PDO
    {
        if (!extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('The pdo_pgsql extension is required to connect to PostgreSQL.');
        }

        if ($dsn === '') {
            throw new RuntimeException('PostgreSQL DSN must not be empty.');
        }

        return self::create($dsn, null, null);
    }

    private static function create(string $dsn, ?string $user, ?string $password): PDO
    {
        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException(
                sprintf('Could not connect to PostgreSQL: %s', $exception->getMessage()),
                0,
                $exception
            );
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function requiredString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('PostgreSQL configuration value "%s" must not be empty.', $key));
        }

        return $value;
    }
}
