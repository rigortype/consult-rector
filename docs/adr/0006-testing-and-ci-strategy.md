# ADR-0006: Testing and CI strategy

## Status

Proposed.

## Context

consult-rector bridges three layers: an AI skill, a PHP CLI tool, and upstream Rector/PHP-Parser. Each layer requires different testing approaches. The project inherits patterns from [satiate](https://github.com/zonuexe/satiate), which uses `bamarni/composer-bin-plugin` for tool isolation and Infection for mutation testing.

## Decision

### Tool isolation

All development tools (PHPStan, PHPUnit, Infection, ECS) are installed via `bamarni/composer-bin-plugin` into `.vendor-bin/`, separate from the root `vendor/`. The root `composer.json` only declares `bamarni/composer-bin-plugin` as a dev dependency, with `forward-command: true` for single-command installation.

### PHPUnit

- **Framework**: PHPUnit (13.x, via `.vendor-bin/phpunit`)
- **Config**: Strict mode (`failOnRisky`, `failOnWarning`, `requireCoverageMetadata`, `beStrictAboutCoverageMetadata`)
- **Structure**: Tests mirror `src/` layout under `tests/`
- **Fixtures**: Test PHP files in `tests/*/Fixtures/input/` with expected results in `tests/*/Fixtures/expected/`

### Test scope

| Layer | Approach | Tooling |
|-------|----------|---------|
| Config assembly, DSL parsing, rule search, doc indexing, diff formatting | Unit tests | PHPUnit |
| Rector execution (dry-run/apply with real fixtures) | Integration / E2E | PHPUnit with real Rector process |
| MCP server, console commands | Integration | PHPUnit with process execution |
| Skill triggering, AI interaction | Not tested via PHPUnit (skill-level QA) | Manual / agent-level |

**Mocking**: Minimal. Use only when necessary for edge cases. Rector execution is inherently E2E — mocking it would defeat the purpose.

### Mutation testing (Infection)

- **Tool**: Infection (via `.vendor-bin/infection`)
- **Source scope**: `src/` excluding side-effect-heavy logic (IO, Rector process invocation)
- **MSI gates**: `minMsi: 90`, `minCoveredMsi: 95` (following satiate convention)
- **Config**: Relaxed PHPUnit config for Infection coverage runs (separate from the strict CI config)
- **Schedule**: Weekly scheduled run + manual dispatch (not blocking PR merges)

### Coding standards

- **Tool**: EasyCodingStandard (ECS) via `.vendor-bin/easycs`
- **Config**: Prepared sets (spaces, namespaces, docblocks, arrays, comments) + custom rules (ordered imports, no unused imports)
- **Scope**: `src/`, `tests/`, `bin/`

### Static analysis

- **Tool**: PHPStan via `.vendor-bin/phpstan`
- **Level**: `max` (strictest)
- **Config**: Bleeding edge rules, PHP version matching target
- **Scope**: `src/` + `tests/`

### CI (GitHub Actions)

**Workflow 1 — CI** (push to main + PR):
- PHPStan analysis
- ECS check
- PHPUnit tests (against locked `composer.lock`)
- PHPUnit tests (after `composer update` to test dependency freshness)
- E2E dogfood test (if applicable)

**Workflow 2 — Infection** (scheduled weekly + manual dispatch):
- Mutation testing with pcov coverage driver

**Infrastructure**: Reusable PHP setup action (PHP 8.2+ setup + Composer cache), Dependabot for dependency updates.

### Version policy

- **Rector**: `^2.5` (major.minor tracking; no 1:1 patch-level mapping)
- **CI**: Tests run against both locked (composer.lock) and updated (composer update) dependency trees
- **consult-rector releases**: Not tied to Rector patch releases. Released when meaningful changes accumulate

## Considered Options

### Install tools in root vendor/

Simpler setup, single `composer install`.

**Rejected because**: Tool version conflicts with the target project's dependencies. `bamarni/composer-bin-plugin` isolation avoids this entirely.

### Block PRs on mutation score

Infection gates in CI as a blocking check.

**Rejected because**: Mutation testing is computationally expensive. A weekly schedule + manual trigger catches regressions without blocking development velocity.

## Consequences

- `.vendor-bin/` adds directory overhead but guarantees tool version isolation.
- E2E tests with real Rector are slow but reliable — they test what actually ships.
- The dual `composer.lock` / `composer update` CI strategy catches both locked-environment and fresh-install regressions.
- Infection's `minMsi: 90` gate is ambitious for a project with file-IO-heavy layers — may need scope adjustment during implementation.
