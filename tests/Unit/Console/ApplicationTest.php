<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Console;

use Flux\Console\Application;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testItPrintsHelpWhenNoCommandIsProvided(): void
    {
        [$exitCode, $output] = $this->runApplication(['flux']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Flux 0.1.0-dev', $output);
        self::assertStringContainsString('Usage:', $output);
        self::assertStringContainsString('db:status', $output);
        self::assertStringContainsString('migrate', $output);
    }

    public function testItPrintsTheVersion(): void
    {
        [$exitCode, $output] = $this->runApplication(['flux', '--version']);

        self::assertSame(0, $exitCode);
        self::assertSame("Flux 0.1.0-dev\n", $output);
    }

    public function testRootCliEntryPointExists(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        self::assertFileExists($projectRoot . '/flux');
        self::assertFileDoesNotExist($projectRoot . '/bin' . DIRECTORY_SEPARATOR . 'flux');
    }

    /**
     * @param list<string> $argv
     * @return array{0: int, 1: string}
     */
    private function runApplication(array $argv): array
    {
        $stream = fopen('php://memory', 'w+');

        self::assertIsResource($stream);

        $exitCode = (new Application())->run($argv, $stream);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($output);

        return [$exitCode, $output];
    }
}
