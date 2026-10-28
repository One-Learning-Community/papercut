<?php

declare(strict_types=1);

namespace Papercut\Docx\Map;

use Dskripchenko\PhpDocx\Element\{BlockElement, Table, TableCell as DocxCell, TableRow};
use Dskripchenko\PhpDocx\Style\{TableStyle, VerticalAlign};
use Papercut\Docx\Walk\Frame;
use Papercut\Export\Fidelity;
use Papercut\Style\{ComputedStyle, Justify};
use Papercut\TableCell;

/** Papercut tables as Word tables at WidthResolver's column widths. */
final class TableMapper
{
    public function __construct(
        private readonly Fidelity $fidelity,
        private readonly BoxMapper $boxes,
    ) {
    }

    public function table(ComputedStyle $s, Frame $frame): Table
    {
        $style = BoxMapper::tableStyle($frame->width);
        $borders = BoxMapper::borders($s);
        if ($borders !== null) {
            $style = new TableStyle(...[...get_object_vars($style), 'borders' => $borders]);
        }

        return new Table($frame->rows, $style, gridColumnsTwips: array_map(static fn (float $w): int => max(1, Units::tw($w)), $frame->columns));
    }

    public function row(Frame $frame): TableRow
    {
        return new TableRow($frame->cells, isHeader: $frame->header, cantSplit: true);
    }

    /** @param list<BlockElement> $blocks */
    public function cell(TableCell $n, ComputedStyle $s, Frame $cell, Frame $row, array $blocks, string $path): DocxCell
    {
        $row->column += $n->span();
        $v = match ($s->justify) {
            Justify::Center => VerticalAlign::Center,
            Justify::End => VerticalAlign::Bottom,
            Justify::Start => VerticalAlign::Top,
            default => null,
        };
        if ($v === null) {
            $this->fidelity->approximated('cell justify', $path);
        }

        return new DocxCell(BoxMapper::cellContent($blocks), $this->boxes->cellStyle($s, Units::tw($cell->width), $n->span(), $v ?? VerticalAlign::Top, $row->style));
    }
}
