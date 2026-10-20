# Papercut: Font Sources in HTML Export (Google Fonts and Friends) — Design

**Status:** decisions approved by the maintainer, 2026-10-19. This closes the first item in §12 of `2026-10-19-multi-format-export-design.md` ("Google Fonts in HTML link mode"), with a different shape than §12 guessed.

## 1. Intent

HTML export has three font modes today:
- `none`: family names and a system font stack.
- `embed`: base64 TTF, about 2 MB for Inter alone.
- `link`: one `@font-face` per face, with a file URL from the `fontUrl` callback.

None of them produces a small page in the document's real typeface unless the app hosts the font files itself.

Font services such as Google Fonts serve a **stylesheet** (`/css2?family=…`), not font files. That stylesheet points at woff2 files split by character range, and those file URLs are versioned and not stable. This doesn't fit `link`, which needs a stable file URL for each face.

This design adds a fourth mode, `sources`: an **ordered stack of font sources**.
- Each family the document uses goes down the stack, and the first source that accepts it wins.
- A source answers with a stylesheet URL (Google, Bunny, a private registry) or with `@font-face` faces (local font files, embedded or linked).
- Papercut ships two sources:
  - `GoogleFonts`, the reference implementation for stylesheet services. A different `host` turns it into Bunny Fonts or a css2-compatible mirror.
  - `LocalFonts`, the existing embed and link logic as a source, limited to families registered in `FontSet`.
- Other font libraries (Adobe Fonts, an internal registry, a CDN) plug in through the same interface.

A typical stack is `[LocalFonts::embed(families: ['Acme Sans']), new GoogleFonts()]`: a licensed brand font from local files, and everything else from Google.

**Why the sources decide what they host:** Papercut must never keep a list of which fonts a service hosts. Catalogs change, and a stale bundled list would be a maintenance burden and a source of wrong answers. Papercut offers every used family down the stack. Each source says yes (a result) or no (null).

## 2. Goals, non-goals, anti-goals

**Goals**
- A small HTML page in the document's real typeface, Inter included, with a short opt-in in core and in Laravel.
- A general, ordered source stack, with Google as the reference stylesheet source and Bunny Fonts as a host change.
- Local, licensed fonts mixed with a font service in one document, in an order the app chooses.
- Exact requests: each family asks for the weights and italics it needs, and no more.
- Opt-in only, with the privacy cost stated where people turn it on.

**Non-goals**
- Knowing whether a host actually serves a family: no catalog, and no network calls during export.
- Matching PDF metrics. A service's build of a font may differ slightly from the local file, so line breaks on screen can differ from the PDF. HTML is already "close enough" by design.
- Preconnect hints (`<link rel="preconnect">`). They are host-specific, and the gain is small.
- Changing `none`, `embed` or `link`. They stay as they are. `embed` behaves like `sources` with `[LocalFonts::embed()]`; folding them together is a possible later cleanup, not part of this work.

**Anti-goals** (failures even if the feature works)
- Any third-party request from a default configuration.
- Silently embedding font files the app didn't ask for, adding megabytes.
- One bad family breaking the fonts of other families.
- Raw CSS from a source reaching the output. Sources return typed values, and Papercut writes the CSS.
- Any change to PDF output, or to existing HTML golden files.

## 3. Constraints

- Everything in the hardening plan's Global Constraints still applies:
  - PHP `^8.4` and strict types.
  - PHPStan level 8 in both packages.
  - No new runtime dependencies.
  - PDF bytes identical; the golden JSON is untouched.
  - Existing HTML goldens unchanged. New output gets new golden files.
- No network access in the default test suite (CI included).
- No fidelity report or warning for choosing a third-party host. It's a deliberate configuration choice, not a fidelity loss.

## 4. Decisions (made by the maintainer)

