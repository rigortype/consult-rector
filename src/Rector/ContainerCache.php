<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

use Composer\InstalledVersions;

/**
 * Resolves the per-user, writable directories Rector caches into, off Rector's
 * shared default under `sys_get_temp_dir()`.
 *
 * Rector's default writes its caches directly under `sys_get_temp_dir()`, shared
 * with every other Rector on the machine; in a shared /tmp a foreign-owned
 * subtree then makes Rector fail (or skip files as "unchanged"). Both
 * directories here live under a per-UID root so no other user's tree can collide
 * with — or break — ours:
 *
 * - {@see directory()} — the container + embedded-PHPStan cache
 *   (`containerCacheDirectory`).
 * - {@see skipCacheDirectory()} — the unchanged-files skip cache
 *   (`cacheDirectory`), isolated *per run signature* (rules + paths + Rector
 *   version). Distinct rule sets get distinct directories, so a skip entry from
 *   one run can never suppress a different run's changes, while an identical
 *   re-run reuses the directory and skips the files it already knows are clean.
 */
final class ContainerCache
{
    /**
     * Bump to abandon every previously written skip-cache directory when the
     * cache layout or semantics change.
     */
    private const SKIP_CACHE_FORMAT = '1';

    /**
     * The cache directory path. Pure — no filesystem side effects, so the
     * assemblers can bake it into a config as a literal.
     */
    public static function directory(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'consult-rector-cache-' . self::userSegment();
    }

    /**
     * The unchanged-files skip-cache directory for a given run signature. Two runs
     * with the same signature share it (so the second skips files cached as clean);
     * any difference — rules, paths, or the installed Rector version — yields a
     * different directory, keeping each run's skip decisions isolated. Pure.
     *
     * @param array<int, mixed> $signature run-identifying data (e.g. `[$paths, $rules]`)
     */
    public static function skipCacheDirectory(array $signature): string
    {
        $version = InstalledVersions::getVersion('rector/rector') ?? '';
        $payload = json_encode($signature);
        if ($payload === false) {
            $payload = serialize($signature);
        }

        $hash = substr(hash('sha256', self::SKIP_CACHE_FORMAT . '|' . $version . '|' . $payload), 0, 16);

        return self::directory() . DIRECTORY_SEPARATOR . 'skip-' . $hash;
    }

    /**
     * Ensure the directory exists before Rector runs: Rector fatals on a missing
     * `containerCacheDirectory` rather than creating it. Idempotent.
     */
    public static function ensureDirectory(): string
    {
        $directory = self::directory();
        if (! is_dir($directory)) {
            @mkdir($directory, 0o700, true);
        }

        return $directory;
    }

    /**
     * A per-user path segment so two OS users never share — and so never collide
     * on the ownership of — the same cache directory.
     */
    private static function userSegment(): string
    {
        if (function_exists('posix_getuid')) {
            return (string) posix_getuid();
        }

        foreach (['USERNAME', 'USER', 'USERPROFILE', 'HOME'] as $envName) {
            $value = getenv($envName);
            if (is_string($value) && $value !== '') {
                return substr(hash('sha256', $value), 0, 12);
            }
        }

        return 'shared';
    }
}
