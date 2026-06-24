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

**Phase 2 — Remediation**: Run PHPStan against the changed files. Detect remaining type errors. Apply targeted fixes using AST DSL for each error. Loop under bounded control (see *Remediation loop control* below).

**Phase 3 — Verify**: Re-run PHPStan to confirm zero new errors on the changed files.

### Remediation loop control

**Loop ownership.** The CLI cannot generate fixes — mapping a PHPStan error to an AST DSL transform requires AI judgment — so the **skill orchestrates the loop** while the CLI provides primitives: run PHPStan, return the structured error delta, apply the AST DSL fix the skill produced. The CLI also enforces a hard iteration ceiling as a safety net for non-AI callers.

**Scope — new errors only (delta against a baseline).** Before Phase 1 apply, the CLI captures a PHPStan baseline over the target file set. After apply, remediation targets only the *delta*: errors present after the transform that were absent in the baseline. Pre-existing project errors are never touched. Error identity is matched on `(file, PHPStan identifier, normalized message)`, independent of line number so it survives line shifts. Errors the transform surfaces in *other* files (e.g. callers of a changed signature) are out of scope — the changed-file boundary is intentional; cross-file breakage is left to the project's own CI.

**Stop conditions** (first to trigger wins):

1. **Converged** — the new-error delta reaches zero. Success; proceed to Phase 3.
2. **Exhausted** — iteration count reaches `--max-remediation-iterations` (default **3**; `0` disables remediation entirely). Stop with errors remaining.
3. **Stalled** — an iteration fails to *strictly* reduce the remaining new-error count. Catches oscillation and unfixable errors: an error the skill cannot map to a catalog transform never lowers the count, so the loop terminates naturally instead of spinning.

**On non-convergence (Exhausted / Stalled).** Applied changes are **kept** — Phase 1 was human-approved and partial remediation only improves the result. Remaining errors are reported to the AI/human; there is no automatic rollback. Recovery, if wanted, is via the user's VCS (`git restore`), consistent with the apply schema's reliance on `git diff`.

**PHPStan absent.** If no PHPStan binary is found (detection item 6 below), Phase 2 is skipped at zero iterations with a warning — remediation is best-effort, never a hard dependency.

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
- The remediation loop is bounded (iteration cap + no-progress stop), so worst-case latency is capped — but some transforms will finish with residual errors the user must resolve manually, and cross-file breakage in callers is out of scope by design.
- The `--with-config` merge requires careful config-aware config assembly logic.
- PHPStan found via the target project may have a different config/level than what consult-rector expects.
