---
name: consult-rector-release-prep
description: Prepare a consult-rector release — bump the VERSION constant, seal the changelog, run the gates and packaging checks, then tag and publish to Packagist. Use when the user asks to prepare the next version, cut a release, refresh release metadata, or make versioned files consistent before tagging.
metadata:
  internal: true
---

# consult-rector Release Prep

Follow this workflow when cutting a new `typedduck/consult-rector` release.

consult-rector is a Composer package published on **Packagist**, distributed via
git tags — there is no built artifact to push. Dev tooling runs through
[bamarni/composer-bin-plugin](https://github.com/bamarni/composer-bin-plugin)
under `.vendor-bin/`; the gate commands are `composer` scripts (`composer
install` installs the bin tools too).

## Two facts to internalise first

These differ from a typical gem/npm release; get them wrong and you waste a cut:

- **The version lives in one place: the `VERSION` constant in
  [`src/Console/Application.php`](../../../src/Console/Application.php).** Both the
  CLI (`consult-rector --version`) and the MCP server (`bin/consult-rector-mcp`,
  `setServerInfo`) read it. Bump it there and nowhere else.
- **`composer.json` has no `version` field and `composer.lock` is not
  version-coupled** — the lock only pins dependencies. A version bump does **not**
  change `composer.lock`; never "regenerate the lock for the version". Packagist
  derives the released version from the **git tag `vx.y.z`**. The only lock
  concern at release time is *drift*: `composer validate --strict` must be clean.
  If it flags the content-hash, run `composer update --lock` (refreshes metadata
  only, no dependency changes) and commit that separately.

## Branch: `release/x.y.z`

Cut a dedicated branch from an up-to-date `master`. CI
([`.github/workflows/ci.yml`](../../../.github/workflows/ci.yml)) gates `master`
on push and every PR — PHPStan + ECS on PHP 8.5 and the PHPUnit matrix across PHP
8.2–8.5 (lock / update / prefer-lowest):

```sh
git switch master && git pull
git switch -c release/x.y.z
```

The metadata edits, the local verify, and the version-bump commit all land on
this branch; the PR back to `master` runs the full matrix before merge.

## Update release metadata

Decide the next semantic version first, then update the versioned surfaces
together.

### Seal the `[Unreleased]` changelog — the highest-value step

`CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/). On the
first release it does not exist yet — create it with the standard header (Keep a
Changelog + Semantic Versioning notes) and an `[Unreleased]` section.

**This step — not the version bump — is the deliverable of a release.** `composer
test` cannot see prose, so a changelog that still reads like commit messages is a
release that is **not done**; nothing downstream will catch a skipped rewrite.
Treat the sealing as the work:

- **One sentence per top-level bullet.** No em-dash run-ons; self-contained
  enough to understand without the body.
- **Subsystem label prefix** matching the architecture: `**[cli]**`,
  `**[ast-dsl]**`, `**[mcp]**`, `**[skill]**`, `**[references]**`, `**[ci]**`.
- **User-facing only.** Cut internal refactors, test/spec additions, coverage
  numbers, type-plumbing for the tool's own surface. Ask of each bullet: "would a
  user notice if they weren't reading the source?" If no, delete it.
- **Supplementary detail goes in child items** (`  - …`), one or two sentences.
- A changelog entry is **not** a commit message: many commits may collapse into
  one entry, and one fat `[Unreleased]` line may split into several.

Procedure (the enumeration is what makes it un-skippable):

1. Read the whole `[Unreleased]` block; classify **each** top-level bullet as
   release-style (leave) or commit-style (rewrite).
2. Rewrite every commit-style bullet — lead to one clause, push the why / how /
   measured numbers into a child item, delete internal-only detail outright.
3. Split **merge artefacts**: two entries glued into one bullet (a stray second
   `**[label]**` mid-sentence, a flag description hanging off an unrelated entry).
4. Re-read the sealed section top-to-bottom as a user would. Any top-level bullet
   with two sentences or an em-dash clause is not done.

### Write the release summary

Immediately under the new `## [x.y.z] - YYYY-MM-DD` heading, **before the first
`###` section**, write a short prose summary (≈3–4 sentences) of the release
*themes*: lead with the dominant theme, name one or two secondary threads, close
with a short "other fixes" clause. It is user-facing prose — the same "would a
user care?" bar as the bullets, no internal names or number dumps.

### Changelog mechanics

- Add `## [x.y.z] - YYYY-MM-DD` immediately below `[Unreleased]`; open with the
  summary, then `### Added` / `### Changed` / `### Fixed`.
- Use Keep a Changelog headings verbatim (`Added`, `Changed`, `Deprecated`,
  `Removed`, `Fixed`, `Security`). No description inlined into a heading; no
  `####` sub-headings inside a version block.
- Update the `[Unreleased]` compare link and add the new `[x.y.z]` link at the
  bottom. Compare links target
  `https://github.com/rigortype/consult-rector/compare/...`.

### Bump the version

- Set `Application::VERSION` in `src/Console/Application.php` to `x.y.z` (drop the
  `-dev` suffix). If you keep a post-release dev marker, restore
  `x.y.(z+1)-dev` in a follow-up commit after publishing.
- Do **not** add a `version` to `composer.json`.

### If dependencies changed (especially Rector)

If the release bumps `rector/rector` (or other deps), regenerate the shipped
reference docs and commit them **separately, before** the version-bump commit:

```sh
php tools/generate-references.php   # rebuilds references/rectors-by-category.md + rectors-compendium.md
```

`references/recipe-book.md` is hand-curated — never regenerate it.

## Verify the release

Run before committing:

```sh
composer validate --strict     # composer.json valid + composer.lock not drifted
composer ecs:fix               # coding standard (ECS, fixes in place)
composer phpstan               # static analysis (max + bleedingEdge + strict-rules)
composer test                  # PHPUnit — E2E tests invoke the real Rector/PHPStan binaries
git diff --check               # whitespace in the release diff
```

All three gates must be green — the project keeps them green on every commit.
**Never** suppress a PHPStan finding (`@var` / assert / cast / `@phpstan-ignore`
/ baseline) to clear the gate; fix the type instead.

Confirm the dist is lean and the binary reports the new version:

```sh
git archive HEAD | tar -t      # ships only: src/ bin/ references/ CONTEXT.md SKILL.md README.md LICENSE composer.json composer.lock
php bin/consult-rector --version   # prints "consult-rector x.y.z"
```

Optionally confirm the mutation gate (weekly in CI; ~93% MSI, ~2.5 min because it
runs PHPStan per escaped mutant):

```sh
composer infection -- --threads=max
# or trigger the workflow: gh workflow run infection.yml
```

If verification needs formatting or other non-version cleanup, commit that
**separately** — do not fold it into the version-bump commit.

## Commit

Prefer a single release-prep commit containing the `Application::VERSION` bump
and the `CHANGELOG.md` update. Keep verification cleanup and any reference
regeneration in separate, earlier commits. Use:

```text
Bump up version to x.y.z
```

If the user asks for separate commits, keep the version bump as the final commit.

## Open the release PR and merge on green

Land the release on `master` through a CI-gated PR, not a direct push:

```sh
git push -u origin release/x.y.z
gh pr create --base master --head release/x.y.z \
  --title "Bump up version to x.y.z" --body "<short release summary>"
gh pr checks <pr> --watch        # wait for the required ci.yml matrix
gh pr merge <pr> --rebase --delete-branch
```

- Merge once `ci.yml` is green. Keep the `Bump up version to x.y.z` commit intact
  (**rebase / merge, never squash**) so the tag lands on it.
- The PR merge **is** the merge-back to `master`; publish runs from `master`
  afterward, so there is no separate merge-back step.

## Publish

A Composer package publishes by **tagging** — Packagist reads the git tag, there
is no artifact to build or push. From an up-to-date `master` with the release
commit at `HEAD`:

```sh
git switch master && git pull
git tag -a vx.y.z -m "consult-rector x.y.z"
git push origin vx.y.z
```

Then:

- **Packagist** — if the repo's Packagist hook (or GitHub App) is configured, the
  new version appears within a minute or two. Otherwise update it manually at
  `https://packagist.org/packages/typedduck/consult-rector`, or **submit** the
  package there on the first ever release. This is the one step that must happen
  outside the repo.
- **GitHub Release** — create one from the tag with the changelog section as the
  body:

  ```sh
  gh release create vx.y.z --title "x.y.z" \
    --notes "<the ## [x.y.z] section from CHANGELOG.md>"
  ```

Once Packagist has the version, confirm a consumer can install it:

```sh
composer require --dev typedduck/consult-rector:^x.y.z   # from a scratch project
```

## Quick checklist

- Working tree starts clean, or every pending change is understood.
- `Application::VERSION` is `x.y.z` (no `-dev`); `composer.json` still has **no**
  `version` field; `composer.lock` was **not** touched for the version.
- **Every** former `[Unreleased]` bullet was classified and, if commit-style,
  rewritten — no top-level bullet in `## [x.y.z]` has two sentences, an em-dash
  clause, internal-only detail, or a merge artefact. (This is the step `composer
  test` cannot check; confirm it by eye.)
- The `## [x.y.z]` section opens with a short release-summary paragraph before
  `### Added`.
- `[Unreleased]` / `[x.y.z]` compare links resolve.
- `composer validate --strict` clean; `composer ecs && composer phpstan &&
  composer test` green; `git diff --check` clean.
- `git archive HEAD` ships only the runtime set; `bin/consult-rector --version`
  prints `x.y.z`.
- If Rector was bumped: `references/rectors-*.md` were regenerated and committed
  separately.
- The final commit is `Bump up version to x.y.z`; release work happened on a
  `release/x.y.z` branch (not directly on `master`).
- After publish: the `vx.y.z` tag is pushed, Packagist shows the version, the
  GitHub Release exists, and `composer require` resolves it.
