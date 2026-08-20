<?php

declare(strict_types=1);

namespace Flux\Persistence\Postgres;

use DateTimeImmutable;
use Flux\Broker\Delivery;
use Flux\Broker\DeliveryState;
use PDO;
use RuntimeException;

final readonly class DeliveryRepository
{
    private const COLUMNS = <<<SQL
id, message_route_id, subscription_id, destination_id, state, consumer_id, delivery_tag, attempts,
reserved_at, acknowledged_at, available_at, created_at, updated_at
SQL;

    public function __construct(
        private Connection $connection
    ) {
    }

    public function create(
        int $messageRouteId,
        int $subscriptionId,
        ?DateTimeImmutable $availableAt = null
    ): Delivery {
        if ($availableAt === null) {
            $statement = $this->connection->pdo()->prepare(sprintf(<<<'SQL'
INSERT INTO deliveries (message_route_id, subscription_id, destination_id)
VALUES (:message_route_id, :subscription_id, (
    SELECT destination_id FROM message_routes WHERE id = :message_route_id
))
RETURNING %s
SQL, self::COLUMNS));
        } else {
            $statement = $this->connection->pdo()->prepare(sprintf(<<<'SQL'
INSERT INTO deliveries (message_route_id, subscription_id, destination_id, available_at)
VALUES (:message_route_id, :subscription_id, (
    SELECT destination_id FROM message_routes WHERE id = :message_route_id
), :available_at)
RETURNING %s
SQL, self::COLUMNS));
            $statement->bindValue('available_at', $this->formatTimestamp($availableAt));
        }

        $statement->bindValue('message_route_id', $messageRouteId, PDO::PARAM_INT);
        $statement->bindValue('subscription_id', $subscriptionId, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException('PostgreSQL did not return the created delivery.');
        }

        return $this->mapRow($row);
    }

    public function findById(int $id): ?Delivery
    {
        $statement = $this->connection->pdo()->prepare(sprintf(<<<'SQL'
SELECT %s
FROM deliveries
WHERE id = :id
SQL, self::COLUMNS));
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    /**
     * @return list<Delivery>
     */
    public function allBySubscription(int $subscriptionId): array
    {
        $statement = $this->connection->pdo()->prepare(sprintf(<<<'SQL'
SELECT %s
FROM deliveries
WHERE subscription_id = :subscription_id
ORDER BY id
SQL, self::COLUMNS));
        $statement->bindValue('subscription_id', $subscriptionId, PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): Delivery => $this->mapRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function reserveNext(int $subscriptionId, string $consumerId, ?string $deliveryTag = null): ?Delivery
    {
        return $this->connection->transaction(function (PDO $pdo) use ($subscriptionId, $consumerId, $deliveryTag): ?Delivery {
            $select = $pdo->prepare(<<<'SQL'
SELECT id
FROM deliveries
WHERE subscription_id = :subscription_id
  AND state = 'pending'
  AND available_at <= CURRENT_TIMESTAMP
ORDER BY id
FOR UPDATE SKIP LOCKED
LIMIT 1
SQL);
            $select->bindValue('subscription_id', $subscriptionId, PDO::PARAM_INT);
            $select->execute();

            $id = $select->fetchColumn();

            if ($id === false) {
                return null;
            }

            $update = $pdo->prepare(sprintf(<<<'SQL'
UPDATE deliveries
SET state = 'reserved',
    consumer_id = :consumer_id,
    delivery_tag = :delivery_tag,
    reserved_at = CURRENT_TIMESTAMP,
    attempts = attempts + 1,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
  AND state = 'pending'
RETURNING %s
SQL, self::COLUMNS));
            $update->bindValue('id', (int) $id, PDO::PARAM_INT);
            $update->bindValue('consumer_id', $consumerId);
            $update->bindValue('delivery_tag', $deliveryTag);
            $update->execute();

            $row = $update->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                throw new RuntimeException(sprintf('Delivery %d could not be reserved after it was selected.', (int) $id));
            }

            return $this->mapRow($row);
        });
    }

    public function acknowledge(int $id): Delivery
    {
        return $this->transition($id, DeliveryState::Reserved, DeliveryState::Acknowledged, <<<SQL
state = 'acknowledged',
acknowledged_at = CURRENT_TIMESTAMP,
updated_at = CURRENT_TIMESTAMP
SQL);
    }

    public function reject(int $id): Delivery
    {
        return $this->transition($id, DeliveryState::Reserved, DeliveryState::Rejected, <<<SQL
state = 'rejected',
updated_at = CURRENT_TIMESTAMP
SQL);
    }

    public function release(int $id, ?DateTimeImmutable $availableAt = null): Delivery
    {
        if ($availableAt === null) {
            $statement = $this->connection->pdo()->prepare(sprintf(<<<'SQL'
UPDATE deliveries
SET state = 'pending',
    consumer_id = NULL,
    delivery_tag = NULL,
    reserved_at = NULL,
    available_at = CURRENT_TIMESTAMP,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
  AND state = 'reserved'
RETURNING %s
SQL, self::COLUMNS));
        } else {
            $statement = $this->connection->pdo()->prepare(sprintf(<<<'SQL'
UPDATE deliveries
SET state = 'pending',
    consumer_id = NULL,
    delivery_tag = NULL,
    reserved_at = NULL,
    available_at = :available_at,
    updated_at = CURRENT_TIMESTAMP
WHERE id = :id
  AND state = 'reserved'
RETURNING %s
SQL, self::COLUMNS));
            $statement->bindValue('available_at', $this->formatTimestamp($availableAt));
        }

        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            return $this->mapRow($row);
        }

        $this->throwInvalidTransition($id, DeliveryState::Pending);
    }

    private function transition(
        int $id,
        DeliveryState $from,
        DeliveryState $to,
        string $assignments
    ): Delivery {
        $statement = $this->connection->pdo()->prepare(sprintf(<<<SQL
UPDATE deliveries
SET %s
WHERE id = :id
  AND state = :from_state
RETURNING %s
SQL, $assignments, self::COLUMNS));
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->bindValue('from_state', $from->value);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            return $this->mapRow($row);
        }

        $this->throwInvalidTransition($id, $to);
    }

    private function throwInvalidTransition(int $id, DeliveryState $to): never
    {
        $delivery = $this->findById($id);

        if ($delivery === null) {
            throw DeliveryStateException::notFound($id);
        }

        throw DeliveryStateException::invalidTransition($id, $delivery->state->value, $to->value);
    }

    /**
     * @param array{
     *     id: mixed,
     *     message_route_id: mixed,
     *     subscription_id: mixed,
     *     destination_id: mixed,
     *     state: mixed,
     *     consumer_id: mixed,
     *     delivery_tag: mixed,
     *     attempts: mixed,
     *     reserved_at: mixed,
     *     acknowledged_at: mixed,
     *     available_at: mixed,
     *     created_at: mixed,
     *     updated_at: mixed
     * } $row
     */
    private function mapRow(array $row): Delivery
    {
        return new Delivery(
            (int) $row['id'],
            (int) $row['message_route_id'],
            (int) $row['subscription_id'],
            (int) $row['destination_id'],
            DeliveryState::from((string) $row['state']),
            $row['consumer_id'] === null ? null : (string) $row['consumer_id'],
            $row['delivery_tag'] === null ? null : (string) $row['delivery_tag'],
            (int) $row['attempts'],
            $row['reserved_at'] === null ? null : new DateTimeImmutable((string) $row['reserved_at']),
            $row['acknowledged_at'] === null ? null : new DateTimeImmutable((string) $row['acknowledged_at']),
            new DateTimeImmutable((string) $row['available_at']),
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at'])
        );
    }

    private function formatTimestamp(DateTimeImmutable $timestamp): string
    {
        return $timestamp->format('Y-m-d H:i:s.uP');
    }
}
