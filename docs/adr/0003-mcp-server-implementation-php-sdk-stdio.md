# ADR-0003: MCP server implementation — PHP SDK + STDIO transport

## Status

Proposed.

## Context

consult-rector exposes a hybrid interface: an OpenCode slash command and an MCP tool. The MCP server needs an implementation language and transport strategy.

The upstream Rector is a PHP library. The consult-rector CLI is PHP (Composer package). The MCP protocol supports servers in any language via STDIO transport.

Two options were evaluated:
- **PHP** via [`modelcontextprotocol/php-sdk`](https://github.com/modelcontextprotocol/php-sdk) v0.6.0 (official SDK, Symfony/PHP Foundation)
- **Node.js/TypeScript** via the MCP TypeScript SDK (more mature async/event-loop ecosystem)

## Decision

**Implement the MCP server in PHP using the official PHP SDK with STDIO transport.**

### Architecture

```
MCP client (OpenCode/Claude Code)
         ↕ STDIO JSON-RPC
consult-rector mcp           ← single PHP process, long-lived
         ↕ shell_exec()
consult-rector CLI           ← short-lived subprocess per tool call
         ↕
Rector / PHP-Parser
```

### Tool definitions (one per subcommand)

| MCP Tool | Maps to CLI | Description |
|----------|-------------|-------------|
| `rector_search` | `consult-rector search <keyword>` | Search Rector rules by keyword |
| `rector_dry_run` | `consult-rector dry-run <file> <rule> --json` | Propose changes, return structured diff |
| `rector_apply` | `consult-rector apply <file> <rule> --json` | Apply changes, return result |
| `rector_ast` | `consult-rector ast <file> '<dsl>' --json` | Custom AST transformation |
| `rector_doc_index` | `consult-rector doc index <ref-file>` | Get section index of reference doc |
| `rector_doc_section` | `consult-rector doc section <ref-file> <N>` | Get section content |

### Implementation pattern (PHP SDK)

```php
use Mcp\Capability\Attribute\McpTool;

class RectorTools {
    #[McpTool(name: 'rector_search', description: 'Search Rector rules by keyword')]
    public function search(string $keyword): array {
        $output = shell_exec("vendor/bin/consult-rector search " . escapeshellarg($keyword) . " --json 2>/dev/null");
        return json_decode($output, true);
    }

    #[McpTool(name: 'rector_dry_run', description: 'Propose Rector changes without rewriting')]
    public function dryRun(string $path, string $rule): array {
        $output = shell_exec("vendor/bin/consult-rector dry-run " . escapeshellarg($path) . " --rules=" . escapeshellarg($rule) . " --json 2>/dev/null");
        return json_decode($output, true);
    }
}

// Server bootstrap
$transport = new \Mcp\Transport\StdioTransport();
$server = \Mcp\Server::builder()
    ->addTool(RectorTools::class)
    ->build();
$server->run($transport);
```

### Key constraints

- **STDOUT is reserved for MCP protocol messages.** All debug output, logs, and error messages must go to STDERR.
- **Each tool call spawns a short-lived CLI subprocess.** The MCP server process itself is long-lived but delegates actual work to fresh PHP processes. This avoids memory leak accumulation in the server process.
- **The CLI must support `--json` output** for machine consumption by the MCP tools.

## Considered Options

### Node.js MCP server (TypeScript SDK)

More natural async I/O, better ecosystem for long-running daemons, easier subprocess management.

**Rejected because**: Introduces a Node.js dependency for a PHP-first tool. Requires maintaining two language runtimes. Adds serialization overhead across the PHP/Node boundary. The PHP SDK is mature enough (v0.6.0) to trust.

### CLI-as-MCP-server (no subprocess)

The consult-rector CLI itself parses MCP protocol on STDIN and responds on STDOUT directly, without a separate server process.

**Rejected because**: Every Rector invocation would need to bootstrap the MCP session. The MCP lifecycle (initialize, tools/list, tools/call) would be conflated with CLI argument parsing. Loses the MCP session semantics (notifications, progress, cancellation).

## Consequences

- Single-language (PHP) maintenance — MCP server and CLI are in the same ecosystem.
- Each tool call pays the subprocess spawn cost (~50-200ms), but Rector execution dominates the actual latency anyway.
- The MCP server process itself is lightweight (PHP SDK + transport loop, no Rector autoload until a tool call triggers a subprocess).
- The `--json` output requirement constrains the CLI's default output format but was already the plan.
- If the PHP SDK adds streaming/fiber support in the future, tools could be migrated from subprocess to in-process execution without changing the tool interface.
