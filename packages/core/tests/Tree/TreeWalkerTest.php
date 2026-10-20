<?php

declare(strict_types=1);

namespace Papercut\Tests\Tree;

use Papercut\{Component, Document, Node, Page, PageNumber, Row, Table, TableCell, Text};
use Papercut\Export\PdfOptions;
use Papercut\Style\{ComputedStyle, StyleResolver, Theme};
use Papercut\Value\Color;
use Papercut\Tests\Support\FakeBackend;
use Papercut\Tree\{Region, TreeVisitor, TreeWalker};
use PHPUnit\Framework\TestCase;

final class TreeWalkerTest extends TestCase
{
    public function test_visits_regions_and_nodes_in_order_with_paths(): void
    {
        $doc = Document::make(Page::a4()->header(Text::make('H'))->body(Row::make(Text::make('a'), Text::make('b')))->footer(PageNumber::make()));
        self::assertSame([
            'page:0',
            'region:header', 'enter:page > header > text[0]', 'leave:page > header > text[0]', 'leaveRegion:header',
            'region:body',
            'enter:page > body > row[0]',
            'enter:page > body > row[0] > text[0]', 'leave:page > body > row[0] > text[0]',
            'enter:page > body > row[0] > text[1]', 'leave:page > body > row[0] > text[1]',
            'leave:page > body > row[0]',
            'leaveRegion:body',
            'region:footer', 'enter:page > footer > page-number[0]', 'leave:page > footer > page-number[0]', 'leaveRegion:footer',
            'leavePage:0',
        ], $this->walk($doc));
    }

    public function test_background_comes_first_and_several_pages_are_indexed(): void
    {
        $doc = Document::make(Page::a4()->body(Text::make('a'))->background(Text::make('bg')), Page::a4()->body(Text::make('b')));
        self::assertSame([
            'page:0',
            'region:background', 'enter:page[0] > background > text[0]', 'leave:page[0] > background > text[0]', 'leaveRegion:background',
            'region:body', 'enter:page[0] > body > text[0]', 'leave:page[0] > body > text[0]', 'leaveRegion:body',
            'leavePage:0',
            'page:1',
            'region:body', 'enter:page[1] > body > text[0]', 'leave:page[1] > body > text[0]', 'leaveRegion:body',
            'leavePage:1',
        ], $this->walk($doc));
    }

    public function test_expands_components_in_header_and_table_cells(): void
    {
        $comp = new class implements Component {
            public function render(): Node|Component
            {
                return Text::make('expanded');
            }
        };
        $doc = Document::make(Page::a4()->header($comp)->body(Table::make()->row(TableCell::make($comp))));
        $visitor = $this->recorder();
        (new TreeWalker(new StyleResolver(Theme::default())))->walk($doc, $visitor);
        $texts = array_values(array_filter($visitor->log, fn ($l) => str_starts_with($l, 'enter:') && str_ends_with($l, 'text[0]')));
        self::assertSame([
            'enter:page > header > text[0]',
            'enter:page > body > table[0] > tr[0] > td[0] > text[0]',
        ], $texts);
        self::assertSame(2, count(array_filter($visitor->nodes, fn ($n) => $n instanceof Text)));
    }

    public function test_returning_false_skips_children(): void
    {
        $doc = Document::make(Page::a4()->body(Row::make(Text::make('a')), Text::make('b')));
        $visitor = $this->recorder(skip: 'row');
        (new TreeWalker(new StyleResolver(Theme::default())))->walk($doc, $visitor);
        self::assertSame([
            'enter:page > body > row[0]', 'leave:page > body > row[0]',
            'enter:page > body > text[1]', 'leave:page > body > text[1]',
        ], array_values(array_filter($visitor->log, fn ($l) => str_starts_with($l, 'enter:') || str_starts_with($l, 'leave:'))));
    }

    public function test_table_rows_and_cells_get_layout_paths(): void
    {
        $doc = Document::make(Page::a4()->body(Table::make()->head('H')->row('a', 'b')->row('c')));
        $paths = array_values(array_filter($this->walk($doc), fn ($l) => preg_match('/^enter:.* > (td|th)\[\d+\]$/', $l) === 1));
        self::assertSame([
            'enter:page > body > table[0] > tr[0] > th[0]',
            'enter:page > body > table[0] > tr[1] > td[0]',
            'enter:page > body > table[0] > tr[1] > td[1]',
            'enter:page > body > table[0] > tr[2] > td[0]',
        ], $paths);
    }

