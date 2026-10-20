# Rulings made during autonomous v0.1 build-out (2026-10-18/19)

Decisions the controller took on the maintainer's behalf while they were offline. Each: what was decided — why — cost if wrong.

## p1-core-foundation
- Ruling: fold PdfInspector temp-file fixes into Task 9 dispatch (first task that exercises PdfInspector) — keeps fixes reviewed with real usage — cost if wrong: a few leaked empty temp files in dev
- Ruling: pipeline — dispatch Task N+1 implementer while Task N review runs, when N+1 does not modify N's files — reviews are read-only so no conflicts; saves wall-clock — cost if wrong: a fix round on N lands after N+1 built on the pre-fix code (re-run N+1 tests after any N fix)
- Ruling: commit trailers — subagents use the Co-Authored-By line from their own session attribution reminder (their actual model) plus the Claude-Session line — more accurate attribution — cost if wrong: mixed trailer names in history
- Task 4: Ruling: plan-mandated omission of declare(strict_types=1) in generated palette.php — fix it (global constraint wins over plan code) — cost if wrong: none
- Task 4: Ruling: include reviewer Minors 1-2 (rgb() mixed/missing separators, '##fff'/no-'#' hex accepted) in fix round 1 — cheap correctness fixes on a parser users will hit — cost if wrong: slightly stricter color parsing than CSS for edge forms
- Ruling: batch all NaN/INF hardening (Length::parse INF, Fr/Percent::of, paint ops) into one "hardening" dispatch after Task 10 — same-shape small edits, one review — cost if wrong: none until then (layout not yet producing NaN)
- Ruling: Task 9 render() must call PdfFont::reset() on every loaded font before drawing; add test "render A then B on same backend === B on fresh backend" — php-pdf accumulates usedGlyphs across documents and during measurement, breaking determinism and bloating subsets — cost if wrong: none (reset re-records glyphs during drawing)
- Task 9: Ruling: fix Critical by invalidating ContentStream's private lastFillR/G/B + lastCharSpacing (set NAN via Closure::bind on Page::rawContentStream()) after every wrapper closes, contained in PhpPdfBackend, guarded by a regression test that fails if internals change — php-pdf has no public invalidate API — cost if wrong: breaks on a php-pdf internal rename (test catches it; version pinned ^1.24)
- Task 9: Ruling: fix nested opacity by threading an inherited alpha through drawOps (Opacity → withOpacity(inherited*alpha); color alpha → color.alpha*inherited) despite plan code — spec intent is correct compositing — cost if wrong: none
- Ruling: exception API — InvalidValue keeps withContext(attr) ("for <attr>"), adds within(string $location) (appends " on <x-pdf::tag>"-style phrase, keeps previous chain) and because(string $reason) (": <reason>" detail); UnknownAttribute: withContext renamed/replaced by within() with same semantics; Plans 2/3 call sites updated — consistent composition across both exception types — cost if wrong: API churn before any release (none published)
- Ruling: link scheme allowlist in Paint\Link: http, https, mailto, tel, and '#fragment'; others → InvalidValue kind 'link' — prevents javascript:/file: injection via user-supplied hrefs — cost if wrong: users needing other schemes get an error (can widen later)
- Ruling: spec drift — update spec (remove lineGap, metrics take $size) myself; backend contract test suite scheduled in Plan 4 — cost if wrong: none
- Ruling: FontRegistry bad weight → InvalidValue::for('font weight', ...); render() no pages stays InvalidArgumentException (programmer error)

