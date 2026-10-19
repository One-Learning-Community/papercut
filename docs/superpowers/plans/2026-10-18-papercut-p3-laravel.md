# Papercut Plan 3: Laravel Package (`papercut/laravel`)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `<x-pdf::*>` Blade tags the flagship authoring surface. They compile straight into Papercut's node tree, with no HTML involved. `Pdf::view('pdf.invoice', $data)->download('invoice.pdf')` works in any Laravel 12/13 app.

**Architecture:** Each `<x-pdf::tag>` is a Blade class component that overrides `resolveView()` to return a closure producing an `HtmlString` marker `[[pc:{nonce}:{id}]]`. Each component validates its attributes, builds its core node (from Plan 2), and registers it in a per-render `NodeRegistry`. Containers resolve the markers in their slot back into child nodes. `PdfManager` renders the view inside a registry scope, takes the single root `Document`, and renders it through core with options built from `config/papercut.php`.

**Tech Stack:** PHP 8.4, Laravel 12/13 (`illuminate/*` `^12.0|^13.0`), Orchestra Testbench `^11.3` (dev, Laravel 13), PHPUnit `^13.4`, PHPStan `^2.3`. `papercut/core` via a Composer path repository.

**Spec:** `docs/superpowers/specs/2026-10-18-papercut-design.md` (§6.1, §6.3–6.5, §7.4, §8)

## Global Constraints

