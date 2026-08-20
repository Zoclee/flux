<?php

declare(strict_types=1);

namespace Flux\Console\Commands;

use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\MigrationFailure;
use Flux\Persistence\Postgres\Migrator;
use Throwable;

final readonly class MigrateCommand
{
    /**
     * @param array{
     *     database: array{
     *         host?: string,
     *         port?: int,
     *         name?: string,
     *         user?: string,
     *         password?: string|null
     *     }
     * } $config
     */
    public function __construct(
        private array $config,
        private string $migrationDirectory
    ) {
    }

    /**
     * @param resource $output
     */
    public function run(mixed $output): int
    {
        $database = $this->config['database'];

        $this->write($output, "Flux Database Migrations\n\n");

        try {
            $pdo = Connection::fromConfig($database);

            $this->write($output, sprintf("Database: %s\n", (string) ($database['name'] ?? '')));
            $this->write($output, sprintf(
                "Host:     %s:%d\n\n",
                (string) ($database['host'] ?? ''),
                (int) ($database['port'] ?? 0)
            ));

            $result = (new Migrator($pdo, $this->migrationDirectory))->migrate();
        } catch (MigrationFailure $exception) {
            $this->write($output, sprintf("Migration failed: %s\n\n", $exception->migration));
            $this->write($output, sprintf("ERROR: %s\n", $this->safeError($exception->getPrevious())));

            return 1;
        } catch (Throwable $exception) {
            $this->write($output, sprintf("ERROR: %s\n", $this->safeError($exception)));

            return 1;
        }

        if ($result->count() === 0) {
            $this->write($output, "Nothing to migrate.\n");

            return 0;
        }

        foreach ($result->applied as $migration) {
            $this->write($output, sprintf("Applying %s ... DONE\n", $migration));
        }

        $this->write($output, sprintf("\n%d migrations applied.\n", $result->count()));

        return 0;
    }

    /**
     * @param resource $output
     */
    private function write(mixed $output, string $message): void
    {
        fwrite($output, $message);
    }

    private function safeError(?Throwable $exception): string
    {
        if ($exception === null) {
            return 'Unknown migration error.';
        }

        $password = $this->config['database']['password'] ?? null;
        $message = $exception->getMessage();

        if (is_string($password) && $password !== '') {
            $message = str_replace($password, '[redacted]', $message);
        }

        return $message;
    }
}
