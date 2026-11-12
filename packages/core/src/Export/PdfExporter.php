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
        if ($tagged) {
            self::checkHeadings($pages, $options);
        }

        $meta = $doc->meta()->withEncryption($options->encryption());

        return new Output($backend->render($meta, $pages, $tagged), 'application/pdf', 'pdf');
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
        $missing = $backend instanceof TaggingCapability ? $backend->taggingMissing() : [];
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
     * Tagged mode: a heading more than one level below the previous one, or a first heading below H1, fails
     * PDF/UA-1 (veraPDF 7.4.2-1). Checked on the painted pages, where the body headings are final: in
     * reading order, a heading split over pages or columns reported once, with no measurement passes or retries and no page furniture.
     *
     * @param list<PageDisplayList> $pages
     * @throws InvalidStructure in strict mode
     */
    private static function checkHeadings(array $pages, PdfOptions $options): void
    {
        $previous = 0;
        $warned = [];
        foreach ($pages as $i => $page) {
            foreach (self::headings($page->ops) as $tagged) {
                $level = (int) substr($tagged->tag->role, 1);
                if ($level > $previous + 1 && !isset($warned[$tagged->tag->key])) {
                    $warned[$tagged->tag->key] = true;
                    $text = trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_map(
                        static fn (TextRun $run): string => $run->text,
                        iterator_to_array(self::textRuns($tagged->ops), false),
                    ))));
                    $text = mb_strlen($text) > 40 ? mb_substr($text, 0, 39) . '…' : $text;
                    $fix = sprintf(
                        'Make it H%1$d: Text::h%2$d(\'…\')->asHeading(%1$d) keeps the H%2$d look (a Section or Card takes ->heading(%1$d)).',
                        $previous + 1,
                        $level,
                    );
                    $name = $text === '' ? 'A heading' : sprintf('Heading "%s"', $text);
                    $message = $previous === 0
                        ? sprintf('The first heading%s on page %d, is H%d; a tagged PDF must start with H1. %s', $text === '' ? ',' : sprintf(', "%s"', $text), $i + 1, $level, $fix)
                        : sprintf('%s on page %d is H%d right after an H%d; a tagged PDF can\'t skip heading levels. %s', $name, $i + 1, $level, $previous, $fix);
                    if ($options->strict()) {
                        throw new InvalidStructure($message);
                    }
                    $options->onWarning()?->__invoke('pdf: ' . $message);
                }
                $previous = $level;
            }
        }
    }

    /**
     * @param list<Op> $ops
     * @return iterable<Tagged> heading elements in paint order
     */
    private static function headings(array $ops): iterable
    {
        foreach ($ops as $op) {
            if ($op instanceof Tagged && preg_match('/^H[1-6]$/', $op->tag->role) === 1) {
                yield $op;
            } elseif ($op instanceof Clip || $op instanceof Opacity || $op instanceof Tagged) {
                yield from self::headings($op->ops);
            }
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
