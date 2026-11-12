<?php

declare(strict_types=1);

namespace Papercut\Export;

use Papercut\Backend\{Backend, TaggingCapability};
use Papercut\Document;
use Papercut\Exception\{InvalidStructure, UnsupportedFeature};
use Papercut\Layout\{LayoutContext, LayoutEngine, Paginator};
use Papercut\Paint\{Clip, Op, Opacity, PageDisplayList, Painter, Tagged, TextRun};
use Papercut\Style\PageThemes;
use Papercut\Text\FontRegistry;
use Papercut\Toc\TocIndex;

/** @internal Part of Papercut's engine, not its public API. It may change in any release. */
final class PdfExporter implements Exporter
{
    /**
     * Lays out, paints and renders the document to PDF bytes. The font registry is built
     * on the same backend instance that renders.
     */
    public function export(Document $doc, ExportOptions $options): Output
    {
        $options = PdfOptions::from($options);
        $backend = $options->backend();
        $tagged = $options->tagged() && self::requireTaggable($doc, $options, $backend);
        $fonts = $options->fonts();
        $themes = PageThemes::for($options->theme(), $doc);
        NoteReporter::report($doc, $themes, new Fidelity('pdf', $options));
        $ctx = new LayoutContext(
            FontRegistry::fromSet($fonts, $backend),
            $themes->document(),
            $backend,
            $options->overflow(),
            $options->onWarning(),
            $fonts->fallbacks,
        );
        $ctx->themes = $themes;
        $ctx->bookmarks = $options->bookmarks();
        $ctx->tagged = $tagged;
        $ctx->strict = $options->strict();
        $ctx->toc = TocIndex::collect($doc, $themes);
        // Each page is painted as soon as it is laid out, so its layout is freed before the next page's.
        $pages = Painter::resolve((new Paginator(new LayoutEngine($ctx), $ctx))->paginate($doc, Painter::paintPage(...)));
        if ($options->strict()) {
            self::requireGlyphs($pages);
        }

        $meta = $doc->meta()->withEncryption($options->encryption());

        $bytes = $backend->render($meta, $pages, $tagged);
        if ($tagged && $backend instanceof TaggingCapability) {
            self::checkHeadings($backend->headings(), $options);
            self::checkContent($backend->structureHasContent(), $options);
        }

        return new Output($bytes, 'application/pdf', 'pdf');
    }

    /**
     * Whether a tagged PDF can be written: the document needs a title and a language, and the backend what
     * tagging uses. Without that, warns and writes an untagged PDF.
     *
     * @throws InvalidStructure for a missing title or language
     * @throws UnsupportedFeature in strict mode when the backend cannot tag
     */
    private static function requireTaggable(Document $doc, PdfOptions $options, Backend $backend): bool
    {
        $meta = $doc->meta();
        if ($meta->title === null || trim($meta->title) === '') {
            throw new InvalidStructure('A tagged PDF needs a document title: set one with Document::title().');
        }
        if ($meta->lang === null || trim($meta->lang) === '') {
            throw new InvalidStructure('A tagged PDF needs a document language: set one with Document::lang(), for example lang(\'en-US\').');
        }
        if (!$backend instanceof TaggingCapability) {
            (new Fidelity('pdf', $options))->dropped('tagged output (this backend cannot write a structure tree), writing an untagged PDF', 'document');
            return false;
        }
        $missing = $backend->taggingMissing();
        if ($missing === []) {
            return true;
        }
        (new Fidelity('pdf', $options))->dropped(
            sprintf('tagged output (the installed php-pdf lacks %s; update php-pdf), writing an untagged PDF', implode(', ', $missing)),
            'document',
        );
        return false;
    }

