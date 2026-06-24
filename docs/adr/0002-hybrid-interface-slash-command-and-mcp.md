# ADR-0002: Hybrid interface — Slash command + MCP

## Status

Proposed.

## Context

The consult-rector skill needs to be invocable by AI agents. There are two primary interface mechanisms in the OpenCode ecosystem:
- **Slash commands** (`/consult-rector`): defined in a skill, triggered by the AI when the skill's description matches the user's request
- **MCP tools**: exposed via the Model Context Protocol, available as tool choices to the AI

Each has different discovery and invocation characteristics. We need both.

## Decision

Support **both** interfaces:

| Interface | When it fires | Initiation path |
|-----------|---------------|-----------------|
| **Slash command** (`/consult-rector`) | AI agent's internal skill selection matches the user's refactoring intent | `skill → CLI` |
| **MCP tool** (e.g., `rector_dry_run`) | AI agent explicitly calls the tool for a specific operation | `MCP → CLI` |

### How they share the CLI

Both interfaces call the same underlying `consult-rector` CLI. The CLI's interface is designed shell-first; the skill wraps it with prompts and conventions, the MCP wraps it with structured tool definitions.

```
Skill invocation:
  AI agent → /consult-rector skill → bash(L: consult-rector ...) → CLI

MCP invocation:
  AI agent → consult-rector MCP tool → CLI (via MCP server)
```

## Rationale

- **Slash commands** are how skills naturally fire in OpenCode. Keeping this path preserves the standard skill discovery flow.
- **MCP tools** become valuable when consult-rector is composed with other agents or IDEs that speak MCP. They also allow fine-grained tool definitions per operation (e.g. separate tools for `rector_search`, `rector_dry_run`, `rector_apply` — see ADR-0003 for the canonical tool set).
- Both share the same CLI backend — no duplication of transformation logic.

## Considered Options

### Slash command only

Simpler to implement. One interface to maintain.

**Rejected because**: Limits integration surface. MCP is emerging as a cross-platform standard; skipping it now would require a costly migration later.

### MCP only

Clean, typed interface. No skill glue needed.

**Rejected because**: OpenCode cannot route MCP tools based on skill descriptions — tools must be explicitly called by the AI or user. The skill's trigger-on-description mechanism is lost.

## Consequences

- The CLI must be callable both as a standalone binary (for slash command → bash path) and as a subprocess from a MCP server process.
- MCP tool definitions must mirror the skill's capabilities to avoid confusion about which path to use.
- Documentation must explain both invocation paths.
