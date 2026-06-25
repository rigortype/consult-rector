<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Rector;

use InvalidArgumentException;
use TypedDuck\ConsultRector\Dsl\CompiledRule;

/**
 * Emits the temporary rector.php for an AST DSL run (ADR-0005): a closure config
 * that sets the target paths and registers each shipped rule via
 * `ruleWithConfiguration()`. Specs for the same rule class are grouped into one
 * call, preserving first-seen order.
 *
 * Like {@see ConfigAssembler}, this routes both of Rector's caches to per-user
 * directories ({@see ContainerCache}) — off Rector's shared default — and keys the
 * unchanged-files skip cache by this run's signature (rule classes + specs +
 * paths) so an identical re-run skips known-clean files while a different transform
 * never reuses stale skip decisions.
 */
final class DslConfigAssembler
{
    /**
     * @param list<string>       $paths
     * @param list<CompiledRule> $rules
     */
    public function assemble(array $paths, array $rules): string
    {
        if ($paths === []) {
            throw new InvalidArgumentException('At least one target path is required.');
        }

        if ($rules === []) {
            throw new InvalidArgumentException('At least one transform is required.');
        }

        /** @var array<class-string, list<array<string, mixed>>> $grouped */
        $grouped = [];
        foreach ($rules as $rule) {
            $grouped[$rule->ruleClass][] = $rule->spec;
        }

        $pathLiterals = implode(",\n        ", array_map(
            static fn (string $path): string => var_export($path, true),
            $paths,
        ));

        $statements = [];
        foreach ($grouped as $ruleClass => $specs) {
            $statements[] = sprintf(
                '    $rectorConfig->ruleWithConfiguration(\\%s::class, %s);',
                ltrim($ruleClass, '\\'),
                var_export($specs, true),
            );
        }

        $skipCacheDirectory = var_export(ContainerCache::skipCacheDirectory([$paths, $grouped]), true);

        return implode("\n", [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use Rector\Config\RectorConfig;',
            '',
            'return static function (RectorConfig $rectorConfig): void {',
            '    $rectorConfig->cacheDirectory(' . $skipCacheDirectory . ');',
            '    $rectorConfig->containerCacheDirectory(' . var_export(ContainerCache::directory(), true) . ');',
            '    $rectorConfig->paths([',
            '        ' . $pathLiterals . ',',
            '    ]);',
            ...$statements,
            '};',
            '',
        ]);
    }
}
