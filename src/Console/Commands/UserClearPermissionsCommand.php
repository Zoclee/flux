<?php

declare(strict_types=1);

namespace Flux\Console\Commands;

use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\UserRepository;
use Throwable;

final readonly class UserClearPermissionsCommand
{
    public function __construct(
        private Connection $connection
    ) {
    }

    /**
     * @param list<string> $arguments
     * @param resource $output
     */
    public function run(array $arguments, mixed $output): int
    {
        $username = $arguments[0] ?? '';
        $virtualHost = $arguments[1] ?? '';

        if ($username === '' || $virtualHost === '') {
            $this->write($output, "Usage: flux user:clear-permissions <username> <vhost>\n");

            return 1;
        }

        try {
            (new UserRepository($this->connection))->clearPermissions($username, $virtualHost);
        } catch (Throwable $exception) {
            $this->write($output, sprintf("ERROR: %s\n", $exception->getMessage()));

            return 1;
        }

        $this->write($output, sprintf("Cleared permissions for user \"%s\" on virtual host \"%s\".\n", $username, $virtualHost));

        return 0;
    }

    /**
     * @param resource $output
     */
    private function write(mixed $output, string $message): void
    {
        fwrite($output, $message);
    }
}
