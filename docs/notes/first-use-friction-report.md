# First-use friction report (anonymized)

A record of the first real-world trial of consult-rector, kept to explain what the
cache-robustness work was responding to. All project-specific details (names, paths,
classes, tooling, host) are omitted; only the shape and scale of the task remain.

## Context

A first-time user drove consult-rector through a **sandboxed AI coding agent**
(Cursor) to apply a single existing Rector rule —
`Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector` — converting anonymous
`function () { return …; }` closures to arrow functions (`fn () => …`) in one large
file.

**Scale of the task:**

- one ~780-line dependency-injection configuration file
- ~120 single-return closure factory definitions in it
- result: 120 conversions, file shrank to ~510 lines (diff: +304 / −582)
- a mid-size production PHP project

Illustrative shape (fully genericized):

```php
// before
SomePort::class => function () {
    return new SomeAdapter(/* … */);
},
// after
SomePort::class => fn () => new SomeAdapter(/* … */),
```

## What went smoothly

- The documented workflow (search → dry-run → review → apply) mapped cleanly onto
  the task.
- One existing Rector rule covered the whole job; no custom AST DSL was needed.
- `--json` gave machine-readable totals and diffs the agent could act on.

## Friction, and the round-trips it cost

1. **Multi-keyword search was rejected.** Searching with two keywords failed with
   "Too many arguments"; only a single keyword was accepted. One wasted retry, plus
   uncertainty about how to search.

2. **A silent `changed_files: 0`** — the expensive one. dry-run reported zero
   changes despite ~120 obviously-convertible closures. Nothing pointed at the
   cause, so the agent spent roughly **fifteen steps** ruling things out: re-running
   with and without flags and verbose output, grepping the skill files, reading
   consult-rector's own source, building a minimal reproduction file, and finally
   invoking the underlying Rector binary directly. Only that last step surfaced the
   real error — `mkdir(): Permission denied` for the cache directory under the
   system temp dir. The sandbox forbade writes to the system `/tmp`. The user
   recovered by pointing the temp dir at a workspace-local writable directory.

3. **Post-apply style mismatch.** Rector emitted `fn()` while the project's coding
   standard wanted `fn ()`; the project's own formatter also rejected a single-file
   argument, so a whole-project format pass was needed to normalize the output.

## Root cause

The costly issue (#2) was **environmental, not a rule mismatch**: in a restricted
sandbox the system temp dir is unwritable, and the tool both (a) mapped that failure
to a clean `changed_files: 0` and (b) emitted no actionable signal — forcing the
user to debug by reading source and running Rector directly. An environmental
failure that looks identical to "your rule matched nothing" is the worst possible
outcome for a first-time user.

## What it drove

This trial motivated the cache-handling work recorded in
[`cache-strategy-disk-vs-memory.md`](cache-strategy-disk-vs-memory.md):

- **Multi-keyword rule search** (AND-narrow), removing friction #1.
- **No more silent zero:** Rector's `fatal_errors` are surfaced instead of being
  mapped to `changed_files: 0`.
- **A writable cache root with fallback:** caches and the temp config resolve to the
  first writable of an explicit override, the system temp, the user cache dir, and a
  self-ignored workspace directory — so a restricted sandbox just works, with a
  one-time notice instead of a silent failure.
- **Redirecting the Rector subprocess's `TMPDIR`,** because Rector's parallel
  workers create scratch via `tmpfile()` independent of the cache directories.

## Takeaways

- An environmental failure must never be indistinguishable from a clean,
  successful-but-empty result.
- For an AI-agent-first tool, "works out of the box inside a restricted sandbox" is a
  core requirement, not an edge case — the agent cannot be expected to discover
  `TMPDIR` by reading the tool's source.
- Post-transform output should be expected to need the project's own formatter; the
  workflow docs say so explicitly.
