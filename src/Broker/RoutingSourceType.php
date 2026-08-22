<?php

declare(strict_types=1);

namespace Flux\Broker;

enum RoutingSourceType: string
{
    case Direct = 'direct';
    case Fanout = 'fanout';
    case Topic = 'topic';
}
