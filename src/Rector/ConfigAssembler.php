<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

use InvalidArgumentException;

/**
 * Assembles a temporary rector.php (CONTEXT.md "Rule set"): the selected Rector
 * rule FQCNs plus the target paths, emitted as a standalone config that the CLI
 * hands to a Rector subprocess. Pure string assembly — actually running the
 * config is the Runner's job.
 *
 * Both of Rector's caches are routed to per-user directories ({@see
 * ContainerCache}), off Rector's shared default where a foreign-owned
 * `rector_cached_files`/`cache` tree could make Rector fail or skip files as
 * "unchanged" (surfacing as a baffling `changed_files: 0`). The unchanged-files
 * skip cache additionally gets a directory keyed by this run's signature (rules +
 * paths), so an identical re-run skips the files it already knows are clean while
 * a different rule set never reuses stale skip decisions.
 */
final class ConfigAssembler
{
    /**
     * @param list<string> $paths target files, directories, or globs
     * @param list<string> $rules Rector rule FQCNs
     */
    public function assemble(array $paths, array $rules): string
    {
        if ($paths === []) {
            throw new InvalidArgumentException('At least one target path is required.');
        }

        if ($rules === []) {
            throw new InvalidArgumentException('At least one Rector rule is required.');
        }

        $pathLiterals = implode(",\n        ", array_map(
            static fn (string $path): string => var_export($path, true),
            $paths,
        ));

        $ruleLiterals = implode(",\n        ", array_map(
            self::ruleLiteral(...),
            $rules,
        ));

        $skipCacheDirectory = var_export(ContainerCache::skipCacheDirectory([$paths, $rules]), true);
        $containerCacheDirectory = var_export(ContainerCache::directory(), true);

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use Rector\Config\RectorConfig;',
            '',
            'return RectorConfig::configure()',
            '    ->withCache(cacheDirectory: ' . $skipCacheDirectory . ', containerCacheDirectory: ' . $containerCacheDirectory . ')',
            '    ->withPaths([',
            '        ' . $pathLiterals . ',',
            '    ])',
            '    ->withRules([',
            '        ' . $ruleLiterals . ',',
            '    ]);',
            '',
        ]);
    }

    /**
     * Normalise a rule FQCN to a leading-backslash `::class` reference, rejecting
     * anything that is not a well-formed class name.
     */
    private static function ruleLiteral(string $rule): string
    {
        $normalized = ltrim($rule, '\\');

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid Rector rule FQCN: %s', $rule));
        }

        return '\\' . $normalized . '::class';
    }
}
