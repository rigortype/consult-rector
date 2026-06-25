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
                'description' => 'Search Rector rules by keyword',
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
        return $this->run(['search', $keyword]);
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
        $escaped = implode(' ', array_map('escapeshellarg', $command));

        // STDERR is discarded: only the structured JSON on STDOUT is the result.
        $output = shell_exec($escaped . ' 2>/dev/null');
        if (! is_string($output) || trim($output) === '') {
            return [
                'error' => 'consult-rector produced no output',
            ];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($output, true);

        if (! is_array($decoded)) {
            return [
                'error' => 'consult-rector returned invalid JSON',
                'raw' => $output,
            ];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
