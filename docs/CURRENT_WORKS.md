# Current works — session handoff

Last updated: 2026-06-25. This is a working handoff for the next session, which starts cold. Read `CONTEXT.md` (glossary), `docs/adr/` (decisions), and this file first.

## TL;DR

consult-rector went from design-docs-only to a working tool this session (27 commits). All planned commands and the Phase-1 AST DSL catalogue are implemented and tested; every commit kept the gates green.

- **6 CLI commands**: `search`, `dry-run`, `apply`, `ast`, `doc`, `phpstan`.
- **8 AST DSL transforms** (7 declaration + 1 usage-site — see below).
- **MCP server** boots; `tools/list` advertises 7 `rector_*` tools.
- **Gates**: PHPStan max + bleedingEdge + strict-rules, ECS, strict PHPUnit — **74 tests, 691 assertions, all green**.
- **SKILL.md** brushed up to waza/agentskills compliance (High, 9/9 spec, 497/500 tokens, quality 4.6/5).

## How to work (verify any change)

```bash
composer ecs:fix && composer phpstan && composer test   # the three gates — keep all green
php tools/generate-references.php                        # regenerate references/ after a Rector bump
```

Tools live in isolated bamarni bin namespaces under `.vendor-bin/` (run `composer bin all install` if missing). The E2E tests really invoke the Rector and PHPStan binaries (~1–2s each), which is expected.

## What's implemented

### Commands (`src/Command/`, registered in `src/Console/Application.php`)
- `search <keyword>` — `Rector\RuleCatalog` scans the installed rule classes (`rules/` dir) since `rector list-rules` only reports *configured* rules.
- `dry-run` / `apply <path> --rules=FQCN` — `Rector\Runner` (real Rector via `proc_open`, `--output-format=json`). `--config=rector.php` is wired; `apply --verify` runs the PHPStan oracle. Shared input handling in `AbstractRectorCommand`.
- `ast <path> '<dsl-json>'` — the DSL pipeline; dry-run by default, `--apply`, `--apply --verify`.
- `doc index|section <file> [N]` — `Doc\DocumentIndex`, md2idx-style access to large reference docs.
- `phpstan <path> [--baseline=<file>]` — the completeness-oracle primitive (`PhpStan\PhpStanRunner`).

### AST DSL (`src/Dsl/`, `src/Rector/Rule/`)
`Interpreter` → `TransformResolver` (catalogue) → each transform compiles to a shipped `ConfigurableRectorInterface` rule's *configuration* → `Rector\DslConfigAssembler` emits a temp rector.php (`ruleWithConfiguration`) → `Runner`.

Transforms: `replace-param-type`, `replace-return-type`, `replace-type` (property), `add-import`, `add-trait-use`, `rename-trait-method-as`, `change-trait-visibility-as` (all *declaration* transforms), plus `migrate-arg-to-enum` (the first *usage-site* transform). Type-change transforms require a `from` precondition guard. Shared helpers: `TypeNodeFactory`, `TraitUseAdaptationManipulator`.

### ADR-0004 propagation workflow (fully implemented)
ADR-0004 was **reframed**: not accidental-error remediation but propagating an intentional breaking declaration change to its usage sites.
1. Phase 1 — change the declaration (`replace-param-type` etc.).
2. Phase 2 — propagate (`migrate-arg-to-enum` rewrites call literal args to enum cases; AST is the doer — it alone knows which case a literal becomes).
3. Phase 3 — completeness oracle: `PhpStan\{PhpStanRunner, PhpStanResult, Verifier}`. `PhpStanResult::newErrorsSince` is the line-independent delta. Surfaced three ways: the `phpstan` subcommand (`--baseline`), the `--verify` flag on apply/ast, and the `rector_phpstan` MCP tool.

### MCP server (`bin/consult-rector-mcp`, `src/Mcp/RectorTools.php`)
Registered **per method** via `addTool([RectorTools::class, $method], $name, $desc)` from `RectorTools::definitions()`.

### references/ auto-generation (`src/Reference/`, `tools/generate-references.php`)
`RuleIntrospector` (category + description) + `ReferenceGenerator` produce `rectors-by-category.md` (488 rules grouped) and `rectors-compendium.md` (one `doc`-accessible section per rule). `recipe-book.md` stays hand-curated.

## Gotchas learned this session (read before touching these areas)

