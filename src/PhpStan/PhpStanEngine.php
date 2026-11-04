<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\PhpStan;

use TypedDuck\ConsultRector\Diff\ChangeRun;
use TypedDuck\ConsultRector\Diff\Patcher;
use TypedDuck\ConsultRector\Diff\UnifiedDiffWriter;
use TypedDuck\ConsultRector\Dsl\CompiledRule;
use TypedDuck\ConsultRector\PhpStan\Extension\MigrateArgToEnumRule;
use TypedDuck\ConsultRector\Rector\DslConfigAssembler;
use TypedDuck\ConsultRector\Rector\FileChange;
use TypedDuck\ConsultRector\Rector\Rule\MigrateArgToEnumRector;
use TypedDuck\ConsultRector\Rector\Runner;
use TypedDuck\ConsultRector\Rector\RunResult;

/**
 * Runs an AST DSL program with usage-site transforms on PHPStan (ADR-0007):
 * declaration transforms still go through Rector, usage-site transforms through
 * {@see FixCollector}. Both produce diffs against the same original files, which
 * are spliced together so a `chain` keeps ADR-0004's atomicity, then shown as one
 * consolidated diff per file (dry-run) or written (apply).
 */
final class PhpStanEngine
{
    public function __construct(
        private readonly FixCollector $collector = new FixCollector(),
        private readonly Runner $rector = new Runner(),
        private readonly DslConfigAssembler $assembler = new DslConfigAssembler(),
        private readonly Patcher $patcher = new Patcher(),
        private readonly UnifiedDiffWriter $writer = new UnifiedDiffWriter(),
    ) {
    }

    /**
     * @param list<string>       $paths
     * @param list<CompiledRule> $rules
     */
    public function run(array $paths, array $rules, bool $dryRun): RunResult
    {
        $declarations = [];
        $specs = [];
        foreach ($rules as $rule) {
            if ($rule->ruleClass === MigrateArgToEnumRector::class) {
                $specs[] = $rule->spec;
            } else {
                $declarations[] = $rule;
            }
        }

        /** @var array<string, list<array{runs: list<ChangeRun>, hash: ?string, rules: list<string>, label: string}>> $groupsByFile */
        $groupsByFile = [];
        $errors = [];

        if ($declarations !== []) {
            $declared = $this->rector->runConfig($this->assembler->assemble($paths, $declarations), true);
            array_push($errors, ...$declared->errors);
            foreach ($declared->files as $change) {
                $groupsByFile[$this->normalize($change->file)][] = [
                    'runs' => ChangeRun::fromUnifiedDiff($change->diff),
                    'hash' => null,
                    'rules' => $change->appliedRules,
                    'label' => 'declaration change by ' . implode(', ', $change->appliedRules),
                ];
            }
        }

        if ($specs !== []) {
            $seen = [];
            foreach ($this->collector->collect($paths, $specs) as $fix) {
                // PHPStan can visit one call twice (a nullsafe call also as its plain
                // form), reporting the same fix twice; apply it once.
                $key = $fix->file . "\0" . $fix->originalHash . "\0" . $fix->diff;
                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $label = sprintf('%s:%d: %s', $fix->file, $fix->line, $fix->message);
                if ($fix->diff === null) {
                    $errors[] = 'No single-file fix for ' . $label;

                    continue;
                }

                $groupsByFile[$this->normalize($fix->file)][] = [
                    'runs' => ChangeRun::fromUnifiedDiff($fix->diff),
                    'hash' => $fix->originalHash,
                    'rules' => [MigrateArgToEnumRule::class],
                    'label' => $label,
                ];
            }
        }

        $changes = [];
        foreach ($groupsByFile as $file => $groups) {
            $change = $this->patchFile($file, $groups, $dryRun, $errors);
            if ($change !== null) {
                $changes[] = $change;
            }
        }

        return new RunResult(count($changes), count($errors), $changes, $errors);
    }

    /**
     * @param list<array{runs: list<ChangeRun>, hash: ?string, rules: list<string>, label: string}> $groups
     * @param list<string>                                                                       $errors
     */
    private function patchFile(string $file, array $groups, bool $dryRun, array &$errors): ?FileChange
    {
        $original = @file_get_contents($file);
        if ($original === false) {
            $errors[] = 'Could not read ' . $file;

            return null;
        }

        $hash = hash('sha256', $original);
        $runGroups = [];
        foreach ($groups as $key => $group) {
            if ($group['hash'] !== null && $group['hash'] !== $hash) {
                $errors[] = 'Skipped (file changed since analysis): ' . $group['label'];

                continue;
            }

            $runGroups[$key] = $group['runs'];
        }

        $patched = $this->patcher->apply($original, $runGroups);
        foreach ($patched['skipped'] as $key) {
            $errors[] = 'Skipped (overlaps another change): ' . $groups[$key]['label'];
        }

        if ($patched['runs'] === []) {
            return null;
        }

        if (! $dryRun && file_put_contents($file, $patched['content']) === false) {
            $errors[] = 'Could not write ' . $file;

            return null;
        }

        $rules = [];
        foreach (array_keys($runGroups) as $key) {
            if (! in_array($key, $patched['skipped'], true)) {
                array_push($rules, ...$groups[$key]['rules']);
            }
        }

        return new FileChange($file, array_values(array_unique($rules)), $this->writer->write($original, $patched['runs']));
    }

    private function normalize(string $file): string
    {
        $real = realpath($file);

        return $real === false ? $file : $real;
    }
}