### 4.1 Shape: a mode plus an ordered source stack
- New `FontMode::Sources = 'sources'`.
- `HtmlOptions` gains `fontSources`, a `list<FontSource|Closure(FontRequest): ?FontAnswer>`, with the accessor `fontSources()`. Objects and plain closures are both accepted, so `[new GoogleFonts()]` works in plain PHP without `(...)`.
- A core interface `Papercut\Html\FontSource` with `__invoke(FontRequest $request): ?FontAnswer`. `GoogleFonts` and `LocalFonts` implement it, and Laravel validates configured classes against it.
- Order is the app's. Each family is offered to the sources top to bottom, and the first non-null answer wins.

### 4.2 Which families are offered, and what happens when no source accepts
- **Every used family is offered, Inter included.** "Used" means what `HtmlExporter` already collects: families in computed styles, the root family, and the `FontSet` fallbacks (an existing ruling counts fallbacks as used).
- **Registered and unregistered families are both offered.** HTML export doesn't require families to be registered; only PDF fails with `Unknown font family`. An HTML-only document can name `->font('Roboto')` with no font files and get Roboto from Google.
- **Faces requested:**
  - A family registered in `FontSet` (Inter, or a custom face): exactly its registered weight and italic pairs from `FontSet::effectiveFaces()`. They are known to exist, and they match what PDF chooses from.
  - An unregistered family: the weight and italic pairs the document actually uses with that family. `HtmlWriter` already sees every computed style, so it collects these alongside the family names.
- **When no source accepts a family, it is reported, never silently dropped:** `Approximated`, feature `font not linked` (§5.5). The text falls back to the system font stack, as in `none` mode. Nothing is embedded unless a `LocalFonts::embed` source in the stack accepted the family.

### 4.3 Stack order and catch-all hosts
- `GoogleFonts` can't tell whether Google hosts a family, so it accepts every family its allowlist allows (all, by default). A catch-all source therefore ends the stack in practice: nothing below it is ever asked.
- Bunny Fonts makes this stricter. For an unknown family it returns an empty `200` stylesheet, so even the browser can't tell the family is missing.
- The docs give the rule: **specific sources first, catch-all hosts last.** Local fonts and private registries go at the top, Google or Bunny at the bottom. Bunny above Google only makes sense when Bunny has a `families` allowlist.
- Papercut doesn't warn about the order. It is a valid configuration, and Papercut can't know what a custom source hosts.

### 4.4 Privacy
- **Opt-in only.** The default stays `none` in core and in Laravel.
  - In core, `Sources` mode with an empty stack throws, like `Link` without `fontUrl`. A third-party host is therefore always named in code or config.
- **Documented where people turn it on:** the `FontMode::Sources` and `GoogleFonts` docblocks, the Laravel config comment, and a README section in each package.
  - The text says that visitors' browsers request the fonts from the host, and the host sees their IP address.
  - It cites the January 2022 Munich Regional Court ruling on Google Fonts (LG München I, 3 O 17493/20).
- **Bunny Fonts is shown as a host change:** `new GoogleFonts(host: 'https://fonts.bunny.net')`. The README describes Bunny as "a host that states it does not log IP addresses". It doesn't vouch for anyone's compliance.
- **The README names the fully private routes:** `LocalFonts::embed()`, `LocalFonts::link(...)` pointing at your own font files, or the existing `embed` and `link` modes.

### 4.5 Laravel
```php
'html' => [
    'fonts' => 'none',            // none | embed | link | sources
    'image_url' => null,
    'font_url' => null,
    // Used when fonts is sources: an ordered list of classes implementing Papercut\Html\FontSource,
    // built by the container. Each font family goes to the first source that accepts it.
    // Specific sources first; catch-all hosts such as Google last.
    'font_sources' => [\Papercut\Html\GoogleFonts::class],
    'google_fonts' => [
        'host' => 'https://fonts.googleapis.com',   // or https://fonts.bunny.net
        'display' => 'swap',
        'families' => null,                         // null = every family; or a list (allowlist)
    ],
    'local_fonts' => [
        'mode' => 'embed',                          // embed | link (link uses font_url)
        'families' => null,                         // null = every registered family; or a list
    ],
],
```
- **`html.fonts` accepts `sources`** and works with no other setting, because `font_sources` defaults to Google.
- **`font_sources` follows the H3 resolver rules**, applied to each entry:
  - Each entry is a class name, built by the container and validated against `Papercut\Html\FontSource`.
  - Closures are rejected with an error naming the key and its index (`papercut.html.font_sources.1`).
  - A non-list value is rejected naming `papercut.html.font_sources`.
  - An empty list in `sources` mode is rejected.
  - When the container fails, the error reads `Cannot build papercut.html.font_sources.<i> resolver …`.
