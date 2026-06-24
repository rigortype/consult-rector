# consult-rector — Domain Glossary

## Platform

- **Runtime**: PHP 8.2+ (per [supported versions](https://www.php.net/supported-versions.php))
- **Distribution**: Composer package (`composer require --dev`)
- **CLI framework**: `symfony/console`
- **Rector dependency**: `rector/rector:^2.5` (targeting latest 2.x at time of development; not locked to patch versions)
- **Version policy**: Follows latest Rector major/minor. No 1:1 patch release mapping. CI tests against both locked (`composer.lock`) and updated (`composer update`) dependency trees

## Purpose

consult-rector is an interactive refactoring package for PHP projects. It wraps [rector/rector](https://github.com/rectorphp/rector) and PHP-Parser AST so that AI agents can perform selective, conversational code transformations — rather than editing code as raw text.

## Architecture

```
Human (user) ←→ AI Agent ←→ consult-rector skill ←→ consult-rector CLI ←→ Rector / PHP-Parser
```

- **consult-rector skill**: AI agent skill. Interprets the user's refactoring request, plans which Rector rules to apply, and delegates execution to the CLI tool.
- **consult-rector CLI**: Shell-level helper that assembles temporary Rector configs, runs Rector, invokes PHP-Parser, and returns structured results to the AI.
- **Rector / PHP-Parser**: Upstream PHP transformation libraries.

## Interaction model

- **Driver**: AI agent (initiated via skill invocation by human user)
- **Interface**: Hybrid — skill invocation via slash command and/or MCP tool
- **Responsibility distribution between Skill and CLI**:
  - **CLI**: Executes Rector, assembles config files, queries rules, returns structured output
  - **Skill / AI**: Decides which rules to apply, evaluates output, handles ambiguous cases, interacts with the user

## Revision modes

| Mode | Meaning | CLI behavior |
|------|---------|-------------|
| `dry-run` | Propose changes, no file rewriting | Rector's `--dry-run` equivalent; returns diff |
| `apply` | Actually rewrite files | Rector's full execution |

**Workflow**: Always dry-run first → AI reviews diff → human approves → apply.

## Operation types

| Type | Example | Implementation |
|------|---------|---------------|
| **Single rule** | Convert closure `function` → `fn` | One existing Rector rule |
| **Multi-rule / recipe** | PSR-4 compliance | Multiple rules in sequence |
| **Custom AST op** | Convert string arg to enum | Custom PHP-Parser transformation |

## CLI concepts

- **Rule query**: Search existing Rector rules by keyword
- **Rule set**: Selected rules with config, assembled as temporary `rector.php`
- **Recipe**: Multi-step procedure with ordered rule applications
- **Custom rule**: PHP-Parser-based transformation generated ephemerally within a session

## CLI subcommands

```
consult-rector search <keyword>                 # Rule search
consult-rector dry-run <file|dir> --rules=FQCN [...]  # Dry-run (--json for structured output)
consult-rector apply <file|dir> --rules=FQCN [...]    # Apply rewrite
consult-rector ast <file|dir> '<dsl-json>'      # Custom AST DSL transformation
consult-rector doc index <file>                  # md2idx-style section index
consult-rector doc section <file> <N>            # Extract section by number
```

- **Rule specification**: FQCN-based (`--rules=Rector\...\SomeRector`), with `--config=rector.php` fallback
- **Target files**: single file, directory, or glob
- **Output format**: human-friendly by default; `--json` flag for machine-readable output (AI consumption)
- **Diff style control**: `--diff-style=unified` (default, string) or `--diff-style=array` (structured array). Different keys per style (`diff_unified` vs `diff_array`) to keep types clean (never same key with variant types)
- **dry-run JSON schema**: file-grouped changes, each with per-file diff (unified or array) + change metadata
- **apply JSON schema**: lightweight — `files_changed`, `files_errored`, `errors[]` only (detailed diff via `git diff`)
- **Config merging**: with `--with-config=rector.php`, consult-rector asks user permission before merging project settings with temporary config

## AST DSL

Custom PHP-Parser transformations expressed as an S-expression in JSON array form:

```json
["replace-param-type",
  ["class", "App\\Service\\OrderService"],
  ["method", "setStatus"],
  ["param", 0],
  ["from", "string"],
  ["to", "App\\Enum\\OrderStatus"]]
```

- **Implementation**: JSON array S-expressions (no custom parser needed)
- **Precondition guard**: type-change transforms require the current type (`from`); the transform fires only when the existing type matches, preventing accidental rewrites
- **Internal architecture**: DSL Interpreter → Transform Resolver (plugin-based) → Rule Generator (Visitor → temporary Rector rule) → Rector Runner → Result Formatter
- **Composite transforms**: `["chain", [...], [...]]` for multi-step transformations. Sub-transforms run **sequentially in a temporary sandbox copy** (each step sees the previous step's output); the user is shown one **consolidated** original→final diff, not N partial diffs. `apply` commits the sandbox only on full success, so a chain is **atomic** (all-or-nothing). `chain` is a composition primitive, not a catalog leaf
- **Built-in transform catalog**: per-type knowledge of how to generate PHP-Parser Visitors. Plugin-like extension via traits/utilities for declarative-style visitors
- **Transform naming**: kebab-case (`replace-param-type`, `add-import`, `add-trait-use`)
- **Catalog scope**: DSL covers pinpoint transformations that no existing Rector rule handles. If a Rector rule exists for a transformation, skill/search should find it instead
- **Phase 1 transforms** (initial release):
  - `replace-type` — property/param/return type swapping
  - `replace-param-type` — specific parameter type change
  - `replace-return-type` — method return type change
  - `add-import` — add use statement
  - `add-trait-use` — add trait to class
  - `rename-trait-method-as` — rename trait method via `use T { ... as ...; }`
  - `change-trait-visibility-as` — change trait method visibility via `use T { ... as private; }`
- **Excluded from DSL** (delegate to Rector rules instead): readonly promotion, constructor promotion, class-level readonly, bulk code style changes
- **Detailed catalog to be refined during implementation**
- **Output**: same schema as dry-run/apply

## Refactoring workflow (two-phase strategy + verification)

**Phase 1 — Declarative**: Apply the main transformation (Rector rule or AST DSL)
**Phase 2 — Remediation**: Run PHPStan → detect remaining type errors → apply targeted fixes (bounded loop, see below)
**Phase 3 — Verify**: Re-run PHPStan to confirm zero errors

- **PHPStan execution**: the CLI runs PHPStan and returns the structured error *delta*; the remediation **loop is skill-orchestrated** (the CLI cannot generate fixes, only execute them). The CLI enforces a hard iteration ceiling as a safety net
- **PHPStan detection priority** (first match wins):
  1. Explicitly configured (`--phpstan-binary` option or config file)
  2. Installed alongside consult-rector (`vendor/bin/phpstan` in the same composer.json) — **excludes** the version vendored inside `vendor/rector/rector/vendor/`
  3. Target project's `vendor/bin/phpstan`
  4. Composer global installation (`~/.composer/vendor/bin/phpstan` or `~/.config/composer/vendor/bin/phpstan`)
  5. `phpstan` on PATH
  6. Not found → remediation phase skipped (warning emitted)
- **Remediation scope**: type errors only. Unused imports, code style → deferred to project's own rector.php config or ECS/php-cs-fixer
- **Remediation loop control**: targets only *new* errors (delta vs a pre-transform baseline; pre-existing project errors untouched). Stop conditions — **converged** (delta = 0) / **exhausted** (`--max-remediation-iterations`, default 3; `0` disables) / **stalled** (an iteration fails to strictly reduce the remaining count). On non-convergence, applied changes are kept and remaining errors reported — recovery via VCS, no auto-rollback. Cross-file breakage in callers is out of scope
- **Config merging**: user-permission-gated before merging project's rector.php with temporary config

## Testing strategy

- **Philosophy**: consult-rector's core is a config compiler and interactive helper. Rector execution is inherently E2E
- **Unit-testable**: Config assembly, DSL parsing, rule search, doc indexing, diff formatting
- **Integration/E2E**: Actual Rector execution with real PHP fixtures (`tests/Fixtures/input/` → `tests/Fixtures/expected/`)
- **Mocking**: Minimal. Use only when necessary for edge cases
- **Mutation testing (Infection)**: Exclude side-effect-heavy logic (IO, Rector execution). Focus on pure computation. Flexible scope based on realistic execution time
- **CI**: GitHub Actions (push + PR for main; scheduled/on-demand for Infection)

## Reference documents (shipped with package)

```
references/
├── rectors-by-category.md    # Small-Medium: category→rules mapping (readable whole)
├── recipe-book.md            # Medium: natural-language intent→rule mapping (version-controlled)
└── rectors-compendium.md     # Large: full rule details, auto-generated from Rector source (md2idx-style access)
```

- **Skill is thin**: rule selection knowledge lives in these `.md` files, not in SKILL.md
- **`doc` subcommand**: provides md2idx-style index/section extraction for large references
- **Priority**: categories → recipe book → search → LLM direct interpretation
- **recipe-book.md**: hybrid of human experience + AI-generated knowledge; version-controlled
- **rectors-compendium.md**: auto-generated from Rector source code; generation script in dev repo
