<?php

declare(strict_types=1);

namespace Flux\Broker;

use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\BindingRepository;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
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
        private ?RoutingSourceRepository $routingSources = null
    ) {
    }

    public function publish(PublishRequest $request): PublishResult
    {
        $virtualHost = $this->virtualHosts->findByName($request->virtualHost);

        if ($virtualHost === null) {
            throw VirtualHostNotFoundException::forName($request->virtualHost);
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
        return $this->deliveries->reject($request->deliveryId);
    }

    public function release(ReleaseRequest $request): Delivery
    {
        return $this->deliveries->release($request->deliveryId, $request->availableAt);
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

            return $this->destinations->create(
                $virtualHost->id,
                $name,
                DestinationType::Queue,
                $durable,
                $autoDelete,
                ['declared_by' => 'topology']
            );
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
}