- **The service provider binds** `GoogleFonts` from `papercut.html.google_fonts` and `LocalFonts` from `papercut.html.local_fonts`. Switching to Bunny, or putting local fonts first, is then a config change with no class to write.
  - `local_fonts.mode = link` uses the `font_url` resolver. It is an error if `font_url` is unset.
- **When things are built and checked:**
  - `font_sources` is built only when `html.fonts` is `sources`.
  - `google_fonts` and `local_fonts` are checked only when their class is in `font_sources`.
  - So a typo in an unused block never breaks exports. With the default `none`, nothing new is built.
  - Errors come when HTML options are first built (the first `->html()` export), not at boot, and they name the full key, for example `Invalid papercut.html.google_fonts.display: 'fast'; expected auto, block, swap, fallback or optional`.
  - `GoogleFonts`' and `LocalFonts`' own constructor exceptions don't know config keys, so the manager or the container factory checks the config first and names the key.
- **No `fonts = google` alias.** Each mode has one name.
- **The preview route** (`?format=html`) uses the same options and needs no change.

## 5. How it works

### 5.1 Types
- **`FontRequest`** (readonly):
  - `family`: the family name as the document wrote it.
  - `faces`: a `list<FontVariant>` of weight (int) and style (`FontStyle`) pairs. Deduplicated, sorted by style (normal first) and then by weight, and never empty (§5.2).
  - `registered: bool`: true when the faces came from `FontSet`.
- **`FontAnswer`** (readonly), built through two named constructors:
  - `FontAnswer::stylesheet(string $url)`: a stylesheet to load.
  - `FontAnswer::faces(FontFaceSrc ...$faces)`: `@font-face` rules for Papercut to write. Each `FontFaceSrc` has a weight, a style, a `src` URL and a format string (`truetype`, `woff2`, …).
  - Sources never return CSS text.
- **`FontSource`**: the interface (§4.1).

The names `FontRequest`, `FontVariant`, `FontAnswer`, `FontFaceSrc`, `FontSource`, `LocalFonts` and `FontMode::Sources` are suggestions. The builder may rename them for consistency with the codebase, but must then use the new names everywhere, docs included.

### 5.2 Collecting the faces
- `HtmlWriter::textCss` already records each family. It now also records the (weight, style) pair from the same computed style, per family. The key is case-insensitive, and the first spelling seen is kept, as `FontRegistry` does.
- A registered family (a case-insensitive match against `effectiveFaces()`) uses its registered faces and ignores usage.
- An unregistered family uses its recorded usage.
- An unregistered fallback family has no usage of its own, so it gets the union of every pair used in the document: glyph fallback can apply to any text.
- The root family gets at least the root style's pair.
- Weights pass through as the document has them (1..1000). Whether a host offers that weight is the host's business (§6).

### 5.3 Walking the stack
- Each family is offered to each source in order. The first non-null `FontAnswer` wins, and later sources aren't called for that family.
- An answer the exporter refuses counts as a decline. It warns, and the next source is asked. The warning follows the style of the refused-`imageUrl` warning: `font source <i> returned an unsafe URL for "<family>"; trying the next source`. These answers are refused:
  - A stylesheet URL that isn't `http:`, `https:` or a relative path. "Relative" follows the `ImageSource::safe` rule: no scheme, and not starting with `//` or `\\`.
  - A face `src` that isn't `http:`, `https:`, a relative path or `data:font/…`.
  - Any URL with control characters or leading or trailing whitespace.
  - A `faces()` answer with no faces.
- If no source answers, the family gets the `font not linked` report.

