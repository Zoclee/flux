<?php

declare(strict_types=1);

namespace Flux\Broker;

use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\DeliveryRepository;
use Flux\Persistence\Postgres\DestinationRepository;
use Flux\Persistence\Postgres\SubscriptionRepository;
use Flux\Persistence\Postgres\VirtualHostRepository;

final readonly class Broker
{
    public function __construct(
        private VirtualHostRepository $virtualHosts,
        private PublishTransaction $publisher,
        private DestinationRepository $destinations,
        private SubscriptionRepository $subscriptions,
        private DeliveryRepository $deliveries
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
}