- **mcp/sdk v0.6**: the manual `addTool()` path ignores `#[McpTool]` attributes and takes the name/description from the call; passing a class-string treats it as an invokable (`__invoke`) and **crashes on boot**. Register per method.
- **Rector's `Symplify\RuleDocGenerator\Contract\*`** (e.g. `DocumentedRuleInterface`, `CodeSampleInterface`) is **not autoloadable outside Rector's own process** — phpstan can't resolve it and runtime `instanceof` is false. `RuleIntrospector` extracts the description duck-typed (`method_exists` + call), not via the interface.
- **PHPStan in tests must be isolated**: running phpstan from the repo root picks up consult-rector's own `phpstan.neon.dist`. Tests pass `--configuration=<temp neon>` to `PhpStanRunner::analyse`.
- **bamarni**: `target-directory: .vendor-bin` is required (default is `vendor-bin`); `bin-links: false` avoids colliding with the phpstan that rector pulls into root `vendor/bin`. Scripts call tools by their `.vendor-bin/...` path.
- **Composer package name is `mcp/sdk`** (`modelcontextprotocol/php-sdk` is only the GitHub repo). PHP-Parser is at `vendor/rector/rector/vendor/nikic/php-parser` (v5: `UseItem` not `UseUse`; `String_` under `Scalar`).
- **`chain`** runs as one multi-rule Rector fixpoint pass (not the ADR's original sandbox-sequential) — it already yields the consolidated diff; sandbox-sequential is a deferred note in ADR-0005.
- PHPStan max + strict-rules: never suppress (`@var`/assert/cast/`@phpstan-ignore`/baseline). Narrow `mixed` from JSON with `is_*` checks (see any rule's `configure()`).

## What remains / next steps

- **`--with-config` merge** — deferred **by decision** (the user judged re-running the rector command by hand is fine for v1). Intentionally omitted, not unbuilt.
- **eval suite for the skill** — **done.** `evals/consult-rector/` holds a waza trigger suite: 5 positive tasks (one per USE-FOR clause: closure→fn, type decls, arg→enum + propagate, version upgrade, PSR-4) and 5 negative tasks (one per DO-NOT clause + a boundary guard against the sibling `phpstan-error-reduction` skill). Modeled on the user's own phpstan/acp evals: realistic JP/EN prompts + `expected.should_trigger`, eval-level `token-budget` behavior grader, executor `gpt-5-mini`. `waza check` now reports `eval.found: true` and `schema.valid: true`. Run with `waza run consult-rector` (needs the copilot-sdk executor / model access). Note: `waza check`'s overall `ready` stays false only because its link scanner walks `vendor/`/`.vendor-bin/` (gitignored) third-party changelogs — repo-wide noise, unrelated to the skill.
- **`recipe-book.md`** — still the hand-curated placeholder; needs initial intent→rule content.
- **Release prep** — README, CI workflows, and `composer.json` `description`/keywords are set but not yet validated against a real `composer install` from a clean checkout.
  - CI status: `.github/workflows/eval.yml` exists as a **non-blocking draft** (waza trigger eval, manual + weekly, advisory). **Two things must be wired before it runs green**: (1) how the `microsoft/waza` azd extension installs/pins on the runner; (2) a **model credential as a repo secret** for the `copilot-sdk` engine — the generated template ships no auth step, so this is the gap. Note: this repo keeps `SKILL.md` at root, so waza discovery needs `--path .` (default scans `skills/` only); `.waza.yaml` declares `paths.skills: .` (honored by `run`, but `coverage` still needs `--path .`).
  - Still unwritten: ADR-0006's **main CI** (phpstan / ecs / phpunit, dual `composer.lock` + `composer update`) and the **weekly Infection** workflow.
- **More usage-site transforms** — `migrate-arg-to-enum` is the only one; siblings (e.g. return-value or property-assignment migration) could follow the same shape. Isomorphic transforms can be delegated to subagents off an existing reference (this was done for the type-family and trait-alias pairs).

## Conventions
- PSR-4 `TypedDuck\ConsultRector\`. Tests mirror `src/` under `tests/`; E2E uses temp workspaces + real binaries.
- Commit per logical unit; keep the three gates green every commit. ADRs are the source of truth — reconcile docs when behaviour diverges.
- Persistent project memory: `~/.claude/projects/-Users-megurine-repo-php-consult-rector/memory/implementation-status.md`.
