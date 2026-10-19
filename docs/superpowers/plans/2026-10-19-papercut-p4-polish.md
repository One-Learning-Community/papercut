# Papercut Plan 4: v0.1 Polish (performance, visual regression, contract suite, release readiness)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Meet the remaining v0.1 success criteria from spec §3 and §9. That means a 10-page table document rendering in under 150 ms with a CI benchmark gate, visual regression snapshots, a shared backend contract test suite, and release-ready packaging and docs.

**Architecture:** No new runtime features. This plan adds benchmark and test infrastructure, plus targeted performance fixes found by profiling.

**Tech Stack:** PHP 8.4, PHPUnit, `pdftoppm` (poppler) for snapshots, GD for pixel diffs (dev only), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-10-18-papercut-design.md` (§3 criteria 4–5, §9)

## Global Constraints

- Plans 1–3 constraints hold. No new runtime dependencies. GD and poppler are dev/CI-only.
- Benchmark target (spec §3.5): a 10-page table document in < 150 ms on PHP 8.4 without FFI. Measured as the median of 5 runs after 1 warm-up, Xdebug off, OPcache on (CLI `opcache.enable_cli=1`).
- CI regression gate: fail if the median is > 20% slower than the committed baseline (`packages/core/bench/baseline.json`).
- HarfBuzz FFI is **out of v0.1** (ruling, 2026-10-19). php-pdf exposes no glyph-ID drawing path, and its built-in shaping covers kerning and ligatures within runs; RTL/bidi paragraph layout is out of v0.1. Spec §4/§10 are updated accordingly.

## Review Focus

1. **Benchmark noise:** CI machines vary. The gate must compare against a baseline measured on the same runner class, and it must print both numbers. A flaky gate is worse than none. Test: Task 1 (gate script handles a missing baseline by writing one and passing).
2. **Snapshot portability:** font rasterization differs between poppler versions. Comparisons use a tolerance (≤ 0.5% differing pixels, with a per-pixel channel threshold of 32) and fixed DPI (72). Test: Task 2.
3. **Contract suite against the fake backend:** the suite must be runnable against any `Backend`. Running it against `FakeBackend` must fail loudly with clear skips (fake output is not a PDF), not silently pass. Test: Task 3.

---

### Task 1: Benchmark harness, profiling pass, CI gate

**Files:** `packages/core/bench/transcript.php` (builds the spec's 10-page table document deterministically: header row + 300 body rows, 3 columns, header/footer with page numbers), `packages/core/bench/run.php` (warm-up + 5 runs, prints JSON `{median_ms, runs_ms[], pages, bytes}`), `packages/core/bench/check.php` (compares against baseline.json: exit 1 if > 1.2×; writes a baseline if missing), `packages/core/bench/baseline.json`, `.github/workflows/core.yml` (add a `bench` job on ubuntu-latest PHP 8.4 with OPcache), `packages/core/tests/Feature/BenchmarkDocumentTest.php` (asserts the bench document has exactly 10 pages, so the target stays meaningful).

- [ ] Write `BenchmarkDocumentTest` (10 pages) → FAIL → write `transcript.php` → PASS.
- [ ] Write `run.php` and record the current median locally (`php -d xdebug.mode=off -d opcache.enable_cli=1 packages/core/bench/run.php`).
- Input: the Plan 2 final review profiled the engine (ledger .superpowers/sdd/2026-10-18-papercut-p2-layout-engine/progress.md, "Final review" line). Its warm-profile hotspots, in priority order: (a) memoize style resolution per node+parent ComputedStyle (WeakMap), about 34%; (b) TableLayouter columnContent skips span-1 cells in Length tracks and measures only min-content for fr tracks, about 32%; (c) cache TextLayouter intrinsic per node+style; (d) measureRow re-lays only cells with background, border, vertical alignment or growing children. Cold start: about 70 ms per font parsing GPOS kerning in the backend. Add a persistent pair-table cache keyed by font path and mtime (behind the PhpPdf adapter; cache dir configurable via RenderOptions, default sys_get_temp_dir()).
- [ ] If the median is ≥ 150 ms, profile. Use `php -d xdebug.mode=profile` and inspect the cachegrind output, or use `excimer`. Fix the top hotspots, each with a test that pins behavior. Likely candidates:
  - `shapedGlyphs` called twice (measure + draw) — `CachedFont` already caches measurement;
  - repeated `intrinsic()` calls in flex/table — memoize per node+style within a layout pass;
  - fragment flattening allocations;
  - `mb_*` hot loops in the Itemizer.
  Re-measure after each fix and record the numbers in the report.
- [ ] Write `check.php` and `baseline.json` (from a CI-class run; locally, write the local median with a `"machine": "local"` note). Add the CI job.
- [ ] Commit `perf(core): benchmark harness, CI gate and hotspot fixes`.

### Task 2: Visual regression snapshots

**Files:** `packages/core/tests/Visual/SnapshotTest.php`, `packages/core/tests/Visual/fixtures/*.php` (each returns a `Document`: flex-justify, table-split-repeat-header, text-align-justify, borders-radius-background, image-fit, page-numbers, keep-together), `packages/core/tests/Visual/snapshots/*.png` (committed), `packages/core/tests/Support/ImageDiff.php`.
- `ImageDiff::compare(string $pngA, string $pngB, int $threshold = 32): float` returns the fraction of pixels whose max channel delta exceeds the threshold. Different dimensions → 1.0.
- Rendering: `pdftoppm -png -r 72` per page. A snapshot is named `<fixture>-<page>.png`.
- `UPDATE_SNAPSHOTS=1` rewrites the snapshots. Otherwise a missing snapshot fails with instructions. On failure, write the actual PNG and a diff PNG to `tests/Visual/output/` (git-ignored) and include their paths in the assertion message.
- The suite is skipped (not failed) when `pdftoppm` is unavailable.
- [ ] TDD the `ImageDiff` unit (identical → 0.0; one pixel changed in a 10×10 → 0.01; size mismatch → 1.0) → write the fixtures → generate the snapshots → **visually inspect every snapshot PNG** (Read them) and fix any layout bugs found, each with a regression test in the relevant layouter test → commit `test(core): visual regression snapshots`.

### Task 3: Backend contract suite

**Files:** `packages/core/tests/Contract/BackendContractTestCase.php` (abstract), `packages/core/tests/Contract/PhpPdfBackendContractTest.php`.
- The abstract case declares `abstract protected function backend(): Backend;` and covers the spec §9 contract:
  - valid PDF (qpdf);
  - text extractable (pdftotext) with ToUnicode;
  - fonts embedded and subsetted (`pdffonts` shows `emb yes`, and file size < 25% of the TTF size for a short text);
  - op geometry (text bbox, Link `/Rect`, image placement via `pdfimages -list` or the qdf `cm` operator);
  - deterministic bytes;
  - independence across renders;
  - `.notdef` width agreement;
  - nested opacity;
  - text-state resync after wrappers.
  Consolidate/move the overlapping assertions from `PhpPdfBackendRenderTest` into the contract case. Keep backend-specific tests (vendor-internals guard) in `PhpPdfBackendRenderTest`.
- [ ] Write the abstract case + concrete subclass → all pass → delete the duplicated assertions from `PhpPdfBackendRenderTest` → commit `test(core): shared backend contract suite`.

### Task 4: Release readiness

**Files:** `LICENSE` (MIT, root + each package), `packages/laravel/composer.json` (replace `"papercut/core": "@dev"` with `"^0.1"` and add `extra.branch-alias` `dev-main: 0.1.x-dev` to core so the path repo still resolves; document `COMPOSER_ROOT_VERSION` if needed), a Tailwind MIT notice file next to `resources/palette.php`, `CHANGELOG.md` (root; `## [0.1.0] - unreleased` with Added sections per package), `CONTRIBUTING.md` (dev setup: brew/apt deps, running both packages' tests, snapshot updates, the benchmark), `packages/core/README.md` (full plain-PHP usage guide: elements, styles, units, tables, pagination controls, fonts, errors), root `README.md` (link both packages, status, roadmap from spec §10 + "HarfBuzz shaping"), `packages/*/composer.json` (add `homepage`, `support`, `authors` [role-based, no personal email], `keywords`), `docs/superpowers/specs/...` (update §4 and §10 for the HarfBuzz ruling).
- [ ] A docs test that executes every PHP code block in `packages/core/README.md` (extract the fenced `php` blocks, wrap each into a script that renders to a temp file, assert exit 0 and qpdf validity), so the README can't rot → write the docs → commit `docs: v0.1 release readiness`.
- Publishing to Packagist and tagging v0.1.0 are user actions (outside this repo's scope): list the exact steps in CONTRIBUTING.md under "Releasing".