- Package `papercut/laravel`, namespace `Papercut\Laravel\`, source `packages/laravel/src`, tests `packages/laravel/tests` (`Papercut\Laravel\Tests\`).
- Requires: `php ^8.4`, `papercut/core` (path repo `../core`, constraint `@dev` during monorepo development), `illuminate/support`, `illuminate/view`, `illuminate/http`, `illuminate/console`, `illuminate/routing`, all `^12.0|^13.0`.
- Blade component namespace: `pdf` (tags `<x-pdf::document>`, `<x-pdf::page>`, `<x-pdf::view>`, `<x-pdf::row>`, `<x-pdf::column>`, `<x-pdf::text>`, `<x-pdf::h1>`…`<x-pdf::h4>`, `<x-pdf::p>`, `<x-pdf::image>`, `<x-pdf::table>`, `<x-pdf::thead>`, `<x-pdf::tr>`, `<x-pdf::th>`, `<x-pdf::td>`, `<x-pdf::spacer>`, `<x-pdf::page-number>`, `<x-pdf::page-break>`, `<x-pdf::link>`).
- Markers: `[[pc:{nonce}:{id}]]`, where nonce = `bin2hex(random_bytes(8))` per render scope and id is an int. **Never use NUL or whitespace in markers.** Blade `trim()`s slots (verified by spike).
- Unknown attributes on `<x-pdf::*>` tags are errors (`UnknownAttribute`), with context `on <x-pdf::tag>`. Spike-verified: the enclosing component's attributes do not leak into nested components.
- Blade components receive attributes only through `$data['attributes']` (an `Illuminate\View\ComponentAttributeBag`); `getAttributes()` returns static strings as strings, presence attributes as `true`, and `:bound` attributes as raw PHP values. Ignore the `ignoredParameterNames` key in `$data`. Named slots are the `$data` values that are `Illuminate\View\ComponentSlot` instances other than `slot`.
- Text in slots: `{{ }}` output is HTML-escaped by Blade, and core's `InlineMarkup` decodes entities after tag parsing, so user data cannot inject inline tags.
- Overflow default: `throw` in `local`/`testing` environments, `clip` otherwise (config `papercut.overflow` = `null` means "by environment").
- Run tests: `cd packages/laravel && php -d xdebug.mode=off vendor/bin/phpunit`.

## Review Focus

1. **Laravel's real view pipeline with app components:** `<x-student-card :student="$s" class="mb-4"/>`, whose own view contains `<x-pdf::*>` tags, must work with no `UnknownAttribute` from leaked attributes. Test: Task 3.
2. **Escaped user data containing tag-like text** (`{{ '<b>hacked</b>' }}`) must render literally as `<b>hacked</b>`, not bold. Test: Task 4.
3. **A view with no `<x-pdf::document>` root, or two roots,** must fail with a clear `InvalidStructure`, not render an empty PDF. Test: Task 6.
4. **Rendering two PDFs in one request** (or a `Pdf::view()` call inside a Blade render) must not cross-contaminate nodes between registries. Test: Task 2 (nested scopes) + Task 6.
5. **Errors show the Blade file:** an invalid attribute value in `resources/views/pdf/x.blade.php` must surface an exception whose message names that file. Test: Task 6.

---

## File Structure

```
packages/laravel/
  composer.json  phpunit.xml.dist  phpstan.neon
  config/papercut.php
  src/
    PapercutServiceProvider.php
    Facades/Pdf.php
    PdfManager.php          view()/make(), RenderOptions from config
    PendingPdf.php          Responsable: download/inline/save/toString
    Exception/InvalidStructure.php
    Rendering/NodeRegistry.php
    Rendering/Segments.php  marker/text segment list helpers
    Components/
      PdfComponent.php      abstract base (resolveView override, attrs, children, text)
      Document.php Page.php View.php Row.php Column.php Text.php Heading.php (h1–h4 via subclasses H1..H4) P.php
      Image.php Table.php Thead.php Tr.php Th.php Td.php Spacer.php PageNumber.php PageBreak.php Link.php
    Preview/PreviewController.php  Preview/PreviewRegistry.php
    Console/FontsCommand.php
  resources/views/            (none; components render markers)
  tests/
    TestCase.php            Testbench base
    Unit/NodeRegistryTest.php
    Feature/*.php
    views/                  fixture Blade views used by feature tests
examples/laravel/            invoice/transcript/report Blade views + a render script
```

---

### Task 1: Scaffold the package and service provider

**Files:** `packages/laravel/composer.json`, `phpunit.xml.dist`, `phpstan.neon`, `config/papercut.php`, `src/PapercutServiceProvider.php`, `tests/TestCase.php`, `tests/Feature/ServiceProviderTest.php`. Root `.gitignore` already ignores `packages/*/vendor`.

**Interfaces:**
- `composer.json`:
```json
{
    "name": "papercut/laravel",
    "description": "Blade components that compile to Papercut's PDF layout engine.",
    "type": "library",
    "license": "MIT",
    "repositories": [{ "type": "path", "url": "../core", "options": { "symlink": true } }],
    "require": {
        "php": "^8.4",
        "illuminate/console": "^12.0|^13.0",
        "illuminate/http": "^12.0|^13.0",
        "illuminate/routing": "^12.0|^13.0",
        "illuminate/support": "^12.0|^13.0",
        "illuminate/view": "^12.0|^13.0",
        "papercut/core": "@dev"
    },
    "require-dev": {
        "orchestra/testbench": "^11.3",
        "phpstan/phpstan": "^2.3",
        "phpunit/phpunit": "^13.4"
    },
    "autoload": { "psr-4": { "Papercut\\Laravel\\": "src/" } },
    "autoload-dev": { "psr-4": { "Papercut\\Laravel\\Tests\\": "tests/" } },
    "extra": {
        "laravel": {
            "providers": ["Papercut\\Laravel\\PapercutServiceProvider"],
            "aliases": { "Pdf": "Papercut\\Laravel\\Facades\\Pdf" }
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true,
    "config": { "sort-packages": true }
}
```
- `config/papercut.php` (exact keys):
```php
<?php

return [
    // Default page size used by <x-pdf::page> when no size is given: a3|a4|a5|a6|letter|legal
    'paper' => 'a4',
    'margin' => '20mm',
    // Font families: name => [weight => ['normal' => path, 'italic' => path]]. Inter is always available.
    'fonts' => [],
    'fallback_fonts' => [],
    // Theme overrides: font_family, font_size (pt float), color, line_height
    'theme' => [],
    // Named styles usable as style="card": name => attribute array (same keys as tag attributes)
    'styles' => [],
    // throw | clip | null (null = throw in local/testing, clip elsewhere)
    'overflow' => null,
    // Base directory for relative <x-pdf::image src>
    'image_path' => null, // null = public_path()
    'preview' => [
        'enabled' => env('PAPERCUT_PREVIEW', false),
        'path' => '_papercut',
    ],
];
```
- `PapercutServiceProvider`: `register()` merges config and binds `PdfManager` as a scoped singleton. `boot()` publishes config (tag `papercut-config`), calls `Blade::componentNamespace('Papercut\\Laravel\\Components', 'pdf')`, registers `FontsCommand` when running in console, and registers the preview routes when `preview.enabled` and the app environment is `local` (Task 8).
- `tests/TestCase.php`: extends `Orchestra\Testbench\TestCase`; `getPackageProviders()` returns the provider; `defineEnvironment()` adds `__DIR__ . '/views'` to `view.paths`.

- [ ] **Step 1:** Write `tests/Feature/ServiceProviderTest.php`: assert `config('papercut.paper') === 'a4'`; assert the `PdfManager` binding resolves (it is created in Task 6, so in this task bind `PdfManager::class` lazily and assert only the config and `Blade::getClassComponentNamespaces()['pdf'] === 'Papercut\\Laravel\\Components'`).
- [ ] **Step 2:** `composer --working-dir=packages/laravel install`, run the test → FAIL.
- [ ] **Step 3:** Implement the provider + config → PASS. PHPStan (level 8, paths `src` `tests`) clean.
- [ ] **Step 4: Commit** `feat(laravel): scaffold papercut/laravel package and service provider`

---

### Task 2: NodeRegistry and segments

**Files:** `src/Rendering/NodeRegistry.php`, `src/Rendering/Segments.php`, `src/Exception/InvalidStructure.php`; Test: `tests/Unit/NodeRegistryTest.php`

**Interfaces:**
- `final class InvalidStructure extends \Papercut\PapercutException`
- `final class NodeRegistry` (bound as a singleton in the provider):
  - `scope(callable $fn): mixed`: pushes a fresh scope (new nonce, empty table) and pops it in `finally`. Scopes nest.
  - `register(object $node): HtmlString`: returns the marker for the current scope. Outside any scope → `InvalidStructure('<x-pdf::*> components can only be rendered through Pdf::view().')`.
  - `segments(string $html): list<object|string>`: splits by `/\[\[pc:([0-9a-f]{16}):(\d+)\]\]/`. Markers with the current scope's nonce resolve to their node (each node can be taken **once**; a second take → `InvalidStructure('Node rendered twice')`). Markers with another nonce stay literal text. Text between markers is kept as strings (no trimming).
  - `remaining(): int`: count of registered-but-untaken nodes in the current scope.
- `final class Segments`:
  - `static nodes(list<object|string> $segs, string $tag): list<object>`: drops whitespace-only strings. A non-whitespace string becomes `\Papercut\Text::markup(trim(string))`.
  - `static text(list<object|string> $segs, string $tag): string`: if any segment is an object → `InvalidStructure("<x-pdf::$tag> can only contain text and inline tags (<b>, <i>, <u>, <s>, <a>, <br>, <span>)")`; else returns the concatenation.

- [ ] **Step 1: Failing tests:**
```php
<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Unit;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\{NodeRegistry, Segments};
use Papercut\Text;
use PHPUnit\Framework\TestCase;

final class NodeRegistryTest extends TestCase
{
    public function test_markers_round_trip_with_text(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            $a = Text::make('a');
            $b = Text::make('b');
            $html = '  ' . $r->register($a) . "\n hello " . $r->register($b);
            $segs = $r->segments($html);
            self::assertSame($a, $segs[1]);
            self::assertSame("\n hello ", $segs[2]);
            self::assertSame($b, $segs[3]);
            self::assertSame(0, $r->remaining());
        });
    }

    public function test_marker_format_is_printable(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            self::assertMatchesRegularExpression('/^\[\[pc:[0-9a-f]{16}:\d+\]\]$/', (string) $r->register(Text::make('x')));
        });
    }

    public function test_foreign_nonce_stays_literal(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            $segs = $r->segments('x [[pc:0000000000000000:1]] y');
            self::assertSame(['x [[pc:0000000000000000:1]] y'], $segs);
        });
    }

    public function test_nested_scopes_are_isolated(): void
    {
        $r = new NodeRegistry();
        $r->scope(function () use ($r) {
            $outer = (string) $r->register(Text::make('outer'));
            $r->scope(function () use ($r, $outer) {
                self::assertSame([$outer], $r->segments($outer));
            });
            self::assertInstanceOf(Text::class, $r->segments($outer)[0]);
        });
    }

    public function test_register_outside_scope_fails(): void
    {
        $this->expectException(InvalidStructure::class);
        (new NodeRegistry())->register(Text::make('x'));
    }

    public function test_node_taken_twice_fails(): void
    {
        $r = new NodeRegistry();
        $this->expectException(InvalidStructure::class);
        $r->scope(function () use ($r) {
            $m = (string) $r->register(Text::make('x'));
            $r->segments($m);
            $r->segments($m);
        });
    }

    public function test_segments_helpers(): void
    {
        $t = Text::make('x');
        self::assertCount(2, Segments::nodes(["\n  ", $t, ' more text '], 'column'));
        self::assertSame('a <b>b</b>', Segments::text(['a <b>b</b>'], 'text'));
        $this->expectException(InvalidStructure::class);
        Segments::text(['a', $t], 'text');
    }
}
```
- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(laravel): add node registry with nonce markers`

