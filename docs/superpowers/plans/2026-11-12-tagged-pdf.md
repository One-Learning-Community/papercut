# Tagged PDF (PDF/UA-1): productionize the spike

Branch `tagged-pdf` (worklog, local), started from `spike/tagged-pdf` (3de4a4d). Findings: `docs/superpowers/2026-11-11-tagged-pdf-spike.md`.

**Dependency.** Needs php-pdf with PR #53 (`enablePdfUa`, struct attributes, link `/Contents`, `placeLinkInStructure`).
Dev uses an UNCOMMITTED Composer path override in `packages/core/composer.json`
(`repositories.php-pdf-fork` → `~/work/olc/php-pdf-pr53`, version faked as 1.99.0). Never commit it: stage files
explicitly, never `git add -A` / `git commit -a`. At release: remove the override, require the php-pdf version that ships #53.

**Upstream methods marked `@internal`** (`addStructElement`, `beginStructContainer`, `endStructContainer`) are used
anyway (decided 2026-11-12). Guards: calls only in `PhpPdfBackend`; a contract test; a soft fallback (warn and write
an untagged PDF) when php-pdf lacks the methods.

## Decisions

- Public API: `new PdfOptions(tagged: true)`. One switch: a structure tree **and** PDF/UA-1 identification
  (`enablePdfUa()`). Docs call the feature "accessible PDF".
- Tagged mode requires a document title and language (`Document::title()`, `->lang()`): missing either throws
  `InvalidStructure` (or the closest existing exception) with a message naming the fix, before any rendering.
- Tags are computed only when tagging (`LayoutContext::$tagged`). Untagged output and every existing test are
  unchanged: the 80 failing tests on the spike branch pass again without editing them.
- `Backend::render()` gains `bool $tagged = false` (Backend is `@internal`). The spike's
  `PhpPdfBackend(tagged:)` constructor parameter goes.
- Heading levels that skip (H1 → H3, or a first heading below H1) produce a warning through `onWarning`;
  in strict mode they throw. Fix the example documents that skip.
- Alt text: `Image::alt()` (exists). `Svg` uses its existing title, `QrCode` its existing label, as alt text when
  non-empty; otherwise they stay artifacts. Markup `<img alt>` → `Image::alt()`. HTML export writes `Image::alt()`
  into `alt=""`. Word export writes it as `wp:docPr/@descr`. Laravel image components accept `alt`.
- Decorator roles: Blockquote → BlockQuote, CodeBlock → Code, Section/Card/Callout → Div (Section → Sect),
  Toc → TOC with TOCI entries. Badge/Stat/Totals/KeyValue/Field/Signature/ChoiceList: leave as their children's roles
  unless a role is obvious.
- Encryption with tagging: PDF/UA needs the accessibility permission (P bit 10) set. Check php-pdf; if a user's
  `Encryption` would clear it, force it on in tagged mode (note in docs).

## Tasks (sequential; each: TDD, full core suite + phpstan green, one commit, no Claude-Session trailer)

1. **Option and plumbing.** `PdfOptions(tagged:)` + `tagged()` (keep `withEncryption` copying it);
   `LayoutContext::$tagged`; `LayoutEngine`/`MarkedItemLayouter`/`TableLayouter` add tags only when tagged;
   `Backend::render(..., bool $tagged = false)`; drop the constructor param; title/lang precondition;
   soft fallback + contract test (a tagged render whose struct tree is read back: Document → H1, P, L/LI/Lbl/LBody,
   Table/TR/TH with /Scope, Figure /Alt, Link with OBJR); encryption permission check.
   Replace `SPIKE` comments, inline FQCNs → `use` imports. Untagged GoldenBytesTest must stay byte-identical.
2. **Heading-skip warning** (+ strict throw) and fix the examples that skip levels
   (guide, paper, responses, transcript under `examples/core`).
3. **Alt text** across Svg, QrCode, Markup, HTML export, Word export, Laravel components.
4. **Decorator roles** as above, plus tests on the struct tree.
5. **veraPDF in CI**: a script that renders every core example with `tagged: true` and runs
   `verapdf/cli --flavour ua1` (docker), and a CI job in `.github/workflows/core.yml` (will pass once the php-pdf
   release is required). Local run must show every example compliant.
6. **Docs**: `site/src/guide/accessibility.md` (+ sidebar), `PdfOptions` docs, CHANGELOG entry. Mention what a
   validator can't judge (alt text quality, reading order).
7. **Final review** (Opus) of the whole branch, fixes, then condense later at release time.
