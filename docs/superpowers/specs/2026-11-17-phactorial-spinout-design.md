# Phactorial: the math library leaves Quoin

## Intent

The math library (`packages/math`, `quoin/math`) typesets TeX and MathML in plain PHP and writes SVG, PNG, MathML,
TeX, Word equations and text. It does not depend on the rest of Quoin, yet it is named and documented as Quoin's
equation engine, so nobody searching for "PHP LaTeX to SVG" finds it.

It becomes its own product, **Phactorial**, in its own monorepo. Quoin uses it like any other dependency. The new
monorepo also hosts integrations with other tools, and the first one ships at launch: a league/commonmark extension.
league/commonmark is downloaded about 27 million times a month and backs Laravel's `Str::markdown()`, so an extension
puts Phactorial in front of people who would never search for a math library.

## Background

- Demand research (2026-11-17): no league/commonmark math extension exists, of any kind. The only request in the
  commonmark tracker, issue #1053 (2024-10), drew a maintainer reply declining math in-repo and offering to list a
  third-party extension in the README. The PHP Markdown math packages that exist have tiny download counts. The no-JS
  need (email, RSS, print, CSP-locked pages) is real but small. This is an adoption bet, not answered demand.
- `phpoffice/math` (25M downloads, via PHPWord) is a neighbor, not a rival: it reads and writes MathML and OMML for ten
  element types, with no TeX and no rendering.
- A spike of the extension works: branch `spike/commonmark-math`, `packages/commonmark` (uncommitted). It parses
  GitHub's syntax and renders through `MathRender`. Findings are below under the extension.
- `quoin/math` 0.17.0 shipped on 2026-11-17. It has 16 downloads in total. `MathRender` is merged and unreleased.

## Goals

1. A new monorepo, `One-Learning-Community/phactorial`, holding the library and the CommonMark extension, each split
   to its own read-only repository and published on Packagist, the way Quoin publishes its packages.
2. The library's git history, from its first commit in Quoin, carried into the new repository.
3. The namespace `Quoin\Math` renamed `Phactorial`, and the package `quoin/math` renamed `phactorial/phactorial`.
4. `phactorial/commonmark`: GitHub's math syntax in league/commonmark, typeset on the server, tested and documented.
5. Phactorial's own docs site, holding the moved math docs and the extension's docs.
6. Phactorial 0.18.0 released, carrying `MathRender` and the extension. Then Quoin 0.18.0 released, requiring
   `phactorial/phactorial` instead of containing it.
7. `quoin/math` abandoned on Packagist in favor of `phactorial/phactorial`.

## Non-goals

- Other integrations (Blade directive, Twig filter, PHPWord bridge, Parsedown). The monorepo is laid out to take them;
  none is built now.
- Client-side rendering or any JavaScript in the extension's output.
- New math features. The move changes names, not behavior.
- A compatibility shim: no `quoin/math` package that aliases the old class names. Both are pre-1.0 and `quoin/math`
  has almost no users.

## Anti-goals

These would make the work a failure even if everything technically runs:

- Any change to what Quoin draws. PDF, Word and HTML output with equations must be byte-identical before and after
  (golden tests), apart from what the namespace rename forces in the source.
- Lost history: blame on a moved file must reach back to its Quoin commits.
- Quoin's CI depending on a sibling checkout of Phactorial. CI installs Phactorial from Packagist.
- A release order that leaves a published Quoin requiring a Phactorial version that does not exist.
- Consumer-specific content in tests, docs or examples; both packages are public.

## Approaches considered

**A. Clean cut (chosen).** Filter the history into a new repo, rename the namespace, delete `packages/math` from
Quoin, and have Quoin require Phactorial from Packagist. The cost: a change that spans both repos becomes two commits
and two releases, Phactorial first.

**B. Shim package.** As A, with `quoin/math` kept as a thin package that requires Phactorial and aliases the old class
names. Rejected: it protects about 16 installs at an ongoing maintenance cost, and both projects allow breaking changes
before 1.0.

**C. Keep a copy inside Quoin** through a git submodule or subtree, so both change in one commit. Rejected: submodules
burden every clone and CI run, subtree syncs fight the split workflow, and the coupling is what the move ends.

## Decided

These were settled with the user:

- The name is **Phactorial**, final.
- Approach A.
- A separate monorepo, laid out like Quoin's, with packages split to read-only repositories.
- Hold `MathRender`: it ships as Phactorial's first release, not as `quoin/math` 0.18.0.
- The first Phactorial version is **0.18.0**, continuing `quoin/math`'s line. After it, the two projects version
  independently. The lockstep rule in the 1.0 API decisions keeps covering Quoin's own packages, not Phactorial.
- A full launch: the library, the CommonMark extension and the docs site go out together.
- Branding at launch is a wordmark: "Phactorial" set in STIX Two (the font the library draws with), used for the site
  header, favicon and social card. A designed logo may follow later; it does not block the launch, and the launch
  does not borrow Quoin's look.

