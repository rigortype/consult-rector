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

The interpreter flattens a chain into an ordered list of compiled rules, which the DSL Config Assembler writes into a **single** temporary rector.php (one `ruleWithConfiguration()` per rule class, grouping repeated specs). Rector applies them in one pass and re-iterates to a fixpoint, so the user is shown a single **consolidated** original→final diff — never N partial diffs — for both dry-run and apply. Same-rule sub-transforms apply their specs in order (so a dependent `A→B` then `B→C` composes); cross-rule effects compose through Rector's fixpoint iteration.

> A stricter **sandbox-sequential** mode (copy to a temp workspace, then apply each sub-transform in its own Rector run against the cumulative result) was the original design. It proved unnecessary for the current catalogue — the single-pass approach already produces the consolidated diff and composes the dependent cases above — and can be revisited if a future transform needs guaranteed step-by-step ordering across rules.

#### `chain` is a composition primitive, not a catalog leaf

`chain` does not transform code itself — it sequences other transforms — so it is intentionally absent from the built-in transform catalog. It is a structural primitive, available from Phase 1 alongside the leaf transforms.

### Transform naming

**kebab-case** throughout: `replace-param-type`, `add-import`, `add-trait-use`, etc.

### Internal architecture

Each Phase 1 transform is a **shipped, configurable Rector rule**
(`ConfigurableRectorInterface` under `src/Rector/Rule/`). The DSL does not
generate rule source — it compiles the S-expression into that rule's
*configuration*, which keeps the transform logic in real, testable classes.

```
AST DSL JSON
    ↕ JSON decode
DSL Interpreter — validates structure, resolves each transform
    ↕
Transform Resolver — maps transform name → a shipped rule + one config entry
    ↕ (plugin-based catalog)
DSL Config Assembler — temporary rector.php registering each rule via ruleWithConfiguration(Rule::class, [spec])
    ↕
Rector Runner — executes the config (same subprocess as dry-run/apply)
    ↕
Result Formatter — produces diff + change metadata
```

### Built-in transform catalog (Phase 1)

| Transform | Purpose |
|-----------|---------|
| `replace-type` | Replace a property type |
| `replace-param-type` | Change a specific method parameter type |
| `replace-return-type` | Change a method return type |
| `add-import` | Add a use statement |
| `add-trait-use` | Add a trait to a class |
| `rename-trait-method-as` | Rename trait method via `use T { ... as ...; }` |
| `change-trait-visibility-as` | Change trait method visibility via `use T { ... as private; }` |
| `migrate-arg-to-enum` | **Usage-site**: rewrite a call's literal argument to an enum case (ADR-0004 propagation) |

The first seven change a **declaration** (a class member or its `use` statements); `migrate-arg-to-enum` is the first **usage-site** transform — it rewrites call nodes across the project to propagate a declaration change (ADR-0004).

### Extension mechanism

Transform types are plugin-like: each pairs a shipped `ConfigurableRectorInterface` rule with a small `Transform` class that compiles the S-expression into that rule's configuration. The catalog can be extended without modifying the interpreter.

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
- Each new transform type adds a shipped rule plus a small Transform compiler class — but both are ordinary, unit-testable PHP, no generated rule source.
- Rector boundary means some user requests will fall through to "search for a Rector rule" instead of using DSL — this is by design.
