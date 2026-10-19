<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Node, Table, TableCell, TableRow, View};
use Papercut\Exception\UnknownAttribute;
use Papercut\Style\{ComputedStyle, Style, Theme};
use Papercut\Value\{Auto, Fr, Length, Percent};

use function Papercut\pt;

/**
 * Tables: a grid of columns sized from the track list, rows stacked top to
 * bottom, every cell stretched to its row's height. Cells (TableCell) are
 * block containers laid out like a Column; each row is a box of the table's
 * width painted with the row's own background, border and padding.
 *
 * Fragmentation is by row. Header rows open the first fragment and, when the
 * table repeats its header, every continuation. A body row too tall for a
 * fresh page splits its cells in parallel: the token then holds the row index
 * and one entry per cell (null = finished); on resume finished cells draw
 * nothing.
 *
 * Column measures and resolved grids are cached per Table instance (keyed by
 * the inherited style and width), so continuations and retries neither
 * re-measure cells nor repeat column warnings.
 */
final class TableLayouter implements Layouter
{
    private const float EPSILON = 1e-9;

    /** @var \WeakMap<Table, array<string, list<array{min: float, max: float}>>> */
    private \WeakMap $content;
    /** @var \WeakMap<Table, array<string, array{width: float, widths: list<float>, offsets: list<float>}>> */
    private \WeakMap $grids;
    /** @var \WeakMap<Table, true> tables already warned about a dropped repeated header */
    private \WeakMap $headerWarned;

    public function __construct(private readonly BlockLayouter $block)
    {
        $this->content = new \WeakMap();
        $this->grids = new \WeakMap();
        $this->headerWarned = new \WeakMap();
    }

