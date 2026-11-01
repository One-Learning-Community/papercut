# Theme overrides per document and per page

## Why

Today one theme serves every document an app makes: `PdfOptions(theme:)` in PHP, `papercut.theme` and `papercut.styles` in Laravel. People asked to change theme values for one document: a different body size for a dense report, a brand colour for one customer's invoice, a different heading font for a certificate. Changing the app-wide config for that is wrong, and building a whole `Theme` and passing options for every render is clumsy, and impossible from a Blade view.

The same need shows up inside a document: a cover page or an appendix set in a different font or size than the body.

## What it does

Theme values merge down through three levels:

1. **The base theme**: what exists today (`Theme::default()`, `PdfOptions(theme:)`, Laravel's `papercut.theme` and `papercut.styles`).
2. **The document**: overrides that apply to every page of one document.
3. **The page**: overrides that apply to one page section (`Page`, `<x-doc::page>`): its body, headers, footers, background and overlay.

Each level only names what it changes. Anything it leaves out comes from the level above.

### What can be overridden

The same values the Laravel config exposes, plus named styles:

- font family, mono family, font size, colour, line height
- named styles (`card`, `callout.warning`, `h1`–`h4`, your own names, ...)
- the code highlighting theme (`code_theme` in Laravel, `codeTheme` in core): the default for code blocks that don't name one. A block's own `theme` still wins; then the page, the document, and `papercut.code.theme` (or `CodeHighlighting`'s theme in core). An unknown name falls back to the default theme with the same warning a block's unknown theme gives today.

In core, also the values `Theme::with()` already takes (font weight, cell padding, min lines), since they cost nothing extra there.

### Named styles merge, they don't replace

A named style in an override merges onto that name as the level above resolves it (including built-in decorator defaults). Overriding `callout.warning` with only a background keeps its border and padding. A name that exists nowhere above is simply defined at that level. *(Builder note: this differs from `papercut.styles`, which replaces a decorator default outright; that stays as it is.)*

### Headings become themeable

Today heading looks are fixed in `Theme::heading()` (24/18/14/12pt, bold, keep-with-next) and no theme style can change them. This feature adds named styles `h1` to `h4`, at every level: `Theme::withStyle('h1', ...)`, `papercut.styles`, and document and page overrides.

- Layering for a heading: the built-in heading look, then the theme's `h<n>` style if one is defined, then the node's own style name, then its own `style=`.
- `h<n>` applies only to headings of that level (the `h1`–`h4` presets; markup's `h5` and `h6` use `h4`, as they already do). Using `style="h1"` on other nodes keeps working as any named style.
- Without an `h<n>` style defined anywhere, output is unchanged. An app that already defined a custom style called `h1`–`h4` will see its headings pick it up; the CHANGELOG says so.
- The table of contents still finds headings by level, not by style.

## API

### Core (PHP)

```php
use Papercut\Style\ThemeOverride;

$doc = Document::make($cover, $body)
    ->theme(new ThemeOverride(fontSize: pt(11), color: Color::parse('slate-800')));

$cover = Page::a4()
    ->theme(new ThemeOverride(fontFamily: 'Merriweather', styles: ['h1' => Style::make(fontSize: pt(36))]))
    ->body(...);
```

- `ThemeOverride` is an immutable value object, all fields optional, named like `Theme::with()`, plus `codeTheme` and `styles: array<string, Style>`. Sizes are `Length`s (`pt(11)`), as everywhere else in the PHP API, even though `Theme` keeps points internally.
- `Theme::withOverride(ThemeOverride): Theme` does the merge. (`apply` is already the verb for applying a named style to a node.)
- `Document::theme()` and `Page::theme()` set it; calling again merges with the earlier override (later wins), consistent with the rest of the fluent API.

### Laravel

```blade
<x-doc::document :theme="['font_size' => 11, 'color' => 'slate-800']">
  <x-doc::page :theme="['font_family' => 'Merriweather', 'styles' => ['h1' => ['font-size' => '36pt']]]">
```

```php
Papercut::view('pdf.invoice', $data)->theme(['color' => $tenant->brandColor])->pdf();
```

- Keys and values are those of `papercut.theme`, plus `styles` in the `papercut.styles` shape. Invalid values fail the same way the config does, pointing at the tag or call.
- `->theme([...])` on a pending document merges on top of the view's `<x-doc::document>` override, so the controller has the last word for the document. Page overrides still apply after both. Order: base → view's document override → controller's `->theme()` → page.
- Blade's early checks (unknown style names, variants that style nothing) see the overrides of the document and page they're in. A controller's `->theme()` runs after the view has rendered, so it can change theme values and restyle names that already exist, but a new style name a view uses must be defined in config or in the view's `:theme`; otherwise the view's early check fails as it does today. *(Planner's call, amending an earlier draft that deferred such names to export: keeping the early check keeps Blade's errors pointing at the tag.)*
- On `<x-doc::pre>`, `theme` keeps meaning the code block's colour theme; the document theme's key for it is `code_theme`.

## Per format

- **PDF**: each page section lays out with its own effective theme.
- **Word**: Papercut already writes every paragraph's values directly; the document defaults (`docDefaults`) come from the document level. A page override just changes the values written in that section. Word-native, no parity concern.
- **HTML**: each page section's root element carries the declarations where its effective root style differs from the document's, so the cascade does the rest; elements inside resolve against their section's theme.

## Behaviour to keep

- A document without overrides and without `h1`–`h4` styles renders byte-identically in all three formats: goldens and snapshots unchanged.
- The table of contents lists headings as styled on their own pages; its own entry styles (`toc.*`) come from the page that holds it.
- A font family named in an override must be bundled or registered, checked the same way as `mono_family` in config.

## Non-goals

- Overrides on arbitrary subtrees (a `View`). Named styles and `style=` already cover that.
- Changing page size, margins or other page geometry through the theme.

## Left to the builder

- How the PDF layout switches theme per page section (the layout context and its style resolver are built once per run today), and how memoized styles stay shared within a section.
- How the Blade components find the enclosing document's and page's overrides for their early checks.
- Test and docs placement; a guide section in `site/src/guide/styles.md` and the Laravel docs.
