<?php

declare(strict_types=1);

namespace Flux\Broker;

final readonly class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public string $username
    ) {
    }
}
