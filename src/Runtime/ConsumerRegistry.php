<?php

declare(strict_types=1);

namespace Flux\Runtime;

final class ConsumerRegistry
{
    /**
     * @var array<string, RuntimeConsumer>
     */
    private array $consumers = [];

    public function add(RuntimeConsumer $consumer): void
    {
        if (isset($this->consumers[$consumer->id])) {
            throw new RuntimeRegistrationException(sprintf(
                'Runtime consumer "%s" is already registered.',
                $consumer->id
            ));
        }

        $this->consumers[$consumer->id] = $consumer;
    }

    public function remove(string $consumerId): bool
    {
        if (!isset($this->consumers[$consumerId])) {
            return false;
        }

        unset($this->consumers[$consumerId]);

        return true;
    }

    public function removeByConnection(string $connectionId): int
    {
        $removed = 0;

        foreach ($this->consumers as $id => $consumer) {
            if ($consumer->connectionId !== $connectionId) {
                continue;
            }

            unset($this->consumers[$id]);
            $removed++;
        }

        return $removed;
    }

    public function find(string $consumerId): ?RuntimeConsumer
    {
        return $this->consumers[$consumerId] ?? null;
    }

    /**
     * @return list<RuntimeConsumer>
     */
    public function all(): array
    {
        return array_values($this->consumers);
    }

    /**
     * @return list<RuntimeConsumer>
     */
    public function allByConnection(string $connectionId): array
    {
        return array_values(array_filter(
            $this->consumers,
            static fn (RuntimeConsumer $consumer): bool => $consumer->connectionId === $connectionId
        ));
    }

    public function count(): int
    {
        return count($this->consumers);
    }

    public function clear(): void
    {
        $this->consumers = [];
    }
}
