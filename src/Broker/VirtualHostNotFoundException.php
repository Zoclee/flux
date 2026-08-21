<?php

declare(strict_types=1);

namespace Flux\Broker;

use RuntimeException;

final class VirtualHostNotFoundException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('Virtual host "%s" does not exist.', $name));
    }
}
