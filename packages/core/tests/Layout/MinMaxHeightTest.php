<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\{Column, Row, Text, View};
use Papercut\Decorator\Field;
use Papercut\Layout\{Fragment, LayoutResult, Status};
use Papercut\Tests\Support\LayoutHarness;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

/** min-height and max-height on boxes that size to their content. */
final class MinMaxHeightTest extends TestCase
{
    public function test_view_grows_to_min_height(): void
    {
        $r = (new LayoutHarness())->layout(View::make(Text::make('One line'))->border(pt(1))->minHeight(pt(80)), 200);
        self::assertEqualsWithDelta(80.0, self::frag($r)->height, 0.001);
    }

    public function test_column_and_row_grow_to_min_height(): void
    {
        $h = new LayoutHarness();
        self::assertEqualsWithDelta(80.0, self::frag($h->layout(Column::make(Text::make('x'))->minHeight(pt(80)), 200))->height, 0.001);
        self::assertEqualsWithDelta(80.0, self::frag($h->layout(Row::make(Text::make('x'), Text::make('y'))->minHeight(pt(80)), 200))->height, 0.001);
    }

    public function test_min_height_inside_a_column_and_a_row(): void
    {
        $h = new LayoutHarness();
        $box = View::make(Text::make('x'))->border(pt(1))->minHeight(pt(80));

        $col = self::frag($h->layout(Column::make($box, Text::make('after')), 200));
        self::assertEqualsWithDelta(80.0, $col->children()[0]['fragment']->height, 0.001);
        self::assertEqualsWithDelta(80.0, $col->children()[1]['y'], 0.001, 'the next sibling sits below the full min-height');

        $row = self::frag($h->layout(Row::make($box, Text::make('beside')), 200));
        self::assertEqualsWithDelta(80.0, $row->height, 0.001);
    }

    public function test_content_taller_than_min_height_keeps_its_height(): void
    {
        $r = (new LayoutHarness())->layout(View::make(...array_map(fn (int $i) => Text::make("line $i"), range(1, 10)))->minHeight(pt(20)), 200);
        self::assertEqualsWithDelta(140.0, self::frag($r)->height, 0.001);
    }

    public function test_text_honours_min_max_and_explicit_height(): void
    {
        $h = new LayoutHarness();
        self::assertEqualsWithDelta(80.0, self::frag($h->layout(Text::make('x')->border(pt(1))->minHeight(pt(80)), 200))->height, 0.001);
        self::assertEqualsWithDelta(80.0, self::frag($h->layout(Text::make('x')->height(pt(80)), 200))->height, 0.001);
        self::assertEqualsWithDelta(20.0, self::frag($h->layout(Text::markup("a<br>b<br>c<br>d")->maxHeight(pt(20)), 200))->height, 0.001);
    }

    public function test_max_height_caps_a_content_sized_box(): void
    {
        $r = (new LayoutHarness())->layout(View::make(Text::make('a'), Text::make('b'), Text::make('c'), Text::make('d'))->maxHeight(pt(20)), 200);
        self::assertEqualsWithDelta(20.0, self::frag($r)->height, 0.001);
    }

    public function test_empty_field_with_lines_is_that_tall(): void
    {
        $r = (new LayoutHarness())->layout(Field::make()->lines(4), 200);
        // lines(4) = four 14pt lines inside the field's 5pt padding and 1pt border: 56 + 10 + 2.
        self::assertEqualsWithDelta(68.0, self::frag($r)->height, 0.001);
    }

    public function test_min_height_that_does_not_fit_moves_to_the_next_page(): void
    {
        $h = new LayoutHarness();
        $box = View::make(Text::make('x'))->minHeight(pt(80));

        $notFresh = $h->layout($box, 200, 50.0, null, false);
        self::assertSame(Status::DoesNotFit, $notFresh->status);

        $fresh = $h->layout($box, 200, 50.0, null, true);
        self::assertEqualsWithDelta(50.0, self::frag($fresh)->height, 0.001, 'on a fresh page it is capped to the space');
    }

    private static function frag(LayoutResult $r): Fragment
    {
        self::assertNotNull($r->fragment);
        return $r->fragment;
    }
}
