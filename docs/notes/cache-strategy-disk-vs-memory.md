# Note: Rector cache strategy — disk vs in-memory, measured at real-project scale

**Date:** 2026-06-26
**Status:** Informational (records the evidence behind the cache policy in `ConfigAssembler` / `DslConfigAssembler` / `ContainerCache`)

## Background

consult-rector assembles a throwaway `rector.php` per invocation and runs Rector in
a short-lived subprocess. A first-use report on a restricted environment surfaced a
baffling failure: `dry-run` kept returning `changed_files: 0` even though the rule
matched. Root cause was Rector's on-disk cache in a shared temp directory — a
`rector_cached_files/` tree owned by another user made Rector fail (or silently
skip files as "unchanged").

Rector actually keeps **two independent disk caches**, and they deserve opposite
treatment:

| Cache | Rector setting | Default location | What it stores | Invalidation |
|---|---|---|---|---|
| **① unchanged-files skip** | `cacheDirectory()` | `<tmp>/rector_cached_files` | "file processed & unchanged" → skip next run | keyed on the **config file path** |
| **② container + embedded-PHPStan** | `containerCacheDirectory()` | `<tmp>` (→ `<tmp>/cache/…`) | compiled DI container; per-file name-scope / phpdoc (`FileTypeMapper`) | content-addressed (**SHA-256 + version**) |

Our policy (the interim version measured below; **see "Follow-up" for the final
state** where ① was re-enabled on disk safely):

- **① → in-memory** (`cacheClass(MemoryCacheStorage::class)`). consult-rector writes a
  *unique* temp config path every run, which defeats Rector's "config changed → clear
  cache" safety net (that net keys on the config path). The skip cache then leaks
  across runs/users and the only observable symptom is `changed_files: 0`. For an
  interactive diff tool, cross-run skipping is also semantically wrong (you want the
  diff every time). So it is disabled — pure liability *under this design*.
- **② → kept on disk, but pinned to a stable per-user directory**
  (`sys_get_temp_dir()/consult-rector-cache-<uid>`, see `ContainerCache`). It is
  content-addressed and therefore safe to reuse, and moving it off Rector's shared
  default removes the foreign-ownership collision. The Runner creates the directory
  first (Rector fatals on a missing one) and surfaces `fatal_errors` instead of
  mapping them to `changed_files: 0`.

The open question this note answers: **does keeping ② on disk actually pay off?**

## Method

Rule: `Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector` (`function () { return … }`
→ `fn () => …`), `dry-run` only (no files rewritten).

Cold vs warm timing by isolating the cache directory via `TMPDIR`:

- **cold** — a fresh empty `TMPDIR` (② cache must be rebuilt from scratch)
- **warm** — reuse the now-populated `TMPDIR` (② cache hot)

Two scales:

1. **Tiny** — consult-rector's own `src/` (~a dozen files; ~2.5 s/run).
2. **Real project** — a mid-sized internal PHP web application (name withheld):
   **826 source `.php` files, ~76,500 LOC** (plus ~380 test files; ~3,300 `.php`
   files in total). Git-managed; ships its own `rector.php` with **no** explicit cache
   configuration (i.e. it would use Rector's shared defaults — exactly the case this
   policy guards).

## Results

| Scale | cold | warm (repeats) | speedup | run output |
|---|---|---|---|---|
| Tiny (~12 files, ~2.5 s) | 2.65 s | 2.23 / 2.23 / 2.45 s | **~0.4 s (~15%)** | — |
| **Real (826 files, ~76.5k LOC)** | **39.6 s** | **39.2 / 39.8 / 41.2 s** | **~0 s (within noise)** | `changed_files: 18`, `errors: 0` |

Warm-cache contents after the real-project run (proving ② *was* populated and reused):

- container (`nette.configurator`): **352 KB** — one-time bootstrap
- `FileTypeMapper` (`PHPStan/`): **23 MB across 1,690 entries** (≈ 826 files × 2)

## Analysis — why warm ≈ cold at scale

② **is** working: 1,690 per-file analysis entries persisted and are reused on warm
runs. It just doesn't move the needle, because it only accelerates **bootstrap
(container compile) + phpdoc parsing** — a *fixed* cost of ~0.4 s. The dominant cost
of a Rector run, **per-file scope/type resolution and rule application, is recomputed
every run** and is not what ② caches.

So the saving is a constant, and its share collapses as the run grows:

- 2.5 s run − 0.4 s ⇒ ~15% (visible)
- 40 s run − 0.4 s ⇒ ~1% (noise)

The cache that *would* make a warm run on 826 files dramatically faster is the
**unchanged-files skip cache (①)** — skip the 808 clean files, reprocess only the 18
with pending diffs. That is precisely the cache we disabled for correctness. For an
interactive diff tool that must re-evaluate every file every time, **large runs are
inherently ~linear in file count** — the cost of correctness.

## Conclusion

The earlier "②'s disk cache speeds up re-runs (~15%)" claim holds **only for small,
fast runs**; at real-project scale it is **negligible**. The justification for keeping
② on disk is therefore **robustness, not speed**:

1. **Robustness (scale-independent, the real win):** avoids the shared-`/tmp`
   permission failure and stale-skip that the first-use report hit.
2. **Speed:** a fixed ~0.4 s, perceptible only on small/repeated runs; ~0 at scale.

Cost of the policy is ~nil (one directory + an `is_dir`/`mkdir`), so ② stays — framed
as "so it doesn't break," not "so it's fast."

## Follow-up: skip cache re-enabled, safely (implemented)

The conclusion above measured the *interim* policy (skip cache → in-memory), which
forfeits the only cache that scales. We then re-enabled the skip cache on disk
**without** the stale-skip hazard, by isolating it instead of relying on Rector's
config-path invalidation:

- The skip cache's `cacheDirectory` is **content-addressed by run signature** —
  `skip-<hash of paths + rules + Rector version>` under the per-user root (see
  `ContainerCache::skipCacheDirectory`). Identical runs share the directory; any
  different rule set gets its own, so a skip entry can never suppress a different
  run's changes. (An initially-considered "single stable config path" was rejected:
  it would race under parallel invocations. Keying the *cache directory*, not the
  config path, keeps configs unique and race-free.)
- The config path stays unique per run, so there is no config-write race.

Measured on the same 826-file project, `ClosureToArrowFunctionRector` dry-run:

| | time | result |
|---|---|---|
| cold (fresh signature dir) | 38.0 s | `changed_files: 18` |
| warm (identical re-run) | **4.0–4.2 s** | `changed_files: 18` (same 18 diffs) |

**~9× faster on the second pass, with the change set fully preserved** — the 18
diff-producing files are reprocessed every run (Rector never caches a file that
still has a pending diff), only the ~808 clean files are skipped. This restores the
scale-relevant speedup while keeping every robustness property: per-user ownership,
content-hash validation, per-signature isolation, and `fatal_errors` surfacing.
