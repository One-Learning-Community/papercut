# Code blocks with syntax highlighting

Date: 2026-10-29. Status: draft for approval.

## Intent

A dedicated code block: arbitrary source code goes in, it comes out syntax-highlighted by a real language parser, with a small muted label naming the language, and it looks clean on a printed page. It suits any document with code in it: tutorials, handouts, API documentation, reports with snippets.

The maintainer wants this because it is appealing, not because a consumer asked for it: the real documents seen so far had `<pre>` blocks of prose, not source code. That is a deliberate choice, recorded here so nobody re-argues it.

Colours come from TextMate themes, the format VS Code and Shiki use, so authors can pick from the many existing palettes (light, dark, anything) or bring their own, and the same goes for grammars.

## Goals

- A code block with a language, highlighted, in PDF, Word and HTML, each in its native form: coloured text in the PDF, coloured editable runs in Word, coloured `<span>`s in HTML.
- A muted language label inside the block's top-right corner (look A, described below), with two other looks as variants: a header strip and a caption above.
- A clean default look: the palette the maintainer approved (below), shipped as a TextMate theme.
- Any TextMate theme: Phiki's bundled ones by name, or a theme JSON file. A document-wide default plus a per-block override.
- Any TextMate grammar: Phiki's bundled ones by name, or a grammar JSON file registered under a name, plus aliases.
- Works without the highlighter installed: plain monospace text in the same block, label included.

## Non-goals

- Line numbers, highlighted or emphasised lines, diff markers, copy buttons, titles or file names on the block.
- Highlighting inline `code` spans.
- Guessing the language from the content.
- A tokenizer or grammar maintained in papercut. Highlighting is always done by an external, optional package.
- Highlighting a `<pre>` that has no language.

## Anti-goals

- Changing any existing document's output, with one deliberate exception (decided by the maintainer): markup `<pre><code class="language-x">`, which Markdown converters write for every fenced block, becomes a highlighted code block automatically. A `pre` without a language, markup `<pre>` without a language class, and every existing golden stay byte-identical.
- Making the highlighter a hard dependency of `papercut/core`.
- Failing a render because of highlighting outside strict mode: an unknown language, an unknown theme name or a missing package degrades to plain text with a warning. (In strict mode they throw; see "When things are missing".) (A theme or grammar *file* path that is configured but unreadable is a configuration error and may throw, as other bad config does.)
- Word output that isn't native Word (no images of code, no text boxes).

## The highlighter: Phiki

`phiki/phiki` (MIT, PHP ^8.2, actively released; 2.2.1 at the time of writing) is a PHP port of Shiki. It parses with TextMate grammars (247 bundled) and colours with TextMate/VS Code themes (60 bundled: github-light/dark, dracula, nord, one-dark-pro, solarized, catppuccin, tokyo-night, …). A spike confirmed:

- `Phiki::codeToHighlightedTokens($code, $grammar, $theme)` returns lines of tokens, each with the token text and the theme's `foreground`, `background` and `fontStyle` for it.
- Theme JSON carries `colors.editor.background` and `colors.editor.foreground`.
- `Phiki::grammar($name, $path)`, `Phiki::theme($name, $path)` and `Phiki::alias($alias, $for)` register custom grammars, themes and aliases.

A spike (below, under "Performance") also confirmed: every awkward input tried highlighted without crashing; unknown grammar and theme names throw typed exceptions (`UnrecognisedGrammarException`, `UnrecognisedThemeException`); and the text comes back exactly, except that Phiki ends every line with `\n` (the last one too) and turns CRLF into LF. The adapter drops that final newline and normalises line endings before highlighting, so text out equals text in.

It is a `suggest` of `papercut/core` (and documented as the package to install), never a `require`. It weighs about 10 MB installed, which is another reason it stays optional.

Papercut talks to it through a small interface of its own (`Highlighter`, with a Phiki implementation), so another highlighter could be added later without touching the block. The interface's shape is the builder's call. It must give, per line, a list of tokens (text, colour, bold, italic, underline), plus the theme's background and foreground colours.

## The block

### Look (decided: mockup A)

The block is a box with a tinted background, a hairline border, a small corner radius and padding. Inside it:

- The code is in the theme's mono family (bundled Cousine by default) at 0.9em, with `white-space: pre-wrap`, the same as today's `pre`. Long lines wrap; spaces and tabs are kept; tabs advance to multiples of four spaces.
- The language label is in the body font, small (about 0.7em), uppercase, semi-bold with slight letter spacing, in a muted colour, at the top-right corner inside the padding.

