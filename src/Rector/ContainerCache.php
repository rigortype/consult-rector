<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

use Composer\InstalledVersions;
use RuntimeException;

/**
 * Resolves the per-user, writable directory Rector caches and scratch configs
 * land in, tolerating an unwritable system temp dir.
 *
 * Rector (and the Runner's temp config) default to `sys_get_temp_dir()`. In a
 * restricted sandbox — Cursor, CI containers — that directory is often not
 * writable, which otherwise surfaces as a baffling `changed_files: 0` or a
 * "could not create a temporary Rector config" failure. So the root is the first
 * writable candidate of:
 *
 *   1. $CONSULT_RECTOR_CACHE_DIR (explicit override)
 *   2. sys_get_temp_dir()/consult-rector-cache-<uid>  (honours TMPDIR)
 *   3. $XDG_CACHE_HOME | $HOME/.cache, then /consult-rector
 *   4. <cwd>/.consult-rector-cache  (workspace; last resort, self-ignored)
 *
 * Falling past the system temp dir is announced once on STDERR. Both the
 * container + embedded-PHPStan cache ({@see directory()}) and the unchanged-files
 * skip cache ({@see skipCacheDirectory()}) live under the resolved root.
 */
final class ContainerCache
{
    public const ENV_OVERRIDE = 'CONSULT_RECTOR_CACHE_DIR';

    /**
     * Bump to abandon every previously written skip-cache directory when the
     * cache layout or semantics change.
     */
    private const SKIP_CACHE_FORMAT = '1';

    private static bool $announced = false;

    /**
     * The resolved cache root (`containerCacheDirectory`). Pure — no filesystem
     * writes — so the assemblers can bake it into a config as a literal.
     */
    public static function directory(): string
    {
        return self::resolve()[0];
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
     * Ensure the resolved root exists before Rector runs (Rector fatals on a
     * missing `containerCacheDirectory`), self-ignore a workspace fallback, and
     * announce a fallback once. Idempotent.
     */
    public static function ensureDirectory(): string
    {
        [$root, $source] = self::resolve();

        if (! is_dir($root)) {
            @mkdir($root, 0o700, true);
        }

        if (! is_dir($root)) {
            throw new RuntimeException(sprintf(
                'consult-rector could not create a cache directory at "%s". Set %s to a writable path.',
                $root,
                self::ENV_OVERRIDE,
            ));
        }

        if ($source === 'workspace') {
            $gitignore = $root . DIRECTORY_SEPARATOR . '.gitignore';
            if (! is_file($gitignore)) {
                @file_put_contents($gitignore, "*\n");
            }
        }

        self::announce($root, $source);

        return $root;
    }

    /**
     * @return array{0: string, 1: string} the resolved root and its source label
     */
    private static function resolve(): array
    {
        /** @var array<string, string> $candidates */
        $candidates = [];

        $override = getenv(self::ENV_OVERRIDE);
        if (is_string($override) && $override !== '') {
            $candidates['env'] = rtrim($override, '/\\');
        }

        $candidates['system-temp'] = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'consult-rector-cache-' . self::userSegment();

        $userCache = self::userCacheBase();
        if ($userCache !== null) {
            $candidates['user-cache'] = $userCache . DIRECTORY_SEPARATOR . 'consult-rector';
        }

        $cwd = getcwd();
        if ($cwd !== false) {
            $candidates['workspace'] = $cwd . DIRECTORY_SEPARATOR . '.consult-rector-cache';
        }

        foreach ($candidates as $source => $directory) {
            if (self::isCreatable($directory)) {
                return [$directory, $source];
            }
        }

        throw new RuntimeException(sprintf(
            'consult-rector could not find a writable cache directory (tried: %s). Set %s to a writable path.',
            implode(', ', $candidates),
            self::ENV_OVERRIDE,
        ));
    }

    /**
     * Whether $directory exists writable, or its nearest existing ancestor is a
     * writable directory we could create it under. No side effects.
     */
    private static function isCreatable(string $directory): bool
    {
        $probe = $directory;
        while (! file_exists($probe)) {
            $parent = dirname($probe);
            if ($parent === $probe) {
                return false;
            }

            $probe = $parent;
        }

        return is_dir($probe) && is_writable($probe);
    }

    private static function userCacheBase(): ?string
    {
        $xdg = getenv('XDG_CACHE_HOME');
        if (is_string($xdg) && $xdg !== '') {
            return rtrim($xdg, '/\\');
        }

        foreach (['HOME', 'USERPROFILE'] as $envName) {
            $home = getenv($envName);
            if (is_string($home) && $home !== '') {
                return rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.cache';
            }
        }

        return null;
    }

    private static function announce(string $root, string $source): void
    {
        if (self::$announced || ($source !== 'workspace' && $source !== 'user-cache')) {
            return;
        }

        self::$announced = true;

        if (! defined('STDERR')) {
            return;
        }

        $message = $source === 'workspace'
            ? sprintf('consult-rector: system temp dir is not writable; caching under "%s" (self-ignored via .gitignore).', $root)
            : sprintf('consult-rector: system temp dir is not writable; caching under "%s".', $root);

        fwrite(STDERR, $message . "\n");
    }

    /**
     * A per-user path segment so two OS users never share — and so never collide
     * on the ownership of — the system-temp cache directory.
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
