<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\ContainerCache;

#[CoversClass(ContainerCache::class)]
final class ContainerCacheTest extends TestCase
{
    public function testDirectoryIsStableAndLivesUnderTheSystemTempDir(): void
    {
        $first = ContainerCache::directory();

        self::assertSame($first, ContainerCache::directory(), 'the path must be deterministic across calls');
        self::assertStringStartsWith(sys_get_temp_dir() . DIRECTORY_SEPARATOR, $first);
        self::assertStringContainsString('consult-rector-cache-', $first);
    }

    /**
     * A per-user segment keeps the directory off the shared default, so it cannot
     * collide with a foreign-owned `rector_cached_files`/`cache` tree.
     */
    public function testDirectoryIsDistinctFromRectorsSharedDefault(): void
    {
        self::assertNotSame(sys_get_temp_dir(), ContainerCache::directory());
        self::assertNotSame(sys_get_temp_dir() . '/rector_cached_files', ContainerCache::directory());
    }

    /**
     * The skip-cache directory is content-addressed: the same signature always maps
     * to the same directory (so an identical re-run reuses it), and it lives under
     * the per-user container directory.
     */
    public function testSkipCacheDirectoryIsDeterministicPerSignatureAndNested(): void
    {
        $signature = [['src'], ['Rector\Some\Rule']];

        $directory = ContainerCache::skipCacheDirectory($signature);

        self::assertSame($directory, ContainerCache::skipCacheDirectory($signature));
        self::assertStringStartsWith(ContainerCache::directory() . DIRECTORY_SEPARATOR . 'skip-', $directory);
    }

    /**
     * Any difference in the signature — rules or paths — must yield a different
     * directory, so one run's skip decisions never leak into another's.
     */
    public function testSkipCacheDirectoryDiffersWhenTheSignatureDiffers(): void
    {
        $base = ContainerCache::skipCacheDirectory([['src'], ['Rector\Some\Rule']]);

        self::assertNotSame($base, ContainerCache::skipCacheDirectory([['src'], ['Rector\Other\Rule']]));
        self::assertNotSame($base, ContainerCache::skipCacheDirectory([['lib'], ['Rector\Some\Rule']]));
    }

    public function testEnsureDirectoryCreatesThePathAndIsIdempotent(): void
    {
        $directory = ContainerCache::ensureDirectory();

        self::assertSame(ContainerCache::directory(), $directory);
        self::assertDirectoryExists($directory);

        // A second call must not fail on the now-existing directory.
        self::assertSame($directory, ContainerCache::ensureDirectory());
        self::assertDirectoryExists($directory);
    }
}
