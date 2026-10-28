<?php

declare(strict_types=1);

namespace Papercut\Docx;

use Dskripchenko\PhpDocx\Document as DocxDocument;
use Dskripchenko\PhpDocx\Element\{BlockElement, PageBreak};
use Dskripchenko\PhpDocx\Section;
use Dskripchenko\PhpDocx\Writer\Word2007Writer;
use Papercut\Backend\DefaultBackend;
use Papercut\Document;
use Papercut\Docx\Map\{BoxMapper, ImageMapper, LineMetrics, ListMapper, RowMapper, TableMapper, TextMapper};
use Papercut\Docx\Walk\DocxVisitor;
use Papercut\Export\{ExportOptions, Exporter, Fidelity, NoteReporter, Output};
use Papercut\Layout\WidthResolver;
use Papercut\Style\StyleResolver;
use Papercut\Tree\TreeWalker;

/** Word (.docx) export: the document's pages as one flowing Word section that Word paginates. */
final class DocxExporter implements Exporter
{
    public const string MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function export(Document $doc, ExportOptions $options): Output
    {
        $options = DocxOptions::from($options);
        $styles = new StyleResolver($options->theme());
        $fidelity = new Fidelity('docx', $options);
        NoteReporter::report($doc, $styles, $fidelity);

        $pages = $doc->pages();
        foreach ($pages as $i => $page) {
            $name = count($pages) > 1 ? sprintf('page[%d]', $i) : 'page';
            if ($page->templatePage() !== null) {
                $fidelity->dropped('PDF page template', $name);
            }
            if ($page->backgroundNodes() !== []) {
                $fidelity->dropped('background layer', $name . ' > background');
            }
            if ($page->overlayPlaces() !== []) {
                $fidelity->dropped('overlay layer', $name . ' > overlay');
            }
        }

        $boxes = new BoxMapper($fidelity);
        $visitor = new DocxVisitor(
            $fidelity,
            WidthResolver::for($options),
            new TextMapper(new LineMetrics($options->fonts()), $fidelity),
            $boxes,
            new RowMapper($fidelity, $boxes),
            new TableMapper($fidelity, $boxes),
            new ListMapper(),
            new ImageMapper($fidelity, DefaultBackend::make(false)),
        );
        (new TreeWalker($styles))->walk($doc, $visitor);
        $first = $visitor->pages()[0] ?? throw new \LogicException('A document needs a page');

        $section = new Section($this->body($visitor), $first['regions']['header'] ?? [], $first['regions']['footer'] ?? []);

        return new Output((new Word2007Writer())->write(new DocxDocument($section)), self::MIME, 'docx');
    }

    /** @return list<BlockElement> every page's body, joined by page breaks */
    private function body(DocxVisitor $visitor): array
    {
        $body = [];
        foreach ($visitor->pages() as $i => $p) {
            if ($i > 0) {
                $body[] = new PageBreak();
            }
            foreach ($p['regions']['body'] ?? [] as $b) {
                $body[] = $b;
            }
        }

        return $body;
    }

    public function defaultOptions(): DocxOptions
    {
        return new DocxOptions();
    }
}
