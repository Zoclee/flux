<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Protocol\Amqp;

use Flux\Broker\Broker;
use Flux\Broker\DestinationType;
use Flux\Persistence\Postgres\BindingRepository;
use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\Migrator;
use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\RoutingSourceRepository;
use Flux\Persistence\Postgres\SubscriptionRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use Flux\Protocol\Amqp\AmqpConnection;
use Flux\Protocol\Amqp\AmqpListener;
use Flux\Protocol\Amqp\Frame;
use Flux\Protocol\Amqp\FrameCodec;
use Flux\Runtime\ConnectionRegistry;
use PDO;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

final class AmqpTopologyTest extends TestCase
{
    private Connection $connection;
    private PDO $pdo;
    private int $virtualHostId;

    #[Before]
    public function setUpDatabase(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('The pdo_pgsql extension is required for AMQP topology integration tests.');
        }

        $dsn = getenv('FLUX_TEST_DATABASE_URL');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set FLUX_TEST_DATABASE_URL to run AMQP topology integration tests.');
        }

        $this->connection = Connection::fromDsn($dsn);
        $this->pdo = $this->connection->pdo();
        $this->assertSafeTestDatabase();
        $this->resetSchema();
        (new Migrator($this->connection, dirname(__DIR__, 4) . '/database/migrations'))->migrate();
        $this->virtualHostId = (new VirtualHostRepository($this->connection))->findByName('/')?->id
            ?? throw new \RuntimeException('Default virtual host was not created by migrations.');
    }

    public function testAmqpCanDeclareQueueExchangeBindAndCloseChannel(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);

            fwrite($client, $codec->encode(Frame::methodFrame(1, 50, 10, $this->queueDeclare('orders', durable: true))));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(1, 50, 10, $this->queueDeclare('orders', durable: true))));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(1, 40, 10, $this->exchangeDeclare('orders.direct', 'direct', durable: true))));
            self::assertSame([40, 11], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(1, 50, 20, $this->queueBind('orders', 'orders.direct', 'created'))));
            self::assertSame([50, 21], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(1, 50, 20, $this->queueBind('orders', '', 'orders'))));
            self::assertSame([50, 21], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(1, 20, 40, pack('n', 200) . "\x00" . pack('nn', 0, 0))));
            self::assertSame([20, 41], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }

        $queue = (new DestinationRepository($this->connection))->findByName($this->virtualHostId, 'orders');
        self::assertNotNull($queue);
        self::assertSame(DestinationType::Queue, $queue->type);
        self::assertTrue($queue->durable);

        $source = (new RoutingSourceRepository($this->connection))->findByName($this->virtualHostId, 'orders.direct');
        self::assertNotNull($source);
        self::assertSame('direct', $source->type->value);

        $bindings = (new BindingRepository($this->connection))->findForRoute($this->virtualHostId, 'orders.direct', 'created');
        self::assertCount(1, $bindings);
        self::assertSame($queue->id, $bindings[0]->destinationId);
    }

    public function testIncompatibleQueueRedeclarationClosesChannelButConnectionSurvives(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            fwrite($client, $codec->encode(Frame::methodFrame(1, 50, 10, $this->queueDeclare('orders', durable: true))));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(1, 50, 10, $this->queueDeclare('orders', durable: false))));
            self::assertSame([20, 40], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(2, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testUnsupportedExchangeTypeClosesChannel(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            fwrite($client, $codec->encode(Frame::methodFrame(1, 40, 10, $this->exchangeDeclare('orders.topic', 'topic'))));

            self::assertSame([20, 40], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    /**
     * @return array{0: AmqpListener, 1: resource}
     */
    private function startedListener(): array
    {
        $listener = new AmqpListener(new ConnectionRegistry(), '127.0.0.1', 0, broker: $this->broker());
        $listener->start();
        $client = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $listener->port()), $errorCode, $errorMessage, 1.0);
        self::assertIsResource($client, sprintf('Could not connect to AMQP listener: %s', $errorMessage));
        stream_set_blocking($client, false);

        return [$listener, $client];
    }

    /**
     * @param resource $client
     */
    private function connectAndOpenChannel(AmqpListener $listener, mixed $client, int $channel): void
    {
        $codec = new FrameCodec();
        fwrite($client, AmqpConnection::PROTOCOL_HEADER);
        self::assertSame([10, 10], $this->readMethod($listener, $client));
        fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 11)));
        self::assertSame([10, 30], $this->readMethod($listener, $client));
        fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 31)));
        $listener->tick();
        fwrite($client, $codec->encode(Frame::methodFrame(0, 10, 40)));
        self::assertSame([10, 41], $this->readMethod($listener, $client));
        fwrite($client, $codec->encode(Frame::methodFrame($channel, 20, 10, "\x00")));
        self::assertSame([20, 11], $this->readMethod($listener, $client));
    }

    private function queueDeclare(string $queue, bool $durable): string
    {
        $bits = $durable ? 0b00000010 : 0;

        return pack('n', 0) . $this->shortString($queue) . chr($bits) . pack('N', 0);
    }

    private function exchangeDeclare(string $exchange, string $type, bool $durable = false): string
    {
        $bits = $durable ? 0b00000010 : 0;

        return pack('n', 0) . $this->shortString($exchange) . $this->shortString($type) . chr($bits) . pack('N', 0);
    }

    private function queueBind(string $queue, string $exchange, string $routingKey): string
    {
        return pack('n', 0)
            . $this->shortString($queue)
            . $this->shortString($exchange)
            . $this->shortString($routingKey)
            . "\x00"
            . pack('N', 0);
    }

    private function shortString(string $value): string
    {
        return chr(strlen($value)) . $value;
    }

    /**
     * @param resource $client
     * @return array{0: int, 1: int}
     */
    private function readMethod(AmqpListener $listener, mixed $client): array
    {
        $codec = new FrameCodec();

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $listener->tick();
            $bytes = fread($client, 8192);
            if (is_string($bytes) && $bytes !== '') {
                $frames = $codec->push($bytes);
                if ($frames !== []) {
                    return $frames[0]->method();
                }
            }
            usleep(1000);
        }

        self::fail('Timed out waiting for AMQP method frame.');
    }

    private function broker(): Broker
    {
        return new Broker(
            new VirtualHostRepository($this->connection),
            new PublishTransaction($this->connection),
            new DestinationRepository($this->connection),
            new SubscriptionRepository($this->connection),
            new DeliveryRepository($this->connection),
            new BindingRepository($this->connection),
            new RoutingSourceRepository($this->connection)
        );
    }

    private function resetSchema(): void
    {
        $this->pdo->exec('DROP SCHEMA public CASCADE');
        $this->pdo->exec('CREATE SCHEMA public');
    }

    private function assertSafeTestDatabase(): void
    {
        $database = (string) $this->pdo->query('SELECT current_database()')->fetchColumn();

        if (!str_contains(strtolower($database), 'test')) {
            self::markTestSkipped(sprintf(
                'Refusing to reset PostgreSQL database "%s"; FLUX_TEST_DATABASE_URL must point to a test database.',
                $database
            ));
        }
    }
}