    public function test_bad_named_style_in_cell_matches_pdf_error(): void
    {
        $doc = Document::make(Page::a4()->body(Table::make()->row(TableCell::make('x')->apply('nope'))));
        $pdfMsg = self::messageOf(fn () => $doc->toPdf(new PdfOptions(backend: new FakeBackend())));
        $walkMsg = self::messageOf(fn () => $this->walk($doc));
        self::assertNotSame('', $pdfMsg);
        self::assertSame($pdfMsg, $walkMsg);
    }

    public function test_bad_named_style_in_body_view_matches_pdf_error(): void
    {
        $doc = Document::make(Page::a4()->body(Row::make(Text::make('x')->apply('nope'))));
        $pdfMsg = self::messageOf(fn () => $doc->toPdf(new PdfOptions(backend: new FakeBackend())));
        self::assertNotSame('', $pdfMsg);
        self::assertSame($pdfMsg, self::messageOf(fn () => $this->walk($doc)));
    }

    public function test_style_inherits_through_the_walk(): void
    {
        $doc = Document::make(Page::a4()->body(Row::make(Text::make('x'))->color(Color::hex('#ff0000'))));
        $visitor = $this->recorder();
        (new TreeWalker(new StyleResolver(Theme::default())))->walk($doc, $visitor);
        $text = array_values(array_filter($visitor->nodes, fn ($n) => $n instanceof Text))[0];
        self::assertEquals(Color::hex('#ff0000'), $visitor->styles[spl_object_id($text)]->color);
    }

    public function test_table_cells_get_table_defaults(): void
    {
        $theme = Theme::default();
        $doc = Document::make(Page::a4()->body(Table::make()->head('H')->row('d')));
        $visitor = $this->recorder();
        (new TreeWalker(new StyleResolver($theme)))->walk($doc, $visitor);
        $cells = array_values(array_filter($visitor->nodes, fn ($n) => $n instanceof TableCell));
        self::assertCount(2, $cells);
        [$th, $td] = $cells;
        self::assertSame(700, $visitor->styles[spl_object_id($th)]->fontWeight);
        self::assertEquals($theme->cellPadding, $visitor->styles[spl_object_id($th)]->padding);
        self::assertSame(400, $visitor->styles[spl_object_id($td)]->fontWeight);
        self::assertEquals($theme->cellPadding, $visitor->styles[spl_object_id($td)]->padding);
    }

    /** @return list<string> */
    private function walk(Document $doc): array
    {
        $visitor = $this->recorder();
        (new TreeWalker(new StyleResolver(Theme::default())))->walk($doc, $visitor);
        return $visitor->log;
    }

    private static function messageOf(\Closure $fn): string
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        return '';
    }

    private function recorder(?string $skip = null): RecordingVisitor
    {
        return new RecordingVisitor($skip);
    }
}

final class RecordingVisitor implements TreeVisitor
{
    /** @var list<string> */
    public array $log = [];
    /** @var list<Node> */
    public array $nodes = [];
    /** @var array<int, ComputedStyle> */
    public array $styles = [];

    public function __construct(private readonly ?string $skip)
    {
    }

    public function enterPage(Page $page, int $index): void
    {
        $this->log[] = 'page:' . $index;
    }

    public function leavePage(Page $page, int $index): void
    {
        $this->log[] = 'leavePage:' . $index;
    }

    public function enterRegion(Region $region, ComputedStyle $root): void
    {
        $this->log[] = 'region:' . $region->value;
    }

    public function leaveRegion(Region $region): void
    {
        $this->log[] = 'leaveRegion:' . $region->value;
    }

    public function enter(Node $node, ComputedStyle $style, string $path): bool
    {
        $this->log[] = 'enter:' . $path;
        $this->nodes[] = $node;
        $this->styles[spl_object_id($node)] = $style;
        return $node->debugName() !== $this->skip;
    }

    public function leave(Node $node, ComputedStyle $style, string $path): void
    {
        $this->log[] = 'leave:' . $path;
    }
}
