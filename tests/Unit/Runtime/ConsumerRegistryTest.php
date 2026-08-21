<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Runtime;

use DateTimeImmutable;
use Flux\Runtime\ConsumerRegistry;
use Flux\Runtime\RuntimeConsumer;
use Flux\Runtime\RuntimeRegistrationException;
use PHPUnit\Framework\TestCase;

final class ConsumerRegistryTest extends TestCase
{
    public function testAddFindCountListAndListByConnection(): void
    {
        $registry = new ConsumerRegistry();
        $consumerA = $this->consumer(
            '00000000-0000-4000-8000-000000000011',
            '00000000-0000-4000-8000-000000000101'
        );
        $consumerB = $this->consumer(
            '00000000-0000-4000-8000-000000000012',
            '00000000-0000-4000-8000-000000000101'
        );
        $consumerC = $this->consumer(
            '00000000-0000-4000-8000-000000000013',
            '00000000-0000-4000-8000-000000000102'
        );

        $registry->add($consumerA);
        $registry->add($consumerB);
        $registry->add($consumerC);

        self::assertSame($consumerA, $registry->find($consumerA->id));
        self::assertSame(3, $registry->count());
        self::assertSame([$consumerA, $consumerB, $consumerC], $registry->all());
        self::assertSame([$consumerA, $consumerB], $registry->allByConnection($consumerA->connectionId));
    }

    public function testDuplicateConsumerIdFailsClearly(): void
    {
        $registry = new ConsumerRegistry();
        $consumer = $this->consumer(
            '00000000-0000-4000-8000-000000000014',
            '00000000-0000-4000-8000-000000000103'
        );

        $registry->add($consumer);

        $this->expectException(RuntimeRegistrationException::class);
        $this->expectExceptionMessage('Runtime consumer "00000000-0000-4000-8000-000000000014" is already registered.');

        $registry->add($consumer);
    }

    public function testRemoveUnknownRemoveConnectionCleanupAndClear(): void
    {
        $registry = new ConsumerRegistry();
        $connectionId = '00000000-0000-4000-8000-000000000104';
        $consumerA = $this->consumer('00000000-0000-4000-8000-000000000015', $connectionId);
        $consumerB = $this->consumer('00000000-0000-4000-8000-000000000016', $connectionId);
        $consumerC = $this->consumer(
            '00000000-0000-4000-8000-000000000017',
            '00000000-0000-4000-8000-000000000105'
        );

        $registry->add($consumerA);
        $registry->add($consumerB);
        $registry->add($consumerC);

        self::assertFalse($registry->remove('00000000-0000-4000-8000-000000009999'));
        self::assertTrue($registry->remove($consumerA->id));
        self::assertSame(1, $registry->removeByConnection($connectionId));
        self::assertSame([$consumerC], $registry->all());

        $registry->clear();

        self::assertSame(0, $registry->count());
    }

    private function consumer(string $id, string $connectionId): RuntimeConsumer
    {
        return new RuntimeConsumer(
            $id,
            $connectionId,
            '/',
            'orders',
            'worker-a',
            new DateTimeImmutable()
        );
    }
}
