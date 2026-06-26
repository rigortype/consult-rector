<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Tests\Rector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypedDuck\ConsultRector\Rector\ContainerCache;

#[CoversClass(ContainerCache::class)]
final class ContainerCacheTest extends TestCase
{
    private ?string $savedOverride;

    protected function setUp(): void
    {
        $current = getenv(ContainerCache::ENV_OVERRIDE);
        $this->savedOverride = $current === false ? null : $current;
        // Default-chain tests must not be perturbed by an ambient override.
        putenv(ContainerCache::ENV_OVERRIDE);
    }

    protected function tearDown(): void
    {
        if ($this->savedOverride === null) {
            putenv(ContainerCache::ENV_OVERRIDE);
        } else {
            putenv(ContainerCache::ENV_OVERRIDE . '=' . $this->savedOverride);
        }
    }

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

    /**
     * An explicit, writable $CONSULT_RECTOR_CACHE_DIR wins over every default
     * candidate, so a restricted-sandbox user can pin the cache anywhere.
     */
    public function testEnvOverrideRootsTheCacheThere(): void
    {
        $custom = sys_get_temp_dir() . '/cr-override-' . uniqid('', true);
        mkdir($custom, 0o755, true);
        putenv(ContainerCache::ENV_OVERRIDE . '=' . $custom);

        try {
            self::assertSame($custom, ContainerCache::directory());
            self::assertStringStartsWith(
                $custom . DIRECTORY_SEPARATOR . 'skip-',
                ContainerCache::skipCacheDirectory([['src'], ['Rector\Some\Rule']]),
            );
        } finally {
            @rmdir($custom);
        }
    }

    /**
     * A non-writable candidate is skipped, so resolution falls through to the next
     * usable one (here: an unwritable override → the system temp default). This is
     * the mechanism that lets consult-rector survive an unwritable system temp.
     */
    public function testUnwritableCandidateIsSkipped(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            self::markTestSkipped('root bypasses write permissions, so the read-only dir would be "writable".');
        }

        $readOnly = sys_get_temp_dir() . '/cr-ro-' . uniqid('', true);
        mkdir($readOnly, 0o500, true);
        // Point the override at a not-yet-existing dir under the read-only parent:
        // it cannot be created there, so the override must be skipped.
        putenv(ContainerCache::ENV_OVERRIDE . '=' . $readOnly . '/sub');

        try {
            $resolved = ContainerCache::directory();

            self::assertStringStartsWith(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'consult-rector-cache-', $resolved);
            self::assertStringNotContainsString($readOnly, $resolved);
        } finally {
            chmod($readOnly, 0o700);
            @rmdir($readOnly);
        }
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