    /**
     * Tagged mode: a structure tree with nothing in it passes PDF/UA but a screen reader finds nothing to read.
     * That is what a document of only artifacts (headers, footers, backgrounds, template pages, images without
     * alt text) comes to.
     *
     * @throws InvalidStructure in strict mode
     */
    private static function checkContent(bool $hasContent, PdfOptions $options): void
    {
        if ($hasContent) {
            return;
        }
        $message = 'The tagged PDF has no content in its structure: everything in the document is an artifact (page headers and footers, backgrounds, a template page, images or SVG without alt text, an imported PDF page), so a screen reader finds nothing to read. Put text in the body or an overlay, or give images alt text.';
        if ($options->strict()) {
            throw new InvalidStructure($message);
        }
        $options->onWarning()?->__invoke('pdf: ' . $message);
    }

    /**
     * Tagged mode: a heading more than one level below the previous one, or a first heading below H1, fails
     * PDF/UA-1 (veraPDF 7.4.2-1). Checked on the backend's structure tree, in the order a reader meets the
     * headings (a container that goes on over pages or beside another keeps its place), a split heading reported
     * once, with no page furniture.
     *
     * @param list<array{level: int, text: string, page: int, key: string}> $headings
     * @throws InvalidStructure in strict mode
     */
    private static function checkHeadings(array $headings, PdfOptions $options): void
    {
        $previous = 0;
        $warned = [];
        foreach ($headings as $heading) {
            $level = $heading['level'];
            if ($level > $previous + 1 && !isset($warned[$heading['key']])) {
                $warned[$heading['key']] = true;
                $text = mb_strlen($heading['text']) > 40 ? mb_substr($heading['text'], 0, 39) . '…' : $heading['text'];
                $page = $heading['page'];
                $fix = sprintf(
                    'Make it H%1$d: %2$s (a Section or Card takes ->heading(%1$d), a Blade text tag heading="%1$d").',
                    $previous + 1,
                    $level <= 4
                        ? sprintf('Text::h%2$d(\'…\')->asHeading(%1$d) keeps the H%2$d look', $previous + 1, $level)
                        : sprintf('->asHeading(%d)', $previous + 1),
                );
                $name = $text === '' ? 'A heading' : sprintf('Heading "%s"', $text);
                $message = $previous === 0
                    ? sprintf('The first heading%s on page %d, is H%d; a tagged PDF must start with H1. %s', $text === '' ? ',' : sprintf(', "%s"', $text), $page, $level, $fix)
                    : sprintf('%s on page %d is H%d right after an H%d; a tagged PDF can\'t skip heading levels. %s', $name, $page, $level, $previous, $fix);
                if ($options->strict()) {
                    throw new InvalidStructure($message);
                }
                $options->onWarning()?->__invoke('pdf: ' . $message);
            }
            $previous = $level;
        }
    }

    /**
     * Strict mode: text on a page with characters its font has no glyph for (they print as empty boxes) throws.
     *
     * @param list<PageDisplayList> $pages
     * @throws UnsupportedFeature
     */
    private static function requireGlyphs(array $pages): void
    {
        foreach ($pages as $i => $page) {
            foreach (self::textRuns($page->ops) as $run) {
                if ($run->font->covers($run->text)) {
                    continue;
                }
                $missing = implode('', array_filter(
                    array_unique(mb_str_split($run->text, 1, 'UTF-8')),
                    static fn (string $ch): bool => !$run->font->covers($ch),
                ));
                throw UnsupportedFeature::from(new FidelityReport(
                    FidelityLevel::Dropped,
                    'pdf',
                    sprintf('characters no font has ("%s"; register a fallback font that has them)', $missing),
                    sprintf('page %d', $i + 1),
                ));
            }
        }
    }

    /**
     * @param list<Op> $ops
     * @return iterable<TextRun>
     */
    private static function textRuns(array $ops): iterable
    {
        foreach ($ops as $op) {
            if ($op instanceof TextRun) {
                yield $op;
            } elseif ($op instanceof Clip || $op instanceof Opacity || $op instanceof Tagged) {
                yield from self::textRuns($op->ops);
            }
        }
    }

    public function defaultOptions(): ExportOptions
    {
        return new PdfOptions();
    }
}
