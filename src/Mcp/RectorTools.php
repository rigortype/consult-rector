<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Mcp;

/**
 * MCP tool surface (ADR-0003): one tool per CLI subcommand, each delegating to a
 * short-lived `consult-rector` subprocess so the long-lived server never
 * accumulates Rector's autoload or memory.
 *
 * The mcp/sdk builder's manual registration path takes a tool's name and
 * description from the `addTool()` call (not from attributes), so the mapping
 * lives in {@see self::definitions()} and is wired up in bin/consult-rector-mcp.
 */
final class RectorTools
{
    /**
     * Maps each handler method to its MCP tool name and description.
     *
     * @return array<string, array{name: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            'search' => [
                'name' => 'rector_search',
                'description' => 'Search Rector rules by keyword(s); space-separated keywords narrow the result (AND)',
            ],
            'dryRun' => [
                'name' => 'rector_dry_run',
                'description' => 'Propose Rector changes without rewriting files',
            ],
            'apply' => [
                'name' => 'rector_apply',
                'description' => 'Apply Rector changes, rewriting files',
            ],
            'ast' => [
                'name' => 'rector_ast',
                'description' => 'Apply a custom AST DSL transformation',
            ],
            'docIndex' => [
                'name' => 'rector_doc_index',
                'description' => 'Get the section index of a reference document',
            ],
            'docSection' => [
                'name' => 'rector_doc_section',
                'description' => 'Get a section from a reference document by number',
            ],
            'phpStan' => [
                'name' => 'rector_phpstan',
                'description' => 'Run PHPStan over a path; with a baseline file, report only new errors (ADR-0004)',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function search(string $keyword): array
    {
        // Mirror the CLI's multiple-keyword AND search: split on whitespace so an
        // agent can narrow with a single string ("closure arrow").
        $parts = preg_split('/\s+/', trim($keyword), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = is_array($parts) && $parts !== [] ? $parts : [$keyword];

        return $this->run(array_merge(['search'], $keywords));
    }

    /**
     * @return array<string, mixed>
     */
    public function dryRun(string $path, string $rule): array
    {
        return $this->run(['dry-run', $path, '--rules=' . $rule]);
    }

    /**
     * @return array<string, mixed>
     */
    public function apply(string $path, string $rule): array
    {
        return $this->run(['apply', $path, '--rules=' . $rule]);
    }

    /**
     * @return array<string, mixed>
     */
    public function ast(string $path, string $dsl): array
    {
        return $this->run(['ast', $path, $dsl]);
    }

    /**
     * @return array<string, mixed>
     */
    public function docIndex(string $file): array
    {
        return $this->run(['doc', 'index', $file]);
    }

    /**
     * @return array<string, mixed>
     */
    public function docSection(string $file, int $section): array
    {
        return $this->run(['doc', 'section', $file, (string) $section]);
    }

    /**
     * @return array<string, mixed>
     */
    public function phpStan(string $path, ?string $baseline = null): array
    {
        $args = ['phpstan', $path];
        if ($baseline !== null && $baseline !== '') {
            $args[] = '--baseline=' . $baseline;
        }

        return $this->run($args);
    }

    /**
     * @param list<string> $args
     *
     * @return array<string, mixed>
     */
    private function run(array $args): array
    {
        $binary = \dirname(__DIR__, 2) . '/bin/consult-rector';
        $command = array_merge([PHP_BINARY, $binary], $args, ['--json']);

        // proc_open with an array command bypasses the shell (no escaping needed) and
        // keeps STDOUT and STDERR separate, so a failure's STDERR detail is captured
        // rather than discarded — otherwise every error reads as "no output".
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];

        $process = proc_open($command, $descriptors, $pipes);
        if (! is_resource($process)) {
            return [
                'error' => 'Could not start consult-rector.',
            ];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);

        $stdout = is_string($stdout) ? $stdout : '';
        $stderr = is_string($stderr) ? trim($stderr) : '';

        if (trim($stdout) === '') {
            return [
                'error' => $stderr !== '' ? $stderr : 'consult-rector produced no output',
            ];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($stdout, true);
        if (! is_array($decoded)) {
            $result = [
                'error' => 'consult-rector returned invalid JSON',
                'raw' => $stdout,
            ];
            if ($stderr !== '') {
                $result['stderr'] = $stderr;
            }

            return $result;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
