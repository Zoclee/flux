<?php

declare(strict_types=1);

namespace Flux\Broker;

use Flux\Persistence\Postgres\PublishTransaction;
use Flux\Persistence\Postgres\VirtualHostRepository;

final readonly class Broker
{
    public function __construct(
        private VirtualHostRepository $virtualHosts,
        private PublishTransaction $publisher
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
}
