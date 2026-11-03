# ADR-0007: PHPStan as an opt-in engine for usage-site transforms

## Status

Proposed (experimental). Amends ADR-0004's "PHPStan is a completeness oracle, not an error-fixer".

## Context

ADR-0004 splits Phase 2 (propagation) into a doer — a Rector rule that rewrites call literals — and an oracle — a PHPStan run that lists what the doer missed. The two sides resolve types independently: the Rector rule sees the PHPStan instance embedded in Rector, without the project's own PHPStan configuration (stubs, bootstrap files, framework extensions with dynamic return types). Where they disagree, the doer misses a site the oracle can type, or rewrites one it should not.

PHPStan 2.3.5 made custom rules auto-fixable: a rule attaches an AST change to an error with `RuleErrorBuilder::fixNode()`. Two properties of that API matter here:

- **A plain `analyse` is already a dry-run.** For every fixable error PHPStan computes a unified diff of the fix, against a hash of the file's contents, without touching the source. `--fix` only applies those diffs.
- **A custom error formatter (`ErrorFormatter`, `@api`) sees every error.** It can emit only the errors carrying our identifiers, so the dry-run is scoped to our transform, and fixable errors from other rules (PHPStan's own `method.missingOverride`, extension rules, …) are never touched.

The diffs themselves have no stable public accessor yet. `Error::getFixedErrorDiffs()` is `@internal Experimental` (and `getFixedErrorDiff()` is deprecated in its favour); the `@api` `ErrorFixer` applies fixes but only by writing them to disk. `Error::jsonSerialize()`, a public method of the `@api` `Error` class, carries them as `[file, originalHash, diff]` tuples — the shape PHPStan uses between its parallel workers.

## Decision

Usage-site transforms (today: `migrate-arg-to-enum`) can run on PHPStan instead of Rector, opted into per run with `ast --engine=phpstan` (MCP: `engine`). Rector stays the default engine.

### Spec-driven, same DSL

The DSL is unchanged. A PHPStan rule shipped with consult-rector (`consultRector.migrateArgToEnum`) applies the same spec as the Rector rule — `Class::method`, argument position, explicit value→case map — with the same matching semantics (ADR-0004 "Call-site matching"): receiver type checked for class-qualified methods, untyped receivers on the method-name heuristic, named arguments by parameter name, nullsafe and static calls. It does not depend on the Phase 1 declaration change being on disk.

### consult-rector applies the diffs, not `--fix`

PHPStan runs a normal `analyse` with a generated configuration that includes the project's own PHPStan configuration (when one is found in the working directory), registers the rule, and selects consult-rector's error formatter. The formatter prints, as JSON, each of our errors with its diff and the hash it was computed against, read from the error's public serialization; if that shape is missing it reports a failure rather than an empty result. consult-rector then:

1. turns every diff into change runs (lines removed from the original and lines inserted in their place);
2. in a `chain`, adds the Rector engine's diff for the declaration transforms as one more group — both sides are computed against the same original files, so ADR-0004's atomicity (declaration and usages applied together) holds;
3. splices the groups into the original contents in one pass; a group whose hash no longer matches, or whose runs overlap an earlier group, is skipped and reported;
4. prints one consolidated original→final diff per file (dry-run) or writes the files (apply).

`--fix` is not used: it would apply every fixable error in the project, re-analyse up to ten rounds, and offers no preview.

### Loading the rule into PHPStan

The rule and formatter live in their own namespace and depend only on PHPStan and PHP-Parser. A small bootstrap registers an autoloader for that namespace alone, so a standalone consult-rector install never pulls its own Composer dependencies into the project's PHPStan process.

## Consequences

- The doer uses the project's PHPStan setup, the same one the oracle uses.
- Requires PHPStan 2.3.5 or later (the release that made custom rules fixable); older versions fail with a clear message rather than silently doing nothing.
- A fix spanning several files is reported, not applied. `migrate-arg-to-enum` fixes are always single-file.
- PHPStan can visit one call twice (a nullsafe call also in its plain form) and report the same fix twice; identical fixes are applied once.
- With no project configuration, the generated one sets `customRulesetUsed`, so PHPStan runs only consult-rector's rule.
- One analysis round: fixes are not re-analysed for follow-up fixes (`--fix` does up to ten). Literal→case substitution needs no second round.
- Output spelling differs by engine: PHPStan writes class names relative to the namespace and imports (`Dir::Asc`); the Rector engine writes them fully qualified (`\App\Dir::Asc`).
- The PHPStan engine is a full project analysis; it is slower than a Rector run on small targets.
- Built on an API PHPStan marks experimental, and on an undocumented serialization shape. If either changes, only this engine is affected and it fails loudly; the Rector engine remains.

## Considered Options

### Run `phpstan analyse --fix`

**Rejected because**: no preview, and it applies every fixable error in the project — not just ours — including PHPStan's built-in fixable rules and any extension's.

### Type-error-driven propagation

Fix every `argument.type` error where a string literal is passed to an enum-typed parameter, with no method spec, after Phase 1 is applied.

**Deferred**: broader reach (functions, constructors, any callee) and no map for backed enums, but it needs the declaration change on disk first, so the declaration and its usages can no longer be previewed and applied together (ADR-0004 atomicity).

### Replace the Rector engine

**Rejected for now**: the fixable-rule API is experimental; Rector stays the default until the PHPStan engine has proven itself.
