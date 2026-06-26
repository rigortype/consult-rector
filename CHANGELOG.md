# Changelog

All notable changes to consult-rector are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.2] - 2026-06-26

consult-rector 0.0.2 is about running reliably wherever an agent drives it. It now works inside restricted sandboxes where the system temporary directory is unwritable, no longer reports a misleading `changed_files: 0` when a run actually failed, and re-runs an unchanged codebase far faster. The MCP tools reach parity with the CLI's multi-keyword search and now surface real error detail instead of a bare "no output". Other changes include multi-keyword rule search on the CLI and the removal of a non-functional option.

### Added

- **[cli]** `search` accepts multiple keywords and returns only the rules that match all of them.

### Changed

- **[cli]** consult-rector resolves its cache and temporary-config directory to the first writable location, so it works inside restricted sandboxes (Cursor, CI containers) where the system temporary directory is unwritable.
  - The candidates, in order, are `$CONSULT_RECTOR_CACHE_DIR`, the system temporary directory, the user cache directory, then a self-ignored directory in the working tree; a fallback past the system temporary directory is announced once on stderr.
- **[cli]** Re-running the same rules over an unchanged codebase is much faster, reusing a per-run cache instead of reprocessing every file.
- **[mcp]** `rector_search` mirrors the CLI's multi-keyword search, narrowing on a space-separated keyword string.

### Removed

- **[cli]** The non-functional `--with-config` option; config merging stays deferred, and `--config` runs a project's `rector.php` verbatim.

### Fixed

- **[cli]** A failed run now surfaces Rector's fatal errors instead of masquerading as a successful `changed_files: 0`.
  - This covers an unwritable or foreign-owned cache directory, which previously looked like "the rule matched nothing".
- **[mcp]** A failed tool call returns the underlying error detail instead of a bare "produced no output".

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

[Unreleased]: https://github.com/rigortype/consult-rector/compare/v0.0.2...HEAD
[0.0.2]: https://github.com/rigortype/consult-rector/compare/v0.0.1...v0.0.2
[0.0.1]: https://github.com/rigortype/consult-rector/releases/tag/v0.0.1
