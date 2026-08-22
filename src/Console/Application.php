<?php

declare(strict_types=1);

namespace Flux\Console;

use Flux\Console\Commands\DbStatusCommand;
use Flux\Console\Commands\BindingListCommand;
use Flux\Console\Commands\MigrateCommand;
use Flux\Console\Commands\MessagePeekCommand;
use Flux\Console\Commands\QueueListCommand;
use Flux\Console\Commands\QueueShowCommand;
use Flux\Console\Commands\ReadOnlyDatabaseContext;
use Flux\Console\Commands\ServerStartCommand;
use Flux\Console\Commands\SubscriptionListCommand;
use Flux\Console\Commands\VhostListCommand;
use Flux\Persistence\Postgres\ConnectionConfig;
use Flux\Support\Dotenv;
use Throwable;

final class Application
{
    public const VERSION = '0.1.0-dev';

    public function __construct(
        private readonly ?string $projectRoot = null
    ) {
    }

    /**
     * @param list<string> $argv
     * @param resource|null $output
     */
    public function run(array $argv, mixed $output = null): int
    {
        $output ??= STDOUT;

        $command = $argv[1] ?? 'help';

        return match ($command) {
            'help', '-h', '--help' => $this->showHelp($output),
            '--version', '-V' => $this->showVersion($output),
            'db:status' => $this->dbStatus($output),
            'migrate' => $this->migrate($output),
            'server:start' => $this->serverStart($output),
            'vhost:list' => $this->readOnlyCommand(VhostListCommand::class, [], $output),
            'queue:list' => $this->readOnlyCommand(QueueListCommand::class, [], $output),
            'queue:show' => $this->readOnlyCommand(QueueShowCommand::class, array_slice($argv, 2), $output, true),
            'binding:list' => $this->readOnlyCommand(BindingListCommand::class, [], $output),
            'subscription:list' => $this->readOnlyCommand(SubscriptionListCommand::class, [], $output),
            'message:peek' => $this->readOnlyCommand(MessagePeekCommand::class, array_slice($argv, 2), $output, true),
            default => $this->showUnknownCommand($command, $output),
        };
    }

    /**
     * @param resource $output
     */
    private function showHelp(mixed $output): int
    {
        $this->write($output, <<<'HELP'
Flux 0.1.0-dev

Usage:
  flux <command>

Available commands:
  help                  Display this help message
  --version             Display the Flux version

Database:
  db:status             Show PostgreSQL connection and migration status
  migrate               Apply pending PostgreSQL database migrations

Server:
  server:start          Start the Flux broker runtime

Broker state:
  vhost:list            List virtual hosts
  queue:list            List queues
  queue:show <queue>    Show queue details
  binding:list          List bindings
  subscription:list     List subscriptions
  message:peek <queue>  Inspect queued messages

HELP);

        return 0;
    }

    /**
     * @param resource $output
     */
    private function showVersion(mixed $output): int
    {
        $this->write($output, sprintf("Flux %s\n", self::VERSION));

        return 0;
    }

    /**
     * @param resource $output
     */
    private function showUnknownCommand(string $command, mixed $output): int
    {
        $this->write($output, sprintf("Unknown command: %s\n\n", $command));
        $this->showHelp($output);

        return 1;
    }

    /**
     * @param resource $output
     */
    private function write(mixed $output, string $message): void
    {
        fwrite($output, $message);
    }

    /**
     * @param resource $output
     */
    private function migrate(mixed $output): int
    {
        try {
            [$projectRoot, $config] = $this->projectContext();
            $databaseConfig = ConnectionConfig::fromArray($config['database']);
        } catch (Throwable $exception) {
            $this->write($output, "Flux Database Migrations\n\n");
            $this->write($output, sprintf("ERROR: %s\n", $exception->getMessage()));

            return 1;
        }

        return (new MigrateCommand(
            $databaseConfig,
            $projectRoot . '/database/migrations'
        ))->run($output);
    }

    /**
     * @param resource $output
     */
    private function dbStatus(mixed $output): int
    {
        try {
            [$projectRoot, $config] = $this->projectContext();
            $databaseConfig = ConnectionConfig::fromArray($config['database']);
        } catch (Throwable $exception) {
            $this->write($output, "Flux Database Status\n\n");
            $this->write($output, "Status:   disconnected\n\n");
            $this->write($output, sprintf("ERROR: %s\n", $exception->getMessage()));

            return 1;
        }

        return (new DbStatusCommand(
            $databaseConfig,
            $projectRoot . '/database/migrations'
        ))->run($output);
    }

    /**
     * @param resource $output
     */
    private function serverStart(mixed $output): int
    {
        try {
            [, $config] = $this->projectContext();
            $databaseConfig = ConnectionConfig::fromArray($config['database']);
            $amqpConfig = $config['amqp'] ?? [];
        } catch (Throwable $exception) {
            $this->write($output, "Flux Message Broker\n\n");
            $this->write($output, "Status:   stopped\n\n");
            $this->write($output, sprintf("ERROR: %s\n", $exception->getMessage()));

            return 1;
        }

        return (new ServerStartCommand(
            $databaseConfig,
            self::VERSION,
            (string) ($amqpConfig['host'] ?? '127.0.0.1'),
            (int) ($amqpConfig['port'] ?? 5672)
        ))->run($output);
    }

    /**
     * @param class-string $commandClass
     * @param list<string> $arguments
     * @param resource $output
     */
    private function readOnlyCommand(string $commandClass, array $arguments, mixed $output, bool $passesArguments = false): int
    {
        try {
            [, $config] = $this->projectContext();
            $context = new ReadOnlyDatabaseContext(ConnectionConfig::fromArray($config['database']));
        } catch (Throwable $exception) {
            $this->write($output, sprintf("ERROR: %s\n", $exception->getMessage()));

            return 1;
        }

        $command = new $commandClass($context);

        if (!$passesArguments) {
            return $command->run($output);
        }

        return $command->run($arguments, $output);
    }

    /**
     * @return array{0: string, 1: array{database: array<string, mixed>, amqp?: array<string, mixed>}}
     */
    private function projectContext(): array
    {
        $projectRoot = $this->projectRoot ?? dirname(__DIR__, 2);
        Dotenv::load($projectRoot . '/.env');
        $config = require $projectRoot . '/config/flux.php';

        return [$projectRoot, $config];
    }
}
