<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Runtime;

use DateTimeImmutable;
use Flux\Broker\Broker;
use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\ConnectionConfig;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\SubscriptionRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use Flux\Runtime\BrokerRuntime;
use Flux\Runtime\ConnectionRegistry;
use Flux\Runtime\ConsumerRegistry;
use Flux\Runtime\RuntimeConnection;
use Flux\Runtime\RuntimeConsumer;
use Flux\Runtime\RuntimeState;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BrokerRuntimeTest extends TestCase
{
    public function testRuntimeRunsFiniteIdleLoopAndStopsCleanly(): void
    {
        $runtime = $this->runtime();

        $runtime->run(maxIterations: 3);

        self::assertSame(RuntimeState::Stopped, $runtime->state());
        self::assertSame(3, $runtime->tickCount());
    }

    public function testShutdownRequestFromIdleSleepIsHonored(): void
    {
        $runtime = null;
        $runtime = $this->runtime(static function () use (&$runtime): void {
            self::assertSame(RuntimeState::Running, $runtime?->state());
            $runtime?->requestShutdown();
        });

        $runtime->run();

        self::assertSame(RuntimeState::Stopped, $runtime->state());
        self::assertSame(1, $runtime->tickCount());
    }

    public function testRuntimeCannotStartTwice(): void
    {
        $runtime = $this->runtime();
        $runtime->run(maxIterations: 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Broker runtime can only be started once.');

        $runtime->run(maxIterations: 0);
    }

    public function testStoppedRuntimeDoesNotContinueTicking(): void
    {
        $runtime = $this->runtime();

        $runtime->run(maxIterations: 1);
        $runtime->tick();

        self::assertSame(RuntimeState::Stopped, $runtime->state());
        self::assertSame(1, $runtime->tickCount());
    }

    public function testStopClearsRuntimeRegistries(): void
    {
        $connections = new ConnectionRegistry();
        $consumers = new ConsumerRegistry();
        $connection = new RuntimeConnection(
            '00000000-0000-4000-8000-000000000201',
            'test',
            new DateTimeImmutable()
        );
        $consumer = new RuntimeConsumer(
            '00000000-0000-4000-8000-000000000202',
            $connection->id,
            '/',
            'orders',
            'worker-a',
            new DateTimeImmutable()
        );
        $connections->add($connection);
        $consumers->add($consumer);

        $runtime = new BrokerRuntime($this->broker(), $connections, $consumers, 0, static function (): void {
        });
        $runtime->run(maxIterations: 0);

        self::assertSame(0, $connections->count());
        self::assertSame(0, $consumers->count());
    }

    private function runtime(?callable $sleep = null): BrokerRuntime
    {
        return new BrokerRuntime(
            $this->broker(),
            new ConnectionRegistry(),
            new ConsumerRegistry(),
            0,
            $sleep ?? static function (): void {
            }
        );
    }

    private function broker(): Broker
    {
        $connection = new Connection(new ConnectionConfig('127.0.0.1', 5432, 'flux_test', 'flux', null));

        return new Broker(
            new VirtualHostRepository($connection),
            new PublishTransaction($connection),
            new DestinationRepository($connection),
            new SubscriptionRepository($connection),
            new DeliveryRepository($connection)
        );
    }
}
