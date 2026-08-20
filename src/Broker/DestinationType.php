<?php

declare(strict_types=1);

namespace Flux\Broker;

enum DestinationType: string
{
    case Queue = 'queue';
}
