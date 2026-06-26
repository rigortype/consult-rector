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

- **Rule query**: Search existing Rector rules by keyword (multiple keywords AND-narrow: a rule must contain every keyword)
- **Rule set**: Selected rules with config, assembled as temporary `rector.php`
- **Cache root resolution** (`ContainerCache`): the writable root for caches *and* the temp config is the first usable of (1) `$CONSULT_RECTOR_CACHE_DIR`, (2) `sys_get_temp_dir()/consult-rector-cache-<uid>` (honours `TMPDIR`), (3) `$XDG_CACHE_HOME`/`$HOME/.cache/consult-rector`, (4) `<cwd>/.consult-rector-cache` (workspace last resort, self-ignored via a `*` `.gitignore`). This survives a restricted sandbox (Cursor, CI) where the system temp is unwritable — otherwise a baffling `changed_files: 0` or "could not create a temporary Rector config". Falling past the system temp is announced once on STDERR; if nothing is writable, a clear error names the candidates and the override. The Rector subprocess also runs with `TMPDIR`/`TMP`/`TEMP` pointed at this root, since Rector's parallel workers create scratch via `tmpfile()` independent of the cache dirs.
- **Cache policy**: assembled configs route *both* of Rector's caches to that per-user root — off Rector's shared default, so no foreign-owned tree can make Rector fail or skip files. (1) The **container + embedded-PHPStan cache** (`containerCacheDirectory`) sits at the root; content-addressed (SHA-256 + version), it speeds Rector's bootstrap/type-resolution. (2) The **unchanged-files skip cache** (`cacheDirectory`) gets a subdirectory keyed by the run signature (`skip-<hash of paths + rules + Rector version>`): identical re-runs reuse it (skipping files already known clean — e.g. a 826-file dry-run drops from ~38 s to ~4 s on the second pass), while any different rule set lands elsewhere so a stale skip can never suppress another run's changes. The Runner creates the root before invoking Rector (Rector fatals on a missing `containerCacheDirectory`) and surfaces Rector's `fatal_errors` instead of mapping them to `changed_files: 0`. A user-supplied `--config=rector.php` is run verbatim and keeps its own cache settings.
- **Recipe**: Multi-step procedure with ordered rule applications
- **Custom rule**: PHP-Parser-based transformation generated ephemerally within a session

## CLI subcommands

```
consult-rector search <keyword> [<keyword>...]   # Rule search (multiple keywords AND-narrow)
consult-rector dry-run <file|dir> --rules=FQCN [...]  # Dry-run (--json for structured output)
consult-rector apply <file|dir> --rules=FQCN [...]    # Apply rewrite
consult-rector ast <file|dir> '<dsl-json>' [--apply]  # Custom AST DSL transformation (dry-run by default)
consult-rector doc index <file>                  # md2idx-style section index
consult-rector doc section <file> <N>            # Extract section by number
```

- **Rule specification**: FQCN-based (`--rules=Rector\...\SomeRector`), with `--config=rector.php` fallback
- **Target files**: single file, directory, or glob
- **Output format**: human-friendly by default; `--json` flag for machine-readable output (AI consumption)
- **Diff style control**: `--diff-style=unified` (default, string) or `--diff-style=array` (structured array). Different keys per style (`diff_unified` vs `diff_array`) to keep types clean (never same key with variant types)
- **dry-run JSON schema**: `{mode, totals:{changed_files, errors}, files:[{file, applied_rules, diff_unified|diff_array}], errors}`. `diff_array` is a list of hunks `{from_start, from_count, to_start, to_count, lines:[{type:context|add|remove, text}]}`
- **apply JSON schema**: lightweight — `{mode, files_changed, files_errored, errors}` only (detailed diff via `git diff`)
- **Config merging** (deferred, not in v1): merging the project's `rector.php` *into* the assembled config is intentionally out of scope. Use `--config=rector.php` to run a project config verbatim instead.

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
- **Internal architecture**: DSL Interpreter → Transform Resolver (plugin-based) → DSL Config Assembler (`ruleWithConfiguration` of shipped rules) → Rector Runner → Result Formatter
- **Composite transforms**: `["chain", [...], [...]]` flattens to a **single** temporary rector.php (one `ruleWithConfiguration` per rule); Rector applies them in one fixpoint pass, yielding one **consolidated** original→final diff (not N partial diffs). `chain` is a composition primitive, not a catalog leaf
- **Built-in transform catalog**: each transform is a shipped `ConfigurableRectorInterface` rule plus a small compiler that maps the S-expression to its configuration
- **Transform naming**: kebab-case (`replace-param-type`, `add-import`, `add-trait-use`)
- **Catalog scope**: DSL covers pinpoint transformations that no existing Rector rule handles. If a Rector rule exists for a transformation, skill/search should find it instead
- **Phase 1 transforms** (initial release):
  - `replace-type` — Replace a property type
  - `replace-param-type` — specific parameter type change
  - `replace-return-type` — method return type change
  - `add-import` — add use statement
  - `add-trait-use` — add trait to class
  - `rename-trait-method-as` — rename trait method via `use T { ... as ...; }`
  - `change-trait-visibility-as` — change trait method visibility via `use T { ... as private; }`
  - `migrate-arg-to-enum` — usage-site: rewrite a call's literal argument to an enum case (ADR-0004 propagation)
- **Excluded from DSL** (delegate to Rector rules instead): readonly promotion, constructor promotion, class-level readonly, bulk code style changes
- **Detailed catalog to be refined during implementation**
- **Output**: same schema as dry-run/apply

## Refactoring workflow (declarative change + usage-site propagation)

For an intentional breaking change to a declaration whose new shape must reach every usage (e.g. a stringly-typed param → enum), see ADR-0004.

**Phase 1 — Declarative change**: change the declaration with a declaration DSL transform (e.g. `replace-param-type` `string` → enum)
**Phase 2 — Propagation**: rewrite the usage sites with a usage-site transform (e.g. `migrate-arg-to-enum`)
**Phase 3 — Verify**: PHPStan reports zero new errors on the affected set ⇒ migration complete (for everything the type system can see)

- **Hybrid discovery**: the AST pass does the literal→case rewrites — it alone knows *which* case a literal becomes (PHPStan only says "string given"); PHPStan is the **completeness oracle** whose delta enumerates/verifies and flags non-type-checkable sites (dynamic/untyped) for manual handling
- **PHPStan execution**: the CLI runs PHPStan and returns the structured error *delta* against a pre-change baseline; the loop is **skill-orchestrated** (the CLI cannot generate fixes, only execute them)
- **PHPStan detection priority** (first match wins):
  1. Explicitly configured (`--phpstan-binary` option or config file)
  2. Installed alongside consult-rector (`vendor/bin/phpstan` in the same composer.json) — **excludes** the version vendored inside `vendor/rector/rector/vendor/`
  3. Target project's `vendor/bin/phpstan`
  4. Composer global installation (`~/.composer/vendor/bin/phpstan` or `~/.config/composer/vendor/bin/phpstan`)
  5. `phpstan` on PATH
  6. Not found → verification skipped (warning emitted; rewrites still apply)
- **Scope**: project-wide (usage sites span the codebase); the declaration + usages apply atomically via `chain`
- **Config merging**: deferred (not in v1) — see "Config merging" under CLI concepts

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
