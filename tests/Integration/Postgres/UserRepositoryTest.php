<?php

declare(strict_types=1);

namespace Flux\Tests\Integration\Postgres;

use Flux\Broker\Authenticator;
use Flux\Broker\AuthorizationPermission;
use Flux\Broker\Authorizer;
use Flux\Console\Commands\UserClearPermissionsCommand;
use Flux\Console\Commands\UserGrantVhostCommand;
use Flux\Console\Commands\UserListCommand;
use Flux\Console\Commands\UserListPermissionsCommand;
use Flux\Console\Commands\UserSetPermissionsCommand;
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

    public function testPermissionsPersistAndAuthorizeRegexResources(): void
    {
        $this->users->create('alice', 'secret');
        $this->users->grantVirtualHost('alice', '/');
        $permissions = $this->users->setPermissions('alice', '/', '^orders\\.', '^orders\\.direct$', '^orders$');
        $result = (new Authenticator($this->users))->authenticate('alice', 'secret');
        self::assertNotNull($result->user);
        $authorizer = new Authorizer($this->users);

        self::assertSame('^orders\\.', $permissions->configurePattern);
        self::assertTrue($authorizer->authorize($result->user, '/', AuthorizationPermission::Configure, 'orders.queue')->allowed);
        self::assertFalse($authorizer->authorize($result->user, '/', AuthorizationPermission::Configure, 'billing.queue')->allowed);
        self::assertTrue($authorizer->authorize($result->user, '/', AuthorizationPermission::Write, 'orders.direct')->allowed);
        self::assertFalse($authorizer->authorize($result->user, '/', AuthorizationPermission::Write, 'orders.topic')->allowed);
        self::assertTrue($authorizer->authorize($result->user, '/', AuthorizationPermission::Read, 'orders')->allowed);
        self::assertFalse($authorizer->authorize($result->user, '/', AuthorizationPermission::Read, 'orders.dead')->allowed);
    }

    public function testMissingPermissionsDenyAndDifferentUsersOrVhostsRemainIsolated(): void
    {
        $virtualHosts = new VirtualHostRepository($this->connection);
        $virtualHosts->create('tenant-a');
        $this->users->create('alice', 'secret');
        $this->users->create('bob', 'secret');
        $this->users->grantVirtualHost('alice', '/');
        $this->users->grantVirtualHost('alice', 'tenant-a');
        $this->users->grantVirtualHost('bob', '/');
        $this->users->setPermissions('alice', '/', '^alice$', '^alice$', '^alice$');
        $this->users->setPermissions('alice', 'tenant-a', '^tenant$', '^tenant$', '^tenant$');
        $this->users->setPermissions('bob', '/', '^bob$', '^bob$', '^bob$');
        $authorizer = new Authorizer($this->users);
        $alice = (new Authenticator($this->users))->authenticate('alice', 'secret')->user;
        $bob = (new Authenticator($this->users))->authenticate('bob', 'secret')->user;
        self::assertNotNull($alice);
        self::assertNotNull($bob);

        self::assertTrue($authorizer->authorize($alice, '/', AuthorizationPermission::Read, 'alice')->allowed);
        self::assertFalse($authorizer->authorize($alice, '/', AuthorizationPermission::Read, 'tenant')->allowed);
        self::assertTrue($authorizer->authorize($alice, 'tenant-a', AuthorizationPermission::Read, 'tenant')->allowed);
        self::assertFalse($authorizer->authorize($bob, '/', AuthorizationPermission::Read, 'alice')->allowed);
        self::assertFalse($authorizer->authorize($bob, 'tenant-a', AuthorizationPermission::Read, 'tenant')->allowed);
    }

    public function testInvalidPermissionExpressionIsRejected(): void
    {
        $this->users->create('alice', 'secret');
        $this->users->grantVirtualHost('alice', '/');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid configure permission expression.');

        $this->users->setPermissions('alice', '/', '[', '.*', '.*');
    }

    public function testPermissionsRequireExistingVirtualHostGrant(): void
    {
        $this->users->create('alice', 'secret');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Grant the virtual host before setting permissions.');

        $this->users->setPermissions('alice', '/', '.*', '.*', '.*');
    }

    public function testPermissionCommandsSetListAndClearPermissions(): void
    {
        $this->users->create('alice', 'secret');
        $this->users->grantVirtualHost('alice', '/');
        $result = (new Authenticator($this->users))->authenticate('alice', 'secret');
        self::assertNotNull($result->user);
        $authorizer = new Authorizer($this->users);

        $setOutput = fopen('php://temp', 'w+');
        self::assertIsResource($setOutput);
        self::assertSame(0, (new UserSetPermissionsCommand($this->connection))->run(['alice', '/', '^cfg$', '^wr$', '^rd$'], $setOutput));
        self::assertTrue($authorizer->authorize($result->user, '/', AuthorizationPermission::Configure, 'cfg')->allowed);
        self::assertFalse($authorizer->authorize($result->user, '/', AuthorizationPermission::Configure, 'other')->allowed);

        $listOutput = fopen('php://temp', 'w+');
        self::assertIsResource($listOutput);
        self::assertSame(0, (new UserListPermissionsCommand($this->connection))->run(['alice'], $listOutput));
        rewind($listOutput);
        $output = stream_get_contents($listOutput);
        self::assertIsString($output);
        self::assertStringContainsString('^cfg$', $output);
        self::assertStringContainsString('^wr$', $output);
        self::assertStringContainsString('^rd$', $output);

        $clearOutput = fopen('php://temp', 'w+');
        self::assertIsResource($clearOutput);
        self::assertSame(0, (new UserClearPermissionsCommand($this->connection))->run(['alice', '/'], $clearOutput));
        self::assertFalse($authorizer->authorize($result->user, '/', AuthorizationPermission::Configure, 'cfg')->allowed);
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
