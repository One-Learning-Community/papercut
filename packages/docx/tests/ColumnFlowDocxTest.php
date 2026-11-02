<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests;

use Papercut\{ColumnBreak, ColumnFlow, Column, Document, Page, Table, TableCell, Text, View};
use Papercut\Docx\Tests\Support\{DocxInspector, Styles};
use Papercut\Exception\InvalidStructure;
use Papercut\Value\{ColumnSpec, Color, ColumnRule};
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class ColumnFlowDocxTest extends TestCase
{
    use Styles;

    private const string ANY_SECT = '//w:sectPr';

    public function test_on_page_flow_is_a_continuous_section_with_cols(): void
    {
        $d = self::export(Page::a4()->body(ColumnFlow::make(Text::make('one'), Text::make('two'))->columns(2)));
        self::assertSame(2, $d->count(self::ANY_SECT));
        self::assertSame(1, $d->count('//w:cols'));
        // The flow's section is the first one and starts the page; the levelling section after it is the body's own.
        self::assertSame(1, $d->count('/w:document/w:body/w:p[2]/w:pPr/w:sectPr/w:cols'));
        self::assertSame(0, $d->count('/w:document/w:body/w:p/w:pPr/w:sectPr/w:type'));
        self::assertSame(1, $d->count('/w:document/w:body/w:sectPr/w:type[@w:val="continuous"]'));
        self::assertSame('2', $d->value('//w:sectPr/w:cols/@w:num'));
        self::assertSame('720', $d->value('//w:sectPr/w:cols/@w:space'));
        self::assertSame(['one', 'two'], $d->values('//w:t[normalize-space()]'));
        self::assertSame([], self::exportedReports());
    }

    public function test_title_then_flow_then_note_gives_three_sections_on_one_page(): void
    {
        $d = self::export(Page::a4()->body(
            Text::make('title'),
            ColumnFlow::make(Text::make('a'), Text::make('b'))->columns(2)->columnRule(pt(0.5), Color::hex('#000000')),
            Text::make('note'),
        ));
        self::assertSame(3, $d->count(self::ANY_SECT));
        self::assertSame(0, $d->count("//w:br[@w:type='page']"));
        // Document order: title section, flow section, note section (the body's own sectPr).
        $types = $d->values('//w:sectPr/w:type/@w:val');
        self::assertSame(['continuous', 'continuous'], $types);
        self::assertSame(1, $d->count('//w:cols'));
        self::assertSame(1, $d->count('/w:document/w:body/w:p[3]/w:pPr/w:sectPr/w:cols[@w:sep="1"]'));
        self::assertSame(0, $d->count('/w:document/w:body/w:p[1]/w:pPr/w:sectPr/w:type'));
        self::assertSame(['title', 'a', 'b', 'note'], $d->values('//w:t[normalize-space()]'));
    }

    public function test_a_leading_flow_takes_the_page_start(): void
    {
        $d = self::export(Document::make(
            Page::a4()->body(Text::make('first')),
            Page::a5()->body(ColumnFlow::make(Text::make('a'))->columns(2), Text::make('after')),
        ));
        // Section 2 (the flow) starts the new page; no empty section sits between.
        self::assertSame(3, $d->count(self::ANY_SECT));
        self::assertSame(['continuous'], $d->values('//w:sectPr/w:type/@w:val'));
        self::assertSame(1, $d->count('/w:document/w:body/w:p[2]/w:pPr/w:sectPr/w:cols'));
    }

    public function test_flow_before_a_new_template_gets_a_levelling_section(): void
    {
        $d = self::export(Document::make(
            Page::a4()->body(ColumnFlow::make(Text::make('a'), Text::make('b'))->columns(2)),
            Page::a5()->body(Text::make('next')),
        ));
        // flow (next page), hairline levelling (continuous), A5 section (next page).
        self::assertSame(3, $d->count(self::ANY_SECT));
        self::assertSame(['continuous'], $d->values('//w:sectPr/w:type/@w:val'));
        self::assertSame(1, $d->count('//w:cols'));
        self::assertSame(1, $d->count('/w:document/w:body/w:p[2]/w:pPr/w:sectPr/w:cols'));
        self::assertSame(1, $d->count('/w:document/w:body/w:p[3]/w:pPr/w:sectPr/w:type[@w:val="continuous"]'));
        self::assertSame(0, $d->count('/w:document/w:body/w:p[3]//w:t[normalize-space()]'));
        self::assertSame(0, $d->count('/w:document/w:body/w:sectPr/w:type'));
    }

    public function test_last_flow_in_the_document_gets_a_levelling_section(): void
    {
        $d = self::export(Page::a4()->body(Text::make('t'), ColumnFlow::make(Text::make('a'))->columns(2)));
        self::assertSame(3, $d->count(self::ANY_SECT));
        self::assertSame(0, $d->count('/w:document/w:body/w:sectPr/w:cols'));
    }

    public function test_flow_in_a_card_is_one_column_with_a_note(): void
    {
        $d = self::export(Page::a4()->body(View::make(ColumnFlow::make(Text::make('a'), Text::make('b'))->columns(2))->padding(pt(6))));
        self::assertSame(1, $d->count(self::ANY_SECT));
        self::assertSame(0, $d->count('//w:cols'));
        self::assertSame(['Approximated columns inside a box @ page > body > column[0] > column-flow[0]'], self::exportedReports());
        self::assertSame(['a', 'b'], $d->values('//w:t[normalize-space()]'));
    }

    public function test_markup_flow_in_a_cell_is_one_column_with_a_note(): void
    {
        $flow = ColumnFlow::make(Text::make('a'))->withColumnSpec((ColumnSpec::equal(2))->withFromMarkup());
        $d = self::export(Table::make()->columns('1fr')->row(TableCell::make($flow)));
        self::assertSame(0, $d->count('//w:cols'));
        self::assertSame(1, $d->count(self::ANY_SECT));
        $reports = self::exportedReports();
        self::assertCount(1, $reports);
        self::assertStringContainsString('columns in a table cell', $reports[0]);
    }

    public function test_builder_flow_in_a_cell_throws(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('inside a table cell');
        self::export(Table::make()->columns('1fr')->row(TableCell::make(ColumnFlow::make(Text::make('a'))->columns(2))));
    }

    public function test_column_break_becomes_w_br_column(): void
    {
        $d = self::export(Page::a4()->body(ColumnFlow::make(Text::make('a'), ColumnBreak::make(), Text::make('b'))->columns(2)));
        self::assertSame(1, $d->count("//w:p/w:r/w:br[@w:type='column']"));
        self::assertSame(['a', 'b'], $d->values('//w:t[normalize-space()]'));
        self::assertSame([], self::exportedReports());
    }

    public function test_column_break_in_a_boxed_flow_is_dropped_with_a_note(): void
    {
        $d = self::export(Page::a4()->body(View::make(ColumnFlow::make(Text::make('a'), ColumnBreak::make(), Text::make('b'))->columns(2))->padding(pt(6))));
        self::assertSame(0, $d->count("//w:br[@w:type='column']"));
        self::assertCount(2, self::exportedReports());
        self::assertStringContainsString('column break', self::exportedReports()[1]);
    }

    public function test_stray_column_break_throws(): void
    {
        $this->expectException(InvalidStructure::class);
        self::export(Page::a4()->body(Column::make(ColumnBreak::make())));
    }

    public function test_shares_write_per_column_widths(): void
    {
        $spec = new ColumnSpec(2, [1.0, 2.0], [36.0]);
        $d = self::export(Page::a4()->margin(pt(72))->body(ColumnFlow::make(Text::make('a'))->withColumnSpec($spec)));
        // A4 is 595.3pt wide; 72pt margins leave 451.3pt, less the 36pt gap, shared 1:2.
        self::assertSame('0', $d->value('//w:cols/@w:equalWidth'));
        self::assertSame(2, $d->count('//w:cols/w:col'));
        self::assertSame('720', $d->value('//w:cols/w:col[1]/@w:space'));
        self::assertNull($d->value('//w:cols/w:col[2]/@w:space'));
    }

    public function test_a_page_break_after_the_flow_does_not_leave_it_unlevelled(): void
    {
        $d = self::export(Page::a4()->body(ColumnFlow::make(Text::make('a'))->columns(2)->breakAfter(), Text::make('next')));
        self::assertSame(1, $d->count('//w:cols'));
    }
}
