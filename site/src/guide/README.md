# Introduction

Quoin is a document library for PHP 8.4. You describe a document as a tree of components (pages, rows, columns, text, tables). It lays it out, splits it into pages and writes it as a PDF, a Word file or HTML.

It comes as four packages:

- **`quoin/core`** is the layout engine and a fluent PHP API. It doesn't need a framework.
- **`quoin/math`** lays out equations from an OpenType math font. Core uses it for `Equation` and `Span::math()`; it also works on its own. See [Equations](./equations.md).
- **`quoin/laravel`** adds Blade components on top. Every `<x-doc::*>` tag builds a core component, so a Blade view and the PHP API describe exactly the same document.
- **`quoin/docx`** writes Word (`.docx`) files. It needs `ext-zip`. See [Word export](./word-export.md).

## Why another PDF library?

Most PHP PDF libraries fall into one of two camps.

Low-level libraries give you a cursor and coordinates. You place each string yourself, which is precise, and you work out every line break and page break too.

HTML-to-PDF tools let you write HTML and CSS instead. That's comfortable until you need print behavior like repeating table headers, keeping a heading with its paragraph, or "page 3 of 12". Support for those is patchy and varies from one renderer to the next.

This library sits in between. Its layout model is a small subset of flexbox, designed around pages from the start. It is strict: clear warnings and errors tell you when a document won't come out right.

## How it works

1. You build a tree of components, in PHP or with Blade tags.
2. The engine measures each component against the available width and places it. Rows and columns divide up the space using gaps, grow factors and alignment, much like flexbox does.
3. When a page fills up, the engine splits whatever can be split: paragraphs between lines, tables between rows, boxes between their children. It respects keep-together and keep-with-next as it goes.
4. The result is a list of shapes, text and links, which a thin adapter writes out as a PDF using [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf).

The HTML and Word exporters work from the same tree, so one document produces all three formats.

## Built on php-pdf

This package does the layout; [php-pdf](https://github.com/dskripchenko/php-pdf), by Denis Skripchenko, writes the PDF. It parses, subsets and embeds the fonts and applies their kerning, embeds the images, draws SVG, encodes QR codes, encrypts, and reads existing PDFs for page templates. Its sibling [php-docx](https://github.com/dskripchenko/php-docx) writes the Word files. When more was needed from php-pdf, such as even-odd fills in SVG and faster font loading, the maintainer added it upstream.

## Current limitations

This is version 0.17, before 1.0, and a few things aren't supported yet:

- complex text shaping (HarfBuzz), right-to-left paragraphs (a right-to-left name in a left-to-right document works) and hyphenation
- fillable form fields (AcroForms) and PDF/A

Filling in an existing PDF works: [PDF templates](./pages.md#filling-in-an-existing-pdf) place your content on a page of another PDF.

Kerning and ligatures do work. Signing and merging aren't built in, but php-pdf can do both to a generated PDF; see [Passwords, signatures and merging](./passwords-and-signatures.md).

## Next steps

- [Installation](./installation.md) gets the packages into your project.
- [Your first document](./first-document.md) builds and saves a PDF, in Blade and in PHP.
- [Decorators](./decorators.md) covers the ready-made cards, callouts and form fields.
- [Layout](./layout.md#column-flow) covers rows, columns and text set in several columns.
- [Equations](./equations.md) covers formulas, on their own line and inside text.
- [Word export](./word-export.md) turns the same document into an editable Word file.
