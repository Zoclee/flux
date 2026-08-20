<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Support;

use Flux\Support\Dotenv;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DotenvTest extends TestCase
{
    private const VARIABLES = [
        'FLUX_DOTENV_TEST_HOST',
        'FLUX_DOTENV_TEST_PASSWORD',
        'FLUX_DOTENV_TEST_QUOTED',
        'FLUX_DOTENV_TEST_EXISTING',
        'FLUX_DOTENV_TEST_EMPTY',
    ];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $variable) {
            $this->clearVariable($variable);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $variable) {
            $this->clearVariable($variable);
        }
    }

    public function testItLoadsEnvironmentValuesFromDotenvFile(): void
    {
        $path = $this->writeDotenv(<<<'ENV'
# Comment
FLUX_DOTENV_TEST_HOST=127.0.0.1
FLUX_DOTENV_TEST_PASSWORD="secret value"
export FLUX_DOTENV_TEST_QUOTED='quoted # value'
FLUX_DOTENV_TEST_EMPTY=
ENV);

        Dotenv::load($path);

        self::assertSame('127.0.0.1', getenv('FLUX_DOTENV_TEST_HOST'));
        self::assertSame('secret value', getenv('FLUX_DOTENV_TEST_PASSWORD'));
        self::assertSame('quoted # value', getenv('FLUX_DOTENV_TEST_QUOTED'));
        self::assertSame('', getenv('FLUX_DOTENV_TEST_EMPTY'));
    }

    public function testProcessEnvironmentTakesPrecedence(): void
    {
        putenv('FLUX_DOTENV_TEST_EXISTING=from-process');

        Dotenv::load($this->writeDotenv('FLUX_DOTENV_TEST_EXISTING=from-file'));

        self::assertSame('from-process', getenv('FLUX_DOTENV_TEST_EXISTING'));
    }

    public function testMissingDotenvFileIsIgnored(): void
    {
        Dotenv::load(sys_get_temp_dir() . '/flux_missing_' . bin2hex(random_bytes(8)) . '.env');

        self::assertFalse(getenv('FLUX_DOTENV_TEST_HOST'));
    }

    private function writeDotenv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'flux_env_');

        if ($path === false || file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Could not create temporary .env file.');
        }

        return $path;
    }

    private function clearVariable(string $variable): void
    {
        putenv($variable);
        unset($_ENV[$variable], $_SERVER[$variable]);
    }
}
