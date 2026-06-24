# ADR-0005: AST DSL — JSON S-expression format for custom transformations

## Status

Proposed.

## Context

Not all refactoring operations map to existing Rector rules. Pinpoint transformations — changing a single method's argument type, adding a specific trait to a class — are too narrow for Rector's rule granularity.

We need a transformation language that:
- Is easy for an AI to generate (no custom parser, no grammar)
- Supports composition (multi-step transformations)
- Compiles into PHP-Parser Visitors → temporary Rector rules

## Decision

### Format: JSON array S-expressions

Transformations are expressed as nested JSON arrays, where the first element is the transform name (kebab-case) and subsequent elements are `[key, value]` pairs.

```json
["replace-param-type",
  ["class", "App\\Service\\OrderService"],
  ["method", "setStatus"],
  ["param", 0],
  ["from", "string"],
  ["to", "App\\Enum\\OrderStatus"]]
```

For type-change transforms (`replace-type`, `replace-param-type`, `replace-return-type`), the current type (`from`) is **required**. It acts as a precondition guard: the generated Rector rule fires only when the existing type matches `from`, so an unexpected current type is left untouched rather than silently rewritten.

### Composition via chain

Multiple transformations are composed with the `chain` transform:

```json
["chain",
  ["replace-param-type", ...],
  ["add-import", ["class", "App\\Enum\\OrderStatus"]]]
```

Each sub-transform is compiled into a separate temporary Rector rule file. They are **not** run independently against the original code — that would make a dependent chain incoherent: a later step would not see an earlier step's output, and two steps touching the same lines would produce non-composable diffs.

#### Sandbox-sequential execution

A chain runs the same way for both dry-run and apply:

1. Copy the target files into a temporary **sandbox**.
2. Apply each sub-transform **in order, in real apply mode, against the sandbox** — so step *n* sees the cumulative result of steps `1..n-1`.
3. Compute one **consolidated diff** between the original files and the final sandbox state.

- **dry-run** stops there: it returns the consolidated diff and discards the sandbox. The user sees a single original→final diff per file (the same dry-run JSON schema as a single transform), never N partial diffs.
- **apply** commits the sandbox back to the real files **only after the whole chain succeeds**.

A single (non-chain) transform needs no sandbox — it compiles to one temporary Rector rule and uses Rector's native dry-run/apply directly. The sandbox exists specifically to make *dependent* chains coherent.

#### Atomicity

Because real files are written only on full success, a chain is **all-or-nothing**: if sub-transform *n* fails, the real files are left untouched and the failing step is reported (`files_errored`). There is no half-applied chain.

#### `chain` is a composition primitive, not a catalog leaf

`chain` does not transform code itself — it sequences other transforms — so it is intentionally absent from the built-in transform catalog. It is a structural primitive, available from Phase 1 alongside the leaf transforms.

### Transform naming

**kebab-case** throughout: `replace-param-type`, `add-import`, `add-trait-use`, etc.

### Internal architecture

```
AST DSL JSON
    ↕ JSON decode
DSL Interpreter — validates structure, resolves transform type
    ↕
Transform Resolver — maps transform name → PHP-Parser Visitor generator
    ↕ (plugin-based catalog)
Rule Generator — wraps Visitor as a temporary Rector rule
    ↕
Rector Runner — executes the rule (same as dry-run/apply pipeline)
    ↕
Result Formatter — produces diff + change metadata
```

### Built-in transform catalog (Phase 1)

| Transform | Purpose |
|-----------|---------|
| `replace-type` | Replace property/param/return type |
| `replace-param-type` | Change a specific method parameter type |
| `replace-return-type` | Change a method return type |
| `add-import` | Add a use statement |
| `add-trait-use` | Add a trait to a class |
| `rename-trait-method-as` | Rename trait method via `use T { ... as ...; }` |
| `change-trait-visibility-as` | Change trait method visibility via `use T { ... as private; }` |

### Extension mechanism

Transform types are plugin-like: each has a class that knows how to generate a PHP-Parser Visitor. A trait/utility layer reduces boilerplate for common visitor patterns. The catalog can be extended without modifying the interpreter.

### Boundary with Rector rules

AST DSL covers only operations that no existing Rector rule handles. If a Rector rule exists for a transformation, the skill should find and use the rule instead. The built-in catalog explicitly excludes:
- Readonly property/class promotion (Rector has `ReadOnlyPropertyRector`, `ReadOnlyClassRector`)
- Constructor property promotion (`PromotionRector`)
- Bulk code style changes

## Considered Options

### Custom Lisp-like DSL with a parser

A dedicated transformation language with its own grammar.

**Rejected because**: Requires a parser, an additional skill for the AI to learn, and generates more tokens. JSON is universally understood by LLMs and needs no parser.

### Raw PHP-Parser Visitor code as the transformation language

The AI writes PHP code that implements a PHP-Parser NodeVisitor.

**Rejected because**: Requires the AI to write PHP, which is error-prone, verbose, and harder to validate. The Visitor API surface is large and changes across PHP-Parser versions.

## Consequences

- JSON array S-expressions are trivially parseable (no parser dependency, just `json_decode`).
- The plugin catalog keeps the core interpreter stable while transforms grow independently.
- Each new transform type requires a new class in the catalog — but the trait/utility layer reduces per-transform cost.
- Rector boundary means some user requests will fall through to "search for a Rector rule" instead of using DSL — this is by design.
