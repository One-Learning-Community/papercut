<?php

declare(strict_types=1);

namespace Papercut\Docx;

use Dskripchenko\PhpDocx\Document as DocxDocument;
use Dskripchenko\PhpDocx\Element\{Paragraph, Run};
use Dskripchenko\PhpDocx\Section;
use Dskripchenko\PhpDocx\Writer\Word2007Writer;
use Papercut\{Document, Node, Page, Text};
use Papercut\Export\{ExportOptions, Exporter, Fidelity, NoteReporter, Output};
use Papercut\Style\{ComputedStyle, StyleResolver};
use Papercut\Tree\{Region, TreeVisitor, TreeWalker, Visit};

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
        $visitor = new class () implements TreeVisitor {
            /** @var list<Paragraph> */
            public array $body = [];

            public function enterPage(Page $page, int $index): void
            {
            }

            public function leavePage(Page $page, int $index): void
            {
            }

            public function enterRegion(Region $region, ComputedStyle $root): void
            {
            }

            public function leaveRegion(Region $region): void
            {
            }

            public function enter(Node $node, ComputedStyle $style, Visit $at): bool
            {
                if ($node instanceof Text && $at->region() === Region::Body) {
                    $this->body[] = new Paragraph([new Run(implode("", array_map(static fn ($s): string => $s->text, $node->spanList())))]);
                }

                return true;
            }

            public function leave(Node $node, ComputedStyle $style, Visit $at): void
            {
            }
        };
        (new TreeWalker($styles))->walk($doc, $visitor);

        return new Output((new Word2007Writer())->write(new DocxDocument(new Section($visitor->body))), self::MIME, 'docx');
    }

    public function defaultOptions(): DocxOptions
    {
        return new DocxOptions();
    }
}
