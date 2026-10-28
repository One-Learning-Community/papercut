<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests\Walk;

use Papercut\{Column, PageBreak, Spacer, Text};
use Papercut\Docx\Tests\Support\Styles;
use Papercut\Value\Edges;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class FlowTest extends TestCase
{
    use Styles;

    private const string P = '/w:document/w:body/w:p';

    public function test_column_gap_becomes_space_after(): void
    {
        $d = self::export(Column::make(Text::make('a'), Text::make('b'))->gap(pt(8)));
        self::assertSame('160', $d->value(self::P . '[1]/w:pPr/w:spacing/@w:after'));
        self::assertNull($d->value(self::P . '[2]/w:pPr/w:spacing/@w:after'));
    }

    public function test_child_margin_top_becomes_space_before(): void
    {
        $d = self::export(Column::make(Text::make('a')->margin(Edges::of(pt(6), pt(0), pt(0), pt(0)))));
        self::assertSame('120', $d->value(self::P . '[1]/w:pPr/w:spacing/@w:before'));
    }

    public function test_pagination_flags(): void
    {
        $d = self::export(Column::make(
            Text::make('next')->keepWithNext(),
            Column::make(Text::make('t1'), Text::make('t2'))->keepTogether(),
            Text::make('before')->breakBefore(),
            Text::make('after')->breakAfter(),
            Text::make('end'),
        ));
        $p = static fn (string $text): string => self::P . "[.//w:t='$text']";
        self::assertSame(1, $d->count($p('next') . '/w:pPr/w:keepNext'));
        self::assertSame(1, $d->count($p('t1') . '/w:pPr/w:keepLines'));
        self::assertSame(1, $d->count($p('t2') . '/w:pPr/w:keepLines'));
        self::assertSame(1, $d->count($p('t1') . '/w:pPr/w:keepNext'));
        self::assertSame(0, $d->count($p('t2') . '/w:pPr/w:keepNext'));
        self::assertSame(1, $d->count($p('before') . '/w:pPr/w:pageBreakBefore'));
        self::assertSame(1, $d->count($p('after') . "/following-sibling::w:p[1]//w:br[@w:type='page']"));
    }

    public function test_page_break_node(): void
    {
        $d = self::export(Column::make(Text::make('a'), PageBreak::make(), Text::make('b')));
        self::assertSame(1, $d->count("//w:br[@w:type='page']"));
    }

    public function test_fixed_spacer_is_a_hairline_paragraph(): void
    {
        $d = self::export(Column::make(Text::make('a'), Spacer::size(pt(12)), Text::make('b')));
        $hair = self::P . '[2]';
        self::assertSame('238', $d->value("$hair/w:pPr/w:spacing/@w:after"));
        self::assertSame('2', $d->value("$hair/w:pPr/w:spacing/@w:line"));
        self::assertSame('exact', $d->value("$hair/w:pPr/w:spacing/@w:lineRule"));
        self::assertSame(1, $d->count("$hair/w:r"));
        self::assertSame('2', $d->value("$hair/w:r/w:rPr/w:sz/@w:val"));
    }

    public function test_flexible_spacer_in_a_column_is_approximated(): void
    {
        $d = self::export(Column::make(Text::make('a'), Spacer::flex(), Text::make('b')));
        self::assertSame(2, $d->count(self::P . '[.//w:t]'));
        self::assertSame(['Approximated flexible spacer @ page > body > column[0] > spacer[1]'], self::exportedReports());
    }
}