The label must never overlap the code. Decided while planning: the label is a small right-aligned line of its own at the top of the box, kept with the code. A row beside the code would turn into a table in Word, and a line of its own is native in all three formats.

The approved default palette (the default theme's token colours, on a light tint):

| Token kind (TextMate scopes, roughly) | Colour |
|---|---|
| keyword, storage, `new` | `#8250df` |
| string | `#0a7a4b` |
| comment | `#8b949e`, italic |
| function / method name | `#0550ae` |
| variable | `#953800` |
| number, constant | `#0550ae` |
| type, class name | `#6639ba` |
| markup tag name | `#116329` |
| markup attribute name | `#0550ae` |
| other text | `#24292f` |
| block background | `#f6f8fa`; border `#e2e8f0` |
| label | `#94a3b8` |

### Variants: three looks (decided)

The maintainer saw three mockups and wants all three, chosen through the existing decorator variants (`variant="…"` in Blade, `->variant('…')` in PHP):

- **Default (A):** the look above. The label sits in the box's top-right corner.
- **`strip` (B):** a thin header bar across the top of the box carries the label, left-aligned. The bar is a slightly deeper tint than the code area, with a hairline under it, like a file tab.
- **`caption` (C):** the label sits above the box as a small caption. The box has no border or radius, just the tint and a 2pt left rule in the border colour.

Variants usually only add styles. Here the variant also decides where the label goes, so the block reads its own variant names when it renders, in addition to applying the `<part>.<variant>` styles. Each look's spacing, tints and rules live in those variant styles. A part the block needs only for one look (the strip's bar, say) is a part of its own, styled through the same mechanism. The variant names `strip` and `caption` are decided; part names are the builder's call. Combining `strip` and `caption` throws `InvalidVariant` (my call; today an unstyled variant is silently ignored, so this is a check of the block's own).

For reference, the mockups used these tints for B and C: strip bar `#f1f5f9` over a code area of `#f8fafc`; caption tint `#f8fafc` with a rule of `#cbd5e1`. On the default theme the derived colours should come out close to them. The mockups themselves are not committed (they live in the git-ignored `.superpowers/brainstorm/`); this section describes them in full. The theme's colours (below) feed all three looks: the strip's bar and the caption's rule come from the same YIQ table as the border.

### Colours from the theme

- Token colours, bold, italic and underline come from the TextMate theme.
- The block's background is the theme's `editor.background`, and the default text colour its `editor.foreground`. So `github-dark` gives a dark block and `solarized-light` a cream one.
- The label, border, strip-bar and caption-rule colours follow from the YIQ brightness of the theme's editor background, `(299·R + 587·G + 114·B) / 1000` (decided by the maintainer):

  | Background | Label | Border | Strip bar | Caption rule |
  |---|---|---|---|---|
  | light (YIQ ≥ 128) | `#94a3b8` | `#e2e8f0` | `#f1f5f9` | `#cbd5e1` |
  | dark (YIQ < 128) | `#64748b` | `#334155` | `#1e293b` | `#475569` |

  The light row is the mockups' values. The dark row is my call, from the same slate scale.

### Default theme

