---
sidebar: false
---

# From Tailwind

Paste HTML or a Blade view styled with Tailwind classes and get a first draft in Papercut tags. It runs in your browser and nothing is sent anywhere.

<ClientOnly>
  <TailwindTranslator />
</ClientOnly>

## What carries over

- **Boxes.** `flex` becomes `<x-doc::row>`, `flex-col` and plain block elements become `<x-doc::column>`, and an element holding only text becomes `<x-doc::text>`. `p`, `h1` to `h4`, lists, tables, images and links map to their Papercut tags.
- **Spacing and sizes** from Tailwind's scale, where one step is 4px (3pt). Fractions like `w-1/2` become percentages, and arbitrary values like `p-[13px]` are kept.
- **Colours** by name. Papercut knows the whole Tailwind palette, so `bg-slate-50` stays `slate-50`.
- **Text:** sizes, weights, italic, underline, line-through, leading, tracking and alignment.
- **Borders and corners:** widths, sides, colours, `border-dashed` and `rounded`. A border with no colour class gets `gray-200`, Tailwind 3's default.
- **Printing:** `break-inside-avoid` becomes `keep-together`, `break-before-page` and `break-after-page` start new pages, and `print:` classes are applied on top of the others. An element that is `hidden` (or `print:hidden`) is removed.
- **Blade** echoes, comments and `@` directives stay where they were. Other components (`<x-…>`) are kept as they are, and the classes on Papercut tags you already wrote become attributes.

## What doesn't

Anything screen-only is left out and listed in a `{{-- not translated --}}` comment above the tag: breakpoints (`md:`), states (`hover:`), `grid`, shadows, rings, `mx-auto`, negative margins, `rounded-full` and colour opacity (`bg-red-500/50`). Layout classes on tags that can't use them, like `gap-4` on a table cell, are listed too. Papercut only follows absolute links, so a relative `href="/about"` keeps its text and loses the link; a Blade echo such as `href="{{ route('about') }}"` is kept. Tailwind's text sizes also set a line height; Papercut keeps its own unless you add a `leading-` class.

Tailwind resets the margins of paragraphs and headings to zero, while Papercut's `p` and heading presets keep theirs, so the result can be a little roomier than the web page.
