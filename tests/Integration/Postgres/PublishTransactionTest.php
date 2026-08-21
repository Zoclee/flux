<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Postgres;

use Flux\Broker\DeliveryState;
use Flux\Broker\Destination;
use Flux\Persistence\Postgres\BindingRepository;
use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\MessageRepository;
use Flux\Persistence\Postgres\MessageRouteRepository;
use Flux\Persistence\Postgres\Migrator;
use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\SubscriptionRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

final class PublishTransactionTest extends TestCase
{
    private Connection $connection;
    private PDO $pdo;
    private BindingRepository $bindings;
    private DestinationRepository $destinations;
    private MessageRepository $messages;
    private MessageRouteRepository $routes;
    private SubscriptionRepository $subscriptions;
    private PublishTransaction $publisher;
    private int $defaultVirtualHostId;
    private int $sequence = 0;

    #[Before]
    public function setUpPublisher(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('The pdo_pgsql extension is required for PostgreSQL integration tests.');
        }

        $dsn = getenv('FLUX_TEST_DATABASE_URL');

        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set FLUX_TEST_DATABASE_URL to run PostgreSQL repository integration tests.');
        }

        $this->connection = Connection::fromDsn($dsn);
        $this->pdo = $this->connection->pdo();

        $this->assertSafeTestDatabase();
        $this->resetSchema();
        (new Migrator($this->connection, dirname(__DIR__, 3) . '/database/migrations'))->migrate();

        $virtualHosts = new VirtualHostRepository($this->connection);
        $this->bindings = new BindingRepository($this->connection);
        $this->destinations = new DestinationRepository($this->connection);
        $this->messages = new MessageRepository($this->connection);
        $this->routes = new MessageRouteRepository($this->connection);
        $this->subscriptions = new SubscriptionRepository($this->connection);
        $this->publisher = new PublishTransaction($this->connection);
        $this->defaultVirtualHostId = $virtualHosts->findByName('/')?->id
            ?? throw new \RuntimeException('Default virtual host was not created by migrations.');
    }

    public function testOneBindingAndOneSubscriptionCreatesOneCompleteGraph(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');
        $subscription = $this->subscriptions->create($destination->id, 'worker-a');

        $result = $this->publisher->publish(
            $this->defaultVirtualHostId,
            'orders',
            'order.created',
            'payload'
        );

        self::assertSame(1, $result->routeCount());
        self::assertSame(1, $result->deliveryCount());
        self::assertSame($destination->id, $result->routes[0]->destinationId);
        self::assertSame($subscription->id, $result->deliveries[0]->subscriptionId);
        self::assertSame(DeliveryState::Pending, $result->deliveries[0]->state);
        self::assertSame(1, $this->tableCount('messages'));
        self::assertSame(1, $this->tableCount('message_routes'));
        self::assertSame(1, $this->tableCount('deliveries'));
    }

    public function testOneDestinationWithMultipleSubscriptionsCreatesOneRouteAndSeveralDeliveries(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');
        $this->subscriptions->create($destination->id, 'worker-a');
        $this->subscriptions->create($destination->id, 'worker-b');
        $this->subscriptions->create($destination->id, 'worker-c');

        $result = $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'order.created', 'payload');

        self::assertSame(1, $result->routeCount());
        self::assertSame(3, $result->deliveryCount());
        self::assertSame(1, $this->tableCount('message_routes'));
        self::assertSame(3, $this->tableCount('deliveries'));
        self::assertCount(1, array_unique(array_map(
            static fn ($delivery): int => $delivery->messageRouteId,
            $result->deliveries
        )));
    }

    public function testMultipleDestinationsFanOutFromOneStoredPayload(): void
    {
        $destinationA = $this->bindDestination('orders', 'order.created', 'orders-a');
        $destinationB = $this->bindDestination('orders', 'order.created', 'orders-b');
        $this->subscriptions->create($destinationA->id, 'worker-a1');
        $this->subscriptions->create($destinationA->id, 'worker-a2');
        $this->subscriptions->create($destinationB->id, 'worker-b1');

        $payload = "\x00\x01\x80\xFFpayload";
        $headers = ['event' => 'created', 'attempt' => 1, 'nested' => ['ok' => true]];
        $result = $this->publisher->publish(
            $this->defaultVirtualHostId,
            'orders',
            'order.created',
            $payload,
            $headers,
            'application/octet-stream',
            'identity',
            7,
            false
        );

        self::assertSame(1, $this->tableCount('messages'));
        self::assertSame(2, $result->routeCount());
        self::assertSame(3, $result->deliveryCount());
        self::assertSame(2, $this->tableCount('message_routes'));
        self::assertSame(3, $this->tableCount('deliveries'));

        $message = $this->messages->findById($result->message->id);
        self::assertNotNull($message);
        self::assertSame($payload, $message->payload);
        self::assertEquals($headers, $message->headers);
        self::assertSame('application/octet-stream', $message->contentType);
        self::assertSame('identity', $message->contentEncoding);
        self::assertSame(7, $message->priority);
        self::assertFalse($message->persistent);
    }

    public function testNoMatchingBindingStillCommitsUnroutedMessage(): void
    {
        $result = $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'missing', 'payload');

        self::assertSame(0, $result->routeCount());
        self::assertSame(0, $result->deliveryCount());
        self::assertSame(1, $this->tableCount('messages'));
        self::assertSame(0, $this->tableCount('message_routes'));
        self::assertSame(0, $this->tableCount('deliveries'));
    }

    public function testRoutedDestinationWithoutSubscriptionsCreatesRouteOnly(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');

        $result = $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'order.created', 'payload');

        self::assertSame(1, $result->routeCount());
        self::assertSame(0, $result->deliveryCount());
        self::assertSame($destination->id, $result->routes[0]->destinationId);
        self::assertSame(1, $this->tableCount('messages'));
        self::assertSame(1, $this->tableCount('message_routes'));
        self::assertSame(0, $this->tableCount('deliveries'));
    }

    public function testMultipleBindingsResolveToMultipleDestinations(): void
    {
        $destinationA = $this->bindDestination('orders', 'order.created', 'orders-a');
        $destinationB = $this->bindDestination('orders', 'order.created', 'orders-b');
        $this->bindDestination('orders', 'order.cancelled', 'orders-cancelled');
        $this->subscriptions->create($destinationA->id, 'worker-a');
        $this->subscriptions->create($destinationB->id, 'worker-b');

        $result = $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'order.created', 'payload');

        self::assertSame(2, $result->routeCount());
        self::assertSame(2, $result->deliveryCount());
        self::assertEqualsCanonicalizing(
            [$destinationA->id, $destinationB->id],
            array_map(static fn ($route): int => $route->destinationId, $result->routes)
        );
    }

    public function testBindingUniquenessPreventsDuplicateRouteInputs(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');

        $this->expectException(PDOException::class);

        $this->bindings->create($this->defaultVirtualHostId, 'orders', $destination->id, 'order.created');
    }

    public function testRouteAndDeliveryUniquenessRemainAuthoritative(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');
        $subscription = $this->subscriptions->create($destination->id, 'worker-a');
        $result = $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'order.created', 'payload');

        try {
            $this->routes->create($result->message->id, $destination->id);
            self::fail('Duplicate message route should fail.');
        } catch (PDOException) {
            self::assertSame(1, $this->tableCount('message_routes'));
        }

        try {
            (new DeliveryRepository($this->connection))->create($result->routes[0]->id, $subscription->id);
            self::fail('Duplicate delivery should fail.');
        } catch (PDOException) {
            self::assertSame(1, $this->tableCount('deliveries'));
        }
    }

    public function testExplicitMessageUuidWorksAndPublishResultMatchesPersistedGraph(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');
        $this->subscriptions->create($destination->id, 'worker-a');
        $messageId = '00000000-0000-4000-8000-000000000123';

        $result = $this->publisher->publish(
            $this->defaultVirtualHostId,
            'orders',
            'order.created',
            'payload',
            messageId: $messageId
        );

        self::assertSame($messageId, $result->messageId());
        self::assertSame(1, $result->routeCount());
        self::assertSame(1, $result->deliveryCount());
        self::assertNotNull($this->messages->findByMessageId($messageId));
        self::assertSame($result->message->id, $this->routes->findById($result->routes[0]->id)?->messageId);
    }

    public function testDuplicateExplicitMessageUuidFailsWithoutPartialGraph(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');
        $this->subscriptions->create($destination->id, 'worker-a');
        $messageId = '00000000-0000-4000-8000-000000000124';
        $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'order.created', 'first', messageId: $messageId);

        $before = $this->graphCounts();

        try {
            $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'order.created', 'second', messageId: $messageId);
            self::fail('Duplicate message UUID should fail.');
        } catch (PDOException) {
            self::assertSame($before, $this->graphCounts());
        }
    }

    public function testLateDeliveryFailureRollsBackMessageRouteAndDeliveryGraph(): void
    {
        $destination = $this->bindDestination('orders', 'order.created');
        $this->subscriptions->create($destination->id, 'worker-a');
        $before = $this->graphCounts();
        $this->installDeliveryFailureTrigger();

        try {
            $this->publisher->publish($this->defaultVirtualHostId, 'orders', 'order.created', 'payload');
            self::fail('Delivery trigger should fail the publish transaction.');
        } catch (PDOException) {
            self::assertSame($before, $this->graphCounts());
            self::assertSame(0, $this->tableCount('messages'));
            self::assertSame(0, $this->tableCount('message_routes'));
            self::assertSame(0, $this->tableCount('deliveries'));
        }
    }

    private function bindDestination(string $source, string $routingKey, ?string $name = null): Destination
    {
        $destination = $this->destinations->create(
            $this->defaultVirtualHostId,
            $name ?? sprintf('destination-%d', ++$this->sequence),
            'queue'
        );
        $this->bindings->create($this->defaultVirtualHostId, $source, $destination->id, $routingKey);

        return $destination;
    }

    /**
     * @return array{messages: int, message_routes: int, deliveries: int}
     */
    private function graphCounts(): array
    {
        return [
            'messages' => $this->tableCount('messages'),
            'message_routes' => $this->tableCount('message_routes'),
            'deliveries' => $this->tableCount('deliveries'),
        ];
    }

    private function tableCount(string $table): int
    {
        return (int) $this->pdo->query(sprintf('SELECT count(*) FROM %s', $table))->fetchColumn();
    }

    private function installDeliveryFailureTrigger(): void
    {
        $this->pdo->exec(<<<'SQL'
CREATE OR REPLACE FUNCTION flux_test_fail_delivery_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'forced delivery failure for publish rollback test';
END;
$$;

CREATE TRIGGER flux_test_fail_delivery_insert
BEFORE INSERT ON deliveries
FOR EACH ROW
EXECUTE FUNCTION flux_test_fail_delivery_insert();
SQL);
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
