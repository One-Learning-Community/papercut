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
