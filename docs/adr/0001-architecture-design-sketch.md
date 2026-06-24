# ADR-0001: consult-rector architecture design

## Status

Proposed.

## Context

consult-rector is an interactive PHP refactoring package. It wraps [rector/rector](https://github.com/rectorphp/rector) and PHP-Parser so that AI agents can perform selective, conversational code transformations — rather than editing code as raw text.

## Decision

### Three-layer architecture

```
Human (user) ←→ AI Agent ←→ consult-rector skill ←→ consult-rector CLI ←→ Rector / PHP-Parser
```

- **consult-rector skill** (`SKILL.md`): AI agent skill. Interprets refactoring requests, plans Rector rules, and delegates execution to the CLI.
- **consult-rector CLI**: Shell-level helper. Assembles temporary Rector configs, runs Rector (dry-run and apply), invokes PHP-Parser, returns structured results.
- **Rector / PHP-Parser**: Upstream PHP transformation libraries. consult-rector does not vendor or fork them.

### AI-driven interaction

The human user talks to the AI agent. The AI agent invokes the skill. The skill orchestrates the CLI. The human never drives the CLI directly in normal use.

### Responsibility distribution

| Layer | Owns |
|-------|------|
| CLI | Rule search, config assembly, Rector execution, structured output |
| Skill / AI | Rule selection strategy, diff review, ambiguity handling, user communication |

### Revision lifecycle — dry-run first, then apply

1. AI interprets user request → selects rules → builds temporary config
2. CLI runs Rector in **dry-run** mode → returns diff
3. AI reviews diff for unintended changes
4. AI presents diff to human user for **approval**
5. Human approves
6. CLI runs Rector in **apply** mode → rewrites files

### Operation types

| Type | Example | Implementation |
|------|---------|---------------|
| Single rule | Convert closure `function` → `fn` | Apply one existing Rector rule |
| Multi-rule / recipe | PSR-4 compliance | Apply multiple rules in sequence |
| Custom AST op | Convert string arg to enum | Custom PHP-Parser transformation |

### CLI concepts

- **Rule query**: Search existing Rector rules by keyword
- **Rule set**: A set of selected rules with config, assembled as a temporary `rector.php`
- **Recipe**: A multi-step procedure with ordered rule applications
- **Custom rule**: A PHP-Parser-based transformation generated within a skill session (ephemeral)

## Considered Options

### Flat CLI skill

A single SKILL.md that inlines all Rector knowledge and calls Rector directly.

**Rejected because**: Tight coupling to Rector's CLI interface; no room for AI-driven diff review workflow; harder to extend to MCP protocol later; no separation of concerns between "what to do" and "how to do it."

### Pure MCP tool

Expose consult-rector only as an MCP tool, no slash command.

**Rejected because**: Agents cannot auto-invoke MCP tools from a skill-description match — MCP tools must be called explicitly, so a slash-command skill is still needed for description-triggered discovery. MCP alone would limit reach.

## Consequences

- The 3-layer chain adds latency per operation, but each layer's responsibility is testable in isolation.
- Dry-run + AI review catches Rector's overeager transformations before they reach the user.
- The CLI must produce machine-readable structured output (JSON) in addition to human-readable diff for the AI to parse.
- Custom AST ops require PHP-Parser expertise in the CLI layer — increases maintenance surface.