### 5.4 Output
- **Stylesheet answers:**
  - In a full page, one `<link rel="stylesheet" href="…">` per URL in `<head>`, after the meta tags and before the `<style>` block. The href goes through `HtmlWriter::esc`.
  - In a fragment, one `@import url("…");` per URL at the very start of the `<style>` block, before any other rule (CSS requires `@import` first). The URL goes through `Css::string`.
  - Identical URLs are emitted once.
- **Face answers:** `@font-face` rules in the same form `FontFaces` writes today (family via `Css::string`, `src:url(…) format("…")`, `font-weight`, `font-style`). They go in the `<style>` block after any `@import` rules and before the fixed stylesheet.
- Families are processed in the order they're collected today, so output is deterministic.
- The `font-family` declarations on elements don't change: the family name, then the fallbacks, then `system-ui, sans-serif`.

### 5.5 Fidelity
- Feature string, verbatim: `font not linked`. Level `Approximated`, so it never throws in strict mode. One report for each family no source accepted.
- Path: the node path of the first node that used the family. A family with no node use of its own (a fallback family, or the root family when no node sets a family) uses the path `document`. *This is the designer's call. The builder may pick a better anchor if one exists, as long as the rule is stated in the docblock and tested.*
- Laravel logs it at debug level, as it does every `Approximated` report.

### 5.6 `GoogleFonts`
- **Constructor:** `host: string = 'https://fonts.googleapis.com'`, `display: string = 'swap'`, `families: ?list<string> = null`.
  - `host` must be an `http` or `https` URL with no query or fragment. A trailing slash is trimmed. Anything else throws `InvalidArgumentException`.
  - `display` must be one of `auto`, `block`, `swap`, `fallback` or `optional`, or it throws `InvalidArgumentException`. The default `swap` shows text at once in the fallback font. Google's own default can hide text while fonts load.
  - `families` is an optional allowlist, matched case-insensitively. A family outside it returns null, so the next source is asked.
- **URL:** `{host}/css2?family={name}{axes}&display={display}`, returned as `FontAnswer::stylesheet`.
  - `{name}`: spaces become `+`, and other characters are percent-encoded (`rawurlencode`, then `%20` → `+`).
  - `{axes}`, by case:
    - Only `400 normal`: nothing (`family=Inter`).
    - No italics: `:wght@400;700`, weights ascending.
    - Any italic: `:ital,wght@0,400;0,700;1,400;1,700`, with tuples sorted by `ital` and then by weight. **Google answers HTTP 400 when tuples are out of order,** so the sort is a hard requirement with its own test.
- Verified live on 2026-10-19:
  - `fonts.googleapis.com` and `fonts.bunny.net` both answer `200` for `/css2?family=Inter:ital,wght@0,400;0,700;1,400;1,700&display=swap`.
  - For an unknown family, Google answers `400`; Bunny answers `200` with an empty stylesheet.

### 5.7 `LocalFonts`
- `LocalFonts::embed(FontSet $set, ?list<string> $families = null)` and `LocalFonts::link(FontSet $set, Closure(FontFace): ?string $fontUrl, ?list<string> $families = null)`.
  - **The `FontSet` is passed in, not read from the request.** That keeps `FontRequest` small. If passing the export's `FontSet` makes the Laravel binding awkward, the builder may instead pass `FontSet` through `FontRequest`; that choice is the builder's.
- **Accepts only registered families** (`registered === true`) that are within the allowlist. Anything else returns null.
- **`embed`** returns `data:font/ttf;base64,…` faces. It reuses the existing `FontFaces` embed logic, including the `FontNotFound` error for a missing file.
- **`link`** returns the faces whose `fontUrl` result is non-null. If every face returns null, the source returns null, so the next source is asked.
- **The plain `embed` and `link` modes keep their exact current output.** Their golden files don't change. Whether `FontFaces` is rewritten on top of `LocalFonts` or the two share a helper is the builder's call.

