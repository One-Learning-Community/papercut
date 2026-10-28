# Introduction

Papercut is a PDF library for PHP 8.4. You describe a document as a tree of components (pages, rows, columns, text, tables) and Papercut lays it out, splits it into pages and writes the PDF.

It comes as two packages:

- **`papercut/core`** is the layout engine and a fluent PHP API. It doesn't need a framework.
- **`papercut/laravel`** adds Blade components on top. Every `<x-doc::*>` tag builds a core component, so a Blade view and the PHP API describe exactly the same document.

## Why another PDF library?

Most PHP PDF libraries fall into one of two camps.

Low-level libraries give you a cursor and coordinates. You place each string yourself, which is precise, but you also work out every line break and page break yourself.

HTML-to-PDF tools let you write HTML and CSS instead. That's comfortable until you need print behaviour like repeating table headers, keeping a heading with its paragraph, or "page 3 of 12". Support for those is patchy and varies from one renderer to the next.

Papercut sits in between. Its layout model is a small subset of flexbox, so it should feel familiar, but it was designed around pages from the start. It's also strict, with clear warnings and errors so you know your documents come out right.

## How it works

1. You build a tree of components, in PHP or with Blade tags.
2. The engine measures each component against the available width and places it. Rows and columns divide up the space using gaps, grow factors and alignment, much like flexbox does.
3. When a page fills up, the engine splits whatever can be split: paragraphs between lines, tables between rows, boxes between their children. It respects keep-together and keep-with-next as it goes.
4. The result is a list of shapes, text and links, which a thin adapter writes out as a PDF using [`dskripchenko/php-pdf`](https://github.com/dskripchenko/php-pdf).

The HTML exporter works from the same tree, which is how one document can produce both formats.

## Built on php-pdf

Papercut does the layout; [php-pdf](https://github.com/dskripchenko/php-pdf), by Denis Skripchenko, writes the PDF. It parses, subsets and embeds the fonts and applies their kerning, embeds the images, draws SVG, encodes QR codes, encrypts, and reads existing PDFs for page templates. Its sibling [php-docx](https://github.com/dskripchenko/php-docx) writes Papercut's Word files. When Papercut needed more from php-pdf, such as even-odd fills in SVG and faster font loading, the maintainer added it upstream.

## Current limitations

Papercut is at 0.2, and a few things aren't supported yet:

- complex text shaping (HarfBuzz), right-to-left and bidirectional text, and hyphenation
- multi-column text flow
- form filling and PDF/A
- tables of contents

Kerning and ligatures do work. Signing and merging aren't built in, but php-pdf can do both to a Papercut PDF; see [Passwords, signatures and merging](./passwords-and-signatures.md).

## Next steps

- [Installation](./installation.md) gets the packages into your project.
- [Your first document](./first-document.md) builds and saves a PDF, in Blade and in PHP.
- [Decorators](./decorators.md) covers the ready-made cards, callouts and form fields.