## p2-layout-engine
- Task 1: Ruling: include Minors 2-3 in fix round 1 (enum trim/case-insensitive; weight 1..1000; font-size/line-height must be > 0) — prevents div-by-zero/infinite loops in layout — cost if wrong: slightly stricter attribute parsing
- Ruling: allow a fix-round implementer to run concurrently with the next task's implementer when their file sets are disjoint — saves wall-clock; git index.lock collisions are transient — cost if wrong: an agent retries a commit
- Ruling: layout path names (LayoutContext::pushPath) for views use the COMPUTED direction (row/column), not debugName(), so overflow messages are accurate regardless of how direction was set — carry into Task 5/6 dispatch — cost if wrong: none
- Task 3: Ruling: accept TableCell::th() static creator + header(): bool getter (plan defect: same-name static/instance impossible in PHP); Plan 3 updated to TableCell::th — cost if wrong: none
- Ruling: strengthen TDD RED requirement in implementer-rules.md (3 sonnet implementers skipped RED) — cost if wrong: none
- Task 5: Ruling: accept implementer's justify-test text change ('aa bb ccc dd') — plan arithmetic error ('aa bb cc' = 40pt fits) — cost if wrong: none
- Task 5: Ruling: Task 6 adds an optional sibling index to LayoutEngine::layout (e.g. `?int $index = null`) so containers produce paths like `view[2]` — needed for LayoutOverflow paths (spec §7.3) — cost if wrong: none
- Task 5: Ruling: plan gap — Text box styles (padding/border/background/width) must be honored; routed into Task 6 (TextLayouter uses BoxGeometry) — spec §6.4 says box props apply to nodes — cost if wrong: none
- Task 5: Ruling: push path before style resolution (named-style errors get node path); Fragment immutability docblock — routed into Task 6
- Task 6: Ruling: include Minor 1 (no margin-top on continuation slice) and Minor 2 (keepWithNext chains walk back) in fix round 1 — common H1+H2 pattern — cost if wrong: none
- Task 6: Ruling: only an explicit `height` makes a box monolithic; definiteHeight from stretch (Auto height) = minimum fill height, still splittable — spec §7.3 "row split only if every child can split" — cost if wrong: stretched cells taller than a page would split instead of overflow (desired)
- Task 9: Ruling: include Minors 4-6 (colspan-only auto columns, fr min-content clamp, header taller than space) in fix round 1 — cheap correctness — cost if wrong: none
- Ruling: run Task 13 (hardening) before Task 12 (examples) — examples exercise row backgrounds/images/links fixed by Task 9 fix + Task 13 — cost if wrong: none
- Task 10: Ruling: Clip mode clips ONLY the overflowing node (LayoutEngine re-lays it unbounded, wraps in clipped fragment of available size, warns, returns Complete); Paginator clip branch = last-resort fallback — spec §7.3 "clips and reports a warning" intends the element, not the rest of the document — cost if wrong: none
- Task 10: Ruling: include Minors (no-progress guard test via test-only layouter seam, drop null-token Partial checks, 'page >' path prefix)
- Task 12: Ruling: replace fabricated performance figures in report.php stat cards with real/neutral facts (honesty in public examples) → P2 final fix wave — cost if wrong: none
- Ruling: deleted untracked examples/core/*.php-E (sed backup drafts from Task 12; committed files are newer) — cost if wrong: none (drafts superseded)
- Ruling: P2 fix wave = correctness/honesty/API items + cheap deferred minors; performance items (#2 a–d + kerning cache) move to Plan 4 Task 1 (benchmark+profiling) with this profile as input — keeps fix wave focused — cost if wrong: perf target met later rather than now

## p3-laravel
- Task 1: Ruling: accept deferring PdfManager scoped binding + FontsCommand + preview routes to their tasks (6/7/8) — classes don't exist yet; Task 6 dispatch must add the scoped binding — cost if wrong: none
- Task 4: Ruling: accept 'size' alias → font-size (and 'align' → text-align) on text-bearing tags — spec §6.1 examples use size="18pt" — cost if wrong: none
- Task 3: Ruling: include Minors 3-6 (scoped align alias with user-facing key in errors; Style object/invalid style attr; nodeFrom unwrap loop) — cost if wrong: none
- Task 4: Ruling: relative image src must be contained to image_path (realpath prefix check → ImageUnreadable) — security for user-supplied paths; absolute paths stay allowed (documented as trusted) — schedule in P3 hardening batch — cost if wrong: users relying on ../ escapes must use absolute paths
- Ruling: run P3 Task 8 before Task 7 — Task 7 edits core RenderOptions/LayoutContext while P2 Task 12 may touch core — cost if wrong: none
- Task 7: review — Important: font config shape not validated (silent coercion); Ruling: include Minors 1,2,4 (dup docblock, fallback_fonts validation+warning, fallback test asserts font id) in fix round 1 — cost if wrong: none
- Task 7: parked — duplicate @method docblock line in Facades/Pdf.php — Ruling: cosmetic, folded into P3 hardening batch rather than another fix round — cost if wrong: none
- Task 9: Ruling: missing LICENSE files (root + packages, Tailwind MIT notice) + root README install snippet → Plan 4 Task 4 (release readiness), which already covers LICENSE — cost if wrong: none (nothing published yet)
- Task 10: Ruling: switch image containment to textual normalization of base+src (reject climbing above base), allowing app-owner symlinks inside the base — attacker controls only the src string — cost if wrong: a symlink inside public/ pointing to sensitive files would be readable (owner-created, acceptable)
- Final review (opus): Yes (no blockers). Ruling: one fix dispatch for Minors 1-8 + README alias note (cheap; protects strict-errors promise); core version constraint for publishing → Plan 4 Task 4 — cost if wrong: none

## p4-polish
- Task 3: Ruling: subagent was denied 'git rm' of the duplicated PhpPdfBackendRenderTest.php; controller will NOT perform it on its behalf — keep file (duplicated coverage, harmless), mark pending removal, surface to user for approval — cost if wrong: ~19 duplicate tests run twice
- Final review (opus): With fixes — all suites green; bench 56ms; output pixel-identical. Important: CI bench baseline unarmed (MAINTAINER), snapshots unverified on CI poppler (MAINTAINER), kerning cache undocumented (fix dispatched with laravel cache_dir + symlink guard + bench docs). Ruling: CI-dependent items are maintainer follow-ups (no remote/CI available) — cost if wrong: first CI run may go red on snapshots.

# Rulings made during multi-format Phase 0 (seams and renames), 2026-10-19

Plan: docs/superpowers/plans/2026-10-19-multi-format-phase0-seams.md. Each entry: what was decided — why — cost if wrong.

## p0-multi-format-seams
- Ruling: implementers ran on Sonnet 5.5, all reviews on Opus 5.5 — maintainer instruction — cost if wrong: none
- Ruling (R1): Task 5 typed ExportOptions onWarning as a plain `?\Closure`; Task 6 widened the docblock to `\Closure(string, ?FidelityReport=): void` once FidelityReport existed — avoids a PHPStan unknown-class error — cost if wrong: none
- Ruling (R2): Task 5 also adapted Laravel internals minimally (no public API change) so every task left both packages green; Task 8 did the renames — Laravel loads core through a symlink — cost if wrong: small rework in Task 8
- Ruling: golden record mode (`UPDATE_GOLDEN=1`) may only add missing keys; an existing key with a different hash fails even in record mode — the byte-identical promise outranks the plan's convenience flag — cost if wrong: re-baselining later means deleting the JSON entry first
- Ruling: `StyleResolver::resolve()` applies no table-cell defaults; a separate `resolveInTable()` does, and TreeWalker uses it for cells under a table row — the plan's single-layer version changed styling of a bare TableCell outside a table (v0.1: no padding, normal weight) — cost if wrong: none
- Ruling: leftover `x-pdf` text in core docblocks and exception tests was swept in Task 8 (plan gap) — cost if wrong: none
- Ruling: the CHANGELOG "[0.1.0] - unreleased" section keeps v0.1 names as history — it documents v0.1, and the migration table maps from it — cost if wrong: one section rewrite later
- Ruling: Paginator names slot columns `header`/`footer`/`background` in layout paths, so PDF and TreeWalker report identical error paths (spec §5.4) — final review found the plan accepted a divergence — cost if wrong: none (paths never reach PDF bytes)
- Ruling: a new PhpPdfBackend per Laravel render (v0.1 shared one per scoped manager) is accepted — measured 0.7 ms/doc either way, identical bytes, better isolation — cost if wrong: repeated large-image decoding in batch jobs
- Ruling: cheap reviewer Minors were folded into fix rounds where they touched the same lines (test tightening, import order, Tag prefix caching, config comment placement, README prefix/strict rows) — cost if wrong: none

## Carry-overs for the Phase 1 plan (before the 0.2.0 API freeze)
- Settle accessor style: spec §5.2 lists `Output::mime()`/`extension()` methods, but Output exposes public properties (and `bytes` as both); ExportOptions/PdfOptions mix properties and getters.
- TreeVisitor gives no sibling index or header-row flag; phase 2 needs both (FlexSlot::index matching, `w:tblHeader`/`<thead>`).
- WidthResolver: warnings carry no node path; `for()` always uses a default-cache PhpPdfBackend (decide a cache policy for non-PDF exports); add a direction guard or docblock to `flexLines`.
- Fidelity always passes two arguments to onWarning: document it or make the call arity-aware before HtmlExporter reports anything (native callables such as `error_log(...)` would TypeError).
- Extract a shared Laravel options builder (theme, fonts, onWarning, strict) for HtmlOptions.
- Add a test that app components `<x-doc.foo>` don't conflict with `<x-doc::*>` (spec §8.2).
- Add a `composer analyse` script to packages/laravel.
- Deferred test polish: PdfExporterTest cacheDir and WidthResolver fresh-engine assertions are vacuous; `PdfOptions::from` is tested only for theme; the renders-once test doesn't prove `pdf()` memoization; overflow/cache_dir tests are duplicated across HardeningTest and OptionsFromConfigTest; cell defaults are duplicated in `resolveInTable` and `TableLayouter::prepare` (add an equality test); golden test ignores example warnings when exit code is 0.

# Rulings made during multi-format Phase 1 (HTML exporter), 2026-10-19

Plan: docs/superpowers/plans/2026-10-19-multi-format-phase1-html.md. Each entry: what was decided — why — cost if wrong.

## p1-html
- Ruling: implementers on Sonnet 5.5, all reviews on Opus 5.5 — maintainer instruction — cost if wrong: none
- Ruling: a TableCell outside a table renders in HTML as a column `<div>` and emits the same plain warning as PDF ("A table cell outside a table is laid out as a column") — mirrors PDF — cost if wrong: small markup change
- Ruling: spacers — fixed `flex:0 0 {size}pt`, flexible `flex:{grow} 0 0` — matches PDF — cost if wrong: none
- Ruling: the upstream php-docx PR also makes the writer emit `w:keepNext` (php-docx read it but never wrote it) — Papercut's keep-with-next mapping depends on it — cost if wrong: larger upstream PR
- Ruling: a test that couldn't fail (`cacheDir: false`) was replaced with one that checks the default cache directory, proved by a deliberate break — cost if wrong: none
- Ruling: `Css::pagination` diffs `minLines` against the parent (like text properties), so custom themes don't emit orphans/widows on every node — cost if wrong: none
- Ruling: a child turning underline/strike off under a decorated parent can't be undone in CSS; HTML reports Approximated `text decoration override` (once per paragraph) — honest reporting — cost if wrong: an extra debug log line
- Ruling: HTML link errors carry no node path, so they equal PDF's message exactly (spec §5.4); PDF validates links at paint time, where no path exists — cost if wrong: HTML link errors less locatable (idea: validate link URIs when nodes are built, so every format fails early)
- Ruling: tables — `<table>`/`<tr>` carry their text styles; fr widths subtract percent tracks too; tables with an `auto` track use `table-layout:auto`; image and stray-row errors match PDF text exactly — PDF parity — cost if wrong: none
- Ruling: refused `imageUrl` results (empty, `//`, `/\`, `javascript:` …) embed the image and warn — cost if wrong: none
- Ruling: fallback font families count as used for `@font-face` in embed/link modes — PDF uses them for missing glyphs; both modes are opt-in — cost if wrong: larger embedded HTML with big fallback fonts
- Ruling: the committed HTML example renders link fonts by relative path (KB, not 2.2 MB of base64); the unpushed commit that had the blobs was recreated — cost if wrong: renders show Inter only from a checkout
- Ruling: fr columns are plain percentages computed from an estimated table width (page content width minus ancestor and own box edges) — Chrome ignores mixed `%`/`pt` `calc()` on `<col>`; verified exact in headless Chrome for top-level and padded tables — cost if wrong: Length-vs-fr ratio approximate for tables inside fixed/percent-width ancestors or flex rows (reported)
- Ruling: real table cells lay out as columns (wrapper flex div); cell justify maps to `vertical-align`; row padding is reported, not emitted; block Links keep their flex props; nested links render as `<span>` and are reported — PDF lays cells and links out as column Views — cost if wrong: an extra wrapper div per cell
- Ruling: content parity compares word multisets with table-header-row words removed from both sides — header rows repeat per page in PDF (n/a in HTML); mutation-checked to catch every dropped body row — cost if wrong: a body row made only of header words could drop undetected
- Ruling: cheap reviewer Minors were folded into fix rounds where they touched the same lines — cost if wrong: none

# Rulings made during PDF/HTML hardening, 2026-10-19

Plan: docs/superpowers/plans/2026-10-19-pdf-html-hardening.md. Each entry: what was decided — why — cost if wrong.

## hardening
- Ruling (H1): DOCX is on hold until php-docx has the features listed in docs/superpowers/php-docx-1.15-gaps.md — the §5.3 mapping cannot be met on 1.15 — cost if wrong: DOCX starts later than needed
- Ruling (H2): `PendingHtml` is `Htmlable`, `Stringable` and `Responsable`, no longer a `PendingOutput`; `page()` and `fragment()` return `PendingOutput`; `toResponse()` keeps the full-page response (200, text/html, no Content-Disposition) — a string must not mean two things — cost if wrong: callers rename `->html()->save()` to `->html()->page()->save()`
- Ruling (H3): `html.image_url` and `html.font_url` name invokable classes; `html.fonts` accepts `link`; invalid `html.*` config is reported when HTML options are first built, not at boot, so artisan commands still run with a half-set config; `link` needs `html.font_url`; no code-level options hook in 0.2.0 — cost if wrong: a bad config surfaces at first export instead of boot
- Ruling (H4): PDF's "Table columns need X but only Y" warning is not mirrored in HTML — the browser resolves column overflow itself, and mirroring it would need text measurement in HTML — cost if wrong: an HTML export of a too-narrow table gives no warning
- Ruling: php-pdf's `PdfImage::fromBytes` accepts PNG/JPEG cut to 60%, so the truncated-image test asserts parity (same outcome and message) plus a header-only PNG both formats refuse; refusing truncated data is a php-pdf follow-up — cost if wrong: truncated images embed broken in both formats
- Ruling: `FontSet::effectiveFaces` keeps an overridden face at its first-registration position (the old `FontFaces::faces` moved it to the end) — only `@font-face` order for overridden faces changes; no golden changed — cost if wrong: one ordering line
- Ruling: `->html()->toResponse()` sends the full page with no Content-Disposition (as before); `->html()->page()->toResponse()` is a normal `PendingOutput` and sends `inline` — keeps route behaviour unchanged — cost if wrong: none
- Ruling: a resolver class that the container cannot build is reported naming its config key (`Cannot build papercut.html.<key> resolver …`) — a bare container error does not say which key is wrong — cost if wrong: none

## Carry-overs (status after the hardening plan)
- *Done in hardening Task 2.* HTML and PDF now refuse the same images: both validate through the `Backend` interface, so a header-only PNG is refused by both. *Waits for php-pdf:* a PNG or JPEG truncated to about 60% is accepted by php-pdf's `PdfImage`, so it embeds broken in both formats.
- *Done in hardening Task 6 (H2).* `PendingHtml` had `(string) $html` = fragment but `toString()` = full page; it now exposes explicit `page()` and `fragment()`, each a `PendingOutput`.
- *Done in hardening Task 7 (H3).* Laravel has no per-call options hook, so `imageUrl`, `fontUrl` and `link` font mode were PHP-API only; they are now config keys naming invokable resolver classes.
- Add HTML (and DOCX) timings to the bench (spec §9). *HTML: done in hardening Task 5. DOCX: waits for DOCX.*
- *Done in hardening Task 3.* WidthResolver is the single source for track counting (HtmlWriter no longer duplicates TableLayouter's rule); `FontSet::effectiveFaces()` shares face dedupe between FontRegistry and FontFaces.
- *Waits for DOCX* (only DOCX calls `WidthResolver`). WidthResolver carry-overs from Phase 0 still apply: node-path context for warnings, a cache policy for non-PDF exports, a `flexLines` direction guard.
- Minor items:
  - *Done in hardening Task 4:* cell `justify` space-between/around/evenly is reported (`cell justify`).
  - *Done in hardening Task 4 (ruling H4, not mirrored):* PDF's "Table columns need X but only Y" warning needs layout widths, so HTML does not mirror it.
  - *Done in hardening Task 4:* the `table()`/`tableRow()` decoration-override check.
  - *Done in hardening Task 3:* the reused-cell stray warning edge case.
  - *Done in hardening Task 4:* the render.php `fontUrl` returns null for faces outside the repo.

# Rulings made during HTML font sources, 2026-10-19

Design: docs/superpowers/specs/2026-10-19-html-font-sources-design.md. Each entry: what was decided — why — cost if wrong.

## font-sources
- Ruling (S1): HTML export gets a general, ordered stack of font sources (`FontMode::Sources`), with Google Fonts as the reference provider and Bunny Fonts as a host change — fonts services serve stylesheets, not stable file URLs, so `link` cannot express them, and a stack also mixes licensed local fonts with a service — cost if wrong: a larger API than a Google-only option
- Ruling (S2): every used family is offered to the stack, registered or not, and Papercut keeps no font catalog — catalogs change, a stale list gives wrong answers, and HTML export never required registered families — cost if wrong: a family the host lacks costs its stylesheet request, and the browser falls back silently
- Ruling (S3): a family no source accepts is reported as `font not linked` (`Approximated`) and falls back to the system stack, never to embed — silently embedding adds megabytes the app did not ask for — cost if wrong: a missing family shows in a fallback font until a source is added
- Ruling (S4): opt-in only; the default stays `none` and `Sources` with an empty stack throws; the privacy note (visitors' browsers contact the host, which sees their IP address; LG München I, 3 O 17493/20) is stated where people turn it on; Bunny is shown as a host change, with no claim about anyone's compliance — a third-party request must never come from a default configuration — cost if wrong: an app enables Google without reading the note
- Ruling (S5): Laravel `html.font_sources` is a list of classes, built by the container and validated against `Papercut\Html\FontSource`; the Google and local-fonts sources are bound from `html.google_fonts` and `html.local_fonts` and built only in `sources` mode — class names survive `config:cache`, and a typo in an unused block must not break exports — cost if wrong: a custom source needing config of its own must read it itself
- Ruling (planner): `FontRequest` carries the export's `FontSet`, so `LocalFonts` needs no `FontSet` constructor argument (design §5.7 left this choice open) — the Laravel binding then needs no `FontSet` at build time — cost if wrong: `FontRequest` is slightly larger
