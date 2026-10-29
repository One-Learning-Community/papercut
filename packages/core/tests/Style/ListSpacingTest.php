<?php

declare(strict_types=1);

namespace Papercut\Tests\Style;

use Papercut\{ListItem, ListNode, Table, Text, View};
use Papercut\Markup\Markup;
use Papercut\Style\Theme;
use Papercut\Tests\Support\LayoutHarness;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class ListSpacingTest extends TestCase
{
    /** Baseline of the run with this text. */
    private function baseline(\Papercut\Node $root, string $text, ?Theme $theme = null): float
    {
        $res = (new LayoutHarness($theme))->layout($root, 400);
        foreach (LayoutHarness::runs($res->fragment) as $r) {
            if ($r['text'] === $text) {
                return $r['baseline'];
            }
        }
        self::fail("No run '$text'.");
    }

    private function gap(\Papercut\Node $root, string $from, string $to, ?Theme $theme = null): float
    {
        return $this->baseline($root, $to, $theme) - $this->baseline($root, $from, $theme);
    }

    private function lineStep(): float
    {
        return $this->gap(View::make(Text::make('X'), Text::make('Y')), 'X', 'Y');
    }

    public function test_paragraph_after_a_list_sits_6pt_lower(): void
    {
        $gap = $this->gap(View::make(ListNode::unordered('A', 'B'), Text::make('After')), 'B', 'After');
        self::assertEqualsWithDelta($this->lineStep() + 6.0, $gap, 0.001);
    }

    public function test_markup_list_with_unwrapped_paragraph_gets_the_space(): void
    {
        $gap = $this->gap(View::make(Markup::html('<ul><li><p>A</p></li></ul><p>After</p>')), 'A', 'After');
        self::assertEqualsWithDelta($this->lineStep() + 6.0, $gap, 0.001);
    }

    public function test_markup_list_ending_in_a_p_does_not_stack(): void
    {
        $gap = $this->gap(View::make(Markup::html('<ul><li><p>A</p><p>B</p></li></ul><p>After</p>')), 'B', 'After');
        self::assertEqualsWithDelta($this->lineStep() + 6.0, $gap, 0.001);
    }

    public function test_list_item_ending_in_a_p_does_not_stack(): void
    {
        $gap = $this->gap(View::make(ListNode::unordered(ListItem::make(Text::p('A'))), Text::make('After')), 'A', 'After');
        self::assertEqualsWithDelta($this->lineStep() + 6.0, $gap, 0.001);
    }

    public function test_trailing_margin_found_through_nested_lists(): void
    {
        $outer = ListNode::unordered(ListItem::make('Top', ListNode::unordered(ListItem::make(Text::p('Deep')))));
        $gap = $this->gap(View::make($outer, Text::make('After')), 'Deep', 'After');
        self::assertEqualsWithDelta($this->lineStep() + 6.0, $gap, 0.001);
    }

    public function test_nested_list_adds_no_space(): void
    {
        $outer = ListNode::unordered(ListItem::make('Top', ListNode::unordered('Inner'), 'More'));
        $gap = $this->gap(View::make($outer), 'Inner', 'More');
        self::assertEqualsWithDelta($this->lineStep(), $gap, 0.001);
    }

    public function test_own_margin_wins(): void
    {
        $list = ListNode::unordered('A', 'B')->margin(pt(0), pt(0), pt(20), pt(0));
        $gap = $this->gap(View::make($list, Text::make('After')), 'B', 'After');
        self::assertEqualsWithDelta($this->lineStep() + 20.0, $gap, 0.001);
    }

    public function test_cell_ending_in_a_list_gets_the_space(): void
    {
        $withList = Table::make()->columns('200pt')->row(ListNode::unordered('A', 'B'));
        $plain = Table::make()->columns('200pt')->row(View::make(Text::make('A'), Text::make('B')));
        $h = static fn (\Papercut\Node $n): ?float => (new LayoutHarness())->layout($n, 400)->fragment?->height;
        self::assertEqualsWithDelta($h($plain) + 6.0, $h($withList), 0.001);
    }

    public function test_list_spacing_follows_the_theme(): void
    {
        $theme = Theme::default();
        $space = $theme->list()->margin?->bottom->points ?? -1.0;
        self::assertSame($theme->paragraph()->margin?->bottom->points, $space);
        $gap = $this->gap(View::make(ListNode::unordered('A'), Text::make('After')), 'A', 'After', $theme);
        self::assertEqualsWithDelta($this->lineStep() + $space, $gap, 0.001);
    }
}
