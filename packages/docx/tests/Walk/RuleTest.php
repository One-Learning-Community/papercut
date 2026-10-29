<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests\Walk;

use Papercut\Docx\Tests\Support\Styles;
use Papercut\Markup\Markup;
use Papercut\{Row, Style\Style, View};

use function Papercut\pt;
use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{
    use Styles;

    public function test_hr_exports_a_bordered_paragraph_not_a_table(): void
    {
        $d = self::export(Markup::html('<p>a</p><hr><p>b</p>'));
        self::assertSame(0, $d->count('/w:document/w:body/w:tbl'));
        self::assertSame(1, $d->count('/w:document/w:body/w:p/w:pPr/w:pBdr/w:bottom'));
    }

    public function test_a_user_built_bordered_view_keeps_its_table(): void
    {
        $line = View::make()->apply(Style::make(borderWidth: \Papercut\Value\Edges::of(pt(0), pt(0), pt(1), pt(0)), width: pt(100), minHeight: pt(30)));
        $d = self::export($line);
        self::assertSame(1, $d->count('/w:document/w:body/w:tbl'));
        self::assertSame(0, $d->count('/w:document/w:body/w:p/w:pPr/w:pBdr'));
    }

    public function test_a_signature_line_in_a_row_stays_a_cell_border(): void
    {
        $line = View::make()->apply(Style::make(borderWidth: \Papercut\Value\Edges::of(pt(0), pt(0), pt(1), pt(0)), grow: 1));
        $d = self::export(Row::make('Signed:', $line));
        self::assertSame(0, $d->count('//w:pBdr'));
        // identical to the export before `<hr>` support: the line stays a cell of the row, never a paragraph border
        self::assertSame(2, $d->count('/w:document/w:body/w:tbl/w:tr/w:tc'));
    }
}
