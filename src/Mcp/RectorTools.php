<?php

declare(strict_types=1);

namespace TypedDuck\ConsultRector\Mcp;

use Mcp\Capability\Attribute\McpTool;

/**
 * MCP tool surface (ADR-0003). One tool per CLI subcommand; each call spawns a
 * short-lived `consult-rector` subprocess so the long-lived server process never
 * accumulates Rector's autoload or memory.
 */
final class RectorTools
{
    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'rector_search', description: 'Search Rector rules by keyword')]
    public function search(string $keyword): array
    {
        return $this->run(['search', $keyword]);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'rector_dry_run', description: 'Propose Rector changes without rewriting files')]
    public function dryRun(string $path, string $rule): array
    {
        return $this->run(['dry-run', $path, '--rules=' . $rule]);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'rector_apply', description: 'Apply Rector changes, rewriting files')]
    public function apply(string $path, string $rule): array
    {
        return $this->run(['apply', $path, '--rules=' . $rule]);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'rector_ast', description: 'Apply a custom AST DSL transformation')]
    public function ast(string $path, string $dsl): array
    {
        return $this->run(['ast', $path, $dsl]);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'rector_doc_index', description: 'Get the section index of a reference document')]
    public function docIndex(string $file): array
    {
        return $this->run(['doc', 'index', $file]);
    }

    /**
     * @return array<string, mixed>
     */
    #[McpTool(name: 'rector_doc_section', description: 'Get a section from a reference document by number')]
    public function docSection(string $file, int $section): array
    {
        return $this->run(['doc', 'section', $file, (string) $section]);
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
