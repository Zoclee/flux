<?php

declare(strict_types=1);

namespace Flux\Runtime;

interface RuntimeComponent
{
    public function start(): void;

    public function tick(): void;

    public function stop(): void;
}
