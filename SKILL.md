---
name: consult-rector
description: |
  USE FOR: refactoring PHP through Rector rules or custom AST ops — convert a closure to `fn`, add or change type declarations, migrate a stringly-typed argument to an enum and propagate to call sites, apply PSR-4 or version upgrades.
  DO NOT USE FOR: non-PHP files; generating new PHP from scratch; code review or syntax questions; frontend or config files.
---

# consult-rector

Interactive PHP refactoring wrapping Rector and PHP-Parser: edits are AST transforms, not text. **UTILITY SKILL** — you pick the rules and review the diff; the `consult-rector` CLI executes.

## Workflow

1. Pick an approach: an existing Rector rule (search), a multi-rule recipe, or a custom AST DSL op.
2. `dry-run` → review the diff → user approves → `apply` → run the project's own formatter/coding-standard fixer (Rector's output style may differ from the project's, e.g. `fn()` vs `fn ()`).
3. For a breaking change, propagate to call sites then `--verify` (PHPStan flags missed sites; see `docs/adr/0004-*`).

Clarify vague requests before acting; prefer Rector UPGRADE sets for version bumps.

## Rule selection (priority order)

1. `references/rectors-by-category.md`
2. `references/recipe-book.md`
3. `consult-rector search <keyword>`
4. `consult-rector doc section references/rectors-compendium.md <N>`

## Commands

`search <keyword…>` (space-separated keywords AND-narrow) · `dry-run`/`apply <path> --rules=FQCN` · `ast <path> '<dsl-json>'` (`--apply`, `--verify`) · `doc index|section` · `phpstan [--baseline]`. Add `--json` for machine output; full reference in `CONTEXT.md`.

## Example

```bash
consult-rector dry-run src/Order.php --rules=Rector\...\ClosureToArrowFunctionRector --json
```

## Troubleshooting

No rule fits → write a custom `ast` op. Over-eager Rector → narrow the rule set, re-`dry-run`. Type errors after a breaking change → `apply --verify` lists the unmigrated sites. Unexpected `changed_files: 0` → suspect rule/path mismatch, not the environment: the CLI isolates Rector's caches in a per-user, run-signature-keyed directory, so no shared/foreign `rector_cached_files` tree can swallow changes or skip files as "unchanged". Restricted sandbox (unwritable system temp) → the CLI auto-falls back to a writable location (down to `./.consult-rector-cache`, self-ignored) and notes it on STDERR; override with `CONSULT_RECTOR_CACHE_DIR=<writable dir>` if needed.

## References

`references/` (large files via `doc`); `CONTEXT.md` for CLI, AST DSL, and JSON schemas; ADRs in `docs/adr/`.
