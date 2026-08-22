<?php

declare(strict_types=1);

namespace Flux\Broker;

interface AuthorizationService
{
    public function authorize(
        AuthenticatedUser $user,
        string $virtualHost,
        AuthorizationPermission $permission,
        string $resource
    ): AuthorizationResult;
}
