<?php

declare(strict_types=1);

namespace Flux\Broker;

enum AuthorizationPermission: string
{
    case Configure = 'configure';
    case Write = 'write';
    case Read = 'read';
}
