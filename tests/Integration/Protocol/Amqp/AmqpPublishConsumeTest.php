<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Protocol\Amqp;

use Flux\Broker\Broker;
use Flux\Broker\DeliveryState;
use Flux\Persistence\Postgres\BindingRepository;
use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\MessageRepository;
use Flux\Persistence\Postgres\MessageRouteRepository;
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
use Flux\Runtime\ConsumerRegistry;
use PDO;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

final class AmqpPublishConsumeTest extends TestCase
{
    private Connection $connection;
    private PDO $pdo;
    private int $virtualHostId;

    /**
     * @var list<Frame>
     */
    private array $pendingFrames = [];

    #[Before]
    public function setUpDatabase(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('The pdo_pgsql extension is required for AMQP publish/consume integration tests.');
        }

        $dsn = getenv('FLUX_TEST_DATABASE_URL');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set FLUX_TEST_DATABASE_URL to run AMQP publish/consume integration tests.');
        }

        $this->connection = Connection::fromDsn($dsn);
        $this->pdo = $this->connection->pdo();
        $this->assertSafeTestDatabase();
        $this->resetSchema();
        (new Migrator($this->connection, dirname(__DIR__, 4) . '/database/migrations'))->migrate();
        $this->virtualHostId = (new VirtualHostRepository($this->connection))->findByName('/')?->id
            ?? throw new \RuntimeException('Default virtual host was not created by migrations.');
    }

    public function testDefaultExchangePublishConsumeAndAckPreservesBinaryBodyAndProperties(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();
        $payload = "hello\x00world\xff";

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, strlen($payload), 'application/octet-stream', 7)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, substr($payload, 0, 5))));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, substr($payload, 5))));
            $listener->tick();

            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $deliver = $this->readMethodFrame($listener, $client);
            self::assertSame([60, 60], $deliver->method());
            $deliveryTag = $this->deliveryTagFromDeliver($deliver);
            $header = $this->readFrame($listener, $client);
            self::assertSame(Frame::TYPE_HEADER, $header->type);
            self::assertSame(strlen($payload), $this->bodySizeFromHeader($header));
            $body = $this->readFrame($listener, $client);
            self::assertSame(Frame::TYPE_BODY, $body->type);
            self::assertSame($payload, $body->payload);

            $this->sendMethod($client, 1, 60, 80, $this->packLongLong($deliveryTag) . "\x00");
            $listener->tick();
        } finally {
            fclose($client);
            $listener->stop();
        }

        $delivery = $this->singleDelivery('orders');
        self::assertSame(DeliveryState::Acknowledged, $delivery->state);

        $message = (new MessageRepository($this->connection))->findById(
            (new MessageRouteRepository($this->connection))->findById($delivery->messageRouteId)?->messageId ?? 0
        );
        self::assertNotNull($message);
        self::assertSame($payload, $message->payload);
        self::assertSame('application/octet-stream', $message->contentType);
        self::assertSame(7, $message->priority);
    }

    public function testDirectExchangeRejectWithRequeueRedeliversThenRejects(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 40, 10, $this->exchangeDeclare('orders.direct'));
            self::assertSame([40, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 20, $this->queueBind('orders', 'orders.direct', 'created'));
            self::assertSame([50, 21], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('orders.direct', 'created'));
            fwrite($client, $codec->encode($this->contentHeader(1, 5)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, 'first')));
            $listener->tick();

            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $first = $this->readMethodFrame($listener, $client);
            $firstTag = $this->deliveryTagFromDeliver($first);
            $this->readFrame($listener, $client);
            self::assertSame('first', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 60, 90, $this->packLongLong($firstTag) . "\x01");
            $redelivered = $this->readMethodFrame($listener, $client);
            self::assertSame([60, 60], $redelivered->method());
            $secondTag = $this->deliveryTagFromDeliver($redelivered);
            self::assertNotSame($firstTag, $secondTag);
            $this->readFrame($listener, $client);
            self::assertSame('first', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 60, 90, $this->packLongLong($secondTag) . "\x00");
            $listener->tick();
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame(DeliveryState::Rejected, $this->singleDelivery('orders')->state);
    }

    public function testDisconnectReleasesUnackedDeliveries(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, 4)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, 'held')));
            $listener->tick();
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $this->readMethodFrame($listener, $client);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);
            fclose($client);
            for ($i = 0; $i < 10; $i++) {
                $listener->tick();
                usleep(1000);
            }
        } finally {
            $listener->stop();
        }

        self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
    }

    public function testHeartbeatTimeoutReleasesUnackedDeliveryAndCleansConsumerRegistry(): void
    {
        $now = 0;
        $connections = new ConnectionRegistry();
        $consumers = new ConsumerRegistry();
        $listener = new AmqpListener(
            $connections,
            '127.0.0.1',
            0,
            broker: $this->broker(),
            consumers: $consumers,
            heartbeatInterval: 1,
            clock: static function () use (&$now): int {
                return $now * 1_000_000_000;
            }
        );
        $listener->start();
        $client = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $listener->port()), $errorCode, $errorMessage, 1.0);
        self::assertIsResource($client, sprintf('Could not connect to AMQP listener: %s', $errorMessage));
        stream_set_blocking($client, false);
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, 4)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, 'held')));
            $listener->tick();
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $this->readMethodFrame($listener, $client);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);
            self::assertSame(1, $connections->count());
            self::assertSame(1, $consumers->count());

            $now = 2;
            $listener->tick();
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame(0, $connections->count());
        self::assertSame(0, $consumers->count());
        self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
    }

    public function testCleanupIsIdempotentAfterDisconnect(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, 4)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, 'held')));
            $listener->tick();
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $this->readMethodFrame($listener, $client);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);
            fclose($client);
            $listener->tick();
            $listener->stop();
            $listener->stop();
        } finally {
            $listener->stop();
        }

        self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
    }

    public function testBasicQosReturnsQosOk(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 1, false));

            self::assertSame([60, 11], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testPrefetchOneDeliversOnlyOneUnackedMessage(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first', 'second']);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 1, false));
            self::assertSame([60, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $this->readDeliveryBody($listener, $client);
            $this->assertNoFrame($listener, $client);
            self::assertSame([DeliveryState::Reserved, DeliveryState::Pending], $this->deliveryStates('orders'));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testAckFreesPrefetchCapacityAndAllowsNextDelivery(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first', 'second']);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 1, false));
            self::assertSame([60, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $first = $this->readMethodFrame($listener, $client);
            $firstTag = $this->deliveryTagFromDeliver($first);
            $this->readFrame($listener, $client);
            self::assertSame('first', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 60, 80, $this->packLongLong($firstTag) . "\x00");
            $second = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 60], $second->method());
            $this->readFrame($listener, $client);
            self::assertSame('second', $this->readFrame($listener, $client)->payload);
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testRejectFreesPrefetchCapacity(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first', 'second']);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 1, false));
            self::assertSame([60, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $first = $this->readMethodFrame($listener, $client);
            $firstTag = $this->deliveryTagFromDeliver($first);
            $this->readFrame($listener, $client);
            self::assertSame('first', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 60, 90, $this->packLongLong($firstTag) . "\x00");
            $second = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 60], $second->method());
            $this->readFrame($listener, $client);
            self::assertSame('second', $this->readFrame($listener, $client)->payload);
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame([DeliveryState::Rejected, DeliveryState::Pending], $this->deliveryStates('orders'));
    }

    public function testNackRequeueFalseRejectsDelivery(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first']);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $deliver = $this->readMethodFrame($listener, $client);
            $deliveryTag = $this->deliveryTagFromDeliver($deliver);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);

            $this->sendMethod($client, 1, 60, 120, $this->basicNack($deliveryTag, false, false));
            $listener->tick();
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame(DeliveryState::Rejected, $this->singleDelivery('orders')->state);
    }

    public function testNackRequeueTrueReleasesAndRedelivers(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first']);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 1, false));
            self::assertSame([60, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $first = $this->readMethodFrame($listener, $client);
            $firstTag = $this->deliveryTagFromDeliver($first);
            $this->readFrame($listener, $client);
            self::assertSame('first', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 60, 120, $this->basicNack($firstTag, false, true));
            $second = $this->readMethodFrame($listener, $client);
            $secondTag = $this->deliveryTagFromDeliver($second);

            self::assertSame([60, 60], $second->method());
            self::assertNotSame($firstTag, $secondTag);
            $this->readFrame($listener, $client);
            self::assertSame('first', $this->readFrame($listener, $client)->payload);
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
    }

    public function testPrefetchZeroIsUnlimited(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first', 'second']);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 0, false));
            self::assertSame([60, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));

            self::assertSame('first', $this->readDeliveryBody($listener, $client));
            self::assertSame('second', $this->readDeliveryBody($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame([DeliveryState::Pending, DeliveryState::Pending], $this->deliveryStates('orders'));
    }

    public function testUnsupportedPrefetchSizeIsRejected(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(1, 1, false));

            self::assertSame([20, 40], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testUnsupportedQosGlobalModeIsRejected(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 1, true));

            self::assertSame([20, 40], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testInvalidNackDeliveryTagFailsSafelyAndRuntimeContinues(): void
    {
        $connections = new ConnectionRegistry();
        $listener = new AmqpListener(
            $connections,
            '127.0.0.1',
            0,
            broker: $this->broker(),
            consumers: new ConsumerRegistry()
        );
        $listener->start();
        $client = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $listener->port()), $errorCode, $errorMessage, 1.0);
        self::assertIsResource($client, sprintf('Could not connect to AMQP listener: %s', $errorMessage));
        stream_set_blocking($client, false);

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 120, $this->basicNack(99, false, false));

            self::assertSame([20, 40], $this->readMethod($listener, $client));
            self::assertSame(1, $connections->count());
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame(0, $connections->count());
    }

    public function testNackMultipleTrueIsRejected(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first']);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $deliver = $this->readMethodFrame($listener, $client);
            $deliveryTag = $this->deliveryTagFromDeliver($deliver);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);

            $this->sendMethod($client, 1, 60, 120, $this->basicNack($deliveryTag, true, false));

            self::assertSame([20, 40], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
    }

    public function testMultipleChannelsKeepPrefetchLimitsSeparate(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            fwrite($client, $codec->encode(Frame::methodFrame(2, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['first', 'second']);
            $this->sendMethod($client, 1, 60, 10, $this->basicQos(0, 1, false));
            self::assertSame([60, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 2, 60, 10, $this->basicQos(0, 1, false));
            self::assertSame([60, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            self::assertSame('first', $this->readDeliveryBody($listener, $client));
            $this->sendMethod($client, 2, 60, 20, $this->basicConsume('orders', 'consumer-b'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            self::assertSame('second', $this->readDeliveryBody($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame([DeliveryState::Pending, DeliveryState::Pending], $this->deliveryStates('orders'));
    }

    public function testConfirmSelectReturnsSelectOk(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));

            self::assertSame([85, 11], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testConfirmSelectNoWaitDoesNotSendSelectOkButPublishesAreConfirmed(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(true));
            $this->publishBody($listener, $client, 1, '', 'orders', 'confirmed');
            $ack = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 80], $ack->method());
            self::assertSame(1, $this->deliveryTagFromBasicAck($ack));
            self::assertFalse($this->multipleFromBasicAck($ack));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testPublishAfterConfirmModeReceivesIndividualBasicAck(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, '', 'orders', 'confirmed');
            $ack = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 80], $ack->method());
            self::assertSame(1, $this->deliveryTagFromBasicAck($ack));
            self::assertFalse($this->multipleFromBasicAck($ack));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testSubsequentConfirmPublishesIncrementDeliveryTags(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, '', 'orders', 'first');
            self::assertSame(1, $this->deliveryTagFromBasicAck($this->readMethodFrame($listener, $client)));
            $this->publishBody($listener, $client, 1, '', 'orders', 'second');
            self::assertSame(2, $this->deliveryTagFromBasicAck($this->readMethodFrame($listener, $client)));
            $this->publishBody($listener, $client, 1, '', 'orders', 'third');
            self::assertSame(3, $this->deliveryTagFromBasicAck($this->readMethodFrame($listener, $client)));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testSeparateChannelsMaintainIndependentConfirmCounters(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            fwrite($client, $codec->encode(Frame::methodFrame(2, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 2, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, '', 'orders', 'first');
            $ackOne = $this->readMethodFrame($listener, $client);
            $this->publishBody($listener, $client, 2, '', 'orders', 'second');
            $ackTwo = $this->readMethodFrame($listener, $client);

            self::assertSame(1, $ackOne->channel);
            self::assertSame(1, $this->deliveryTagFromBasicAck($ackOne));
            self::assertSame(2, $ackTwo->channel);
            self::assertSame(1, $this->deliveryTagFromBasicAck($ackTwo));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testNonConfirmChannelReceivesNoPublisherConfirm(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->publishBody($listener, $client, 1, '', 'orders', 'unconfirmed');

            $this->assertNoFrame($listener, $client);
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testSplitBinaryPublishIsConfirmedOnlyAfterCompletePersistence(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();
        $payload = "first\x00second\xff";

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, strlen($payload), 'application/octet-stream', 5)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, substr($payload, 0, 5))));
            $listener->tick();
            $this->assertNoFrame($listener, $client);
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, substr($payload, 5))));
            $ack = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 80], $ack->method());
            self::assertSame(1, $this->deliveryTagFromBasicAck($ack));
            self::assertSame($payload, $this->payloadForSingleMessage('orders'));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testFailedConfirmPublishIsNotPositivelyAcknowledged(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));
            $this->publishBody($listener, $client, 1, 'missing.exchange', 'orders', 'failed');
            $frame = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $frame->method());
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testChannelCloseClearsConfirmStateAndSequenceTracking(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));
            $this->publishBody($listener, $client, 1, '', 'orders', 'first');
            self::assertSame(1, $this->deliveryTagFromBasicAck($this->readMethodFrame($listener, $client)));
            fwrite($client, $codec->encode(Frame::methodFrame(1, 20, 40, pack('n', 200) . $this->shortString('Goodbye') . pack('nn', 0, 0))));
            self::assertSame([20, 41], $this->readMethod($listener, $client));
            fwrite($client, $codec->encode(Frame::methodFrame(1, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
            $this->publishBody($listener, $client, 1, '', 'orders', 'second');
            $this->assertNoFrame($listener, $client);
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));
            $this->publishBody($listener, $client, 1, '', 'orders', 'third');
            self::assertSame(1, $this->deliveryTagFromBasicAck($this->readMethodFrame($listener, $client)));
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
        $listener = new AmqpListener(
            new ConnectionRegistry(),
            '127.0.0.1',
            0,
            broker: $this->broker(),
            consumers: new ConsumerRegistry()
        );
        $listener->start();
        $client = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $listener->port()), $errorCode, $errorMessage, 1.0);
        self::assertIsResource($client, sprintf('Could not connect to AMQP listener: %s', $errorMessage));
        stream_set_blocking($client, false);

        return [$listener, $client];
    }

    /**
     * @param resource $client
     */
    private function connectAndOpenChannel(AmqpListener $listener, mixed $client, int $channel, int $heartbeat = 60): void
    {
        $codec = new FrameCodec();
        fwrite($client, AmqpConnection::PROTOCOL_HEADER);
        self::assertSame([10, 10], $this->readMethod($listener, $client));
        $this->sendMethod($client, 0, 10, 11);
        self::assertSame([10, 30], $this->readMethod($listener, $client));
        $this->sendMethod($client, 0, 10, 31, $this->tuneOk($heartbeat));
        $listener->tick();
        $this->sendMethod($client, 0, 10, 40);
        self::assertSame([10, 41], $this->readMethod($listener, $client));
        fwrite($client, $codec->encode(Frame::methodFrame($channel, 20, 10, "\x00")));
        self::assertSame([20, 11], $this->readMethod($listener, $client));
    }

    /**
     * @param resource $client
     */
    private function sendMethod(mixed $client, int $channel, int $classId, int $methodId, string $arguments = ''): void
    {
        fwrite($client, (new FrameCodec())->encode(Frame::methodFrame($channel, $classId, $methodId, $arguments)));
    }

    private function queueDeclare(string $queue): string
    {
        return pack('n', 0) . $this->shortString($queue) . "\x02" . pack('N', 0);
    }

    private function exchangeDeclare(string $exchange): string
    {
        return pack('n', 0) . $this->shortString($exchange) . $this->shortString('direct') . "\x02" . pack('N', 0);
    }

    private function queueBind(string $queue, string $exchange, string $routingKey): string
    {
        return pack('n', 0) . $this->shortString($queue) . $this->shortString($exchange) . $this->shortString($routingKey) . "\x00" . pack('N', 0);
    }

    private function basicPublish(string $exchange, string $routingKey): string
    {
        return pack('n', 0) . $this->shortString($exchange) . $this->shortString($routingKey) . "\x00";
    }

    private function basicConsume(string $queue, string $consumerTag): string
    {
        return pack('n', 0) . $this->shortString($queue) . $this->shortString($consumerTag) . "\x00" . pack('N', 0);
    }

    private function basicQos(int $prefetchSize, int $prefetchCount, bool $global): string
    {
        return pack('Nn', $prefetchSize, $prefetchCount) . chr($global ? 1 : 0);
    }

    private function basicNack(int $deliveryTag, bool $multiple, bool $requeue): string
    {
        $bits = 0;
        if ($multiple) {
            $bits |= 0b00000001;
        }
        if ($requeue) {
            $bits |= 0b00000010;
        }

        return $this->packLongLong($deliveryTag) . chr($bits);
    }

    private function confirmSelect(bool $noWait): string
    {
        return chr($noWait ? 1 : 0);
    }

    /**
     * @param resource $client
     */
    private function publishBody(
        AmqpListener $listener,
        mixed $client,
        int $channel,
        string $exchange,
        string $routingKey,
        string $body
    ): void {
        $codec = new FrameCodec();
        $this->sendMethod($client, $channel, 60, 40, $this->basicPublish($exchange, $routingKey));
        fwrite($client, $codec->encode($this->contentHeader($channel, strlen($body))));
        fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, $channel, $body)));
        $listener->tick();
    }

    /**
     * @param resource $client
     * @param list<string> $bodies
     */
    private function declareQueueAndPublishBodies(AmqpListener $listener, mixed $client, string $queue, array $bodies): void
    {
        $codec = new FrameCodec();
        $this->sendMethod($client, 1, 50, 10, $this->queueDeclare($queue));
        self::assertSame([50, 11], $this->readMethod($listener, $client));

        foreach ($bodies as $body) {
            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', $queue));
            fwrite($client, $codec->encode($this->contentHeader(1, strlen($body))));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, $body)));
            $listener->tick();
        }
    }

    /**
     * @param resource $client
     */
    private function readDeliveryBody(AmqpListener $listener, mixed $client): string
    {
        $deliver = $this->readMethodFrame($listener, $client);
        self::assertSame([60, 60], $deliver->method());
        $this->readFrame($listener, $client);

        return $this->readFrame($listener, $client)->payload;
    }

    /**
     * @param resource $client
     */
    private function assertNoFrame(AmqpListener $listener, mixed $client): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $listener->tick();
            $bytes = fread($client, 8192);
            if (is_string($bytes) && $bytes !== '') {
                $frames = (new FrameCodec())->push($bytes);
                if ($frames !== []) {
                    self::fail('Unexpected AMQP frame was received.');
                }
            }
            usleep(1000);
        }

        self::assertTrue(true);
    }

    private function contentHeader(int $channel, int $bodySize, ?string $contentType = null, int $priority = 0): Frame
    {
        $flags = 0b0001000000000000 | 0b0000100000000000;
        $values = "\x02" . chr($priority);
        if ($contentType !== null) {
            $flags |= 0b1000000000000000;
            $values = $this->shortString($contentType) . $values;
        }

        return new Frame(Frame::TYPE_HEADER, $channel, pack('nn', 60, 0) . $this->packLongLong($bodySize) . pack('n', $flags) . $values);
    }

    private function shortString(string $value): string
    {
        return chr(strlen($value)) . $value;
    }

    private function tuneOk(int $heartbeat): string
    {
        return pack('nNn', 0, 131072, $heartbeat);
    }

    private function packLongLong(int $value): string
    {
        return pack('NN', intdiv($value, 4294967296), $value % 4294967296);
    }

    private function deliveryTagFromDeliver(Frame $frame): int
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShortString();

        return $reader->readLongLong();
    }

    private function deliveryTagFromBasicAck(Frame $frame): int
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader(substr($frame->payload, 4));

        return $reader->readLongLong();
    }

    private function multipleFromBasicAck(Frame $frame): bool
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader(substr($frame->payload, 4));
        $reader->readLongLong();

        return ($reader->readOctet() & 0b00000001) !== 0;
    }

    private function bodySizeFromHeader(Frame $frame): int
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader($frame->payload);
        $reader->readShort();
        $reader->readShort();

        return $reader->readLongLong();
    }

    /**
     * @param resource $client
     * @return array{0: int, 1: int}
     */
    private function readMethod(AmqpListener $listener, mixed $client): array
    {
        return $this->readMethodFrame($listener, $client)->method();
    }

    /**
     * @param resource $client
     */
    private function readMethodFrame(AmqpListener $listener, mixed $client): Frame
    {
        $frame = $this->readFrame($listener, $client);
        self::assertSame(Frame::TYPE_METHOD, $frame->type);

        return $frame;
    }

    /**
     * @param resource $client
     */
    private function readFrame(AmqpListener $listener, mixed $client): Frame
    {
        if ($this->pendingFrames !== []) {
            return array_shift($this->pendingFrames);
        }

        $codec = new FrameCodec();

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $listener->tick();
            $bytes = fread($client, 8192);
            if (is_string($bytes) && $bytes !== '') {
                $frames = $codec->push($bytes);
                if ($frames !== []) {
                    $this->pendingFrames = array_slice($frames, 1);

                    return $frames[0];
                }
            }
            usleep(1000);
        }

        self::fail('Timed out waiting for AMQP frame.');
    }

    private function singleDelivery(string $queue): \Flux\Broker\Delivery
    {
        $destination = (new DestinationRepository($this->connection))->findByName($this->virtualHostId, $queue);
        self::assertNotNull($destination);
        $subscription = (new SubscriptionRepository($this->connection))->findByName($destination->id, 'amqp');
        self::assertNotNull($subscription);
        $deliveries = (new DeliveryRepository($this->connection))->allBySubscription($subscription->id);
        self::assertCount(1, $deliveries);

        return $deliveries[0];
    }

    /**
     * @return list<DeliveryState>
     */
    private function deliveryStates(string $queue): array
    {
        $destination = (new DestinationRepository($this->connection))->findByName($this->virtualHostId, $queue);
        self::assertNotNull($destination);
        $subscription = (new SubscriptionRepository($this->connection))->findByName($destination->id, 'amqp');
        self::assertNotNull($subscription);
        $deliveries = (new DeliveryRepository($this->connection))->allBySubscription($subscription->id);

        return array_map(
            static fn (\Flux\Broker\Delivery $delivery): DeliveryState => $delivery->state,
            $deliveries
        );
    }

    private function payloadForSingleMessage(string $queue): string
    {
        $delivery = $this->singleDelivery($queue);
        $route = (new MessageRouteRepository($this->connection))->findById($delivery->messageRouteId);
        self::assertNotNull($route);
        $message = (new MessageRepository($this->connection))->findById($route->messageId);
        self::assertNotNull($message);

        return $message->payload;
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
            new RoutingSourceRepository($this->connection),
            new MessageRouteRepository($this->connection),
            new MessageRepository($this->connection)
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