Papercut ships its own small light TextMate theme with the palette above, under a name like `papercut-light` (exact name is the builder's call). It is the default when no theme is named. Without Phiki installed, the plain block uses this theme's background, foreground and label colours, so it looks the same, just uncoloured.

### Styling through the papercut theme

The block is a decorator with styled parts, like `Callout`, so the usual mechanisms apply: theme styles, `style=""`, and variants. The parts are the block box and the label; names like `code-block` and `code-block.label` are the builder's call. Padding, radius, border, font size, margin and the label's size all live in these styles.

My call on precedence: the TextMate theme owns every colour of the block (background, text, label, border, the strip's tint, the caption's rule). The built-in styles (`DefaultStyles` and the variant styles) hold geometry only: padding, radius, border width, font size, margin and the label's size. They hold no colours. The one way past the TextMate colours is the block's own style, set on that block (`style=""` attributes in Blade, `->background()`/`->color()` and the like in PHP), which wins for that block. To recolour every block, authors pick or write a TextMate theme. That keeps one source of truth for colour and needs no tracking of where a style property came from.

My call: the code block's styles are its own, not stacked on `pre`. Its geometry defaults start from today's `pre` values (0.9em mono, the same padding and margin), so a block without a language and a code block sit alike on the page.

### Pages

A block taller than the space left breaks between lines like any `pre`. The label appears on the first part only. Orphan and widow control is whatever `pre` text does today.

### Label text

The default is the language's display name: the grammar's own display name where Phiki has one (`PHP`, `JavaScript`, `Shell`), else the language id as written. My call: authors can override it with any text (`label="composer.json"`), or turn it off (`label=false` in Blade, `->label(false)` or `null` in PHP). Without a language there is no default label, but an explicit one still shows.

## Entry points

- **PHP:** a decorator, for example `CodeBlock::make($code)->language('php')->theme('github-dark')->label('…')`. The exact class and method names are the builder's call; they should read like the other decorators.
- **Blade:** `<x-doc::pre language="php">` becomes a code block, with optional `theme="…"`, `label="…"` and `variant="strip|caption"`. It keeps all of today's slot behaviour: the literal slot, entity decoding, and the indentation strip with its U+E000 start mark. `<x-doc::pre>` without `language` is unchanged.
- **Markup:** `<pre><code class="language-php">` (the Markdown and highlight.js convention; `lang-php` too) becomes a code block automatically (decided). The class must be on the `code` element or on the `pre`. A `<pre>` without a language class is unchanged. Unknown class words are ignored. A `MarkupOptions` switch turns this off, giving today's plain `pre`, with a Laravel config key `papercut.markup.highlight` (default on).
  - Fence words that name a diagram or other non-code language (`mermaid` and the like) are just unknown languages: plain text, label as written, and a report. My call: only the plain-text words above are exempt.
  - My call: markup can't set a per-block theme, label or variant (no `data-` attributes). Markup blocks use the document-wide defaults: the default theme, the language's display name, and the document-wide default variant below.
  - My call: if the `code` element has child elements (spans from a server-side highlight.js or Prism run, `<mark>`, `<a>`, `<b>`), its text content is highlighted afresh and those elements are dropped. If the dropped elements included anything other than spans (links, marks, emphasis), that is reported as approximated ("markup inside a code block"). Pre-highlighted HTML is common, and re-highlighting gives it the document's theme.

## Choosing themes and registering grammars

- **Document-wide default theme:** in a `CodeHighlighting` options object in core, and `papercut.code.theme` in Laravel config. The per-block `theme` overrides it. (Decided while planning: decorators render without access to the papercut `Theme`, so the defaults travel in this object instead. It is handed to each block and to `MarkupOptions`, and Laravel builds it once from config.)
- **Document-wide default variant** (my call, so markup blocks can have the strip or caption look): in `CodeHighlighting` and `papercut.code.variant` in Laravel (`null`, `strip` or `caption`). A block's own `variant` overrides it.
- **Custom themes and grammars:**
  - In Laravel: `papercut.code.themes` and `papercut.code.grammars`, each a map from name to file path, and `papercut.code.aliases` (alias => language).
  - In PHP: register them on papercut's highlighter, or hand papercut an already-configured `Phiki` instance.
  - After registration, `language="mylang"` and `theme="my-theme"` work like the bundled ones.
- **Injection grammars:** VS Code lets a grammar inject itself into others with `injectionSelector` plus `injectTo` in the extension's manifest. Phiki has no `injectTo`; it only honours a host grammar's own `injections` map. Papercut supports injection grammars by merging them into their hosts: an injection grammar is registered with the scope names it injects into, and before highlighting, its patterns go into each host's `injections` under its `injectionSelector`, with its repository merged in. Laravel: `papercut.code.injections`, a list of `['path' => …, 'into' => ['source.x', …]]`. PHP: an equivalent registration method. The spike proved the merge works: injected scopes apply at every nesting depth, as in VS Code. If a repository key would collide on merge, the injection's entries are namespaced (builder's call how), never silently overwriting the host's.
- An embedded language a grammar includes by scope name (`source.ts`, `text.html.basic`, `source.css.scss`) resolves to Phiki's bundled grammars, or to registered ones. Nothing extra is needed.
- How the highlighter and the default theme reach the block at render time (export options, the theme, or something else) is the builder's call. Two constraints: no new global mutable state, and Laravel wires it from config with no app code.

## When things are missing

Each of these is a fidelity report, "dropped syntax highlighting" with the reason, once per document (per language or theme name), and the block still renders. My call: being a dropped feature, it throws in strict mode, as other dropped features do. That way CI with `strict: true` catches a mistyped `language="phpp"` or a missing package.

| Situation | Result |
|---|---|
| Phiki not installed | Plain text in the default-theme block, label shown. The warning says to install `phiki/phiki`. |
| Unknown language | Plain text in the block; the label shows the language as written. |
| A plain-text fence word: `text`, `plaintext`, `txt`, `plain` | The block and its label ("Text"), no colours, and no report. |
| `nohighlight` or `no-highlight` (the highlight.js opt-out) | The block without a label, no colours, and no report. |
| Unknown theme name | The default theme is used. |
| Theme or grammar file path configured but unreadable or invalid | Configuration error, thrown with the config path in the message, like other bad config. |

## Performance

Spike results on a laptop, PHP 8.4, Phiki 2.2.1, one fresh `Phiki` per case:

| Input | Time |
|---|---|
| Snippets of 10–80 lines (PHP, TypeScript, JavaScript, Python, SQL, JSON, HTML, Bash, YAML) | 4–65ms each |
| A 416-line TypeScript file | ~0.9s |
| A 1,234-line PHP file | ~2.6s |
| One 3,500-character minified line | ~1.1s |
| 6,000 nested brackets | ~2.9s |
| Custom grammars with embedded languages, 50–100-line files | 66–208ms |

Peak memory was 28MB. Over half the time went into applying the theme: Phiki's `ParsedTheme::match()` runs the whole theme for every token, and tokens repeat their scope combinations heavily (17,779 tokens, 638 distinct combinations, in the PHP file).

Decided: papercut does no performance work of its own for now. The adapter calls Phiki's `codeToHighlightedTokens()` as it is: no theme-matching memo, no output cache and no size guard. Typical snippets take tens of milliseconds, which is fine. The theme-matching fix goes upstream (Part 2), and papercut gets it by upgrading Phiki. A cache or a size limit can be added later if real documents need it.

## Formats

- **PDF:** coloured spans in the existing text layout. Bold and italic tokens use Cousine's bold and italic faces, which are bundled. The highlighting changes no layout: a highlighted block and the same code without colours wrap and break identically.
- **Word:** native. The code is paragraphs with the block's shading (`w:shd`) and border, coloured runs, kept spaces, real tabs and `w:br` as `pre` does today; the label is a native element too. How the box and label are built is the builder's call, within the "native Word" rule. Small visual drift from the PDF is acceptable and goes in the fidelity notes where it matters.
- **HTML:** `<pre>` with `<code>`, tokens as `<span>`s with inline colours, and the label as a small element in the corner. Background and colours are inline.

## Part 2: the upstream Phiki PR (separate, last)

After the feature works, a pull request to `phikiphp/phiki` (branch `2.x`) from the maintainer's fork `serpentblade/phiki`, opened only on the maintainer's go-ahead:

- **What:** memoise theme matching within one `Highlighter::highlight()` call. Keep a local array keyed by theme id and scope combination, so each distinct combination is matched once per highlight.
- **Why it's acceptable to upstream:** no state on `ParsedTheme` or any other object. `Phiki` is a singleton in Phiki's own Laravel adapter, so a memo on the theme would live for a worker's whole life and grow across requests. A local array lives for one call only.
- **Evidence:** the spike numbers above, and its tests: identical output with and without the memo.
- **Once it's released:** papercut benefits by raising its suggested Phiki version; no code change.

Nothing in papercut waits on this PR.

## Docs

- A "Code blocks" section in the guide covering install, language, label, themes (bundled names with a pointer to Phiki's list), custom grammars and themes, and the plain fallback.
- The Blade tag reference and the config reference.
- `suggest` in core's composer.json, and a CHANGELOG entry.

## Testing

- Unit tests for the Phiki adapter and the token → span mapping: colours, bold, italic, line breaks, tabs, and trailing newlines.
- Markup: `language-x` becomes a code block by default and stays `pre` with the switch off; the plain-text and `nohighlight` words; a pre-highlighted `code` with spans, and one with a link.
- Strict mode throws for each dropped-highlighting case.
- The fallbacks: no Phiki (simulate the class being absent, or put an injectable seam in the adapter), unknown language, unknown theme, custom grammar, custom theme, alias.
- One golden per format for a highlighted block on the default theme, one on a dark theme, and one each for the `strip` and `caption` variants.
- Existing goldens unchanged (anti-goal). A golden that changes outside the new fixtures is a bug.
- Laravel tests for `<x-doc::pre language>` and the config keys. Markup tests for `language-x` and `lang-x` on `code` and on `pre`.
- An injection grammar applies inside nested contexts of its host, and a repository key collision doesn't overwrite the host's.
- CI must run the Phiki-dependent tests with Phiki installed (as a dev dependency) and the fallback tests without it, or with the seam.

## Left to the builder

Class, method and style-part names; the `Highlighter` interface's shape; how the highlighter and the default theme reach the block; the label's layout (never overlapping); the exact label and border colour derivation; the default theme's file name and its TextMate scope rules (matching the palette table); how the Word box and label are built; caching Phiki instances per export; how injection repositories are namespaced.
