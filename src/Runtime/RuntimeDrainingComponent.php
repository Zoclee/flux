<?php

declare(strict_types=1);

namespace Flux\Runtime;

interface RuntimeDrainingComponent extends RuntimeComponent
{
    public function beginDrain(): void;

    public function inFlightCount(): int;
}
