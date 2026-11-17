<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\DisplayItem;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use Papercut\Math\Node\Node;
use PHPUnit\Framework\TestCase;

final class BoxTest extends TestCase
{
    private static function nested(): Box
    {
        $inner = new Box(10.0, 5.0, 2.0);
        $inner->add(new Glyph(7, 'x', 1.0, 0.0, 12.0));
        $inner->add(new Rule(0.0, -3.0, 10.0, 1.0));
        $mid = new Box(30.0, 5.0, 2.0);
        $mid->add(new Rule(0.0, 0.0, 1.0, 1.0));
        $mid->place($inner, 4.0, -2.0);
        $outer = new Box(50.0, 5.0, 2.0);
        $outer->place($mid, 10.0, 1.0);
        $outer->add(new Glyph(9, 'y', 0.5, 0.0, 12.0));
        $outer->place($inner, 1.0, 1.0);

        return $outer;
    }

    public function test_items_are_the_flattened_list_at_absolute_offsets(): void
    {
        $items = self::nested()->items;

        self::assertEquals([
            new Rule(10.0, 1.0, 1.0, 1.0),
            new Glyph(7, 'x', 15.0, -1.0, 12.0),
            new Rule(14.0, -4.0, 10.0, 1.0),
            new Glyph(9, 'y', 0.5, 0.0, 12.0),
            new Glyph(7, 'x', 2.0, 1.0, 12.0),
            new Rule(1.0, -2.0, 10.0, 1.0),
        ], $items);
    }

    public function test_each_visits_leaves_in_drawing_order_with_their_offset(): void
    {
        $seen = [];
        self::nested()->each(static function (DisplayItem $i, float $dx, float $dy) use (&$seen): void {
            $seen[] = [$i::class, $dx, $dy];
        });

        self::assertSame([
            [Rule::class, 10.0, 1.0],
            [Glyph::class, 14.0, -1.0],
            [Rule::class, 14.0, -1.0],
            [Glyph::class, 0.0, 0.0],
            [Glyph::class, 1.0, 1.0],
            [Rule::class, 1.0, 1.0],
        ], $seen);
    }

    public function test_items_can_be_assigned(): void
    {
        $b = new Box();
        $b->items = [new Rule(1.0, 2.0, 3.0, 4.0)];
        $b->add(new Rule(0.0, 0.0, 1.0, 1.0));

        self::assertCount(2, $b->items);
    }

    public function test_a_deep_chain_copies_nothing_until_it_is_read(): void
    {
        CountedItem::$copies = 0;
        $box = new Box();
        $box->add(new CountedItem());
        for ($i = 0; $i < 500; $i++) {
            $parent = new Box();
            $parent->add(new CountedItem());
            $parent->place($box, 1.0, 1.0);
            $box = $parent;
        }
        self::assertSame(0, CountedItem::$copies, 'place() must not copy items');

        $items = $box->items;
        self::assertCount(501, $items);
        // Reading translates each leaf once, not once per enclosing level.
        self::assertLessThanOrEqual(501, CountedItem::$copies);
        self::assertInstanceOf(CountedItem::class, $items[500]);
        self::assertSame(500.0, $items[500]->x);
    }

    public function test_a_deep_sqrt_chain_copies_no_item_while_laying_out(): void
    {
        $leaf = new class extends Node {
            public function layout(Context $c): Box
            {
                $b = new Box(5.0, 5.0, 0.0);
                $b->add(new CountedItem());

                return $b;
            }
        };
        $node = Math::sqrt($leaf);
        for ($i = 1; $i < 500; $i++) {
            $node = Math::sqrt($node);
        }
        CountedItem::$copies = 0;
        $box = Math::layout($node);
        self::assertSame(0, CountedItem::$copies, 'layout must not translate items level by level');

        $counted = 0;
        $box->each(static function (DisplayItem $i) use (&$counted): void {
            $counted += $i instanceof CountedItem ? 1 : 0;
        });
        self::assertSame(1, $counted);
        $leaves = 0;
        $box->each(static function () use (&$leaves): void {
            $leaves++;
        });
        $items = $box->items;
        self::assertCount($leaves, $items);
        self::assertLessThanOrEqual(1, CountedItem::$copies, 'reading copies each leaf at most once');
    }
}
