#!/usr/bin/env php
<?php

declare(strict_types=1);

use Flux\Console\Application;

$autoloaders = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../autoload.php',
];

$autoloaded = false;

foreach ($autoloaders as $autoload) {
    if (is_file($autoload)) {
        require $autoload;
        $autoloaded = true;
        break;
    }
}

if (! $autoloaded) {
    fwrite(STDERR, "Flux could not find Composer's autoloader. Run composer install or install Flux through Composer.\n");
    exit(1);
}

$application = new Application();

exit($application->run($argv));
