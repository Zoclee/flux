<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Console;

use Flux\Console\Commands\ConnectionListCommand;
use Flux\Console\Commands\ConsumerListCommand;
use Flux\Runtime\RuntimeDiagnostics;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DiagnosticsCommandTest extends TestCase
{
    public function testConnectionListDisplaysRuntimeConnections(): void
    {
        [$exitCode, $output] = $this->runCommand(new ConnectionListCommand(new FakeRuntimeDiagnostics(
            connections: [[
                'id' => 'connection-1',
                'protocol' => 'amqp-0-9-1',
                'remote_address' => '127.0.0.1:50000',
                'connected_at' => '2026-08-22T10:00:00+00:00',
            ]]
        )));

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Connections', $output);
        self::assertStringContainsString('connection-1', $output);
        self::assertStringContainsString('amqp-0-9-1', $output);
    }

    public function testConsumerListDisplaysRuntimeConsumers(): void
    {
        [$exitCode, $output] = $this->runCommand(new ConsumerListCommand(new FakeRuntimeDiagnostics(
            consumers: [[
                'id' => 'consumer-1',
                'connection_id' => 'connection-1',
                'virtual_host' => '/',
                'destination' => 'orders',
                'subscription' => 'amqp',
                'created_at' => '2026-08-22T10:00:00+00:00',
            ]]
        )));

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Consumers', $output);
        self::assertStringContainsString('consumer-1', $output);
        self::assertStringContainsString('orders', $output);
    }

    public function testRuntimeUnavailableIsReportedClearly(): void
    {
        [$connectionExitCode, $connectionOutput] = $this->runCommand(new ConnectionListCommand(new FakeRuntimeDiagnostics(unavailable: true)));
        [$consumerExitCode, $consumerOutput] = $this->runCommand(new ConsumerListCommand(new FakeRuntimeDiagnostics(unavailable: true)));

        self::assertSame(1, $connectionExitCode);
        self::assertSame("Runtime: unavailable\n", $connectionOutput);
        self::assertSame(1, $consumerExitCode);
        self::assertSame("Runtime: unavailable\n", $consumerOutput);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runCommand(object $command): array
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $exitCode = $command->run($stream);
        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($output);

        return [$exitCode, $output];
    }
}

final readonly class FakeRuntimeDiagnostics implements RuntimeDiagnostics
{
    /**
     * @param list<array<string, mixed>> $connections
     * @param list<array<string, mixed>> $consumers
     */
    public function __construct(
        private array $connections = [],
        private array $consumers = [],
        private bool $unavailable = false
    ) {
    }

    public function stats(): array
    {
        if ($this->unavailable) {
            throw new RuntimeException('unavailable');
        }

        return ['connections' => count($this->connections), 'consumers' => count($this->consumers)];
    }

    public function connections(): array
    {
        if ($this->unavailable) {
            throw new RuntimeException('unavailable');
        }

        return $this->connections;
    }

    public function consumers(): array
    {
        if ($this->unavailable) {
            throw new RuntimeException('unavailable');
        }

        return $this->consumers;
    }
}
