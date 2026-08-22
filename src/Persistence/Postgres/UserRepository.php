<?php

declare(strict_types=1);

namespace Flux\Persistence\Postgres;

use DateTimeImmutable;
use Flux\Broker\User;
use PDO;
use RuntimeException;

final readonly class UserRepository
{
    public function __construct(
        private Connection $connection
    ) {
    }

    public function create(string $username, string $password, bool $enabled = true): User
    {
        $passwordHash = password_hash($password, $this->passwordAlgorithm());
        if (!is_string($passwordHash)) {
            throw new RuntimeException('Could not hash user password.');
        }

        $statement = $this->connection->pdo()->prepare(<<<'SQL'
INSERT INTO users (username, password_hash, enabled)
VALUES (:username, :password_hash, :enabled)
RETURNING id, username, password_hash, enabled, created_at, updated_at
SQL);
        $statement->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'enabled' => $enabled,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException('PostgreSQL did not return the created user.');
        }

        return $this->mapRow($row);
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
SELECT id, username, password_hash, enabled, created_at, updated_at
FROM users
WHERE username = :username
SQL);
        $statement->execute(['username' => $username]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        $statement = $this->connection->pdo()->query(<<<'SQL'
SELECT id, username, password_hash, enabled, created_at, updated_at
FROM users
ORDER BY username
SQL);

        if ($statement === false) {
            throw new RuntimeException('Could not read users from PostgreSQL.');
        }

        return array_map(
            fn (array $row): User => $this->mapRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function grantVirtualHost(string $username, string $virtualHost): void
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
INSERT INTO user_virtual_hosts (user_id, virtual_host_id)
SELECT users.id, virtual_hosts.id
FROM users
CROSS JOIN virtual_hosts
WHERE users.username = :username
  AND virtual_hosts.name = :virtual_host
ON CONFLICT DO NOTHING
SQL);
        $statement->execute([
            'username' => $username,
            'virtual_host' => $virtualHost,
        ]);

        if ($statement->rowCount() === 0 && ($this->findByUsername($username) === null || !$this->virtualHostExists($virtualHost))) {
            throw new RuntimeException(sprintf('User "%s" or virtual host "%s" was not found.', $username, $virtualHost));
        }
    }

    public function hasVirtualHostAccess(int $userId, string $virtualHost): bool
    {
        $statement = $this->connection->pdo()->prepare(<<<'SQL'
SELECT 1
FROM user_virtual_hosts
INNER JOIN virtual_hosts ON virtual_hosts.id = user_virtual_hosts.virtual_host_id
WHERE user_virtual_hosts.user_id = :user_id
  AND virtual_hosts.name = :virtual_host
SQL);
        $statement->execute([
            'user_id' => $userId,
            'virtual_host' => $virtualHost,
        ]);

        return $statement->fetchColumn() !== false;
    }

    private function virtualHostExists(string $virtualHost): bool
    {
        $statement = $this->connection->pdo()->prepare('SELECT 1 FROM virtual_hosts WHERE name = :name');
        $statement->execute(['name' => $virtualHost]);

        return $statement->fetchColumn() !== false;
    }

    private function passwordAlgorithm(): string
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    /**
     * @param array{id: mixed, username: mixed, password_hash: mixed, enabled: mixed, created_at: mixed, updated_at: mixed} $row
     */
    private function mapRow(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['password_hash'],
            $row['enabled'] === true || $row['enabled'] === 1 || $row['enabled'] === '1' || $row['enabled'] === 't',
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at'])
        );
    }
}
