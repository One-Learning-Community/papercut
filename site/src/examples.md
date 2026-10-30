---
sidebar: false
---

# Examples

The repository includes four example documents, each written twice: once as a Blade view and once in plain PHP. Comparing the two versions is a good way to see how Blade tags map to the PHP API.

| Document | What it shows | Blade | PHP |
|---|---|---|---|
| Invoice | a letterhead row, a tinted panel, line items, right-aligned totals, a footer | [invoice.blade.php][inv-b] | [invoice.php][inv-p] |
| Transcript | a sixty-row table across pages with its header repeated, keep-together blocks | [transcript.blade.php][tr-b] | [transcript.php][tr-p] |
| Report | justified prose, stat cards from an app component, an eighty-row table, a forced page break | [report.blade.php][rep-b] | [report.php][rep-p] |
| Form responses | eight of the nine [decorators](/guide/decorators.md): a card of details, a callout, numbered sections, fields, choice lists, rated statements with badges, a blockquote | [responses.blade.php][res-b] | [responses.php][res-p] |

[inv-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/invoice.blade.php
[inv-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/invoice.php
[tr-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/transcript.blade.php
[tr-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/transcript.php
[rep-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/report.blade.php
[rep-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/report.php
[res-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/responses.blade.php
[res-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/responses.php

## What they look like

Each card shows the PDF. Download the Word file too, and open it in Word, LibreOffice or Google Docs to see the same document as editable text.

<div class="example-gallery">
  <figure class="example-card">
    <div class="example-pages"><a :href="$withBase('/examples/invoice.pdf')"><img :src="$withBase('/examples/invoice-1.png')" alt="Invoice, page 1" loading="lazy"></a></div>
    <figcaption><strong>Invoice</strong> <a :href="$withBase('/examples/invoice.pdf')">PDF</a> · <a :href="$withBase('/examples/invoice.docx')" download>Word</a></figcaption>
  </figure>
  <figure class="example-card two-pages">
    <div class="example-pages"><a :href="$withBase('/examples/transcript.pdf')"><img :src="$withBase('/examples/transcript-1.png')" alt="Transcript, page 1" loading="lazy"></a><a :href="$withBase('/examples/transcript.pdf')"><img :src="$withBase('/examples/transcript-2.png')" alt="Transcript, page 2" loading="lazy"></a></div>
    <figcaption><strong>Transcript</strong> <a :href="$withBase('/examples/transcript.pdf')">PDF</a> · <a :href="$withBase('/examples/transcript.docx')" download>Word</a></figcaption>
  </figure>
  <figure class="example-card two-pages">
    <div class="example-pages"><a :href="$withBase('/examples/report.pdf')"><img :src="$withBase('/examples/report-1.png')" alt="Report, page 1" loading="lazy"></a><a :href="$withBase('/examples/report.pdf')"><img :src="$withBase('/examples/report-2.png')" alt="Report, page 2" loading="lazy"></a></div>
    <figcaption><strong>Report</strong> <a :href="$withBase('/examples/report.pdf')">PDF</a> · <a :href="$withBase('/examples/report.docx')" download>Word</a></figcaption>
  </figure>
  <figure class="example-card">
    <div class="example-pages"><a :href="$withBase('/examples/responses.pdf')"><img :src="$withBase('/examples/responses-1.png')" alt="Form responses, page 1" loading="lazy"></a></div>
    <figcaption><strong>Form responses</strong> <a :href="$withBase('/examples/responses.pdf')">PDF</a> · <a :href="$withBase('/examples/responses.docx')" download>Word</a></figcaption>
  </figure>
</div>

## Running them

From a checkout, install the dependencies for the package you want to try, then run the scripts from the repository root.

```bash
# Plain PHP: writes examples/core/<name>.pdf
(cd packages/core && composer install)
php examples/core/invoice.php
php examples/core/transcript.php
php examples/core/report.php
php examples/core/responses.php

# The same documents as HTML: writes examples/html/<name>.html
php examples/html/render.php

# The same documents as Word: writes examples/docx/<name>.docx
(cd packages/docx && composer install)
php examples/docx/render.php

# Blade: writes examples/laravel/<name>.pdf and .html
(cd packages/laravel && composer install)
php examples/laravel/render.php
```

The Blade script spins up a temporary Laravel app with Orchestra Testbench, so you don't need a full Laravel install. The sample data for the views is in `examples/laravel/data.php`.

## The form responses example

This example shows how to build something specific to your app from the decorators. Papercut doesn't know anything about questions or answers. The view just loops over the items and picks a decorator for each one:

```blade
@foreach ($items as $item)
  @if ($item['type'] === 'instruction')
    <x-doc::callout type="note" :title="$item['prompt']">{{ $item['text'] }}</x-doc::callout>
    @continue
  @endif

  <x-doc::section :number="++$n" :title="$item['prompt']" rule keep-together>
    @switch($item['type'])
      @case('text')
        <x-doc::field placeholder="No response" lines="2">{{ $item['answer'] }}</x-doc::field>
        @break
      @case('choices')
        <x-doc::choices :type="$item['multiple'] ? 'checkbox' : 'radio'" emphasize-selected>
          @foreach ($item['options'] as $option)
            <x-doc::choice :selected="in_array($option, $item['selected'], true)">{{ $option }}</x-doc::choice>
          @endforeach
        </x-doc::choices>
        @break
    @endswitch
  </x-doc::section>
@endforeach
```

An unanswered question shows "No response" in a grey box rather than leaving a gap, and selected options get a filled checkbox and a bold label. All the colours come from the theme, so the form matches every other document the app produces.
