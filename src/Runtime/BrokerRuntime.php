<?php

declare(strict_types=1);

namespace Flux\Runtime;

use Closure;
use Flux\Broker\Broker;
use RuntimeException;

final class BrokerRuntime
{
    private RuntimeState $state = RuntimeState::Created;
    private bool $shutdownRequested = false;
    private int $tickCount = 0;
    private Closure $sleep;

    public function __construct(
        private readonly Broker $broker,
        private readonly ConnectionRegistry $connections,
        private readonly ConsumerRegistry $consumers,
        private readonly int $idleIntervalMicroseconds = 50000,
        ?callable $sleep = null
    ) {
        if ($this->idleIntervalMicroseconds < 0) {
            throw new RuntimeException('Runtime idle interval must not be negative.');
        }

        $this->sleep = $sleep instanceof Closure ? $sleep : Closure::fromCallable($sleep ?? 'usleep');
    }

    public function run(?int $maxIterations = null): void
    {
        if ($this->state !== RuntimeState::Created) {
            throw new RuntimeException('Broker runtime can only be started once.');
        }

        if ($maxIterations !== null && $maxIterations < 0) {
            throw new RuntimeException('Runtime max iterations must not be negative.');
        }

        $this->state = RuntimeState::Starting;
        $this->state = RuntimeState::Running;

        try {
            while ($this->state === RuntimeState::Running && !$this->shutdownRequested) {
                if ($maxIterations !== null && $this->tickCount >= $maxIterations) {
                    $this->requestShutdown();
                    break;
                }

                $this->tick();

                if ($this->state === RuntimeState::Running && !$this->shutdownRequested) {
                    ($this->sleep)($this->idleIntervalMicroseconds);
                }
            }
        } finally {
            $this->stop();
        }
    }

    public function tick(): void
    {
        if ($this->state !== RuntimeState::Running) {
            return;
        }

        $this->tickCount++;
    }

    public function requestShutdown(): void
    {
        if ($this->state === RuntimeState::Stopped) {
            return;
        }

        $this->shutdownRequested = true;

        if ($this->state === RuntimeState::Running) {
            $this->state = RuntimeState::Stopping;
        }
    }

    public function stop(): void
    {
        if ($this->state === RuntimeState::Stopped) {
            return;
        }

        $this->state = RuntimeState::Stopping;
        $this->consumers->clear();
        $this->connections->clear();
        $this->state = RuntimeState::Stopped;
    }

    public function state(): RuntimeState
    {
        return $this->state;
    }

    public function broker(): Broker
    {
        return $this->broker;
    }

    public function connections(): ConnectionRegistry
    {
        return $this->connections;
    }

    public function consumers(): ConsumerRegistry
    {
        return $this->consumers;
    }

    public function tickCount(): int
    {
        return $this->tickCount;
    }
}
