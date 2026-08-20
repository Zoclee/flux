<?php

declare(strict_types=1);

namespace Flux\Tests\Unit\Persistence\Postgres;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class MigrationFilenameTest extends TestCase
{
    public function testMigrationFilenamesUseUniqueSortableTimestamps(): void
    {
        $migrationFiles = glob(dirname(__DIR__, 4) . '/database/migrations/*.sql');
        self::assertNotFalse($migrationFiles);
        sort($migrationFiles);

        $timestamps = [];

        foreach ($migrationFiles as $migrationFile) {
            $filename = basename($migrationFile);

            self::assertMatchesRegularExpression(
                '/^(?<timestamp>\d{8}_\d{6})_[a-z0-9_]+\.sql$/',
                $filename
            );

            $timestamp = substr($filename, 0, 15);
            self::assertNotContains($timestamp, $timestamps);
            $timestamps[] = $timestamp;

            $date = DateTimeImmutable::createFromFormat('Ymd_His', $timestamp);
            self::assertInstanceOf(DateTimeImmutable::class, $date);
            self::assertSame($timestamp, $date->format('Ymd_His'));
        }

        $sortedTimestamps = $timestamps;
        sort($sortedTimestamps);

        self::assertSame($sortedTimestamps, $timestamps);
    }
}
