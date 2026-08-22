<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Protocol\Amqp;

use Flux\Broker\Broker;
use Flux\Broker\Authorizer;
use Flux\Broker\Authenticator;
use Flux\Broker\DeliveryState;
use Flux\Broker\ResourceLimits;
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
use Flux\Persistence\Postgres\UserRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use Flux\Protocol\Amqp\AmqpConnection;
use Flux\Protocol\Amqp\AmqpListener;
use Flux\Protocol\Amqp\AmqpTlsConfig;
use Flux\Protocol\Amqp\Frame;
use Flux\Protocol\Amqp\FrameCodec;
use Flux\Runtime\ConnectionRegistry;
use Flux\Runtime\ConsumerRegistry;
use Flux\Tests\Fixtures\TlsCertificate;
use PDO;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        $users = new UserRepository($this->connection);
        $users->create('guest', 'guest');
        $users->grantVirtualHost('guest', '/');
        $users->setPermissions('guest', '/', '.*', '.*', '.*');
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

    public function testQueueDeclareOkReportsZeroMessagesForEmptyQueue(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            $declareOk = $this->readMethodFrame($listener, $client);

            self::assertSame([50, 11], $declareOk->method());
            self::assertSame(0, $this->queueDeclareMessageCount($declareOk));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testQueueDeclareOkReportsOneReadyMessageAfterPublish(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->publishBody($listener, $client, 1, '', 'orders', 'one');

            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            $declareOk = $this->readMethodFrame($listener, $client);

            self::assertSame([50, 11], $declareOk->method());
            self::assertSame(1, $this->queueDeclareMessageCount($declareOk));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testQueueDeclareOkReportsSeveralReadyMessagesAfterPublishes(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['one', 'two', 'three']);

            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            $declareOk = $this->readMethodFrame($listener, $client);

            self::assertSame([50, 11], $declareOk->method());
            self::assertSame(3, $this->queueDeclareMessageCount($declareOk));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testQueueDeclareOkMessageCountDecreasesAfterBasicGetNoAck(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['one', 'two']);

            $this->sendMethod($client, 1, 60, 70, $this->basicGet('orders', noAck: true));
            $getOk = $this->readMethodFrame($listener, $client);
            self::assertSame([60, 71], $getOk->method());
            $this->readFrame($listener, $client);
            self::assertSame('one', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            $declareOk = $this->readMethodFrame($listener, $client);

            self::assertSame([50, 11], $declareOk->method());
            self::assertSame(1, $this->queueDeclareMessageCount($declareOk));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testPassiveQueueDeclareOkReportsReadyMessageCount(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['one', 'two']);

            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders', passive: true));
            $declareOk = $this->readMethodFrame($listener, $client);

            self::assertSame([50, 11], $declareOk->method());
            self::assertSame(2, $this->queueDeclareMessageCount($declareOk));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testRoutableMandatoryPublishWithConfirmsIsAcknowledged(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 40, 10, $this->exchangeDeclare('orders.direct'));
            self::assertSame([40, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 20, $this->queueBind('orders', 'orders.direct', 'created'));
            self::assertSame([50, 21], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, 'orders.direct', 'created', 'routable', mandatory: true);
            $ack = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 80], $ack->method());
            self::assertSame(1, $this->deliveryTagFromBasicAck($ack));
            self::assertSame(1, $this->tableCount('messages'));
            self::assertSame(1, $this->tableCount('message_routes'));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testFanoutPublishIgnoresRoutingKeyAndDeliversToEveryBoundQueue(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            foreach (['orders-a', 'orders-b', 'unbound'] as $queue) {
                $this->sendMethod($client, 1, 50, 10, $this->queueDeclare($queue));
                self::assertSame([50, 11], $this->readMethod($listener, $client));
            }
            $this->sendMethod($client, 1, 40, 10, $this->exchangeDeclare('events', 'fanout'));
            self::assertSame([40, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 20, $this->queueBind('orders-a', 'events', 'created'));
            self::assertSame([50, 21], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 20, $this->queueBind('orders-b', 'events', 'updated'));
            self::assertSame([50, 21], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, 'events', 'ignored', 'fanout-body', mandatory: true);
            $ack = $this->readMethodFrame($listener, $client);
            self::assertSame([60, 80], $ack->method());
            self::assertSame(1, $this->deliveryTagFromBasicAck($ack));
            self::assertSame(1, $this->tableCount('messages'));
            self::assertSame(2, $this->tableCount('message_routes'));
            self::assertSame(2, $this->tableCount('deliveries'));

            $this->sendMethod($client, 1, 60, 70, $this->basicGet('orders-a', noAck: true));
            self::assertSame([60, 71], $this->readMethod($listener, $client));
            self::assertSame(Frame::TYPE_HEADER, $this->readFrame($listener, $client)->type);
            self::assertSame('fanout-body', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 60, 70, $this->basicGet('orders-b', noAck: true));
            self::assertSame([60, 71], $this->readMethod($listener, $client));
            self::assertSame(Frame::TYPE_HEADER, $this->readFrame($listener, $client)->type);
            self::assertSame('fanout-body', $this->readFrame($listener, $client)->payload);

            $this->sendMethod($client, 1, 60, 70, $this->basicGet('unbound', noAck: true));
            self::assertSame([60, 72], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testFanoutPublishResourceFailureRollsBackAndDoesNotConfirm(): void
    {
        [$listener, $client] = $this->startedListener(limits: new ResourceLimits(maxQueueDepth: 1));

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            foreach (['orders-a', 'orders-b'] as $queue) {
                $this->sendMethod($client, 1, 50, 10, $this->queueDeclare($queue));
                self::assertSame([50, 11], $this->readMethod($listener, $client));
            }
            $this->sendMethod($client, 1, 40, 10, $this->exchangeDeclare('events', 'fanout'));
            self::assertSame([40, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 20, $this->queueBind('orders-a', 'events', 'a'));
            self::assertSame([50, 21], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 20, $this->queueBind('orders-b', 'events', 'b'));
            self::assertSame([50, 21], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, '', 'orders-b', 'already-full');
            self::assertSame(1, $this->tableCount('messages'));
            self::assertSame(1, $this->tableCount('message_routes'));
            self::assertSame(1, $this->tableCount('deliveries'));

            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, 'events', 'ignored', 'blocked', mandatory: true);
            $close = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $close->method());
            self::assertSame(506, $this->replyCode($close));
            self::assertSame(1, $this->tableCount('messages'));
            self::assertSame(1, $this->tableCount('message_routes'));
            self::assertSame(1, $this->tableCount('deliveries'));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testUnroutableMandatoryPublishReturnsOriginalMessageAndThenConfirms(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 40, 10, $this->exchangeDeclare('orders.direct'));
            self::assertSame([40, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, 'orders.direct', 'missing', 'returned', mandatory: true);
            $return = $this->readMethodFrame($listener, $client);
            $header = $this->readFrame($listener, $client);
            $body = $this->readFrame($listener, $client);
            $ack = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 50], $return->method());
            self::assertSame(
                [
                    'reply_code' => 312,
                    'exchange' => 'orders.direct',
                    'routing_key' => 'missing',
                ],
                $this->basicReturnDetails($return)
            );
            self::assertStringContainsString('NO_ROUTE', $this->basicReturnReplyText($return));
            self::assertSame(Frame::TYPE_HEADER, $header->type);
            self::assertSame(strlen('returned'), $this->bodySizeFromHeader($header));
            self::assertSame(Frame::TYPE_BODY, $body->type);
            self::assertSame('returned', $body->payload);
            self::assertSame([60, 80], $ack->method());
            self::assertSame(1, $this->deliveryTagFromBasicAck($ack));
            self::assertSame(0, $this->tableCount('messages'));
            self::assertSame(0, $this->tableCount('message_routes'));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testUnroutableNonMandatoryPublishIsSilentlyDiscardedAndConfirmed(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 40, 10, $this->exchangeDeclare('orders.direct'));
            self::assertSame([40, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 85, 10, $this->confirmSelect(false));
            self::assertSame([85, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, 'orders.direct', 'missing', 'discarded');
            $ack = $this->readMethodFrame($listener, $client);

            self::assertSame([60, 80], $ack->method());
            self::assertSame(1, $this->deliveryTagFromBasicAck($ack));
            self::assertSame(0, $this->tableCount('messages'));
            self::assertSame(0, $this->tableCount('message_routes'));
            self::assertSame(0, $this->tableCount('deliveries'));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testTlsDefaultExchangePublishConsumeAndAckUsesSameBrokerFlow(): void
    {
        [$listener, $client] = $this->startedTlsListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, 5)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, 'hello')));
            $listener->tick();

            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $deliver = $this->readMethodFrame($listener, $client);
            self::assertSame([60, 60], $deliver->method());
            $deliveryTag = $this->deliveryTagFromDeliver($deliver);
            $this->readFrame($listener, $client);
            $body = $this->readFrame($listener, $client);
            self::assertSame('hello', $body->payload);

            $this->sendMethod($client, 1, 60, 80, $this->packLongLong($deliveryTag) . "\x00");
            $listener->tick();
        } finally {
            fclose($client);
            $listener->stop();
        }

        self::assertSame(DeliveryState::Acknowledged, $this->singleDelivery('orders')->state);
    }

    public function testInvalidCredentialsNeverReceiveOpenOkAndCleanRuntimeConnection(): void
    {
        $connections = new ConnectionRegistry();
        [$listener, $client] = $this->startedListener($connections);

        try {
            fwrite($client, AmqpConnection::PROTOCOL_HEADER);
            self::assertSame([10, 10], $this->readMethod($listener, $client));
            $this->sendMethod($client, 0, 10, 11, $this->startOk("\0guest\0wrong"));

            self::assertSame([10, 50], $this->readMethod($listener, $client));
            self::assertSame(0, $connections->count());
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testUnknownVirtualHostNeverReceivesOpenOkAndCleanRuntimeConnection(): void
    {
        $connections = new ConnectionRegistry();
        [$listener, $client] = $this->startedListener($connections);

        try {
            fwrite($client, AmqpConnection::PROTOCOL_HEADER);
            self::assertSame([10, 10], $this->readMethod($listener, $client));
            $this->sendMethod($client, 0, 10, 11, $this->startOk());
            self::assertSame([10, 30], $this->readMethod($listener, $client));
            $this->sendMethod($client, 0, 10, 31, $this->tuneOk(60));
            $listener->tick();
            $this->sendMethod($client, 0, 10, 40, $this->connectionOpen('missing'));

            self::assertSame([10, 50], $this->readMethod($listener, $client));
            self::assertSame(0, $connections->count());
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testDeniedPublishClosesChannelWithAccessRefused(): void
    {
        (new UserRepository($this->connection))->setPermissions('guest', '/', '.*', '^allowed$', '.*');
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('orders.direct', 'created'));
            $close = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $close->method());
            self::assertSame(403, $this->replyCode($close));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testDeniedConsumeClosesChannelWithAccessRefused(): void
    {
        $this->broker()->declareQueue('/', 'orders', true, false);
        (new UserRepository($this->connection))->setPermissions('guest', '/', '.*', '.*', '^allowed$');
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            $close = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $close->method());
            self::assertSame(403, $this->replyCode($close));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testDeniedBasicGetClosesChannelWithAccessRefused(): void
    {
        $this->broker()->declareQueue('/', 'orders', true, false);
        (new UserRepository($this->connection))->setPermissions('guest', '/', '.*', '.*', '^allowed$');
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 70, $this->basicGet('orders'));
            $close = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $close->method());
            self::assertSame(403, $this->replyCode($close));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testAuthorizationStillAppliesOverTls(): void
    {
        $this->broker()->declareQueue('/', 'orders', true, false);
        (new UserRepository($this->connection))->setPermissions('guest', '/', '.*', '.*', '^allowed$');
        [$listener, $client] = $this->startedTlsListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            $close = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $close->method());
            self::assertSame(403, $this->replyCode($close));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testTlsDisconnectReleasesUnackedDelivery(): void
    {
        [$listener, $client] = $this->startedTlsListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['one']);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            self::assertSame([60, 60], $this->readMethod($listener, $client));
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);
            fclose($client);

            for ($attempt = 0; $attempt < 50; $attempt++) {
                $listener->tick();
                usleep(1000);
            }
        } finally {
            $listener->stop();
        }

        self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
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

    public function testBasicGetReturnsAvailableMessageWithBinaryBodyAndManualAck(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $payload = "get\x00binary\xff";
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', [$payload]);

            $this->sendMethod($client, 1, 60, 70, $this->basicGet('orders'));
            $get = $this->readMethodFrame($listener, $client);
            self::assertSame([60, 71], $get->method());
            self::assertSame(1, $this->deliveryTagFromBasicGetOk($get));
            $header = $this->readFrame($listener, $client);
            self::assertSame(strlen($payload), $this->bodySizeFromHeader($header));
            self::assertSame($payload, $this->readFrame($listener, $client)->payload);
            self::assertSame(DeliveryState::Reserved, $this->singleDelivery('orders')->state);

            $this->sendMethod($client, 1, 60, 80, $this->packLongLong(1) . "\x00");
            $listener->tick();
            self::assertSame(DeliveryState::Acknowledged, $this->singleDelivery('orders')->state);
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testBasicGetEmptyWhenNoMessageIsAvailable(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 70, $this->basicGet('orders'));

            self::assertSame([60, 72], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testBasicGetNoAckSettlesDeliveryImmediately(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['auto']);

            $this->sendMethod($client, 1, 60, 70, $this->basicGet('orders', true));
            self::assertSame([60, 71], $this->readMethod($listener, $client));
            $this->readFrame($listener, $client);
            self::assertSame('auto', $this->readFrame($listener, $client)->payload);
            self::assertSame(DeliveryState::Acknowledged, $this->singleDelivery('orders')->state);
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testQueueDeleteIfUnusedRejectsQueueWithActiveConsumerAndRuntimeContinues(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 50, 40, $this->queueDelete('orders', ifUnused: true));
            $close = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $close->method());
            self::assertSame(406, $this->replyCode($close));
            self::assertSame(1, $listener->connectionCount());

            fwrite($client, $codec->encode(Frame::methodFrame(2, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testExistingAckSucceedsDuringDrainAndCompletesInflightWork(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['held']);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $delivery = $this->readMethodFrame($listener, $client);
            $deliveryTag = $this->deliveryTagFromDeliver($delivery);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);

            $listener->beginDrain();
            self::assertSame(1, $listener->inFlightCount());
            $this->sendMethod($client, 1, 60, 80, $this->packLongLong($deliveryTag) . "\x00");
            $listener->tick();

            self::assertSame(0, $listener->inFlightCount());
            self::assertSame(DeliveryState::Acknowledged, $this->singleDelivery('orders')->state);
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testNewPublishConsumerAndGetAreRefusedDuringDrain(): void
    {
        [$listener, $client] = $this->startedListener();
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            fwrite($client, $codec->encode(Frame::methodFrame(2, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
            fwrite($client, $codec->encode(Frame::methodFrame(3, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
            fwrite($client, $codec->encode(Frame::methodFrame(4, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            $listener->beginDrain();

            $this->sendMethod($client, 2, 60, 40, $this->basicPublish('', 'orders'));
            $publishClose = $this->readMethodFrame($listener, $client);
            self::assertSame([20, 40], $publishClose->method());
            self::assertSame(320, $this->replyCode($publishClose));

            $this->sendMethod($client, 3, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            $consumeClose = $this->readMethodFrame($listener, $client);
            self::assertSame([20, 40], $consumeClose->method());
            self::assertSame(320, $this->replyCode($consumeClose));

            $this->sendMethod($client, 4, 60, 70, $this->basicGet('orders'));
            $getClose = $this->readMethodFrame($listener, $client);
            self::assertSame([20, 40], $getClose->method());
            self::assertSame(320, $this->replyCode($getClose));
            self::assertSame(1, $listener->connectionCount());
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testExistingConsumersDoNotReserveNewDeliveriesDuringDrain(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));

            $listener->beginDrain();
            $this->broker()->publishToDefaultExchange('/', 'orders', 'pending-after-drain');
            $this->assertNoFrame($listener, $client);

            self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testShutdownCleanupReleasesRemainingUnackedDeliveryAfterDrain(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['held']);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $this->readMethodFrame($listener, $client);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);

            $listener->beginDrain();
            $listener->stop();

            self::assertSame(DeliveryState::Pending, $this->singleDelivery('orders')->state);
        } finally {
            if (is_resource($client)) {
                fclose($client);
            }
            $listener->stop();
        }
    }

    public function testReleasedDeliveryCanBeConsumedAfterRestart(): void
    {
        [$listener, $client] = $this->startedListener();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['restart']);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            $this->readMethodFrame($listener, $client);
            $this->readFrame($listener, $client);
            $this->readFrame($listener, $client);
            $listener->beginDrain();
            $listener->stop();
            fclose($client);
        } finally {
            $listener->stop();
        }

        [$nextListener, $nextClient] = $this->startedListener();
        try {
            $this->connectAndOpenChannel($nextListener, $nextClient, 1);
            $this->sendMethod($nextClient, 1, 60, 20, $this->basicConsume('orders', 'consumer-b'));
            self::assertSame([60, 21], $this->readMethod($nextListener, $nextClient));

            self::assertSame('restart', $this->readDeliveryBody($nextListener, $nextClient));
        } finally {
            fclose($nextClient);
            $nextListener->stop();
        }
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
            authenticator: $this->authenticator(),
            authorizer: $this->authorizer(),
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

    public function testConsumerLimitPerChannelClosesRejectedChannelAndRuntimeStaysHealthy(): void
    {
        [$listener, $client] = $this->startedListener(limits: new ResourceLimits(maxConsumersPerChannel: 1));

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('invoices'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('invoices', 'consumer-b'));
            $close = $this->readMethodFrame($listener, $client);
            self::assertSame([20, 40], $close->method());
            self::assertSame(506, $this->replyCode($close));
            self::assertSame(1, $listener->connectionCount());
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testConsumerLimitPerConnectionClosesOnlyRejectedChannel(): void
    {
        [$listener, $client] = $this->startedListener(limits: new ResourceLimits(maxConsumersPerConnection: 1));
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('invoices'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));

            fwrite($client, $codec->encode(Frame::methodFrame(2, 20, 10, "\x00")));
            self::assertSame([20, 11], $this->readMethod($listener, $client));
            $this->sendMethod($client, 2, 60, 20, $this->basicConsume('invoices', 'consumer-b'));
            $close = $this->readMethodFrame($listener, $client);
            self::assertSame([20, 40], $close->method());
            self::assertSame(506, $this->replyCode($close));

            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, 4)));
            fwrite($client, $codec->encode(new Frame(Frame::TYPE_BODY, 1, 'ping')));
            $body = $this->readDeliveryBody($listener, $client);
            self::assertSame('ping', $body);
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testMessageAtConfiguredSizeLimitPublishesAndConsumes(): void
    {
        [$listener, $client] = $this->startedListener(limits: new ResourceLimits(maxMessageSize: 5));

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['12345']);
            $this->sendMethod($client, 1, 60, 20, $this->basicConsume('orders', 'consumer-a'));
            self::assertSame([60, 21], $this->readMethod($listener, $client));
            self::assertSame('12345', $this->readDeliveryBody($listener, $client));
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testOversizedDeclaredMessageIsRejectedBeforePersistence(): void
    {
        [$listener, $client] = $this->startedListener(limits: new ResourceLimits(maxMessageSize: 5));
        $codec = new FrameCodec();

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            $this->sendMethod($client, 1, 60, 40, $this->basicPublish('', 'orders'));
            fwrite($client, $codec->encode($this->contentHeader(1, 6)));
            $this->awaitEof($listener, $client);
            self::assertSame(0, $this->tableCount('messages'));
        } finally {
            if (is_resource($client)) {
                fclose($client);
            }
            $listener->stop();
        }
    }

    public function testQueueDepthLimitOnPublishClosesChannelWithResourceError(): void
    {
        [$listener, $client] = $this->startedListener(limits: new ResourceLimits(maxQueueDepth: 1));

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->sendMethod($client, 1, 50, 10, $this->queueDeclare('orders'));
            self::assertSame([50, 11], $this->readMethod($listener, $client));

            $this->publishBody($listener, $client, 1, '', 'orders', 'first');
            $this->publishBody($listener, $client, 1, '', 'orders', 'second');
            $close = $this->readMethodFrame($listener, $client);

            self::assertSame([20, 40], $close->method());
            self::assertSame(506, $this->replyCode($close));
            self::assertSame(1, $this->tableCount('messages'));
            self::assertSame(1, $listener->connectionCount());
        } finally {
            fclose($client);
            $listener->stop();
        }
    }

    public function testZeroQueueDepthLimitAllowsUnlimitedAmqpPublishes(): void
    {
        [$listener, $client] = $this->startedListener(limits: new ResourceLimits(maxQueueDepth: 0));

        try {
            $this->connectAndOpenChannel($listener, $client, 1);
            $this->declareQueueAndPublishBodies($listener, $client, 'orders', ['one', 'two', 'three']);

            self::assertSame(3, $this->tableCount('messages'));
            self::assertSame(3, $this->tableCount('deliveries'));
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
            authenticator: $this->authenticator(),
            authorizer: $this->authorizer(),
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
    private function startedListener(?ConnectionRegistry $connections = null, ?ResourceLimits $limits = null): array
    {
        $listener = new AmqpListener(
            $connections ?? new ConnectionRegistry(),
            '127.0.0.1',
            0,
            maxFrameSize: $limits?->maxFrameSize ?? 131072,
            broker: $this->broker($limits),
            authenticator: $this->authenticator(),
            authorizer: $this->authorizer(),
            consumers: new ConsumerRegistry(),
            maxMessageSize: $limits?->maxMessageSize ?? 10485760,
            limits: $limits
        );
        $listener->start();
        $client = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $listener->port()), $errorCode, $errorMessage, 1.0);
        self::assertIsResource($client, sprintf('Could not connect to AMQP listener: %s', $errorMessage));
        stream_set_blocking($client, false);

        return [$listener, $client];
    }

    /**
     * @return array{0: AmqpListener, 1: resource}
     */
    private function startedTlsListener(?ConnectionRegistry $connections = null, ?ResourceLimits $limits = null): array
    {
        $listener = new AmqpListener(
            $connections ?? new ConnectionRegistry(),
            '127.0.0.1',
            0,
            maxFrameSize: $limits?->maxFrameSize ?? 131072,
            broker: $this->broker($limits),
            authenticator: $this->authenticator(),
            authorizer: $this->authorizer(),
            consumers: new ConsumerRegistry(),
            maxMessageSize: $limits?->maxMessageSize ?? 10485760,
            tls: $this->tlsConfig(),
            limits: $limits
        );
        $listener->start();

        return [$listener, $this->connectTls($listener)];
    }

    /**
     * @param resource $client
     */
    private function connectTls(AmqpListener $listener): mixed
    {
        $client = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $listener->port()), $errorCode, $errorMessage, 1.0);
        self::assertIsResource($client, sprintf('Could not connect to AMQP TLS listener: %s', $errorMessage));
        stream_set_blocking($client, false);
        stream_context_set_options($client, [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => $this->clientCryptoMethod(),
            ],
        ]);

        for ($attempt = 0; $attempt < 200; $attempt++) {
            $listener->tick();
            $result = @stream_socket_enable_crypto($client, true, $this->clientCryptoMethod());
            if ($result === true) {
                return $client;
            }

            if ($result === false) {
                self::fail('TLS handshake failed.');
            }

            usleep(1000);
        }

        self::fail('Timed out waiting for TLS handshake.');
    }

    /**
     * @param resource $client
     */
    private function connectAndOpenChannel(AmqpListener $listener, mixed $client, int $channel, int $heartbeat = 60): void
    {
        $codec = new FrameCodec();
        fwrite($client, AmqpConnection::PROTOCOL_HEADER);
        self::assertSame([10, 10], $this->readMethod($listener, $client));
        $this->sendMethod($client, 0, 10, 11, $this->startOk());
        self::assertSame([10, 30], $this->readMethod($listener, $client));
        $this->sendMethod($client, 0, 10, 31, $this->tuneOk($heartbeat));
        $listener->tick();
        $this->sendMethod($client, 0, 10, 40, $this->connectionOpen('/'));
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

    private function queueDeclare(string $queue, bool $passive = false): string
    {
        $bits = 0b00000010;
        if ($passive) {
            $bits |= 0b00000001;
        }

        return pack('n', 0) . $this->shortString($queue) . chr($bits) . pack('N', 0);
    }

    private function exchangeDeclare(string $exchange, string $type = 'direct'): string
    {
        return pack('n', 0) . $this->shortString($exchange) . $this->shortString($type) . "\x02" . pack('N', 0);
    }

    private function queueBind(string $queue, string $exchange, string $routingKey): string
    {
        return pack('n', 0) . $this->shortString($queue) . $this->shortString($exchange) . $this->shortString($routingKey) . "\x00" . pack('N', 0);
    }

    private function basicPublish(string $exchange, string $routingKey, bool $mandatory = false): string
    {
        return pack('n', 0) . $this->shortString($exchange) . $this->shortString($routingKey) . chr($mandatory ? 1 : 0);
    }

    private function basicConsume(string $queue, string $consumerTag): string
    {
        return pack('n', 0) . $this->shortString($queue) . $this->shortString($consumerTag) . "\x00" . pack('N', 0);
    }

    private function basicGet(string $queue, bool $noAck = false): string
    {
        return pack('n', 0) . $this->shortString($queue) . chr($noAck ? 1 : 0);
    }

    private function queueDelete(string $queue, bool $ifUnused = false, bool $ifEmpty = false, bool $noWait = false): string
    {
        $bits = 0;
        if ($ifUnused) {
            $bits |= 0b00000001;
        }
        if ($ifEmpty) {
            $bits |= 0b00000010;
        }
        if ($noWait) {
            $bits |= 0b00000100;
        }

        return pack('n', 0) . $this->shortString($queue) . chr($bits);
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
        string $body,
        bool $mandatory = false
    ): void {
        $codec = new FrameCodec();
        $this->sendMethod($client, $channel, 60, 40, $this->basicPublish($exchange, $routingKey, $mandatory));
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

    /**
     * @param resource $client
     */
    private function awaitEof(AmqpListener $listener, mixed $client): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $listener->tick();
            fread($client, 8192);
            if (feof($client)) {
                return;
            }
            usleep(1000);
        }

        self::fail('Expected AMQP client socket to close.');
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

    private function longString(string $value): string
    {
        return pack('N', strlen($value)) . $value;
    }

    private function startOk(?string $response = null): string
    {
        $response ??= "\0guest\0guest";

        return pack('N', 0)
            . $this->shortString('PLAIN')
            . $this->longString($response)
            . $this->shortString('en_US');
    }

    private function connectionOpen(string $virtualHost): string
    {
        return $this->shortString($virtualHost) . $this->shortString('') . "\x00";
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

    private function deliveryTagFromBasicGetOk(Frame $frame): int
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader(substr($frame->payload, 4));

        return $reader->readLongLong();
    }

    private function queueDeclareMessageCount(Frame $frame): int
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShortString();

        return $reader->readLong();
    }

    /**
     * @return array{reply_code: int, exchange: string, routing_key: string}
     */
    private function basicReturnDetails(Frame $frame): array
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader(substr($frame->payload, 4));
        $replyCode = $reader->readShort();
        $reader->readShortString();
        $exchange = $reader->readShortString();
        $routingKey = $reader->readShortString();

        return [
            'reply_code' => $replyCode,
            'exchange' => $exchange,
            'routing_key' => $routingKey,
        ];
    }

    private function basicReturnReplyText(Frame $frame): string
    {
        $reader = new \Flux\Protocol\Amqp\AmqpMethodReader(substr($frame->payload, 4));
        $reader->readShort();

        return $reader->readShortString();
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

    private function replyCode(Frame $frame): int
    {
        $value = unpack('nreplyCode', substr($frame->payload, 4, 2));

        return (int) $value['replyCode'];
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

    private function tableCount(string $table): int
    {
        return (int) $this->pdo->query(sprintf('SELECT count(*) FROM %s', $table))->fetchColumn();
    }

    private function broker(?ResourceLimits $limits = null): Broker
    {
        return new Broker(
            new VirtualHostRepository($this->connection),
            new PublishTransaction($this->connection, $limits ?? new ResourceLimits()),
            new DestinationRepository($this->connection),
            new SubscriptionRepository($this->connection),
            new DeliveryRepository($this->connection),
            new BindingRepository($this->connection),
            new RoutingSourceRepository($this->connection),
            new MessageRouteRepository($this->connection),
            new MessageRepository($this->connection),
            $limits
        );
    }

    private function authenticator(): Authenticator
    {
        return new Authenticator(new UserRepository($this->connection));
    }

    private function authorizer(): Authorizer
    {
        return new Authorizer(new UserRepository($this->connection));
    }

    private function tlsConfig(): AmqpTlsConfig
    {
        try {
            $tls = TlsCertificate::create();
        } catch (RuntimeException $exception) {
            self::markTestSkipped($exception->getMessage());
        }

        return new AmqpTlsConfig($tls['cert'], $tls['key']);
    }

    private function clientCryptoMethod(): int
    {
        $method = 0;

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        return $method !== 0 ? $method : STREAM_CRYPTO_METHOD_TLS_CLIENT;
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
