# consult-rector

Interactive PHP refactoring for AI agents — a conversational wrapper around
[Rector](https://github.com/rectorphp/rector) and PHP-Parser. Edits are AST
transforms, not text: the agent picks the rules, you review the diff, the CLI
executes.

One package, three faces:

- a **CLI** (`consult-rector`) that assembles temporary Rector configs, runs Rector in a subprocess, and returns structured (JSON) results;
- an **agent skill** (`SKILL.md`) that teaches an AI assistant to drive that CLI;
- an **MCP server** (`consult-rector-mcp`) exposing the same operations as `rector_*` tools over stdio.

## Requirements

- PHP **8.2+**
- [rector/rector](https://github.com/rectorphp/rector) **^2.5** (installed as a dependency)

## Installation

```bash
composer require --dev typedduck/consult-rector
```

The binaries land at `vendor/bin/consult-rector` and `vendor/bin/consult-rector-mcp`.

## Usage

The core loop is **dry-run → review the diff → apply**:

```bash
# 1. Find a rule
vendor/bin/consult-rector search closure

# 2. Propose changes (no files written) — add --json for an agent to consume
vendor/bin/consult-rector dry-run src/Order.php \
  --rules='Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector' --json

# 3. Apply once the diff looks right
vendor/bin/consult-rector apply src/Order.php \
  --rules='Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector'
```

### Commands

| Command | Purpose |
|---------|---------|
| `search <keyword>` | Search installed Rector rules by keyword |
| `dry-run <path> --rules=FQCN` | Propose changes without rewriting files |
| `apply <path> --rules=FQCN` | Rewrite files (`--verify` runs the PHPStan oracle afterwards) |
| `ast <path> '<dsl-json>'` | Apply a custom AST DSL transform (dry-run by default; `--apply`) |
| `doc index\|section <file> [N]` | Index / extract sections of large reference docs |
| `phpstan <path> [--baseline=<file>]` | Run PHPStan; report errors, or the delta against a baseline |

Add `--json` to a transforming command for machine-readable output; `--rules`
is repeatable, and `--config=rector.php` is accepted instead of `--rules`.

### AST DSL

For pinpoint transforms with no off-the-shelf Rector rule, describe the change
as a JSON-array S-expression:

```bash
vendor/bin/consult-rector ast src/Order.php '["replace-param-type",
  ["class","App\\Service\\OrderService"],["method","setStatus"],
  ["param",0],["from","string"],["to","App\\Enum\\OrderStatus"]]'
```

Type-change transforms require a `from` guard, so they fire only when the
current type matches — no accidental rewrites. The catalogue and JSON schemas
are documented in [`CONTEXT.md`](CONTEXT.md).

## As an agent skill

`SKILL.md` makes consult-rector available to compatible AI assistants: the agent
chooses rules and reviews diffs, the CLI does the AST work. For a breaking
declaration change (e.g. a stringly-typed argument → enum), the skill changes
the declaration, propagates it to call sites, then uses `apply --verify` /
`phpstan --baseline` as a completeness oracle to surface any missed sites
(see [`docs/adr/0004-*`](docs/adr/)).

## Development

Dev tooling is isolated with
[bamarni/composer-bin-plugin](https://github.com/bamarni/composer-bin-plugin)
under `.vendor-bin/`:

```bash
composer install                      # installs the project + the bin tools
composer ecs:fix                      # coding standard (ECS)
composer phpstan                      # static analysis (PHPStan max + strict-rules)
composer test                         # PHPUnit (E2E tests invoke the real binaries)
composer infection -- --threads=max   # mutation testing (weekly in CI)
```

See [`docs/adr/`](docs/adr/) for the design decisions and
[`CONTEXT.md`](CONTEXT.md) for the domain glossary.

## License

[MPL-2.0](LICENSE).
