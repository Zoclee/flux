<?php

declare(strict_types=1);

namespace Flux\Console;

use Flux\Console\Commands\MigrateCommand;
use Flux\Support\Dotenv;

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
            'migrate' => $this->migrate($output),
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
  help        Display this help message
  migrate     Apply pending PostgreSQL database migrations
  --version   Display the Flux version

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
        $projectRoot = $this->projectRoot ?? dirname(__DIR__, 2);
        Dotenv::load($projectRoot . '/.env');
        $config = require $projectRoot . '/config/flux.php';

        return (new MigrateCommand(
            $config,
            $projectRoot . '/database/migrations'
        ))->run($output);
    }
}
