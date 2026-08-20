<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Postgres;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    private PDO $pdo;

    #[Before]
    public function setUpSchema(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('The pdo_pgsql extension is required for PostgreSQL integration tests.');
        }

        $dsn = getenv('FLUX_TEST_DATABASE_URL');

        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set FLUX_TEST_DATABASE_URL to run PostgreSQL schema integration tests.');
        }

        $this->pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->resetSchema();
        $this->applyMigrations();
    }

    public function testDefaultVirtualHostExistsExactlyOnce(): void
    {
        self::assertSame(
            1,
            (int) $this->pdo->query("SELECT count(*) FROM virtual_hosts WHERE name = '/'")->fetchColumn()
        );
    }

    public function testEveryMigrationIsRecorded(): void
    {
        self::assertSame(
            8,
            (int) $this->pdo->query('SELECT count(*) FROM schema_migrations')->fetchColumn()
        );
    }

    public function testMigrationsAreIdempotent(): void
    {
        $this->applyMigrations();

        self::assertSame(
            8,
            (int) $this->pdo->query('SELECT count(*) FROM schema_migrations')->fetchColumn()
        );
        self::assertSame(
            1,
            (int) $this->pdo->query("SELECT count(*) FROM virtual_hosts WHERE name = '/'")->fetchColumn()
        );
    }

    public function testVirtualHostNamesAreUnique(): void
    {
        $this->expectConstraintViolation();

        $this->pdo->exec("INSERT INTO virtual_hosts (name) VALUES ('/')");
    }

    public function testDestinationNamesAreUniqueWithinVirtualHostOnly(): void
    {
        $defaultHostId = $this->virtualHostId('/');
        $otherHostId = $this->insertVirtualHost('tenant-a');

        $this->insertDestination($defaultHostId, 'orders');
        $this->insertDestination($otherHostId, 'orders');

        $this->expectConstraintViolation();

        $this->insertDestination($defaultHostId, 'orders');
    }

    public function testInvalidDestinationTypeFails(): void
    {
        $this->expectConstraintViolation();

        $this->insertDestination($this->virtualHostId('/'), 'topic-a', 'topic');
    }

    public function testBinaryPayloadCanBeStoredAndOneMessageCanHaveMultipleRoutes(): void
    {
        $hostId = $this->virtualHostId('/');
        $destinationAId = $this->insertDestination($hostId, 'queue-a');
        $destinationBId = $this->insertDestination($hostId, 'queue-b');
        $messageId = $this->insertMessage(hex2bin('000102ff') ?: '');

        $routeAId = $this->insertMessageRoute($messageId, $destinationAId);
        $routeBId = $this->insertMessageRoute($messageId, $destinationBId);

        self::assertNotSame($routeAId, $routeBId);
        self::assertSame(2, (int) $this->pdo->query('SELECT count(*) FROM message_routes')->fetchColumn());
        self::assertSame('000102ff', bin2hex((string) $this->pdo->query('SELECT payload FROM messages')->fetchColumn()));
    }

    public function testInvalidDeliveryStateFails(): void
    {
        [$messageRouteId, $subscriptionId] = $this->createRoutedSubscription();

        $this->expectConstraintViolation();
        $this->pdo->exec(sprintf(
            "INSERT INTO deliveries (message_route_id, subscription_id, state) VALUES (%d, %d, 'unknown')",
            $messageRouteId,
            $subscriptionId
        ));
    }

    public function testNegativeDeliveryAttemptsFail(): void
    {
        [$messageRouteId, $subscriptionId] = $this->createRoutedSubscription();

        $this->expectConstraintViolation();
        $this->pdo->exec(sprintf(
            'INSERT INTO deliveries (message_route_id, subscription_id, attempts) VALUES (%d, %d, -1)',
            $messageRouteId,
            $subscriptionId
        ));
    }

    public function testForeignKeyIntegrityIsEnforced(): void
    {
        $this->expectConstraintViolation();

        $this->pdo->exec(
            "INSERT INTO destinations (virtual_host_id, name, type) VALUES (999999, 'orphan', 'queue')"
        );
    }

    public function testExpectedIndexesExist(): void
    {
        $indexes = $this->pdo->query(
            "SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"
        )->fetchAll(PDO::FETCH_COLUMN);

        self::assertContains('destinations_virtual_host_name_unique', $indexes);
        self::assertContains('bindings_route_lookup_idx', $indexes);
        self::assertContains('messages_message_id_unique', $indexes);
        self::assertContains('message_routes_destination_available_idx', $indexes);
        self::assertContains('subscriptions_destination_name_unique', $indexes);
        self::assertContains('deliveries_pending_claim_idx', $indexes);
    }

    private function resetSchema(): void
    {
        $this->pdo->exec('DROP SCHEMA public CASCADE');
        $this->pdo->exec('CREATE SCHEMA public');
    }

    private function applyMigrations(): void
    {
        $migrationFiles = glob(dirname(__DIR__, 3) . '/database/migrations/*.sql');
        self::assertNotFalse($migrationFiles);
        sort($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
            $this->pdo->exec((string) file_get_contents($migrationFile));
        }
    }

    private function virtualHostId(string $name): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM virtual_hosts WHERE name = :name');
        $statement->execute(['name' => $name]);

        return (int) $statement->fetchColumn();
    }

    private function insertVirtualHost(string $name): int
    {
        $statement = $this->pdo->prepare('INSERT INTO virtual_hosts (name) VALUES (:name) RETURNING id');
        $statement->execute(['name' => $name]);

        return (int) $statement->fetchColumn();
    }

    private function insertDestination(int $virtualHostId, string $name, string $type = 'queue'): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO destinations (virtual_host_id, name, type) VALUES (:virtual_host_id, :name, :type) RETURNING id'
        );
        $statement->execute([
            'virtual_host_id' => $virtualHostId,
            'name' => $name,
            'type' => $type,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function insertMessage(string $payload): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO messages (message_id, payload) VALUES (:message_id, :payload) RETURNING id'
        );
        $statement->bindValue('message_id', '00000000-0000-4000-8000-000000000001');
        $statement->bindValue('payload', $payload, PDO::PARAM_LOB);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function insertMessageRoute(int $messageId, int $destinationId): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO message_routes (message_id, destination_id) VALUES (:message_id, :destination_id) RETURNING id'
        );
        $statement->execute([
            'message_id' => $messageId,
            'destination_id' => $destinationId,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createRoutedSubscription(): array
    {
        $hostId = $this->virtualHostId('/');
        $destinationId = $this->insertDestination($hostId, 'deliveries');
        $messageId = $this->insertMessage(hex2bin('aa') ?: '');
        $messageRouteId = $this->insertMessageRoute($messageId, $destinationId);

        $statement = $this->pdo->prepare(
            "INSERT INTO subscriptions (destination_id, name) VALUES (:destination_id, 'default') RETURNING id"
        );
        $statement->execute(['destination_id' => $destinationId]);

        return [$messageRouteId, (int) $statement->fetchColumn()];
    }

    private function expectConstraintViolation(): void
    {
        $this->expectException(PDOException::class);
    }
}
