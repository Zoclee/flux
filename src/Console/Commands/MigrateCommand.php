<?php

declare(strict_types=1);

namespace Flux\Console\Commands;

use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\ConnectionConfig;
use Flux\Persistence\Postgres\MigrationFailure;
use Flux\Persistence\Postgres\Migrator;
use Throwable;

final readonly class MigrateCommand
{
    public function __construct(
        private ConnectionConfig $config,
        private string $migrationDirectory
    ) {
    }

    /**
     * @param resource $output
     */
    public function run(mixed $output): int
    {
        $this->write($output, "Flux Database Migrations\n\n");

        try {
            $connection = Connection::fromConfig($this->config);

            $this->write($output, sprintf("Database: %s\n", $this->config->database));
            $this->write($output, sprintf(
                "Host:     %s:%d\n\n",
                $this->config->host,
                $this->config->port
            ));

            $result = (new Migrator($connection, $this->migrationDirectory))->migrate();
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

        return $this->config->redact($exception->getMessage());
    }
}
