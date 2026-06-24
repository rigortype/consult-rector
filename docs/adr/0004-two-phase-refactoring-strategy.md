# ADR-0004: Two-phase refactoring strategy with PHPStan remediation

## Status

Proposed.

## Context

Rector transformations can be overeager — they may change more than intended, miss edge cases, or introduce type errors in dependent code. A dry-run + human approval workflow catches unintended transformations, but type-level correctness after the change is not guaranteed.

We need a post-transformation verification step that catches remaining issues automatically.

## Decision

Adopt a **two-phase refactoring strategy** (three if counting verification):

### Phases

**Phase 1 — Declarative**: Apply the main transformation (Rector rule or AST DSL). Standard dry-run → approve → apply workflow.

**Phase 2 — Remediation**: Run PHPStan against the changed files. Detect remaining type errors. Apply targeted fixes using AST DSL for each error. Loop until clean.

**Phase 3 — Verify**: Re-run PHPStan to confirm zero new errors on the changed files.

### PHPStan detection priority

The CLI searches for a PHPStan binary in this order (first match wins):

1. **Explicitly configured** (`--phpstan-binary` option or config file)
2. **Same composer.json** (`vendor/bin/phpstan`) — **excludes** the version vendored inside `vendor/rector/rector/vendor/`
3. **Target project** `vendor/bin/phpstan`
4. **Composer global** (`~/.composer/vendor/bin/phpstan` or `~/.config/composer/vendor/bin/phpstan`)
5. **PATH** (`phpstan`)
6. **Not found** → remediation phase skipped with a warning

### Remediation scope

PHPStan type errors only. Unused imports, code style violations, and other non-type issues are deferred to the project's own tooling (rector.php config, ECS, php-cs-fixer).

### Config merging

If the target project has its own `rector.php`, the user may opt to merge it with consult-rector's temporary config via `--with-config=rector.php`. This merge requires explicit user permission — consult-rector does not auto-discover or auto-merge project configs.

## Considered Options

### Single-pass apply

Apply the transformation and stop. Let the user fix remaining issues manually.

**Rejected because**: The whole point of consult-rector is reducing manual effort. Automated remediation catches what Rector misses.

### Defer all post-processing to the user's CI

Run nothing after apply.

**Rejected because**: CI feedback is slow. The AI-skill loop should converge in one session, not across commits.

## Consequences

- consult-rector must include PHPStan execution capability, adding a dependency concern (or detection logic).
- Remediation loop may converge slowly if PHPStan reports many errors on the first pass.
- The `--with-config` merge requires careful config-aware config assembly logic.
- PHPStan found via the target project may have a different config/level than what consult-rector expects.