    public function layout(Node $node, ComputedStyle $style, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult
    {
        if ($node instanceof TableCell) {
            return $this->block->layout(self::column($node), $style, $c, $token, $engine);
        }
        $table = self::table($node);
        $first = $token === null;
        $borderWidth = BoxGeometry::borderBoxWidth($style, $c->width);
        $contentWidth = BoxGeometry::contentWidth($style, $c->width);
        $grid = $this->grid($table, $style, $contentWidth, $engine);

        $available = $c->height === null
            ? null
            : $c->height - ($first ? BoxGeometry::topEdge($style) : 0.0) - BoxGeometry::bottomEdge($style);

        $body = $table->bodyRows();
        $count = count($body);
        $headers = $table->headerRows();
        $offset = count($headers);
        $start = min(max(0, $token->index ?? 0), $count);

        /** @var list<array{height: float, fragment: Fragment}> $headerRows */
        $headerRows = [];
        if ($first || $table->repeatsHeader()) {
            foreach ($headers as $r => $row) {
                $headerRows[] = $this->measureRow($row, $r, $grid, $style, $c->fresh && $headerRows === [], $engine);
            }
        }
        $headerHeight = array_sum(array_column($headerRows, 'height'));

        if (!$first && $headerRows !== [] && $c->fresh && $available !== null && $start < $count
            && $this->crowdsOutRow($body[$start], $offset + $start, $token->parallel !== [], $headerHeight, $available, $grid, $style, $engine)) {
            $headerRows = [];
            $headerHeight = 0.0;
            if (!isset($this->headerWarned[$table])) {
                $this->headerWarned[$table] = true;
                $engine->context()->warn('Table header leaves no room for a row; not repeated on that page');
            }
        }
        if ($headerRows !== [] && !$c->fresh && $available !== null && $headerHeight > $available + self::EPSILON) {
            return LayoutResult::doesNotFit();
        }

        /** @var list<array{y: float, fragment: Fragment}> $placed */
        $placed = [];
        $cursor = 0.0;
        foreach ($headerRows as $measured) {
            $placed[] = ['y' => $cursor, 'fragment' => $measured['fragment']];
            $cursor += $measured['height'];
        }

        $breakAt = null;
        $rowsOnPage = 0;
        for ($k = $start; $k < $count; $k++) {
            $resuming = $k === $start && $token !== null && $token->parallel !== [];
            if (!$resuming) {
                $measured = $this->measureRow($body[$k], $offset + $k, $grid, $style, $c->fresh && $placed === [], $engine);
                if ($available === null || $cursor + $measured['height'] <= $available + self::EPSILON) {
                    $placed[] = ['y' => $cursor, 'fragment' => $measured['fragment']];
                    $cursor += $measured['height'];
                    $rowsOnPage++;
                    continue;
                }
                if ($rowsOnPage > 0) {
                    $breakAt = new BreakToken(index: $k);
                    break;
                }
                if (!$c->fresh) {
                    return LayoutResult::doesNotFit();
                }
            }

            $room = $available === null ? null : max(0.0, $available - $cursor);
            $split = $this->splitRow($body[$k], $offset + $k, $grid, $style, $room, $c->fresh, $resuming ? $token->parallel : null, $engine);
            if ($split === null) {
                return LayoutResult::doesNotFit();
            }
            $placed[] = ['y' => $cursor, 'fragment' => $split['fragment']];
            $cursor += $split['height'];
            $rowsOnPage++;
            if (array_filter($split['tokens'], static fn (?BreakToken $t): bool => $t !== null) !== []) {
                $breakAt = new BreakToken(index: $k, parallel: $split['tokens']);
                break;
            }
        }

        if ($breakAt !== null && $style->keepTogether && !$c->fresh) {
            return LayoutResult::doesNotFit();
        }

        $last = $breakAt === null;
        $f = new Fragment($borderWidth, BoxGeometry::outerHeight($style, $cursor, $first, $last));
        BoxGeometry::decorate($f, $style, $first, $last);
        $ox = $style->borderWidth->left->points + $style->padding->left->points;
        $oy = $first ? BoxGeometry::topEdge($style) : 0.0;
        foreach ($placed as $p) {
            $f->addChild($ox, $oy + $p['y'], $p['fragment']);
        }

        return $breakAt === null ? LayoutResult::complete($f) : LayoutResult::partial($f, $breakAt);
    }

    public function intrinsic(Node $node, ComputedStyle $style, LayoutEngine $engine): array
    {
        if ($node instanceof TableCell) {
            return $this->block->intrinsic(self::column($node), $style, $engine);
        }
        if ($style->width instanceof Length) {
            return ['min' => $style->width->points, 'max' => $style->width->points];
        }
        $table = self::table($node);
        $columns = $this->columnContent($table, $style, $engine);
        $tracks = self::tracks($table, count($columns));
        $min = 0.0;
        $max = 0.0;
        foreach ($columns as $k => $size) {
            $fixed = $tracks[$k] instanceof Length ? $tracks[$k]->points : null;
            $min += $fixed ?? $size['min'];
            $max += $fixed ?? $size['max'];
        }
        $edges = $style->padding->horizontal() + $style->borderWidth->horizontal();
        return ['min' => $min + $edges, 'max' => $max + $edges];
    }

    /**
     * True when a repeated header of $headerHeight leaves no room for the next
     * body row: nothing at all is left, or the row would fit the page without it.
     *
     * @param array{width: float, widths: list<float>, offsets: list<float>} $grid
     */
    private function crowdsOutRow(TableRow $row, int $r, bool $resuming, float $headerHeight, float $available, array $grid, ComputedStyle $style, LayoutEngine $engine): bool
    {
        $room = $available - $headerHeight;
        if ($room <= self::EPSILON) {
            return true;
        }
        if ($resuming) {
            return false;
        }
        $height = $this->measureRow($row, $r, $grid, $style, false, $engine)['height'];
        return $height > $room + self::EPSILON && $height <= $available + self::EPSILON;
    }

    /**
     * Column widths and x offsets for content width $width, computed once per
     * table, style and width; column warnings are emitted only then.
     *
     * @return array{width: float, widths: list<float>, offsets: list<float>}
     */
    private function grid(Table $table, ComputedStyle $style, float $width, LayoutEngine $engine): array
    {
        $key = self::styleKey($style) . '|' . $width;
        $cached = $this->grids[$table] ?? [];
        if (isset($cached[$key])) {
            return $cached[$key];
        }

        $content = $this->columnContent($table, $style, $engine);
        $n = count($content);
        $given = $table->trackList()?->count();
        if ($given !== null && $given !== $n) {
            $engine->context()->warn(sprintf('Table has %d columns but %d tracks', $n, $given));
        }
        $tracks = self::tracks($table, $n);

        $widths = array_fill(0, $n, 0.0);
        $auto = [];
        $fr = [];
        foreach ($tracks as $k => $track) {
            if ($track instanceof Length) {
                $widths[$k] = $track->points;
            } elseif ($track instanceof Percent) {
                $widths[$k] = $track->resolve($width);
            } elseif ($track instanceof Auto) {
                $auto[] = $k;
            } else {
                $fr[$k] = $track->value;
            }
        }

        // Auto tracks: max-content if it fits, min-content if even that overflows, else in between ∝ (max - min).
        $remaining = $width - array_sum($widths);
        $sumMin = 0.0;
        $sumMax = 0.0;
        foreach ($auto as $k) {
            $sumMin += $content[$k]['min'];
            $sumMax += $content[$k]['max'];
        }
        foreach ($auto as $k) {
            $min = $content[$k]['min'];
            $max = $content[$k]['max'];
            $widths[$k] = match (true) {
                $sumMax <= $remaining + self::EPSILON => $max,
                $sumMin >= $remaining - self::EPSILON => $min,
                default => $min + ($remaining - $sumMin) * ($max - $min) / ($sumMax - $sumMin),
            };
        }

        // Fr tracks share what is left, never below their column's min-content.
        $remaining = $width - array_sum($widths);
        $totalFr = array_sum($fr);
        foreach ($fr as $k => $value) {
            $share = $remaining > self::EPSILON ? $remaining * $value / $totalFr : 0.0;
            $widths[$k] = max($content[$k]['min'], $share);
        }

        $total = 0.0;
        foreach ($widths as $w) {
            $total += $w;
        }
        if ($total > $width + self::EPSILON) {
            $engine->context()->warn(sprintf('Table columns need %.2fpt but only %.2fpt is available', $total, $width));
        }

        $offsets = [];
        $x = 0.0;
        foreach ($widths as $w) {
            $offsets[] = $x;
            $x += $w;
        }
        $grid = ['width' => max($width, $total), 'widths' => array_values($widths), 'offsets' => $offsets];
        $cached[$key] = $grid;
        $this->grids[$table] = $cached;
        return $grid;
    }

    /**
     * Per-column min/max-content: the max over its span-1 cells, then each
     * spanning cell's shortfall spread evenly over the auto columns it covers.
     * Cached per table and style.
     *
     * @return list<array{min: float, max: float}>
     */
    private function columnContent(Table $table, ComputedStyle $style, LayoutEngine $engine): array
    {
        $key = self::styleKey($style);
        $cached = $this->content[$table] ?? [];
        if (isset($cached[$key])) {
            return $cached[$key];
        }

        $rows = self::allRows($table);
        $n = 0;
        foreach ($rows as $row) {
            $n = max($n, array_sum(array_map(static fn (TableCell $cell): int => $cell->span(), $row->cells())));
        }
        $tracks = self::tracks($table, $n);
        $columns = array_fill(0, $n, ['min' => 0.0, 'max' => 0.0]);
        /** @var list<array{col: int, span: int, min: float, max: float}> $spanning */
        $spanning = [];
        foreach ($rows as $r => $row) {
            $this->inRow($row, $r, $style, $engine, function (ComputedStyle $rowStyle) use ($row, $engine, &$columns, &$spanning): void {
                $col = 0;
                foreach ($row->cells() as $j => $cell) {
                    $size = $engine->intrinsic(self::prepare($cell, $engine->context()->theme), $rowStyle, $j);
                    if ($cell->span() === 1) {
                        $columns[$col] = [
                            'min' => max($columns[$col]['min'], $size['min']),
                            'max' => max($columns[$col]['max'], $size['max']),
                        ];
                    } else {
                        $spanning[] = ['col' => $col, 'span' => $cell->span(), 'min' => $size['min'], 'max' => $size['max']];
                    }
                    $col += $cell->span();
                }
            });
        }

        foreach ($spanning as $s) {
            $covered = range($s['col'], $s['col'] + $s['span'] - 1);
            $autos = array_values(array_filter($covered, static fn (int $k): bool => $tracks[$k] instanceof Auto));
            if ($autos === []) {
                continue;
            }
            $sumMin = 0.0;
            $sumMax = 0.0;
            foreach ($covered as $k) {
                $fixed = $tracks[$k] instanceof Length ? $tracks[$k]->points : null;
                $sumMin += $fixed ?? $columns[$k]['min'];
                $sumMax += $fixed ?? $columns[$k]['max'];
            }
            $addMin = max(0.0, $s['min'] - $sumMin) / count($autos);
            $addMax = max(0.0, $s['max'] - $sumMax) / count($autos);
            foreach ($autos as $k) {
                $columns[$k] = ['min' => $columns[$k]['min'] + $addMin, 'max' => $columns[$k]['max'] + $addMax];
            }
        }

        $cached[$key] = $columns;
        $this->content[$table] = $cached;
        return $columns;
    }

    /**
     * Lays a row out at unbounded height, stretching every cell to the row's
     * content height, inside a decorated row box.
     *
     * @param array{width: float, widths: list<float>, offsets: list<float>} $grid
     * @return array{height: float, fragment: Fragment}
     */
    private function measureRow(TableRow $row, int $r, array $grid, ComputedStyle $style, bool $fresh, LayoutEngine $engine): array
    {
        return $this->inRow($row, $r, $style, $engine, function (ComputedStyle $rowStyle) use ($row, $grid, $fresh, $engine): array {
            $slots = self::slots($row, $grid);
            $frags = [];
            $height = 0.0;
            foreach ($row->cells() as $j => $cell) {
                $w = $slots[$j]['width'];
                $frags[$j] = $this->layoutCell($cell, $j, $w, $rowStyle, new Constraints($w, null, $fresh), null, $engine)->fragment
                    ?? new Fragment($w, 0.0);
                $height = max($height, $frags[$j]->height);
            }
            $cells = [];
            foreach ($row->cells() as $j => $cell) {
                $f = $frags[$j];
                if ($f->height < $height - self::EPSILON) {
                    $w = $slots[$j]['width'];
                    $f = $this->layoutCell($cell, $j, $w, $rowStyle, new Constraints($w, null, $fresh, $height), null, $engine)->fragment ?? $f;
                }
                $cells[] = ['x' => $slots[$j]['x'], 'fragment' => $f];
            }
            $fragment = self::rowFragment($rowStyle, $grid['width'], $height, $cells, true, true);
            return ['height' => $fragment->height, 'fragment' => $fragment];
        });
    }

    /**
     * Parallel split of one row in $height. $tokens null = fresh split (all
     * cells start); otherwise only cells with a non-null token continue.
     * Returns null when a cell cannot place anything.
     *
     * @param array{width: float, widths: list<float>, offsets: list<float>} $grid
     * @param ?array<int, ?BreakToken> $tokens
     * @return ?array{height: float, tokens: array<int, ?BreakToken>, fragment: Fragment}
     */
    private function splitRow(TableRow $row, int $r, array $grid, ComputedStyle $style, ?float $height, bool $fresh, ?array $tokens, LayoutEngine $engine): ?array
    {
        return $this->inRow($row, $r, $style, $engine, function (ComputedStyle $rowStyle) use ($row, $grid, $height, $fresh, $tokens, $engine): ?array {
            $first = $tokens === null;
            $room = $height === null
                ? null
                : max(0.0, $height - ($first ? BoxGeometry::topEdge($rowStyle) : 0.0) - BoxGeometry::bottomEdge($rowStyle));
            $slots = self::slots($row, $grid);
            $next = [];
            $cells = [];
            $content = 0.0;
            foreach ($row->cells() as $j => $cell) {
                $next[$j] = null;
                $resume = $tokens[$j] ?? null;
                if ($tokens !== null && $resume === null) {
                    continue;
                }
                $w = $slots[$j]['width'];
                $res = $this->layoutCell($cell, $j, $w, $rowStyle, new Constraints($w, $room, $fresh), $resume, $engine);
                if ($res->status === Status::DoesNotFit || $res->fragment === null) {
                    return null;
                }
                $next[$j] = $res->status === Status::Partial ? $res->token : null;
                $cells[] = ['x' => $slots[$j]['x'], 'fragment' => $res->fragment];
                $content = max($content, $res->fragment->height);
            }
            $last = array_filter($next, static fn (?BreakToken $t): bool => $t !== null) === [];
            $fragment = self::rowFragment($rowStyle, $grid['width'], $content, $cells, $first, $last);
            return ['height' => $fragment->height, 'tokens' => $next, 'fragment' => $fragment];
        });
    }

    /**
     * A row box (one slice of it for split rows) with its cells placed inside
     * the row's border and padding.
     *
     * @param list<array{x: float, fragment: Fragment}> $cells
     */
    private static function rowFragment(ComputedStyle $rowStyle, float $width, float $contentHeight, array $cells, bool $first, bool $last): Fragment
    {
        $f = new Fragment($width, BoxGeometry::outerHeight($rowStyle, $contentHeight, $first, $last));
        BoxGeometry::decorate($f, $rowStyle, $first, $last);
        $ox = $rowStyle->borderWidth->left->points + $rowStyle->padding->left->points;
        $oy = $first ? BoxGeometry::topEdge($rowStyle) : 0.0;
        foreach ($cells as $cell) {
            $f->addChild($ox + $cell['x'], $oy, $cell['fragment']);
        }
        return $f;
    }

    /** Lays a cell out at exactly $width, with the theme's cell defaults. */
    private function layoutCell(TableCell $cell, int $j, float $width, ComputedStyle $rowStyle, Constraints $c, ?BreakToken $token, LayoutEngine $engine): LayoutResult
    {
        $cell = self::prepare($cell, $engine->context()->theme)->width(pt($width))->minWidth(pt($width))->maxWidth(pt($width));
        return $engine->layout($cell, $rowStyle, $c, $token, $j);
    }

    /**
     * Runs $fn with the row's computed style (the cells' parent) and its path entry pushed.
     *
     * @template T
     * @param \Closure(ComputedStyle): T $fn
     * @return T
     */
    private function inRow(TableRow $row, int $r, ComputedStyle $style, LayoutEngine $engine, \Closure $fn): mixed
    {
        $rowStyle = $engine->styleOf($row, $style, $r);
        $ctx = $engine->context();
        $ctx->pushPath(sprintf('tr[%d]', $r));
        try {
            return $fn($rowStyle);
        } finally {
            $ctx->popPath();
        }
    }

    /**
     * x and width of each cell of a row; a colspan cell spans its columns' widths.
     *
     * @param array{width: float, widths: list<float>, offsets: list<float>} $grid
     * @return list<array{x: float, width: float}>
     */
    private static function slots(TableRow $row, array $grid): array
    {
        $slots = [];
        $col = 0;
        foreach ($row->cells() as $cell) {
            $span = $cell->span();
            $slots[] = [
                'x' => $grid['offsets'][$col] ?? 0.0,
                'width' => array_sum(array_slice($grid['widths'], $col, $span)),
            ];
            $col += $span;
        }
        return $slots;
    }

    /** Cache key for everything a cell inherits from the table's computed style. */
    private static function styleKey(ComputedStyle $style): string
    {
        return md5(serialize($style));
    }

    /**
     * The given tracks for $n columns: missing tracks are 1fr, extra ones dropped.
     *
     * @return list<Length|Percent|Fr|Auto>
     */
    private static function tracks(Table $table, int $n): array
    {
        $given = $table->trackList()->tracks ?? [];
        $tracks = [];
        for ($k = 0; $k < $n; $k++) {
            $tracks[] = $given[$k] ?? Fr::of(1);
        }
        return $tracks;
    }

    /**
     * Theme cell padding unless the cell (or its named style) sets padding;
     * th cells are bold unless they set a weight.
     */
    private static function prepare(TableCell $cell, Theme $theme): TableCell
    {
        $own = $cell->style();
        $named = new Style();
        $name = $cell->styleName();
        if ($name !== null) {
            try {
                $named = $theme->named($name);
            } catch (UnknownAttribute) {
                // reported with its path when the engine resolves the cell's style
            }
        }
        if ($own->padding === null && $named->padding === null) {
            $cell = $cell->padding($theme->cellPadding);
        }
        if ($cell->header() && $own->fontWeight === null && $named->fontWeight === null) {
            $cell = $cell->weight(700);
        }
        return $cell;
    }

    /** @return list<TableRow> */
    private static function allRows(Table $table): array
    {
        return [...$table->headerRows(), ...$table->bodyRows()];
    }

    private static function column(TableCell $cell): View
    {
        return View::make(...$cell->children());
    }

    private static function table(Node $node): Table
    {
        if ($node instanceof Table) {
            return $node;
        }
        throw new \LogicException(sprintf('TableLayouter cannot lay out %s.', $node::class));
    }
}
