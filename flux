#!/usr/bin/env php
<?php

declare(strict_types=1);

use Flux\Console\Application;

require __DIR__ . '/vendor/autoload.php';

$application = new Application();

exit($application->run($argv));
