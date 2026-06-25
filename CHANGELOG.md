# Changelog

All notable changes to consult-rector are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.1] - 2026-06-25

First release. consult-rector is an Agent Skill plus the CLI it drives: you describe a PHP refactor to a coding agent, and it picks the Rector rule (or composes a custom AST transform), shows you the diff, and applies it on your approval. The headline workflow propagates a breaking declaration change to its call sites and verifies completeness with PHPStan. An MCP server and auto-generated rule references round out the surface.

### Added

- **[skill]** An Agent Skill (`SKILL.md`) that runs the refactor loop for you: pick a rule, dry-run, review the diff, apply on approval.
- **[cli]** The `consult-rector` CLI with `search`, `dry-run`, `apply`, `ast`, `doc`, and `phpstan` commands, with `--json` for agent-readable output.
  - `apply --verify` re-runs PHPStan after the rewrite to confirm no new errors.
- **[ast-dsl]** A custom AST transform DSL written as JSON-array S-expressions, for pinpoint changes no shipped Rector rule covers.
  - Phase-1 catalogue: `replace-param-type`, `replace-return-type`, `replace-type`, `add-import`, `add-trait-use`, `rename-trait-method-as`, `change-trait-visibility-as`, and `migrate-arg-to-enum`. Type-change transforms carry a `from` guard so they fire only when the current type matches.
- **[ast-dsl]** A breaking declaration change can be propagated to its call sites and verified for completeness.
  - `migrate-arg-to-enum` rewrites call-site literals to the matching enum case; `phpstan --baseline` reports the error delta so any site the rewrite could not reach surfaces for manual handling ([ADR-0004](docs/adr/0004-two-phase-refactoring-strategy.md)).
- **[mcp]** An MCP server (`consult-rector-mcp`) exposing the operations as `rector_*` tools over stdio.
- **[references]** Auto-generated `rectors-by-category.md` and `rectors-compendium.md` (paged via `doc`), plus a hand-curated recipe book, for rule selection.

[Unreleased]: https://github.com/rigortype/consult-rector/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/rigortype/consult-rector/releases/tag/v0.0.1
