<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests;

use Papercut\{Document, ListNode, Node, Page, Text};
use Papercut\Docx\DocxExporter;
use Papercut\Docx\Map\ListMapper;
use Papercut\Docx\Tests\Support\{Documents, DocxInspector};
use Papercut\Style\{ComputedStyle, StyleResolver, Theme};
use Papercut\Tree\{Region, TreeVisitor, TreeWalker, Visit};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

/**
 * The words pdftotext finds in the PDF equal the words of the DOCX body, counted with multiplicity (body only).
 * Table header rows repeat on every PDF page and once in Word, so their words are left out of both sides.
 * Checkbox and radio markers are drawn shapes in the PDF and characters in Word, so they are left out too.
 * A table of contents' dot leaders are dots drawn as text in the PDF and a tab leader in Word, so runs of dots are left out of both sides.
 * Equations are dropped from both sides (the PDF sets their letters as mathematical italics, and Word holds them in `m:t`); the text around them stays compared.
 * List labels are text in the PDF and Word numbering in Word (not in the body text), so they are taken off the PDF side.
 */
final class ContentParityTest extends TestCase
{
    private const array MARKERS = ['☐', '▣', '○', '◉'];

    /** @return iterable<string, array{string}> */
    public static function documents(): iterable
    {
        return Documents::all();
    }

    protected function setUp(): void
    {
        exec('command -v pdftotext 2>/dev/null', $out, $code);
        if ($code !== 0) {
            self::markTestSkipped('pdftotext (poppler) is not installed.');
        }
    }

    #[DataProvider('documents')]
    public function test_pdf_and_docx_have_the_same_word_multisets(string $file): void
    {
        $source = Documents::load($file);
        self::assertStringNotContainsString('papercut-math', DocxInspector::of($source->export(new DocxExporter())->bytes())->part('word/document.xml'));
        $pages = array_map(static fn (Page $p): Page => Page::size(pt($p->width()), pt($p->height()))->margin($p->margins())->body(...self::withoutEquations($p->bodyNodes())), $source->pages());
        $document = Document::make(...$pages);

        $skip = $this->headerWords($document) + array_fill_keys(self::MARKERS, true);
        $docx = DocxInspector::of($document->export(new DocxExporter())->bytes())->bodyText();
        $words = $this->words($docx, $skip);
        self::assertNotSame([], $words);
        $pdf = $this->words($this->pdfText($document), $skip);
        foreach ($this->listLabels($document) as $label => $n) {
            if (isset($pdf[$label])) {
                $pdf[$label] -= $n;
                if ($pdf[$label] <= 0) {
                    unset($pdf[$label]);
                }
            }
        }
        self::assertSame($pdf, $words);
    }

    private function pdfText(Document $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pc') ?: self::fail('tempnam');
        try {
            file_put_contents($path, $document->toPdf()->bytes());

            return (string) shell_exec('pdftotext -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
        } finally {
            @unlink($path);
        }
    }

    /** @return array<string,true> every word in every table header row of the document */
    private function headerWords(Document $document): array
    {
        $collector = new class () implements TreeVisitor {
            /** @var array<string,true> */
            public array $words = [];

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
                if ($node instanceof Text && $at->inHeader()) {
                    foreach ($node->spanList() as $span) {
                        foreach (preg_split('/\s+/u', trim(str_replace('-', '', $span->text)), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $w) {
                            $this->words[$w] = true;
                        }
                    }
                }

                return true;
            }

            public function leave(Node $node, ComputedStyle $style, Visit $at): void
            {
            }
        };
        (new TreeWalker(new StyleResolver(Theme::default())))->walk($document, $collector);

        return $collector->words;
    }

    /** @return array<string,int> label => occurrences of every list label the PDF draws as text */
    private function listLabels(Document $document): array
    {
        $collector = new class () implements TreeVisitor {
            /** @var array<string,int> */
            public array $labels = [];

            /** @var list<bool> ordered flag per open list */
            private array $open = [];

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
                if ($node instanceof ListNode) {
                    $marker = ListMapper::markerFor($node, $this->open);
                    $this->open[] = $node->isOrdered();
                    foreach ($node->numbers() as $n) {
                        $label = $marker->label($n);
                        if ($label !== '') {
                            $this->labels[$label] = ($this->labels[$label] ?? 0) + 1;
                        }
                    }
                }

                return true;
            }

            public function leave(Node $node, ComputedStyle $style, Visit $at): void
            {
                if ($node instanceof ListNode) {
                    array_pop($this->open);
                }
            }
        };
        (new TreeWalker(new StyleResolver(Theme::default())))->walk($document, $collector);

        return $collector->labels;
    }

    /**
     * @param array<string,true> $skip
     * @return array<string,int> word => occurrences, sorted by word
     */
    private function words(string $text, array $skip): array
    {
        // pdftotext dehyphenates words that wrap at a hyphen, so hyphens are ignored on both sides.
        $words = preg_split('/\s+/u', trim(str_replace('-', '', $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $counts = [];
        foreach ($words as $w) {
            if (!isset($skip[$w]) && preg_match('/^\.{4,}$/', $w) !== 1) {
                $counts[$w] = ($counts[$w] ?? 0) + 1;
            }
        }
        ksort($counts, SORT_STRING);

        return $counts;
    }

    /**
     * The nodes without any Equation: the PDF sets an equation's letters as mathematical italics and the other formats hold them as math,
     * so their words are not comparable.
     *
     * @param list<\Papercut\Node|\Papercut\Component> $nodes
     * @return list<\Papercut\Node|\Papercut\Component>
     */
    private static function withoutEquations(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $n) {
            if ($n instanceof \Papercut\Equation) {
                continue;
            }
            // A paragraph (or table) with an inline formula: its formula words differ the same way, so it is left out whole.
            if (!($n instanceof \Papercut\View) && self::hasInlineMath($n)) {
                continue;
            }
            $out[] = $n instanceof \Papercut\View ? $n->withChildren(...self::withoutEquations($n->children())) : $n;
        }

        return $out;
    }

    private static function hasInlineMath(\Papercut\Node|\Papercut\Component $n): bool
    {
        if ($n instanceof \Papercut\Text) {
            return array_filter($n->spanList(), static fn (\Papercut\Span $s): bool => $s->math !== null) !== [];
        }
        if ($n instanceof \Papercut\Table) {
            foreach ($n->bodyRows() as $row) {
                foreach ($row->cells() as $cell) {
                    foreach ($cell->children() as $child) {
                        if (self::hasInlineMath($child)) {
                            return true;
                        }
                    }
                }
            }
        }
        if ($n instanceof \Papercut\View) {
            foreach ($n->children() as $child) {
                if (self::hasInlineMath($child)) {
                    return true;
                }
            }
        }

        return false;
    }
}
