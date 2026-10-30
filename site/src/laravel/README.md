# Laravel

`papercut/laravel` lets you write documents as Blade views. Each `<x-doc::*>` tag builds a core component, and the whole view becomes one document. Loops, conditionals, slots and your own components all work just like they do in any other Blade view.

See [Installation](/guide/installation.md) to add the package. Laravel discovers the service provider and the `Papercut` facade automatically.

## A view and a route

```blade
{{-- resources/views/pdf/hello.blade.php --}}
<x-doc::document title="Hello">
  <x-doc::page>
    <x-doc::h1>Hello, {{ $name }}</x-doc::h1>
    <x-doc::p>Rendered by Papercut.</x-doc::p>
  </x-doc::page>
</x-doc::document>
```

```php
use Papercut\Laravel\Facades\Papercut;

Route::get('/hello', fn () => Papercut::view('pdf.hello', ['name' => 'Ada'])->pdf()->download('hello.pdf'));
```

| Call | What you get back |
|---|---|
| `Papercut::view($view, $data)` | a `PendingDocument` |
| `->pdf()` | a `PendingOutput`: `download()`, `inline()`, `save()`, `store()`, `toString()`, `output()` |
| `->html()` | a `PendingHtml`; see [HTML export](/guide/html-export.md) |
| `->docx()` | a `PendingOutput` for a Word file. Needs `papercut/docx`, or it throws `MissingExporter`; see [Word export](/guide/word-export.md) |
| `->encrypt($password)` | a copy whose PDF needs a password; see [Passwords](/guide/passwords-and-signatures.md) |
| `->document()` | the core `Document` the view built |
| `Papercut::make($document)` | a `PendingDocument` for a document built in PHP |

## Your own components

Your own Blade components can wrap `<x-doc::*>` tags. They're a good place to keep pieces you use in lots of documents, like a branded header or a stat card.

```blade
{{-- resources/views/components/stat-card.blade.php --}}
@props(['label', 'value', 'color' => 'slate-900'])
<x-doc::column gap="3pt" padding="14pt" background="slate-50" radius="8pt" grow="1">
  <x-doc::text size="8pt" bold color="slate-500">{{ $label }}</x-doc::text>
  <x-doc::text size="22pt" bold :color="$color">{{ $value }}</x-doc::text>
</x-doc::column>
```

```blade
<x-doc::row gap="8pt">
  <x-stat-card label="SECTIONS" value="4" color="indigo-600" />
  <x-stat-card label="TABLE ROWS" :value="count($rows)" color="emerald-600" />
</x-doc::row>
```

Build on the [decorators](/guide/decorators.md) where you can. A `question` component made from `section`, `field` and `choices` picks up the theme automatically, so it matches the rest of your documents.

## Passing values

Attributes accept plain strings (`gap="8pt"`) or PHP values with the usual colon syntax (`:gap="$gap"`). Boolean attributes can be written on their own (`bold`) or bound to a value (`:bold="$isTotal"`).

## Embedding PHP-built nodes

`<x-doc::node :node="$node" />` places a component built in PHP inside a Blade view. It's useful for a chart, a table put together in a service class, or anything else that's easier to build in code.

Inside a text tag, a `Text` node becomes part of the line. One limitation: an inline tag like `<b>` can't wrap around an embedded node, so close it on either side instead:

```blade
<x-doc::text><b>x </b><x-doc::node :node="$t" /><b> y</b></x-doc::text>
```

## Caching

Cache data, not rendered `<x-doc>` output. A rendered fragment holds markers that only mean something inside the render that made it, so a fragment kept in a cache, a static property or `once()` and then placed in a later render can't be used. Papercut stops with an `InvalidStructure` error that says it found a node marker from another render. Cache the data the fragment is built from, and render the fragment each time.

This isn't always detected. Papercut recognises markers it made earlier in the same process; a fragment cached across workers, or kept long after, can instead show up as literal marker text (`[[pc:…]]`) in the output, or as a "can only contain" error whose message adds a note about the marker.

## The tag prefix

Tags use the `doc` prefix by default. If that clashes with something in your app, change `prefix` in the [configuration](./configuration.md).

## When something goes wrong

If a tag has an attribute it doesn't recognise, the render stops with an `UnknownAttribute` error that names the tag and suggests what you probably meant:

```
Unknown attribute "colour" on <x-doc::text>. Did you mean "color"?
```

Tags in the wrong place, such as an `<x-doc::tr>` outside a table or a view without an `<x-doc::document>` root, throw `InvalidStructure`.

Laravel wraps any exception thrown while rendering a view in an `Illuminate\View\ViewException`. Call `getPrevious()` on it to get Papercut's original exception.
