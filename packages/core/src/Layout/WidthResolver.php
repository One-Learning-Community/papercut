<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Table, View};
use Papercut\Style\ComputedStyle;

/**
 * Horizontal sizes from the layout engine's own flex and table calculations,
 * without pagination, so layout-free exporters match the PDF horizontally.
 */
final class WidthResolver
{
    public function __construct(private readonly LayoutEngine $engine)
    {
    }

    /**
     * One list per flex line. $style is the row's own computed style; $available is the
     * width offered to its border box. x is relative to the content box.
     *
     * @return list<list<FlexSlot>>
     */
    public function flexLines(View $row, ComputedStyle $style, float $available): array
    {
        $lines = [];
        foreach ($this->engine->flexLayouter()->lines($row, $style, $available, $this->engine) as $line) {
            $lines[] = array_map(static fn (array $item): FlexSlot => new FlexSlot($item['index'], $item['x'], $item['main']), $line);
        }
        return $lines;
    }

    /**
     * Column widths in pt; $style is the table's computed style, $available as in flexLines().
     *
     * @return list<float>
     */
    public function tableColumns(Table $table, ComputedStyle $style, float $available): array
    {
        return $this->engine->tableLayouter()->columns($table, $style, $available, $this->engine);
    }
}
