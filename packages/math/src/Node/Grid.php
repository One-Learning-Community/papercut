<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

/**
 * An equation grid (mtable): rows of cells in aligned columns, laid out the way TeX sets a matrix.
 * Columns are one em apart, rows sit at least 1.2 em apart, and the whole grid is centred on the math axis.
 */
final class Grid extends Node
{
    public const COLUMN_ALIGN = ['left', 'center', 'right'];
    public const ROW_ALIGN = ['baseline', 'axis', 'center', 'top', 'bottom'];

    /** @var list<list<Node>> every row padded to the widest */
    public readonly array $rows;
    /** @var list<string> one entry per column */
    public readonly array $columnAlign;
    public readonly int $columns;

    /**
     * @param array<array<Node>> $rows read in order, whatever their keys
     * @param array<mixed> $columnAlign strings, read in order, whatever their keys; left, center or right per column; the last repeats, empty means center
     * @param string $rowAlign how cells of one row line up: baseline, axis, center, top or bottom
     * @param bool $display lay the cells out in display style rather than text style
     */
    public function __construct(
        array $rows,
        array $columnAlign = [],
        public readonly string $rowAlign = 'baseline',
        public readonly bool $display = false,
    ) {
        foreach ($columnAlign as $a) {
            if (! in_array($a, self::COLUMN_ALIGN, true)) {
                throw new \InvalidArgumentException(sprintf('Unknown column alignment "%s"; use one of: %s', is_string($a) ? $a : get_debug_type($a), implode(', ', self::COLUMN_ALIGN)));
            }
        }
        if (! in_array($rowAlign, self::ROW_ALIGN, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown row alignment "%s"; use one of: %s', $rowAlign, implode(', ', self::ROW_ALIGN)));
        }
        $columnAlign = array_values($columnAlign);
        $rows = array_map(array_values(...), array_values($rows));
        $columns = 0;
        foreach ($rows as $row) {
            $columns = max($columns, count($row));
        }
        $padded = [];
        foreach ($rows as $row) {
            while (count($row) < $columns) {
                $row[] = new Row([]);
            }
            $padded[] = $row;
        }
        $align = [];
        $last = $columnAlign === [] ? 'center' : $columnAlign[array_key_last($columnAlign)];
        for ($j = 0; $j < $columns; $j++) {
            $align[] = $columnAlign[$j] ?? $last;
        }
        $this->rows = $padded;
        $this->columnAlign = $align;
        $this->columns = $columns;
    }

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function children(): array
    {
        $out = [];
        foreach ($this->rows as $row) {
            foreach ($row as $cell) {
                $out[] = $cell;
            }
        }

        return $out;
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        if ($this->columns === 0) {
            return new Box();
        }
        $cc = $c->withDisplay($this->display);
        $em = $c->size();
        $axis = $c->k('axisHeight');

        $boxes = [];
        $colW = array_fill(0, $this->columns, 0.0);
        $rowA = [];
        $rowD = [];
        foreach ($this->rows as $i => $row) {
            $rowA[$i] = 0.0;
            $rowD[$i] = 0.0;
            foreach ($row as $j => $cell) {
                $b = $cell->layout($cc);
                $boxes[$i][$j] = $b;
                $colW[$j] = max($colW[$j], $b->width);
                $rowA[$i] = max($rowA[$i], $b->ascent);
                $rowD[$i] = max($rowD[$i], $b->descent);
            }
        }

        $colX = [];
        $x = 0.0;
        foreach ($colW as $j => $w) {
            $colX[$j] = $x;
            $x += $w + $em;
        }
        $width = $x - $em;

        $base = [];
        $y = 0.0;
        foreach ($this->rows as $i => $_) {
            $y = $i === 0 ? 0.0 : $y + max(1.2 * $em, $rowD[$i - 1] + $rowA[$i] + 0.1 * $em);
            $base[$i] = $y;
        }
        $last = count($this->rows) - 1;
        $top = -$rowA[0];
        $bottom = $base[$last] + $rowD[$last];
        $shift = -$axis - ($top + $bottom) / 2;

        $out = new Box();
        foreach ($this->rows as $i => $row) {
            foreach ($row as $j => $_) {
                $b = $boxes[$i][$j];
                $dx = $colX[$j] + match ($this->columnAlign[$j]) {
                    'left' => 0.0,
                    'right' => $colW[$j] - $b->width,
                    default => ($colW[$j] - $b->width) / 2,
                };
                $by = match ($this->rowAlign) {
                    'top' => $base[$i] - $rowA[$i] + $b->ascent,
                    'bottom' => $base[$i] + $rowD[$i] - $b->descent,
                    'center' => $base[$i] + (($rowD[$i] - $rowA[$i]) - ($b->descent - $b->ascent)) / 2,
                    'axis' => $base[$i] - $axis + ($b->ascent - $b->descent) / 2,
                    default => $base[$i],
                };
                $out->place($b, $dx, $by + $shift);
            }
        }
        $h = $bottom - $top;
        $out->width = $width;
        $out->ascent = $h / 2 + $axis;
        $out->descent = $h / 2 - $axis;

        return $out;
    }
}