---

### Task 3: PdfComponent base + block components (view, row, column, link, spacer, page-break)

**Files:** `src/Components/{PdfComponent,View,Row,Column,Link,Spacer,PageBreak}.php`; Test: `tests/Feature/BlockComponentsTest.php`, `tests/views/components/student-card.blade.php`

**Interfaces:**
- `abstract class PdfComponent extends \Illuminate\View\Component`:
  - `final public function render(): string { return ''; }` (never used).
  - `final public function resolveView(): \Closure` → `fn (array $data) => app(NodeRegistry::class)->register($this->build($data))`.
  - `abstract protected function build(array $data): object` (returns a core node).
  - `protected function tag(): string` → kebab-case of the short class name (`PageNumber` → `page-number`).
  - `/** @return list<string> */ protected function ownAttributes(): array` → `[]`. These are tag-specific keys; all other keys must be style keys (`Style::fromAttributes` validates them).
  - `protected function attrs(array $data): array{own: array<string, mixed>, style: array<string, mixed>}`: splits `$data['attributes']->getAttributes()`. The `style` attribute (a named theme style) goes into `own` for every component.
  - `protected function styled(object $node, array $data): object`: applies the `style` attributes via `$node->attributes($style)` and the named style via `$node->apply($name)`. Rethrows `UnknownAttribute`/`InvalidValue` as `->withContext("on <x-pdf::{tag}>")`. (`UnknownAttribute::withContext()` exists in core from Plan 2 Task 1; pass `"on <x-pdf::{tag}>"`. `withContext()` REPLACES the context, and an `InvalidValue` from `Style::fromAttributes` already carries the attribute key as its context, so append: `$e->withContext($e->context === null ? "on <x-pdf::{tag}>" : $e->context . " on <x-pdf::{tag}>")`. Own attributes parsed by the component itself (e.g. `size` on page) use context `"<attr> on <x-pdf::{tag}>"`.)
  - `protected function children(array $data): list<object>` → `Segments::nodes(registry->segments((string) $data['slot']), tag)`.
  - `protected function text(array $data): string` → `Segments::text(...)`.
  - `/** @return array<string, string> */ protected function namedSlots(array $data): array`: slot name → html (the `$data` values that are `ComponentSlot`, excluding `slot`).