## Design

### Repository layout

```
phactorial/
  packages/
    core/          phactorial/phactorial   namespace Phactorial\
    commonmark/    phactorial/commonmark   namespace Phactorial\CommonMark\
  site/            VuePress docs, deployed to GitHub Pages
  .github/workflows/
  CHANGELOG.md  CONTRIBUTING.md  LICENSE  NOTICE.md  README.md
```

- Package names: the library is `phactorial/phactorial`, so the install line is `composer require
  phactorial/phactorial`. The directory is `packages/core`. Split repositories: `phactorial-core` and
  `phactorial-commonmark`, under One-Learning-Community.
- The two packages version in lockstep with each other: one tag versions both, as Quoin does.
- `NOTICE.md` carries the STIX Two Math notice (OFL) that Quoin's `NOTICE.md` has for this font now. Quoin keeps its
  entry, because Quoin embeds the font in PDFs through Phactorial.
- License: MIT, as now.

### History

`git filter-repo` on a local clone of Quoin with every branch (not a GitHub clone, which carries only pushed work) keeps `packages/math` (renamed to `packages/core`) and the math docs in
`site/src/math` (renamed into `site/src`). The namespace rename is a commit on top, so history up to it is the Quoin
history unchanged. Spec and plan documents about math stay in Quoin's history; they are not carried over.

- Local math worklog and spike branches (`tex`, `mhchem`, `mathml`, `matrices`, `cff-math`, `math-render`,
  `integral-limits`, `spike/tex`, `spike/cff-math`, `spike/math`) come through filtered, as local branches in Phactorial,
  never pushed, as in Quoin. Nothing on them needs landing first: the tests on `integral-limits` reached main already.
- Quoin's version tags are dropped from the filtered repository, so its release workflow cannot republish old versions.
- Math changes in Quoin are frozen from the filter until Quoin switches to the released Phactorial.

### Namespace and API

- Every `Quoin\Math\…` class becomes `Phactorial\…`, with the same relative path: `Quoin\Math\MathRender` becomes
  `Phactorial\MathRender`, `Quoin\Math\Font\Font` becomes `Phactorial\Font\Font`.
- The deprecated `TrueTypeFont` alias and `src/aliases.php` are dropped: the namespace changes anyway, so the old name
  cannot resolve.
- Nothing else about the API changes.
- Strings and docs that say "quoin/math" or describe the library as part of Quoin are rewritten. Following the
  existing prose rule, comments and messages name the actor (the reader, the renderer), not the project.

### CI, split and release

The new repository gets the same machinery Quoin has, adapted:

- A test workflow per package: PHPUnit and PHPStan on PHP 8.4 and 8.5. The library's fuzz group stays out of the
  default run, as now.
- A split workflow pushing each package to its split repository, triggered on `main` and on tags. It needs a
  `SPLIT_TOKEN` secret with write access to the two split repositories.
- A release workflow on `v*` tags: the CHANGELOG must have a dated section for the version, the split repositories are
  tagged, and a GitHub release is created, marked as a pre-release while the version is 0.x.
- A docs workflow deploying the site to GitHub Pages.

### The CommonMark extension

`phactorial/commonmark` registers GitHub's math syntax with league/commonmark (^2.6) and typesets each formula
through `MathRender`.

**Syntax**, matching GitHub so Markdown written for GitHub renders the same:

| Source | Result |
|---|---|
| `$…$` | inline math |
| `` $`…`$ `` | inline math, for content with dollar signs or awkward characters |
| `$$…$$` within a paragraph | display math |
| a block opened by a line of just `$$` and closed by a line ending in `$$`, or a line of only `$$…$$` | display block |
| a fence with info string `math` | display block |

Dollar rules: a single `$` opens only before a non-space character and closes only after a non-space character that is
not followed by a digit, so "costs $5 and $10" stays text. Single-dollar math never spans a backtick, which leaves code
spans to the code parser. `\$` stays a literal dollar sign. Fences with other info strings, and code spans, are
untouched.

GitHub's rendered output is the reference, not its docs, which are thin. The edge cases are sent once to GitHub's
Markdown API (`POST /markdown` with `mode: gfm`) and the results pinned as fixtures. Where GitHub's output disagrees
with the rules above, GitHub wins and the rules change to match.

**Output.**
- An inline formula is a `<span class="math math-inline">`.
- A display formula inside a paragraph is a `<span class="math math-display">`, because a `<p>` cannot contain a
  `<div>`. The docs give the one CSS rule that makes it a block.
