# ADR-0004: Declarative change + usage-site propagation (with PHPStan verification)

## Status

Proposed.

## Context

The headline use case is an **intentional breaking change to a declaration** whose new shape must be propagated to every usage site. The motivating example: replace a stringly-typed parameter — `sort(string $dir)` documented `@param 'asc'|'desc' $dir` — with an enum, `sort(AscDesc $dir)`, and rewrite every caller's `'asc'` / `'desc'` literal to `AscDesc::Asc` / `AscDesc::Desc`.

This is *not* about correcting accidental breakage that Rector introduced. The break is deliberate; the work is finding all usages of the changed declaration and migrating them to the intended pattern, then proving nothing was missed.

The hard parts: (a) usage sites are spread across the whole project, (b) deciding *what* each site becomes requires reading the actual literal, and (c) guaranteeing *completeness* — no caller left on the old pattern.

## Decision

A three-phase flow:

**Phase 1 — Declarative change.** Change the declaration to the new pattern with a declaration DSL transform (e.g. `replace-param-type` `string` → `App\AscDesc`). Standard dry-run → approve → apply.

**Phase 2 — Propagation.** Find and rewrite the usage sites with a usage-site DSL transform (e.g. `migrate-arg-to-enum`). Discovery is **hybrid**:

- **AST search is the doer.** Enumerate calls to the changed symbol, inspect each literal argument, and rewrite it per an **explicit** value→case map (`'asc' → App\AscDesc::Asc`). The AST is what knows *which* case a literal becomes — PHPStan only reports "string given", not the value.
- **PHPStan is the completeness oracle.** After the rewrites, run PHPStan over the affected set. Remaining new errors are exactly the sites the AST pass could not handle — dynamic, variable, or untyped values — which are reported for manual handling. Zero new errors means every type-checkable usage was migrated.

**Phase 3 — Verify.** The Phase 2 PHPStan run *is* the verification: zero new type errors on the affected set ⇒ the migration is complete for everything the type system can see.

### Why hybrid (not one or the other)

- **PHPStan-only** can't decide the replacement (it knows *where* is broken, not *what* to put there) and misses untyped/dynamic sites.
- **Heuristic-only** has no completeness guarantee and risks false positives on same-named methods.
- **Together**: the AST pass does the mechanical value→case substitution; PHPStan guarantees nothing type-checkable was missed. This is the role split the design called for — not an either/or.

### Scope — project-wide

Usage sites live across the codebase; finding them is the entire point. Phase 2 operates over the whole target (a path / directory / project), **not** a changed-file boundary.

### Atomicity

The declaration change and the usage rewrites are applied **together** (a `chain`) so the project never sits in a transiently broken intermediate state (declaration changed but callers not yet, or vice versa).

### The value→case map

Supplied **explicitly** in the DSL, so the transform also works for non-backed enums and non-obvious mappings:

```json
["migrate-arg-to-enum",
  ["method", "Sorter::sort"],
  ["arg", 1],
  ["map", [["asc", "App\\AscDesc::Asc"], ["desc", "App\\AscDesc::Desc"]]]]
```

### PHPStan as the completeness oracle

PHPStan is run after the rewrites to confirm completeness and to enumerate any residual sites. The loop is **skill-orchestrated** — mapping a residual PHPStan error to a follow-up fix needs AI judgment — while the CLI provides the primitive: run PHPStan over the affected set and return the structured error **delta** against a pre-change baseline (errors present after the change that were absent before). Error identity is matched on `(file, PHPStan identifier, normalized message)`, independent of line number.

### PHPStan detection priority

The CLI searches for a PHPStan binary in this order (first match wins):

1. **Explicitly configured** (`--phpstan-binary` option or config file)
2. **Same composer.json** (`vendor/bin/phpstan`) — **excludes** the version vendored inside `vendor/rector/rector/vendor/`
3. **Target project** `vendor/bin/phpstan`
4. **Composer global** (`~/.composer/vendor/bin/phpstan` or `~/.config/composer/vendor/bin/phpstan`)
5. **PATH** (`phpstan`)
6. **Not found** → verification is skipped with a warning (the AST rewrites still apply, but completeness is unverified)

### Config merging

If the target project has its own `rector.php`, the user may merge it via `--with-config=rector.php`. This requires explicit user permission — consult-rector does not auto-discover or auto-merge project configs.

## Considered Options

### Accidental-error remediation (original framing)

Run PHPStan after a transform and auto-fix whatever type errors it *accidentally* introduced.

**Superseded because**: the real need is propagating an *intentional* breaking change to its usages, not patching Rector's mistakes. The mechanics (PHPStan delta against a baseline) survive but serve a different goal.

### PHPStan-driven discovery only

Apply the declaration change, let PHPStan errors enumerate the broken call sites, fix each.

**Rejected because**: PHPStan reports *where* but not *what* (which case a literal becomes), so AST inspection is needed anyway; it also misses untyped/dynamic sites and forces a transiently broken state.

### Heuristic discovery only

Find call sites by symbol/method name and rewrite, with no PHPStan step.

**Rejected because**: no completeness guarantee and false positives on same-named methods. Good for discovery, not for proof.

## Consequences

- A new **usage-site transform family** is required — rewriting call nodes (`MethodCall` / `StaticCall`) by literal argument — distinct from the existing declaration transforms.
- PHPStan is used as a **completeness oracle**, not an error-fixer; the CLI provides the PHPStan-delta primitive and the loop is skill-orchestrated.
- Phase 2 is **project-wide** and the migration is applied **atomically** (chain) to avoid a broken intermediate.
- Completeness is "no type-checkable misses" — sites whose values are dynamic or untyped are reported for manual handling, never silently dropped.
- `--phpstan-binary` and the verification step depend on PHPStan being present; absent it, rewrites apply but completeness is unverified.
