<?php

declare(strict_types=1);

namespace Flux\Persistence\Postgres;

use Flux\Broker\Binding;
use Flux\Broker\PublishResult;

final readonly class PublishTransaction
{
    private MessageRepository $messages;
    private BindingRepository $bindings;
    private MessageRouteRepository $routes;
    private SubscriptionRepository $subscriptions;
    private DeliveryRepository $deliveries;

    public function __construct(private Connection $connection)
    {
        $this->messages = new MessageRepository($connection);
        $this->bindings = new BindingRepository($connection);
        $this->routes = new MessageRouteRepository($connection);
        $this->subscriptions = new SubscriptionRepository($connection);
        $this->deliveries = new DeliveryRepository($connection);
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function publish(
        int $virtualHostId,
        string $source,
        string $routingKey,
        string $payload,
        array $headers = [],
        ?string $contentType = null,
        ?string $contentEncoding = null,
        int $priority = 0,
        bool $persistent = true,
        ?string $messageId = null
    ): PublishResult {
        return $this->connection->transaction(function () use (
            $virtualHostId,
            $source,
            $routingKey,
            $payload,
            $headers,
            $contentType,
            $contentEncoding,
            $priority,
            $persistent,
            $messageId
        ): PublishResult {
            $message = $this->messages->create(
                $payload,
                $headers,
                $contentType,
                $contentEncoding,
                $priority,
                $persistent,
                $messageId
            );

            $routes = [];
            $deliveries = [];

            foreach ($this->uniqueDestinationIds($this->bindings->findForRoute($virtualHostId, $source, $routingKey)) as $destinationId) {
                $route = $this->routes->create($message->id, $destinationId);
                $routes[] = $route;

                foreach ($this->subscriptions->allByDestination($destinationId) as $subscription) {
                    $deliveries[] = $this->deliveries->create($route->id, $subscription->id);
                }
            }

            return new PublishResult($message, $routes, $deliveries);
        });
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function publishToDestination(
        int $destinationId,
        string $payload,
        array $headers = [],
        ?string $contentType = null,
        ?string $contentEncoding = null,
        int $priority = 0,
        bool $persistent = true,
        ?string $messageId = null
    ): PublishResult {
        return $this->connection->transaction(function () use (
            $destinationId,
            $payload,
            $headers,
            $contentType,
            $contentEncoding,
            $priority,
            $persistent,
            $messageId
        ): PublishResult {
            $message = $this->messages->create(
                $payload,
                $headers,
                $contentType,
                $contentEncoding,
                $priority,
                $persistent,
                $messageId
            );

            $route = $this->routes->create($message->id, $destinationId);
            $deliveries = [];

            foreach ($this->subscriptions->allByDestination($destinationId) as $subscription) {
                $deliveries[] = $this->deliveries->create($route->id, $subscription->id);
            }

            return new PublishResult($message, [$route], $deliveries);
        });
    }

    /**
     * @param list<Binding> $bindings
     * @return list<int>
     */
    private function uniqueDestinationIds(array $bindings): array
    {
        $seen = [];
        $destinationIds = [];

        foreach ($bindings as $binding) {
            if (isset($seen[$binding->destinationId])) {
                continue;
            }

            $seen[$binding->destinationId] = true;
            $destinationIds[] = $binding->destinationId;
        }

        return $destinationIds;
    }
}