- A display block is a `<div class="math math-display">`.
- Inside the wrapper is either SVG from `MathRender::svg()` (default: the same look in every browser, mail client and
  feed reader, about 4 KB per formula) or MathML from `MathRender::mathml()` (small, selectable text, drawn in the
  browser's math font; Chrome draws matrices and accents less well).

**Configuration** through league/commonmark's configuration schema, under a `math` key, so it works with
`Str::markdown($text, ['math' => ['output' => 'mathml']], [new MathExtension()])`:

| Key | Values | Default |
|---|---|---|
| `output` | `svg`, `mathml` | `svg` |
| `size` | CSS pixels to the em, above zero | `16` |
| `on_error` | `source`, `render` | `source` |

**Errors.** `MathRender` never throws on bad input; it reports what it could not draw in `notes()`. When a formula has
a `Dropped` note and `on_error` is `source`, the extension writes the formula's TeX, escaped, in
`<code class="math math-error">` with the original delimiters, so a typo shows as source rather than a half-drawn
formula. With `render` it draws what it can.

**Untrusted input.** Markdown is often user-written, so formulas are untrusted input. The library's existing fuzz
hardening covers the TeX reader. The extension adds no new exposure: all text it emits is escaped or comes from the
renderers, which escape. Tests show that `html_input: escape` documents cannot inject markup through math. The
hostile-input stopping rule applies: only exponential or unbounded cost, crashes and throws block a release.

**Spike findings** carried into the build:
- Without the backtick rule, a dollar in prose plus a code span containing `$x$` read as one formula.
- Inline `$$…$$` must not produce a `<div>` inside a `<p>`.
- The renderer is registered for `FencedCode` ahead of the stock renderer and returns null for non-math fences, so
  league/commonmark falls through to the stock code renderer.
- Cost on a 12-formula page: SVG takes 15 ms cold (font load) and 1 ms warm per document; MathML takes 0.6 ms warm.
  The page without the extension takes 0.24 ms.

**Tests.** A table of edge cases for the dollar rules (prices, adjacent dollars, escapes, code spans, emphasis and
links around math, math in lists and block quotes, unterminated delimiters, `$$` blocks interrupting paragraphs and
inside list items), GitHub-matching cases, configuration, the error fallback, and an XSS case.

### Docs site

A VuePress site in `site/`, built like Quoin's, at `https://one-learning-community.github.io/phactorial/`.

- The wordmark (see Decided) in the header, as the favicon and on the social card.
- A home page leading with the one-call example and what Phactorial reads and writes.
- The moved math pages (input, output, recipes, backends), renamed.
- A CommonMark page with Laravel and plain league/commonmark setup.
- A one-line note placing `phpoffice/math` as a converter that does not typeset.
- Prose follows the beautiful-prose skill and US English.

### Quoin's side

- Delete `packages/math`. Core, Laravel and DOCX require `phactorial/phactorial: ^0.18` from Packagist and use the new
  namespace. The path repositories for `quoin/math` go away.
- Local co-development: a documented way to point Quoin at a sibling Phactorial checkout without committing it (for
  example, a `composer config` path repository that CONTRIBUTING explains how to add and remove). Committed composer
  files always install from Packagist.
- Workflows: drop `math.yml`, the math split from `split-packages.yml`, and math from `release.yml`.
- CONTRIBUTING: remove math from the package list and the release steps; add the co-development note.
- Site: the `/math/` section goes, each page replaced by a stub that redirects to its Phactorial page. The nav's "Math" entry links to the Phactorial site. Guide pages keep how equations
  work in Quoin documents (`guide/equations.md`) and link to Phactorial for the TeX and MathML reference.
- CHANGELOG `[Unreleased]`: the `MathRender` entries move to Phactorial's changelog. Quoin's entry says that math moved
  to Phactorial and that code naming `Quoin\Math\…` must rename it.
- Golden tests prove the output unchanged, including after the STIX Two Math font moves into `vendor/` with the
  package.

### Order of release

1. Build Phactorial locally: filtered history, rename, CI, extension, site.
2. The user creates the GitHub repositories (monorepo and two split repositories) and the split token, enables Pages,
   and submits both packages to Packagist.
3. Tag Phactorial v0.18.0. Its CHANGELOG starts at 0.18.0, with a line pointing to Quoin's changelog for the library's
   history through 0.17.0.
4. Switch Quoin to the released `phactorial/phactorial ^0.18`, run every suite on PHP 8.4 and 8.5, and release Quoin
   0.18.0.
5. Mark `quoin/math` abandoned on Packagist, pointing to `phactorial/phactorial`. Archive the `quoin-math` split
   repository, with a README pointing to Phactorial.
6. Ask the league/commonmark maintainer to list `phactorial/commonmark` in the README, as offered in issue #1053.

Every step that publishes (creating repositories, pushing, tagging, Packagist, the commonmark request) waits for the
user's yes.

## Left to the builder

- The exact `git filter-repo` invocation, and whether `site/src/math` history comes through cleanly or needs a
  separate pass.
- Workflow details, matching Quoin's where they translate.
- Site theme and structure, following Quoin's site.
- The local co-development mechanism, if a cleaner one than a `composer config` path repository exists.
