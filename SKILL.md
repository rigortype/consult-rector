# /consult-rector

Transform and refactor PHP code via Rector rules or custom AST transformations.

## When to use

Use when the user requests code transformations on PHP files:

- **Specific transformations**: "convert this closure to `fn`", "add type declarations to this property", "change this argument type to an enum"
- **Rule-based refactoring**: "organize for PSR-4 compliance", "upgrade to PHP 8.0"
- **Vague requests**: "improve code quality here", "modernize this class"

For vague requests, ask the user to clarify intent before proceeding. For version upgrades, prefer Rector's UPGRADE rule sets.

## When NOT to use

- Editing non-PHP files
- Generating new PHP code (creating classes from scratch, no transformation involved)
- Pure code review or syntax questions (no transformation)
- Frontend (JS/TS/CSS), config files (YAML/JSON/XML), or other non-PHP code

## Workflow

```
1. Parse the user's request
   ↓
2. Choose an approach:
   a. Existing Rector rule exists → search for it
   b. Multiple rules needed     → consult recipe-book.md
   c. Custom transformation     → assemble AST DSL
   ↓
3. Run dry-run → AI reviews the diff
   ↓
4. Present changes to user for approval
   ↓
5. Run apply
   ↓
6. (Optional) Run PHPStan → fix remaining errors → re-verify
```

## Rule selection priority

1. **Category**: Identify Rector category from the request → `references/rectors-by-category.md`
2. **Recipe book**: Intent-to-rule mapping → `references/recipe-book.md`
3. **Search**: `consult-rector search <keyword>` via CLI
4. **LLM judgment**: If nothing found above, consult `references/rectors-compendium.md` via `doc section` and decide directly

## CLI reference

```bash
# Search existing rules
consult-rector search <keyword>

# Dry-run (propose changes)
consult-rector dry-run <file> --rules=FQCN [...--rules=FQCN2]

# Apply changes
consult-rector apply <file> --rules=FQCN

# Custom AST transformation (dry-run by default; add --apply to rewrite)
consult-rector ast <file> '<dsl-json>'

# Read sections from reference docs
consult-rector doc index references/rectors-compendium.md
consult-rector doc section references/rectors-compendium.md <N>
```

Append `--json` for machine-readable JSON output.
Use `--diff-style=array` for structured diff output.

## AST DSL format

Transformations are expressed as JSON array S-expressions. No custom parser needed — easy for AI to generate.

```json
["replace-param-type",
  ["class", "App\\Service\\OrderService"],
  ["method", "setStatus"],
  ["param", 0],
  ["from", "string"],
  ["to", "App\\Enum\\OrderStatus"]]
```

`from` (the current type) is **required** on type-change transforms — it acts as a precondition guard, so the transform fires only when the existing type matches.

Chain multiple transformations:

```json
["chain",
  ["replace-param-type", ...],
  ["add-import",
    ["class", "App\\Enum\\OrderStatus"]]]
```

**Built-in transforms** (pinpoint operations that no Rector rule covers):

| Transform | Purpose |
|-----------|---------|
| `replace-type` | Replace a property type |
| `replace-param-type` | Change a specific method parameter type |
| `replace-return-type` | Change a method return type |
| `add-import` | Add a use statement |
| `add-trait-use` | Add a trait to a class |
| `rename-trait-method-as` | Rename trait method via `use T { ... as ...; }` |
| `change-trait-visibility-as` | Change trait method visibility via `use T { ... as private; }` |
| `migrate-arg-to-enum` | Usage-site: rewrite a call's literal argument to an enum case (ADR-0004) |

For transformations covered by existing Rector rules (readonly promotion, constructor promotion, bulk changes), search for and use the Rector rule instead.

## Reference documents

- `references/rectors-by-category.md` — Category-to-rules mapping (small, readable whole)
- `references/recipe-book.md` — Intent-to-rule mapping (version-controlled hybrid of human + AI knowledge)
- `references/rectors-compendium.md` — Full rule details (large file — read via `doc section`)
