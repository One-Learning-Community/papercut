<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\TableCell;
use Papercut\TableRow;

final class Thead extends PdfComponent
{
    protected function build(array $data): object
    {
        $children = $this->rawChildren($data, [TableCell::class, TableRow::class]);
        $cells = array_filter($children, fn ($c) => $c instanceof TableCell);
        $rows = array_filter($children, fn ($c) => $c instanceof TableRow);
        if ($children === []) {
            throw new InvalidStructure('<x-pdf::thead> must not be empty');
        }
        if ( count($cells) + count($rows) !== count($children) || ($cells !== [] && $rows !== [])) {
            throw new InvalidStructure('<x-pdf::thead> can only contain <x-pdf::th>/<x-pdf::td> cells or <x-pdf::tr> rows, not both');
        }
        if ($cells !== []) {
            /** @var list<TableCell> $list */
            $list = array_values($cells);

            return new HeaderRows([TableRow::make(...$list)]);
        }
        /** @var list<TableRow> $list */
        $list = array_values($rows);

        return new HeaderRows($list);
    }
}