- Components (each is `final class X extends PdfComponent`):
  - `View` → `\Papercut\View::make(...children)`. Own attributes: none.
  - `Row` → `\Papercut\Row::make(...)`; `Column` → `\Papercut\Column::make(...)`.
  - `Link` → `\Papercut\Link::to(href, ...children)`. Own: `href` (required → `InvalidStructure('<x-pdf::link> requires an href attribute')`).
  - `Spacer` → own `size` (length) → `Spacer::size(Length::parse)`; otherwise `Spacer::flex(grow attr or 1)`. Spacer accepts no style attributes except `grow`.
  - `PageBreak` → `\Papercut\PageBreak::make()`; no attributes allowed.
  - Attribute aliases: `align` on view/row/column = `align-items`.

- [ ] **Step 1: Failing feature tests.** A test helper on `TestCase`: `protected function nodeFrom(string $blade, array $data = []): object`. It renders `Blade::render($blade, $data)` inside `NodeRegistry::scope()`, asserts exactly one node segment (non-whitespace text is an error), and returns it.
```php
<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Column, Link, PageBreak, Spacer, Text, View};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\{Align, Direction};

final class BlockComponentsTest extends TestCase
{
    public function test_row_with_attributes_and_children(): void
    {
        $node = $this->nodeFrom('<x-pdf::row gap="8pt" align="center" padding="4pt"><x-pdf::column>A</x-pdf::column> B </x-pdf::row>');
        self::assertInstanceOf(View::class, $node);
        self::assertSame(Direction::Row, $node->style()->direction);
        self::assertSame(Align::Center, $node->style()->alignItems);
        self::assertSame(8.0, $node->style()->gap?->points);
        self::assertCount(2, $node->children());
        self::assertInstanceOf(Text::class, $node->children()[1]);
    }

    public function test_whitespace_between_blocks_is_dropped(): void
    {
        $node = $this->nodeFrom("<x-pdf::column>\n  <x-pdf::spacer size=\"4pt\" />\n  <x-pdf::page-break />\n</x-pdf::column>");
        self::assertCount(2, $node->children());
        self::assertInstanceOf(Spacer::class, $node->children()[0]);
        self::assertInstanceOf(PageBreak::class, $node->children()[1]);
    }

    public function test_bound_values_pass_through_typed(): void
    {
        $node = $this->nodeFrom('<x-pdf::view :padding="$p" />', ['p' => \Papercut\pt(3)]);
        self::assertSame(3.0, $node->style()->padding?->top->points);
    }

    public function test_unknown_attribute_names_the_tag(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "paddng" on <x-pdf::column>. Did you mean "padding"?');
        $this->nodeFrom('<x-pdf::column paddng="4pt" />');
    }

    public function test_invalid_value_names_attribute_and_tag(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Invalid edges "20 mm" for margin on <x-pdf::view>. Did you mean "20mm"?');
        $this->nodeFrom('<x-pdf::view margin="20 mm" />');
    }

    public function test_link_requires_href(): void
    {
        self::assertSame('https://x.test', $this->nodeFrom('<x-pdf::link href="https://x.test">go</x-pdf::link>')->uri());
        $this->expectException(\Papercut\Laravel\Exception\InvalidStructure::class);
        $this->nodeFrom('<x-pdf::link>go</x-pdf::link>');
    }

    public function test_app_component_with_extra_attributes_nests_cleanly(): void
    {
        $node = $this->nodeFrom('<x-pdf::column><x-student-card class="mb-4" :name="$n" /></x-pdf::column>', ['n' => 'Ada']);
        $card = $node->children()[0];
        self::assertInstanceOf(View::class, $card);
    }
}
```
`tests/views/components/student-card.blade.php`:
```blade
@props(['name'])
<x-pdf::column padding="12pt" background="slate-50"><x-pdf::text bold>{{ $name }}</x-pdf::text></x-pdf::column>
```
(`<x-pdf::text>` comes in Task 4. Until then, write this fixture with `<x-pdf::column>{{ $name }}</x-pdf::column>` and switch it to `<x-pdf::text bold>` in Task 4.)

- [ ] **Step 2–4:** FAIL → implement → PASS. PHPStan clean in both packages.
- [ ] **Step 5: Commit** `feat(laravel): add component base and block components`

---

### Task 4: Text components (text, h1–h4, p, page-number) and image

**Files:** `src/Components/{Text,H1,H2,H3,H4,P,PageNumber,Image}.php`; Test: `tests/Feature/TextComponentsTest.php`