## 6. Known limits (documented, not fixed)
- **Papercut can't tell whether a host serves a family.** An unknown family, or a weight the family lacks (600 on a family with only 400 and 700), makes Google reject that family's stylesheet, and the browser quietly uses the fallback stack.
  - One URL per family keeps the damage to that family.
  - The fix is a `families` allowlist, or a custom source above Google.
- **Catch-all hosts end the stack in practice** (§4.3).
- **A host-side failure produces no report,** because the browser falls back silently.
- **A strict Content Security Policy must allow the host:** `style-src` for the stylesheet, and `font-src` for its font domain. For Google these are `fonts.googleapis.com` and `fonts.gstatic.com`. Embedded faces need `font-src data:`. The README documents all three.
- **Screen line breaks may differ from the PDF** when the host's build of a font differs from the local file.

## 7. Testing
- **Core, unit:**
  - `GoogleFonts` URL building: a single regular face, weights only, italics given out of order, family names with spaces and non-ASCII characters, the allowlist, and an invalid host or display value.
  - `LocalFonts`: an unregistered family returns null; the allowlist; `link` returns null when every face is null; partial `link`; `embed` data URIs.
  - `FontRequest` face collection: registered vs unregistered families, the fallback union, the root default.
- **Core, exporter:**
  - Stack order: the first non-null answer wins, and later sources aren't called for that family.
  - A refused answer warns and falls through to the next source.
  - Link tags in a full page; `@import` first in a fragment's `<style>`; `@font-face` rules after `@import`.
  - One URL per family; identical URLs deduplicated.
  - A family no source accepts → a `font not linked` report with the right path.
  - `Sources` mode with an empty stack throws.
  - Mixed stack: `LocalFonts::embed(families: ['X'])` above Google gives embedded X plus a Google link for Inter.
  - Escaping: a URL containing `"` and `<`.
  - Closures and `FontSource` objects both accepted in `fontSources`.
- **Golden:** new golden files for a sources-mode full page and fragment, including one mixed stack. Existing HTML goldens and the PDF golden bytes stay unchanged.
- **Laravel (Testbench):**
  - `fonts = sources` with the defaults gives Google links.
  - Setting `google_fonts.host` to Bunny changes the host.
  - `font_sources = [LocalFonts::class, GoogleFonts::class]` with a `local_fonts.families` allowlist.
  - `local_fonts.mode = link` without `font_url` is an error.
  - A custom source class works.
  - Each of these errors names its key at first export, not at boot: an invalid `font_sources` entry, a non-list value, an empty list, an invalid `display`, `host` or `families`, and an invalid `local_fonts.mode`.
  - A closure entry is rejected, naming its key and index.
  - With `fonts = none`, invalid `google_fonts` and `local_fonts` values are ignored.
- **Live check (opt-in, never in CI):** a test, skipped unless `PAPERCUT_NETWORK_TESTS=1`, requests the URLs `GoogleFonts` builds for the three examples from both Google and Bunny, and asserts `200`. This catches URL-format mistakes that the unit tests might encode wrongly.

## 8. Spec and doc updates
- **`2026-10-19-multi-format-export-design.md`:**
  - §5.2: `fontMode` values and `fontSources`.
  - §6.1: the Fonts bullet.
  - §7: the HTML cell of the Fonts row.
  - §8.2: the config list.
  - §12: mark the Google Fonts item done, pointing here.
- **`RULINGS.md`:** a new section for this work, starting with the decisions in §4.
- **READMEs:**
  - Core: the font modes, the source stack and its order rule, the privacy note.
  - Laravel: config table rows, the privacy note, a Bunny example, a mixed local-and-Google example, the CSP note.
- **`examples/html/render.php`** keeps `link` mode, and its committed output does not change.
- **`CHANGELOG.md`:** an entry under the unreleased 0.2.0 section.

## 9. Left to the builder
- Class names and namespaces in §5.1, as long as they are consistent.
- Internal structure: how `FontFaces` and `LocalFonts` share code, and where the stack walk lives. `HtmlExporter` must stay thin.
- How `LocalFonts` gets the `FontSet` (§5.7).
- The task split and order in the plan, following the hardening plan's shape.
