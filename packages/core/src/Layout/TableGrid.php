<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\Table;

/**
 * Resolves a table's cells onto a grid, the way a browser does: each cell takes
 * the first column not covered by a rowspan from an earlier row. Header rows and
 * body rows are separate sections; a rowspan never crosses from one into the other.
 */
final readonly class TableGrid
{
    /**
     * @param list<list<GridCell>> $placements one list per row, header rows first
     * @param list<int> $clamped all-row indexes of rows holding a clamped rowspan or a colspan stopped by a rowspan from above
     */
    private function __construct(
        public int $columns,
        public array $placements,
        public array $clamped,
    ) {
    }

    public static function of(Table $table): self
    {
        /** @var \WeakMap<Table, self> $cache */
        static $cache = new \WeakMap();
        return $cache[$table] ??= self::resolve($table);
    }

    /**
     * The last row of the rowspan group that starts at $row: $row itself when
     * nothing spans out of it.
     */
    public function groupEnd(int $row): int
    {
        $end = $row;
        for ($r = $row; $r <= $end; $r++) {
            foreach ($this->placements[$r] ?? [] as $p) {
                $end = max($end, $p->row + $p->rowspan - 1);
            }
        }
        return $end;
    }

    private static function resolve(Table $table): self
    {
        $header = $table->headerRows();
        $sections = [$header, $table->bodyRows()];
        $placements = [];
        $clamped = [];
        $columns = 0;
        $offset = 0;
        foreach ($sections as $rows) {
            $count = count($rows);
            $covered = [];
            foreach ($rows as $r => $row) {
                $at = $offset + $r;
                $placed = [];
                $col = 0;
                foreach ($row->cells() as $i => $cell) {
                    while (isset($covered[$r][$col])) {
                        $col++;
                    }
                    $colspan = $cell->span();
                    for ($dc = 1; $dc < $colspan; $dc++) {
                        if (isset($covered[$r][$col + $dc])) {
                            $colspan = $dc;
                            $clamped[$at] = $at;
                            break;
                        }
                    }
                    $rowspan = min($cell->rowsSpanned(), $count - $r);
                    if ($rowspan < $cell->rowsSpanned()) {
                        $clamped[$at] = $at;
                    }
                    for ($dr = 0; $dr < $rowspan; $dr++) {
                        for ($dc = 0; $dc < $colspan; $dc++) {
                            $covered[$r + $dr][$col + $dc] = true;
                        }
                    }
                    $placed[] = new GridCell($cell, $i, $at, $col, $colspan, $rowspan);
                    $columns = max($columns, $col + $colspan);
                    $col += $colspan;
                }
                $placements[] = $placed;
            }
            $offset += $count;
        }
        return new self($columns, $placements, array_values($clamped));
    }
}
