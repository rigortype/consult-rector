# consult-rector

**Refactor PHP by telling your AI agent what you want changed — it picks the
Rector rule (or composes a custom AST transform), shows you the diff, and applies
it only once you approve.** consult-rector is an
[Agent Skill](https://agentskills.io/) plus the CLI it drives. Edits are AST
transforms, never text munging.

## Talk to your agent, not to a config file

You do not hand-write `rector.php` or memorise rule FQCNs. You say what you want,
in plain language, to a coding agent that has the skill (Claude Code, Cursor, …):

> Convert the one-line closures in `src/` to arrow functions.

The skill takes it from there: it searches the installed Rector rules, runs a
**dry-run**, shows you the diff, and — only after you approve — **applies** it.
Every step is the `consult-rector` CLI returning structured JSON the agent reads;
you stay in the review-and-approve seat.

**It works in your language** — the request is just natural language:

> `src/` の中の、1 行で値を返すクロージャを全部アロー関数にして。

## The flagship: a breaking change, propagated and verified

Changing a declaration and chasing every call site by hand is where refactors go
wrong. Describe the intent instead:

> `Order::setStatus(string $status)` を `Status` enum を受け取るように変えて、
> `'paid'` や `'shipped'` を渡している呼び出し側も対応する enum ケースに直して。

The skill (1) changes the declaration, (2) rewrites the call-site literals to enum
cases — the AST pass alone knows *which* case each literal becomes — and (3) runs
**PHPStan as a completeness oracle**: the error delta against a pre-change
baseline enumerates any site it could not reach, so nothing is silently missed
(see [`docs/adr/0004-*`](docs/adr/)).

## Install

consult-rector is a Composer package; the agent drives its CLI, so install it in
the project you want to refactor:

```sh
composer require --dev typedduck/consult-rector
```

That brings the `consult-rector` binary, the skill instructions (`SKILL.md`), the
MCP server, and the bundled rule references — a skill-aware agent discovers the
skill from there. To drop the skill straight into any agent's skill directory,
use [vercel-labs/skills](https://github.com/vercel-labs/skills):

```sh
npx skills add rigortype/consult-rector
```

**Requirements:** PHP 8.2+ and
[rector/rector](https://github.com/rectorphp/rector) `^2.5` (installed as a
dependency).

## What you can ask for

- Convert closures to arrow functions — or apply any single shipped Rector rule.
- Add or change type declarations (parameter, return, property).
- Migrate a stringly-typed argument to an enum and propagate it to call sites,
  verified by PHPStan (the flagship above).
- Apply PSR-4 namespace fixes, or a PHP version upgrade via a Rector upgrade set.
- A pinpoint **custom AST transform** when no off-the-shelf rule fits, written as
  a JSON-array S-expression — type-change transforms carry a `from` guard so they
  fire only when the current type matches, never by accident.

The agent finds the right rule through `references/rectors-by-category.md`, a
recipe book, or `consult-rector search`; you only describe the goal.

## Under the hood — the CLI the agent drives

You rarely type these yourself; the skill does. They exist so the loop stays
reviewable and scriptable:

| Command | What the agent uses it for |
|---------|----------------------------|
| `search <keyword>` | find an installed Rector rule |
| `dry-run <path> --rules=FQCN` | propose changes (JSON) without writing files |
| `apply <path> --rules=FQCN` | rewrite files (`--verify` runs the PHPStan oracle) |
| `ast <path> '<dsl-json>'` | a custom AST transform (dry-run by default; `--apply`) |
| `doc index\|section <file>` | page through the large rule references |
| `phpstan <path> [--baseline=…]` | the completeness oracle: all errors, or the delta |

An **MCP server** (`consult-rector-mcp`) exposes the same operations as `rector_*`
tools over stdio for MCP-native clients.

## Development

[bamarni/composer-bin-plugin](https://github.com/bamarni/composer-bin-plugin)
isolates the dev tooling under `.vendor-bin/`:

```sh
composer install                      # project + bin tools
composer ecs:fix                      # coding standard (ECS)
composer phpstan                      # static analysis (max + bleedingEdge + strict-rules)
composer test                         # PHPUnit — E2E tests invoke the real Rector/PHPStan binaries
composer infection -- --threads=max   # mutation testing (weekly in CI)
```

Design decisions live in [`docs/adr/`](docs/adr/); the domain glossary is
[`CONTEXT.md`](CONTEXT.md).

## License

[MPL-2.0](LICENSE).