**Rules:**
- `Text`: slot → `Segments::text` → `\Papercut\Text::markup(trim($text))`. Own attribute: `align` (= `text-align`); every other text style key is allowed via style attributes. `H1`…`H4`, `P` → `\Papercut\Text::h1(...)` etc. Use core's `Text::markup($m)->withPreset("h1")` (from Plan 2 Task 2).
- `PageNumber`: own `format` (default `'{page}'`) and `align` → `PageNumber::make($format)` plus text-align. The slot must be empty (non-empty → InvalidStructure).
- `Image`: own `src` (string path; relative paths resolve against `config('papercut.image_path') ?? public_path()`; absolute paths are used as-is), `data` (raw bytes via `:data`), `fit` (`contain|cover|fill`). Exactly one of src/data is required → otherwise InvalidStructure. A path is checked to exist at build time → `\Papercut\Exception\ImageUnreadable("Image not found: <resolved path>")`, so the error surfaces with the Blade file.

- [ ] **Step 1: Failing tests:**
```php
<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Image, PageNumber, Text};
use Papercut\Exception\ImageUnreadable;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\{ImageFit, TextAlign};

final class TextComponentsTest extends TestCase
{
    public function test_text_with_inline_markup_and_style(): void
    {
        $t = $this->nodeFrom("<x-pdf::text size=\"18pt\" bold align=\"right\">\n  Hello <i>there</i>\n</x-pdf::text>");
        self::assertInstanceOf(Text::class, $t);
        self::assertSame(18.0, $t->style()->fontSize?->points);
        self::assertSame(700, $t->style()->fontWeight);
        self::assertSame(TextAlign::Right, $t->style()->textAlign);
        self::assertSame(['Hello ', 'there'], array_map(fn ($s) => $s->text, $t->spanList()));
        self::assertTrue($t->spanList()[1]->style->italic);
    }

    public function test_escaped_user_data_is_not_markup(): void
    {
        $t = $this->nodeFrom('<x-pdf::text>{{ $evil }}</x-pdf::text>', ['evil' => '<b>hacked</b> & co']);
        self::assertCount(1, $t->spanList());
        self::assertSame('<b>hacked</b> & co', $t->spanList()[0]->text);
        self::assertNull($t->spanList()[0]->style->fontWeight);
    }

    public function test_headings_and_paragraph(): void
    {
        self::assertSame('h2', $this->nodeFrom('<x-pdf::h2>Title</x-pdf::h2>')->preset());
        self::assertSame('p', $this->nodeFrom('<x-pdf::p>Body <b>bold</b></x-pdf::p>')->preset());
    }

    public function test_block_inside_text_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->nodeFrom('<x-pdf::text>a <x-pdf::spacer /></x-pdf::text>');
    }

    public function test_page_number(): void
    {
        $n = $this->nodeFrom('<x-pdf::page-number format="Page {page} of {total}" align="center" />');
        self::assertInstanceOf(PageNumber::class, $n);
        self::assertSame('Page {page} of {total}', $n->format());
        self::assertSame(TextAlign::Center, $n->style()->textAlign);
    }

    public function test_image_src_resolution_and_errors(): void
    {
        $dir = sys_get_temp_dir() . '/papercut-img-' . getmypid();
        @mkdir($dir);
        file_put_contents("$dir/logo.png", 'x');
        config(['papercut.image_path' => $dir]);
        $img = $this->nodeFrom('<x-pdf::image src="logo.png" height="12mm" fit="cover" />');
        self::assertInstanceOf(Image::class, $img);
        self::assertSame(['kind' => 'file', 'value' => "$dir/logo.png"], $img->source());
        self::assertSame(ImageFit::Cover, $img->fitMode());
        self::assertSame('bytes', $this->nodeFrom('<x-pdf::image :data="$b" />', ['b' => 'raw'])->source()['kind']);
        $this->expectException(ImageUnreadable::class);
        $this->nodeFrom('<x-pdf::image src="missing.png" />');
    }

    public function test_image_requires_exactly_one_source(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->nodeFrom('<x-pdf::image />');
    }
}
```
- [ ] **Step 2–4:** FAIL → implement → PASS. Switch the `student-card` fixture to `<x-pdf::text bold>`; Task 3's test still passes.
- [ ] **Step 5: Commit** `feat(laravel): add text, heading, page-number and image components`

---

### Task 5: Table components

**Files:** `src/Components/{Table,Thead,Tr,Th,Td}.php`; Test: `tests/Feature/TableComponentsTest.php`

