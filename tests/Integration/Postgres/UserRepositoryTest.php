<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Postgres;

use Flux\Broker\Authenticator;
use Flux\Console\Commands\UserGrantVhostCommand;
use Flux\Console\Commands\UserListCommand;
use Flux\Persistence\Postgres\Connection;
use Flux\Persistence\Postgres\Migrator;
use Flux\Persistence\Postgres\UserRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private Connection $connection;
    private PDO $pdo;
    private UserRepository $users;

    #[Before]
    public function setUpRepository(): void
    {
        if (!extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('The pdo_pgsql extension is required for PostgreSQL user integration tests.');
        }

        $dsn = getenv('FLUX_TEST_DATABASE_URL');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set FLUX_TEST_DATABASE_URL to run PostgreSQL user integration tests.');
        }

        $this->connection = Connection::fromDsn($dsn);
        $this->pdo = $this->connection->pdo();
        $this->assertSafeTestDatabase();
        $this->resetSchema();
        (new Migrator($this->connection, dirname(__DIR__, 3) . '/database/migrations'))->migrate();
        $this->users = new UserRepository($this->connection);
    }

    public function testUserCreationRequiresUniqueUsernameAndStoresHashedPassword(): void
    {
        $user = $this->users->create('alice', 'correct horse battery staple');

        self::assertSame('alice', $user->username);
        self::assertNotSame('correct horse battery staple', $user->passwordHash);
        self::assertTrue(password_verify('correct horse battery staple', $user->passwordHash));

        $this->expectException(PDOException::class);
        $this->users->create('alice', 'different');
    }

    public function testAuthenticatorAcceptsCorrectPasswordAndRejectsIncorrectOrDisabledUsers(): void
    {
        $this->users->create('alice', 'secret');
        $this->users->create('disabled', 'secret');
        $this->pdo->exec("UPDATE users SET enabled = false WHERE username = 'disabled'");
        $authenticator = new Authenticator($this->users);

        self::assertTrue($authenticator->authenticate('alice', 'secret')->authenticated);
        self::assertFalse($authenticator->authenticate('alice', 'wrong')->authenticated);
        self::assertFalse($authenticator->authenticate('disabled', 'secret')->authenticated);
    }

    public function testVirtualHostGrantAllowsOnlyGrantedKnownVirtualHosts(): void
    {
        $this->users->create('alice', 'secret');
        (new VirtualHostRepository($this->connection))->create('tenant-a');
        $this->users->grantVirtualHost('alice', '/');
        $authenticator = new Authenticator($this->users);
        $result = $authenticator->authenticate('alice', 'secret');

        self::assertTrue($result->authenticated);
        self::assertNotNull($result->user);
        self::assertTrue($authenticator->canAccessVirtualHost($result->user, '/'));
        self::assertFalse($authenticator->canAccessVirtualHost($result->user, 'tenant-a'));
        self::assertFalse($authenticator->canAccessVirtualHost($result->user, 'missing'));
    }

    public function testGrantCommandGrantsVhostAndListCommandDoesNotExposeSecrets(): void
    {
        $user = $this->users->create('alice', 'secret');

        $grantOutput = fopen('php://temp', 'w+');
        self::assertIsResource($grantOutput);
        self::assertSame(0, (new UserGrantVhostCommand($this->connection))->run(['alice', '/'], $grantOutput));

        $result = (new Authenticator($this->users))->authenticate('alice', 'secret');
        self::assertNotNull($result->user);
        self::assertTrue((new Authenticator($this->users))->canAccessVirtualHost($result->user, '/'));

        $listOutput = fopen('php://temp', 'w+');
        self::assertIsResource($listOutput);
        self::assertSame(0, (new UserListCommand($this->connection))->run($listOutput));
        rewind($listOutput);
        $output = stream_get_contents($listOutput);
        self::assertIsString($output);
        self::assertStringContainsString('alice', $output);
        self::assertStringNotContainsString('secret', $output);
        self::assertStringNotContainsString($user->passwordHash, $output);
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
