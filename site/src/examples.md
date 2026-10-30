---
sidebar: false
---

# Examples

The repository includes seven example documents, each written twice: once as a Blade view and once in plain PHP. Comparing the two versions is a good way to see how Blade tags map to the PHP API. The invoice, transcript, integration guide and certificate use US Letter and inches; the report, form responses and knowledge base article use A4 and millimetres.

| Document | What it shows | Blade | PHP |
|---|---|---|---|
| Invoice | a letterhead row, a tinted panel, line items, right-aligned totals, a footer | [invoice.blade.php][inv-b] | [invoice.php][inv-p] |
| Transcript | a sixty-row table across pages with its header repeated, keep-together blocks | [transcript.blade.php][tr-b] | [transcript.php][tr-p] |
| Report | justified prose, stat cards from an app component, an eighty-row table, a forced page break | [report.blade.php][rep-b] | [report.php][rep-p] |
| Form responses | eight of the nine [decorators](/guide/decorators.md): a card of details, a callout, numbered sections, fields, choice lists, rated statements with badges, a blockquote | [responses.blade.php][res-b] | [responses.php][res-p] |
| Integration guide | portrait, landscape and portrait pages in one document, highlighted code blocks, method badges, a QR code, a checklist | [guide.blade.php][gd-b] | [guide.php][gd-p] |
| Certificate | a [page template](/guide/pages.md#filling-in-an-existing-pdf): a name, course and date placed on a blank PDF, and a signature image on the signature line. PDF only | [certificate.blade.php][ce-b] | [certificate.php][ce-p] |
| Knowledge base article | HTML from a rich text editor, converted by [Markup](/guide/rich-text.md): headings, a nested list, a table, a highlighted code block, a quotation | [article.blade.php][ar-b] | [article.php][ar-p] |

[inv-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/invoice.blade.php
[inv-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/invoice.php
[tr-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/transcript.blade.php
[tr-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/transcript.php
[rep-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/report.blade.php
[rep-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/report.php
[gd-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/guide.blade.php
[gd-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/guide.php
[ce-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/certificate.blade.php
[ce-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/certificate.php
[ar-b]: https://github.com/One-Learning-Community/papercut/blob/main/examples/laravel/views/article.blade.php
[ar-p]: https://github.com/One-Learning-Community/papercut/blob/main/examples/core/documents/article.php
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
    <figcaption><strong>Transcript</strong> (pages 1–2 of 3) <a :href="$withBase('/examples/transcript.pdf')">PDF</a> · <a :href="$withBase('/examples/transcript.docx')" download>Word</a></figcaption>
  </figure>
  <figure class="example-card two-pages">
    <div class="example-pages"><a :href="$withBase('/examples/report.pdf')"><img :src="$withBase('/examples/report-1.png')" alt="Report, page 1" loading="lazy"></a><a :href="$withBase('/examples/report.pdf')"><img :src="$withBase('/examples/report-2.png')" alt="Report, page 2" loading="lazy"></a></div>
    <figcaption><strong>Report</strong> <a :href="$withBase('/examples/report.pdf')">PDF</a> · <a :href="$withBase('/examples/report.docx')" download>Word</a></figcaption>
  </figure>
  <figure class="example-card">
    <div class="example-pages"><a :href="$withBase('/examples/responses.pdf')"><img :src="$withBase('/examples/responses-1.png')" alt="Form responses, page 1" loading="lazy"></a></div>
    <figcaption><strong>Form responses</strong> <a :href="$withBase('/examples/responses.pdf')">PDF</a> · <a :href="$withBase('/examples/responses.docx')" download>Word</a></figcaption>
  </figure>
  <figure class="example-card three-pages">
    <div class="example-pages"><a :href="$withBase('/examples/guide.pdf')"><img :src="$withBase('/examples/guide-1.png')" alt="Integration guide, page 1" loading="lazy"></a><a class="landscape" :href="$withBase('/examples/guide.pdf')"><img :src="$withBase('/examples/guide-2.png')" alt="Integration guide, page 2, landscape" loading="lazy"></a><a :href="$withBase('/examples/guide.pdf')"><img :src="$withBase('/examples/guide-3.png')" alt="Integration guide, page 3" loading="lazy"></a></div>
    <figcaption><strong>Integration guide</strong> <a :href="$withBase('/examples/guide.pdf')">PDF</a> · <a :href="$withBase('/examples/guide.docx')" download>Word</a></figcaption>
  </figure>
  <figure class="example-card two-pages">
    <div class="example-pages"><a :href="$withBase('/examples/certificate.pdf')"><img :src="$withBase('/examples/certificate-template.png')" alt="The blank certificate template" loading="lazy"><span class="example-label">Template</span></a><a :href="$withBase('/examples/certificate.pdf')"><img :src="$withBase('/examples/certificate-1.png')" alt="The certificate filled in" loading="lazy"><span class="example-label">Filled in</span></a></div>
    <figcaption><strong>Certificate</strong> <a :href="$withBase('/examples/certificate.pdf')">PDF</a> · Word and HTML exports leave templates out, so there is no Word file</figcaption>
  </figure>
  <figure class="example-card">
    <div class="example-pages"><a :href="$withBase('/examples/article.pdf')"><img :src="$withBase('/examples/article-1.png')" alt="Knowledge base article, page 1" loading="lazy"></a></div>
    <figcaption><strong>Knowledge base article</strong> (page 1 of 2) <a :href="$withBase('/examples/article.pdf')">PDF</a> · <a :href="$withBase('/examples/article.docx')" download>Word</a></figcaption>
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
php examples/core/guide.php
php examples/core/certificate.php
php examples/core/article.php

# The same documents as HTML (all but the certificate): writes examples/html/<name>.html
php examples/html/render.php

# The same documents as Word (all but the certificate): writes examples/docx/<name>.docx
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

## The integration guide example

The guide turns one page sideways for a wide endpoint table. A document can hold any number of pages, and each one has its own size, orientation, margins, header and footer. The content in each page flows onto as many sheets as it needs, all in that page's layout:

::: tabs#lang

@tab Blade

```blade
<x-doc::document title="Parcelpost API integration guide" author="Parcelpost">
    <x-doc::page size="letter" margin="0.75in">
        {{-- header, getting started, footer --}}
    </x-doc::page>

    <x-doc::page size="letter" orientation="landscape" margin="0.75in">
        {{-- header, endpoint reference, footer --}}
    </x-doc::page>

    <x-doc::page size="letter" margin="0.75in">
        {{-- header, webhooks, footer --}}
    </x-doc::page>
</x-doc::document>
```

@tab PHP

```php
return Document::make(
    Page::letter()->margin(in(0.75))->header($header('Integration guide'))->body($first)->footer($footer),
    Page::letter()->landscape()->margin(in(0.75))->header($header('Integration guide | Reference'))->body($second)->footer($footer),
    Page::letter()->margin(in(0.75))->header($header('Integration guide | Webhooks'))->body($third)->footer($footer),
)->title('Parcelpost API integration guide')->author('Parcelpost')->createdAt(new DateTimeImmutable('2026-03-02T09:00:00Z'));
```

:::

The page number in the footer counts across all three, so the landscape page reads "Page 2 of 3". In Word each page becomes its own section, and the middle one is a landscape section, so it stays sideways when someone edits the file. See [Pages and pagination](/guide/pages.md) for the other page options.

## The certificate example

The design for a certificate usually comes from a designer, as a PDF. This example fills in a blank one. The page template draws the PDF, and the overlay places the recipient's name, the course, the date and a signature image at fixed points on top of it:

::: tabs#lang

@tab Blade

```blade
<x-doc::page template="certificate-template.pdf">
    <x-slot:overlay>
        <x-doc::place x="5.5in" y="4.15in" anchor="bottom">
            <x-doc::text size="34pt" bold color="slate-900">{{ $name }}</x-doc::text>
        </x-doc::place>
        <x-doc::place x="5.5in" y="6.03in" anchor="bottom">
            <x-doc::text size="22pt" bold color="indigo-600">{{ $course }}</x-doc::text>
        </x-doc::place>
        <x-doc::place x="3.3in" y="6.91in" anchor="bottom">
            <x-doc::text size="13pt" color="slate-900">{{ $date }}</x-doc::text>
        </x-doc::place>
        <x-doc::place x="7.4in" y="7.02in" anchor="bottom">
            <x-doc::svg src="signature.svg" width="2.2in" />
        </x-doc::place>
    </x-slot:overlay>
</x-doc::page>
```

@tab PHP

```php
Page::template(PdfPage::file($assets . '/certificate-template.pdf'))->overlay(
    Place::at(in(5.5), in(4.15), Text::make($name)->size(pt(34))->bold()->color('slate-900'))->anchor(Anchor::Bottom),
    Place::at(in(5.5), in(6.03), Text::make($course)->size(pt(22))->bold()->color('indigo-600'))->anchor(Anchor::Bottom),
    Place::at(in(3.3), in(6.91), Text::make($date)->size(pt(13))->color('slate-900'))->anchor(Anchor::Bottom),
    Place::at(in(7.4), in(7.02), Svg::file($assets . '/signature.svg')->width(in(2.2)))->anchor(Anchor::Bottom),
),
```

:::

The template is drawn as vector content, not as a picture of the page. Its text and borders stay sharp at any zoom, and the name stays selectable text. With `anchor="bottom"` (`Anchor::Bottom` in PHP), the bottom edge of each piece of content sits at its point, so the name rests on its line whatever its length. The name and course are centred on the page because `x` is the middle of the content when the anchor is `bottom`.

Overlay positions ignore the page margins. They are measured from the top-left corner of the sheet, so the numbers match what you'd read off the design. In Blade, a relative `template` or `src` is resolved inside `papercut.image_path`; the example points that at `examples/assets`, where the template and `signature.svg` live. `examples/core/certificate-template.php` draws the blank template with Papercut so that it can be rebuilt, but any PDF from a design tool works the same way.

Word and HTML exports leave page templates out, so this example is PDF only. See [Filling in an existing PDF](/guide/pages.md#filling-in-an-existing-pdf) for the rest of the options.

## The knowledge base article example

Help articles are usually written in a rich text editor such as CKEditor 5, and saved as HTML. The article is that HTML in one string. Papercut converts it with no browser involved, and the page around it adds a header and a page-number footer:

::: tabs#lang

@tab Blade

```blade
<x-doc::column gap="12pt">
    <x-doc::column gap="4pt">
        <x-doc::h1>Setting up two-factor sign-in</x-doc::h1>
        <x-doc::text size="9pt" color="slate-500">Updated 12 March 2026 · 4 min read</x-doc::text>
    </x-doc::column>
    <x-doc::markup :html="$html" />
</x-doc::column>
```

@tab PHP

```php
$article = Column::make(
    Column::make(
        Text::h1('Setting up two-factor sign-in'),
        Text::make('Updated 12 March 2026 · 4 min read')->size(pt(9))->color('slate-500'),
    )->gap(pt(4)),
    Markup::html($html),
)->gap(pt(12));
```

:::

In the example, `$html` is a heredoc holding what an editor would save: `h2` and `h3` headings, paragraphs with bold, italic and links, a list with a nested list, a table with a header row, a `<pre><code class="language-bash">` block and a blockquote. The theme styles all of it, so the article matches the rest of your documents. The code block is highlighted because of its `language-bash` class.

Anything Papercut can't convert is reported, never silently lost. The example's test turns on strict mode and checks that the article produces no warnings. See [Rich text](/guide/rich-text.md#conversion-warnings) for what gets converted and what the warnings mean.
