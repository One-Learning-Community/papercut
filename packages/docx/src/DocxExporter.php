<?php

declare(strict_types=1);

namespace Papercut\Docx;

use Dskripchenko\PhpDocx\Document as DocxDocument;
use Dskripchenko\PhpDocx\Element\{BlockElement, PageBreak};
use Dskripchenko\PhpDocx\Section;
use Dskripchenko\PhpDocx\Writer\Word2007Writer;
use Papercut\Document;
use Papercut\Docx\Map\{BoxMapper, LineMetrics, RowMapper, TextMapper};
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
        $visitor = new DocxVisitor($fidelity, WidthResolver::for($options), new TextMapper(new LineMetrics($options->fonts()), $fidelity), $boxes = new BoxMapper($fidelity), new RowMapper($fidelity, $boxes));
        (new TreeWalker($styles))->walk($doc, $visitor);

        return new Output((new Word2007Writer())->write(new DocxDocument(new Section($this->body($visitor)))), self::MIME, 'docx');
    }

    /** @return list<BlockElement> */
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