**Rules:**
- `Td`/`Th`: children → `TableCell::make(...children)` / `TableCell::th(...)` (core: static creator is `th()`; `header(): bool` is the instance getter). Own `colspan` (int ≥ 1) and `align` (text-align). Other style attributes are allowed.
- `Tr`: children must all be `TableCell` nodes (from td/th). Any other non-whitespace content → `InvalidStructure('<x-pdf::tr> can only contain <x-pdf::td> or <x-pdf::th>')`. Returns `TableRow`.
- `Thead`: children are either all `TableCell`s (→ one header row) or all `TableRow`s (→ several header rows). Anything else → InvalidStructure. It returns an internal value object `Papercut\Laravel\Components\HeaderRows` (list<TableRow>) that only `Table` accepts.
- `Table`: own `columns` (string → `TrackList::parse`, or a bound TrackList/array of tracks), `repeat-header` (bool). Children: `HeaderRows` (at most one, and before body rows), `TableRow`s. Anything else → InvalidStructure naming the offending tag (use the node's `debugName()`).
- A `td`/`th`/`tr`/`thead` rendered outside its parent is only detectable when the parent resolves. A stray `TableCell` reaching a non-table container (e.g. a column) must throw `InvalidStructure('<x-pdf::td> must be inside <x-pdf::tr>')`, and the same for `tr` → table and `thead` → table. Implement this in `Segments::nodes()`: reject TableCell/TableRow/HeaderRows unless the caller passes an allowlist.

- [ ] **Step 1: Failing tests:**
```php
<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Table;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;

final class TableComponentsTest extends TestCase
{
    private const string TABLE = <<<'BLADE'
<x-pdf::table columns="3fr 25mm 20mm" :repeat-header="false">
  <x-pdf::thead><x-pdf::th>Course</x-pdf::th><x-pdf::th>Credits</x-pdf::th><x-pdf::th>Grade</x-pdf::th></x-pdf::thead>
  @foreach ($courses as $c)
    <x-pdf::tr>
      <x-pdf::td>{{ $c[0] }}</x-pdf::td>
      <x-pdf::td>{{ $c[1] }}</x-pdf::td>
      <x-pdf::td align="right" colspan="1">{{ $c[2] }}</x-pdf::td>
    </x-pdf::tr>
  @endforeach
</x-pdf::table>
BLADE;

    public function test_table_from_blade(): void
    {
        $t = $this->nodeFrom(self::TABLE, ['courses' => [['Math', 4, 'A'], ['Art', 2, 'B']]]);
        self::assertInstanceOf(Table::class, $t);
        self::assertCount(3, $t->trackList()?->tracks ?? []);
        self::assertCount(1, $t->headerRows());
        self::assertTrue($t->headerRows()[0]->cells()[0]->header());
        self::assertCount(2, $t->bodyRows());
        self::assertFalse($t->repeatsHeader());
    }

    public function test_thead_with_multiple_rows(): void
    {
        $t = $this->nodeFrom('<x-pdf::table><x-pdf::thead><x-pdf::tr><x-pdf::th>A</x-pdf::th></x-pdf::tr><x-pdf::tr><x-pdf::th>B</x-pdf::th></x-pdf::tr></x-pdf::thead></x-pdf::table>');
        self::assertCount(2, $t->headerRows());
    }

    /** @return iterable<string, array{string, string}> */
    public static function misplaced(): iterable
    {
        yield 'td in column' => ['<x-pdf::column><x-pdf::td>x</x-pdf::td></x-pdf::column>', '<x-pdf::td> must be inside <x-pdf::tr>'];
        yield 'tr in column' => ['<x-pdf::column><x-pdf::tr><x-pdf::td>x</x-pdf::td></x-pdf::tr></x-pdf::column>', '<x-pdf::tr> must be inside <x-pdf::table>'];
        yield 'text in tr' => ['<x-pdf::table><x-pdf::tr>loose</x-pdf::tr></x-pdf::table>', '<x-pdf::tr> can only contain <x-pdf::td> or <x-pdf::th>'];
        yield 'column in table' => ['<x-pdf::table><x-pdf::column /></x-pdf::table>', '<x-pdf::table> can only contain'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('misplaced')]
    public function test_structure_errors(string $blade, string $message): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage($message);
        $this->nodeFrom($blade);
    }
}
```
- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(laravel): add table components with structural validation`

---

### Task 6: Document, Page, PdfManager, PendingPdf, facade

**Files:** `src/Components/{Document,Page}.php`, `src/PdfManager.php`, `src/PendingPdf.php`, `src/Facades/Pdf.php`; Test: `tests/Feature/RenderingTest.php`, `tests/views/pdf/*.blade.php`

**Rules:**
- `Page` component: own `size` (`a3…legal`, default `config('papercut.paper')`), `orientation` (`portrait|landscape`), `margin` (edges, default `config('papercut.margin')`), `header-gap`, `footer-gap`. Named slots `header`, `footer`, `background` are resolved like containers. Default-slot children → `body`. Unknown named slot → InvalidStructure listing the allowed ones. Page accepts no style attributes. (Page-level text defaults belong on the document/theme; keep YAGNI.)
- `Document` component: own `title`, `author`, `subject`, `keywords`, `lang`. Children must all be `Page` nodes. If there are none, non-whitespace content is wrapped into one default page: `<x-pdf::document>` with plain content implies an A4 page from config (convenience).
- `PdfManager`:
  - `view(string $view, array $data = []): PendingPdf`: inside `registry->scope()`, `$html = view($view, $data)->render()`, `$segs = registry->segments($html)`. Require exactly one non-whitespace segment and require it to be a `\Papercut\Document`, else `InvalidStructure('A Papercut view must have exactly one <x-pdf::document> root; found …')` (describe what was found: nothing, text, N nodes, a <tag>). If `registry->remaining() > 0` → InvalidStructure('Some <x-pdf::*> elements were rendered outside the document').
  - `make(\Papercut\Document $doc): PendingPdf`.
  - `options(): RenderOptions`, built once per manager:
    - backend `PhpPdfBackend`;
    - `FontRegistry::withDefaults($backend)` plus the config fonts;
    - Theme: `Theme::default()->with(...)` from `papercut.theme` (keys `font_family`, `font_size`, `color` via `Color::parse`, `line_height`), plus `withStyle($name, Style::fromAttributes($attrs))` for each `papercut.styles` entry;
    - overflow from config/environment;
    - `onWarning` → `logger()->warning('[papercut] ' . $msg)`.
- `PendingPdf implements Responsable`:
  - `toString(): string` (memoized render).
  - `save(string $path): self`.
  - `download(string $filename = 'document.pdf'): Response`; `inline(string $filename = 'document.pdf'): Response`. Headers: `Content-Type: application/pdf`, `Content-Disposition: attachment|inline; filename="<name>"` (via Symfony's `HeaderUtils::makeDisposition`), `Content-Length`.
  - `toResponse($request)` = `inline()`.
- Facade `Pdf` → `PdfManager::class`.

- [ ] **Step 1: Failing tests.** Fixture views:
  - `tests/views/pdf/hello.blade.php`: a document (title "Hello") with a page holding header/footer slots and a body text that uses `{{ $name }}`.
  - `pdf/two-roots.blade.php`: two documents.
  - `pdf/no-root.blade.php`: just `<x-pdf::text>x</x-pdf::text>`.
  - `pdf/bad-attr.blade.php`: `<x-pdf::document><x-pdf::page margin="20 mm">x</x-pdf::page></x-pdf::document>`.
  - `pdf/implicit-page.blade.php`: a document with plain `<x-pdf::text>` content.
```php
<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Pdf;
use Papercut\Laravel\Tests\TestCase;

final class RenderingTest extends TestCase
{
    public function test_renders_view_to_pdf(): void
    {
        $pdf = Pdf::view('pdf.hello', ['name' => 'Ada'])->toString();
        self::assertStringStartsWith('%PDF-', $pdf);
        $file = tempnam(sys_get_temp_dir(), 'pc');
        file_put_contents($file, $pdf);
        exec('pdftotext ' . escapeshellarg($file) . ' -', $out);
        unlink($file);
        self::assertStringContainsString('Ada', implode("\n", $out));
    }

    public function test_download_and_inline_responses(): void
    {
        Route::get('/dl', fn () => Pdf::view('pdf.hello', ['name' => 'A'])->download('report.pdf'));
        Route::get('/in', fn () => Pdf::view('pdf.hello', ['name' => 'A']));
        $this->get('/dl')->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=report.pdf');
        $this->get('/in')->assertOk()->assertHeader('Content-Disposition', 'inline; filename=document.pdf');
    }

    public function test_root_validation(): void
    {
        foreach (['pdf.two-roots', 'pdf.no-root'] as $view) {
            try {
                Pdf::view($view)->toString();
                self::fail("$view accepted");
            } catch (\Throwable $e) {
                $root = $e;
                while ($root->getPrevious() !== null && !$root instanceof InvalidStructure) { $root = $root->getPrevious(); }
                self::assertInstanceOf(InvalidStructure::class, $root);
                self::assertStringContainsString('exactly one <x-pdf::document>', $root->getMessage());
            }
        }
    }

    public function test_attribute_error_names_blade_file(): void
    {
        try {
            Pdf::view('pdf.bad-attr')->toString();
            self::fail('accepted');
        } catch (\Throwable $e) {
            self::assertStringContainsString('bad-attr.blade.php', $e->getMessage());
            self::assertStringContainsString('Invalid edges "20 mm" for margin on <x-pdf::page>', $e->getMessage());
        }
    }

    public function test_implicit_page_and_two_renders_are_isolated(): void
    {
        $a = Pdf::view('pdf.implicit-page')->toString();
        $b = Pdf::view('pdf.hello', ['name' => 'B'])->toString();
        self::assertStringStartsWith('%PDF-', $a);
        self::assertStringStartsWith('%PDF-', $b);
    }

    public function test_named_styles_and_theme_from_config(): void
    {
        config(['papercut.styles' => ['card' => ['padding' => '12pt', 'background' => 'slate-50']], 'papercut.theme' => ['font_size' => 11.0]]);
        $this->app->forgetInstance(\Papercut\Laravel\PdfManager::class);
        $theme = Pdf::options()->theme();
        self::assertSame(11.0, $theme->fontSize);
        self::assertSame(12.0, $theme->named('card')->padding?->top->points);
    }
}
```
(If Laravel wraps the attribute exception in `Illuminate\View\ViewException`, its message already ends with `(View: …/bad-attr.blade.php)`. That is what the test relies on.)
- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(laravel): render Blade views to PDF via Pdf facade`

---

### Task 7: Fonts config + `papercut:fonts` command

**Files:** `src/Console/FontsCommand.php`, modify `src/PdfManager.php` (register config fonts); Test: `tests/Feature/FontsTest.php`

**Rules:**
- The config `fonts` shape is `['Brand' => [400 => ['normal' => '/path/Brand-Regular.ttf', 'italic' => '…'], 700 => ['normal' => '…']]]`. Each entry → `FontRegistry::register(family, path, weight, FontStyle)`. `fallback_fonts` (list of family names) → passed into the itemizer. This needs core support: `RenderOptions` gets `list<string> $fallbackFamilies = []`, which `LayoutContext` forwards to `Itemizer`. Add it in core with a test that a fallback family's glyph coverage is used.
- `php artisan papercut:fonts` prints a table: Family | Weight | Style | Path | Status (`ok` / `missing` / `invalid`, the latter via `backend->loadFont` in a try). Exit code 1 if any row isn't ok.

- [ ] **Step 1: Failing tests:** configure the bundled Inter files as a second family `Brand` → the command lists `Inter` (4 rows) and `Brand` rows with `ok`, exit 0. Configure a missing path → `missing`, exit 1. `Pdf::options()->fonts(...)->has('Brand')` is true. Core: a text with fallback family coverage resolves the fallback font (use FakeBackend-based core test: register a family whose `FakeFont` covers CJK; give `FakeFont` an optional `coversAll` flag).
- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean in both packages.
- [ ] **Step 5: Commit** `feat(laravel): add font configuration and papercut:fonts command`

---

### Task 8: Preview route

**Files:** `src/Preview/{PreviewController,PreviewRegistry}.php`, modify the provider; Test: `tests/Feature/PreviewTest.php`

**Rules:**
- `PreviewRegistry` (singleton): `register(string $view, \Closure|array $data = []): void`. Users call it in a service provider: `Pdf::preview('pdf.invoice', fn () => ['invoice' => Invoice::factory()->make()])`. The facade method `preview()` is on `PdfManager`, which delegates to the registry.
- Routes (only when `config('papercut.preview.enabled')` and `app()->environment('local')`, registered at boot, prefix `config('papercut.preview.path')`):
  - `GET /_papercut`: a minimal HTML index listing registered views as links (escaped).
  - `GET /_papercut/{view}`: renders the registered view with its data (closures are called per request) and returns `inline()`. An unregistered view → 404.
- Errors in development propagate to Laravel's normal exception page (no catching).

- [ ] **Step 1: Failing tests:** with `app()->detectEnvironment(fn () => 'local')` and preview enabled (set config in `defineEnvironment`): index lists `pdf.hello`; `GET /_papercut/pdf.hello` → 200 application/pdf; an unregistered view → 404; with the `testing` environment → no route (404).
- [ ] **Step 2–4:** FAIL → implement → PASS, PHPStan clean.
- [ ] **Step 5: Commit** `feat(laravel): add local preview routes`

---

### Task 9: Blade examples, README, CI

**Files:** `examples/laravel/views/{invoice,transcript,report}.blade.php`, `examples/laravel/views/components/{line-item-row,stat-card}.blade.php`, `examples/laravel/render.php`, `packages/laravel/README.md`, root `README.md`, `.github/workflows/laravel.yml`, `packages/laravel/tests/Feature/ExamplesTest.php`

**Requirements:**
- The three example views mirror Plan 2's plain-PHP examples (same content and design) and use app components (`<x-stat-card>`, `<x-line-item-row>`) to show composition.
- `examples/laravel/render.php` boots Testbench's `Orchestra\Testbench\Foundation\Application::create(basePath: …)` with the provider and the examples view path, renders all three to `examples/laravel/*.pdf`, and prints the paths.
- `ExamplesTest` renders each view with the same inline data, checks `qpdf --check`, and asserts key strings via `pdftotext` (invoice total, transcript `Page 1 of`, report appendix heading on a later page).
- The root `README.md` is the project's front page. It covers:
  - the one-paragraph pitch ("Blade components that compile to a real layout engine — not HTML");
  - a 15-line Blade example;
  - the controller one-liner;
  - feature bullets (flexbox, page-aware tables, repeated headers, keep-together, page numbers, real fonts);
  - a section on how it works (registry markers → node tree → layout → PDF);
  - a pointer to the plain-PHP API;
  - status: v0.1, with what's not in it yet (spec §10);
  - licenses (MIT; Inter OFL; Tailwind palette MIT; backend `dskripchenko/php-pdf` MIT).
- `packages/laravel/README.md`: install, config publish, tags reference (every tag with its own attributes), style attribute reference (every `Style::fromAttributes` key with an example value), named styles, fonts, preview route, errors.
- The CI workflow runs the laravel tests on PHP 8.4 for both Laravel 13 (default) and Laravel 12 (`composer require laravel/framework:^12.0 orchestra/testbench:^10.16 phpunit/phpunit:^12.5 -W --dev`).

- [ ] Steps: write ExamplesTest → FAIL → write the views/components/render script → PASS → render the PDFs to PNG (`pdftoppm -png -r 60`) and visually inspect the first page of each, fixing any glaring issues with regression tests → write the READMEs + CI → commit `docs(laravel): add Blade examples, READMEs and CI`.
