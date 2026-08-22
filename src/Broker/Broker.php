<?php

declare(strict_types=1);

namespace Flux\Broker;

use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\BindingRepository;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\MessageRepository;
use Flux\Persistence\Postgres\MessageRouteRepository;
use Flux\Persistence\Postgres\RoutingSourceRepository;
use Flux\Persistence\Postgres\SubscriptionRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;
use RuntimeException;

final readonly class Broker
{
    public function __construct(
        private VirtualHostRepository $virtualHosts,
        private PublishTransaction $publisher,
        private DestinationRepository $destinations,
        private SubscriptionRepository $subscriptions,
        private DeliveryRepository $deliveries,
        private ?BindingRepository $bindings = null,
        private ?RoutingSourceRepository $routingSources = null,
        private ?MessageRouteRepository $messageRoutes = null,
        private ?MessageRepository $messages = null
    ) {
    }

    public function publish(PublishRequest $request): PublishResult
    {
        $virtualHost = $this->virtualHosts->findByName($request->virtualHost);

        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($request->virtualHost);
        }

        if ($this->routingSources !== null && $this->routingSources->findByName($virtualHost->id, $request->source) === null) {
            throw new TopologyException(
                sprintf('Routing source "%s" does not exist.', $request->source),
                TopologyException::NOT_FOUND
            );
        }

        return $this->publisher->publish(
            $virtualHost->id,
            $request->source,
            $request->routingKey,
            $request->payload,
            $request->headers,
            $request->contentType,
            $request->contentEncoding,
            $request->priority,
            $request->persistent,
            $request->messageId
        );
    }

    public function reserve(ReserveRequest $request): ?Delivery
    {
        $virtualHost = $this->virtualHosts->findByName($request->virtualHost);

        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($request->virtualHost);
        }

        $destination = $this->destinations->findByName($virtualHost->id, $request->destination);

        if ($destination === null) {
            throw DestinationNotFoundException::forName($request->virtualHost, $request->destination);
        }

        $subscription = $this->subscriptions->findByName($destination->id, $request->subscription);

        if ($subscription === null) {
            throw SubscriptionNotFoundException::forName($request->destination, $request->subscription);
        }

        return $this->deliveries->reserveNext($subscription->id, $request->consumerId, $request->deliveryTag);
    }

    public function acknowledge(AcknowledgeRequest $request): Delivery
    {
        return $this->deliveries->acknowledge($request->deliveryId);
    }

    public function reject(RejectRequest $request): Delivery
    {
        $delivery = $this->deliveries->findById($request->deliveryId);
        if ($delivery === null) {
            return $this->deliveries->reject($request->deliveryId);
        }

        $destination = $this->destinations->findById($delivery->destinationId);
        if ($destination === null) {
            return $this->deliveries->reject($request->deliveryId);
        }

        $policy = RetryPolicy::fromDestinationMetadata($destination->metadata);
        if ($policy === null) {
            return $this->deliveries->reject($request->deliveryId);
        }

        $deadLetterDestinationId = null;
        if ($policy->deadLetterDestination !== null) {
            $deadLetterDestination = $this->destinations->findByName($destination->virtualHostId, $policy->deadLetterDestination);
            if ($deadLetterDestination === null || $deadLetterDestination->type !== DestinationType::Queue) {
                throw new TopologyException(
                    sprintf('Dead-letter destination "%s" does not exist.', $policy->deadLetterDestination),
                    TopologyException::NOT_FOUND
                );
            }

            $deadLetterDestinationId = $deadLetterDestination->id;
        }

        return $this->deliveries->fail($request->deliveryId, $policy, $deadLetterDestinationId);
    }

    public function release(ReleaseRequest $request): Delivery
    {
        return $this->deliveries->release($request->deliveryId, $request->availableAt);
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function publishToDefaultExchange(
        string $virtualHostName,
        string $queue,
        string $payload,
        array $headers = [],
        ?string $contentType = null,
        ?string $contentEncoding = null,
        int $priority = 0,
        bool $persistent = true,
        ?string $messageId = null
    ): PublishResult {
        $virtualHost = $this->virtualHosts->findByName($virtualHostName);
        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($virtualHostName);
        }

        $destination = $this->destinations->findByName($virtualHost->id, $queue);
        if ($destination === null || $destination->type !== DestinationType::Queue) {
            throw new TopologyException(sprintf('Queue "%s" does not exist.', $queue), TopologyException::NOT_FOUND);
        }

        return $this->publisher->publishToDestination(
            $destination->id,
            $payload,
            $headers,
            $contentType,
            $contentEncoding,
            $priority,
            $persistent,
            $messageId
        );
    }

    public function ensureQueueSubscription(string $virtualHostName, string $queue, string $subscriptionName): Subscription
    {
        if ($subscriptionName === '') {
            throw new TopologyException('Subscription name must not be empty.', TopologyException::PRECONDITION_FAILED);
        }

        $virtualHost = $this->virtualHosts->findByName($virtualHostName);
        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($virtualHostName);
        }

        $destination = $this->destinations->findByName($virtualHost->id, $queue);
        if ($destination === null || $destination->type !== DestinationType::Queue) {
            throw new TopologyException(sprintf('Queue "%s" does not exist.', $queue), TopologyException::NOT_FOUND);
        }

        $subscription = $this->subscriptions->findByName($destination->id, $subscriptionName);
        if ($subscription !== null) {
            return $subscription;
        }

        return $this->subscriptions->create($destination->id, $subscriptionName, false, ['declared_by' => 'amqp']);
    }

    public function messageForDelivery(Delivery $delivery): Message
    {
        $route = $this->messageRoutes()->findById($delivery->messageRouteId)
            ?? throw new RuntimeException(sprintf('Message route %d does not exist.', $delivery->messageRouteId));

        return $this->messages()->findById($route->messageId)
            ?? throw new RuntimeException(sprintf('Message %d does not exist.', $route->messageId));
    }

    public function declareQueue(
        string $virtualHostName,
        string $name,
        bool $durable,
        bool $autoDelete,
        bool $passive = false
    ): Destination {
        if ($name === '') {
            throw new TopologyException('Queue name must not be empty.', TopologyException::PRECONDITION_FAILED);
        }

        $virtualHost = $this->virtualHosts->findByName($virtualHostName);
        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($virtualHostName);
        }

        $destination = $this->destinations->findByName($virtualHost->id, $name);
        if ($destination === null) {
            if ($passive) {
                throw new TopologyException(sprintf('Queue "%s" does not exist.', $name), TopologyException::NOT_FOUND);
            }

            $destination = $this->destinations->create(
                $virtualHost->id,
                $name,
                DestinationType::Queue,
                $durable,
                $autoDelete,
                ['declared_by' => 'topology']
            );

            $this->ensureQueueSubscription($virtualHostName, $destination->name, 'amqp');

            return $destination;
        }

        if (
            $destination->type !== DestinationType::Queue
            || $destination->durable !== $durable
            || $destination->autoDelete !== $autoDelete
        ) {
            throw new TopologyException(
                sprintf('Queue "%s" exists with incompatible properties.', $name),
                TopologyException::PRECONDITION_FAILED
            );
        }

        $this->ensureQueueSubscription($virtualHostName, $destination->name, 'amqp');

        return $destination;
    }

    public function declareDirectRoutingSource(
        string $virtualHostName,
        string $name,
        bool $durable,
        bool $autoDelete,
        bool $passive = false
    ): RoutingSource {
        if ($name === '') {
            throw new TopologyException(
                'The default AMQP exchange is implicit and cannot be declared.',
                TopologyException::PRECONDITION_FAILED
            );
        }

        $routingSources = $this->routingSources();
        $virtualHost = $this->virtualHosts->findByName($virtualHostName);
        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($virtualHostName);
        }

        $source = $routingSources->findByName($virtualHost->id, $name);
        if ($source === null) {
            if ($passive) {
                throw new TopologyException(
                    sprintf('Routing source "%s" does not exist.', $name),
                    TopologyException::NOT_FOUND
                );
            }

            return $routingSources->create(
                $virtualHost->id,
                $name,
                RoutingSourceType::Direct,
                $durable,
                $autoDelete,
                ['declared_by' => 'topology']
            );
        }

        if (
            $source->type !== RoutingSourceType::Direct
            || $source->durable !== $durable
            || $source->autoDelete !== $autoDelete
        ) {
            throw new TopologyException(
                sprintf('Routing source "%s" exists with incompatible properties.', $name),
                TopologyException::PRECONDITION_FAILED
            );
        }

        return $source;
    }

    public function bindQueue(string $virtualHostName, string $source, string $queue, string $routingKey): ?Binding
    {
        $virtualHost = $this->virtualHosts->findByName($virtualHostName);
        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($virtualHostName);
        }

        $destination = $this->destinations->findByName($virtualHost->id, $queue);
        if ($destination === null || $destination->type !== DestinationType::Queue) {
            throw new TopologyException(sprintf('Queue "%s" does not exist.', $queue), TopologyException::NOT_FOUND);
        }

        if ($source === '') {
            if ($routingKey !== $queue) {
                throw new TopologyException(
                    'The default routing source only binds a queue to its own name.',
                    TopologyException::PRECONDITION_FAILED
                );
            }

            return null;
        }

        $bindings = $this->bindings();
        $routingSources = $this->routingSources();
        if ($routingSources->findByName($virtualHost->id, $source) === null) {
            throw new TopologyException(sprintf('Routing source "%s" does not exist.', $source), TopologyException::NOT_FOUND);
        }

        $binding = $bindings->findExact($virtualHost->id, $source, $destination->id, $routingKey);
        if ($binding !== null) {
            return $binding;
        }

        return $bindings->create($virtualHost->id, $source, $destination->id, $routingKey, ['declared_by' => 'topology']);
    }

    private function bindings(): BindingRepository
    {
        return $this->bindings ?? throw new RuntimeException('Broker topology binding repository is not configured.');
    }

    private function routingSources(): RoutingSourceRepository
    {
        return $this->routingSources ?? throw new RuntimeException('Broker topology routing source repository is not configured.');
    }

    private function messageRoutes(): MessageRouteRepository
    {
        return $this->messageRoutes ?? throw new RuntimeException('Broker message route repository is not configured.');
    }

    private function messages(): MessageRepository
    {
        return $this->messages ?? throw new RuntimeException('Broker message repository is not configured.');
    }
}
