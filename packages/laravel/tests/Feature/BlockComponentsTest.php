<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Column, Link, PageBreak, Spacer, Text, View};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Tests\TestCase;
use Papercut\Style\{Align, Direction};

final class BlockComponentsTest extends TestCase
{
    public function test_row_with_attributes_and_children(): void
    {
        $node = $this->nodeFrom('<x-pdf::row gap="8pt" align="center" padding="4pt"><x-pdf::column>A</x-pdf::column> B </x-pdf::row>');
        self::assertInstanceOf(View::class, $node);
        self::assertSame(Direction::Row, $node->style()->direction);
        self::assertSame(Align::Center, $node->style()->alignItems);
        self::assertSame(8.0, $node->style()->gap?->points);
        self::assertCount(2, $node->children());
        self::assertInstanceOf(Text::class, $node->children()[1]);
    }

    public function test_whitespace_between_blocks_is_dropped(): void
    {
        $node = $this->nodeFrom("<x-pdf::column>\n  <x-pdf::spacer size=\"4pt\" />\n  <x-pdf::page-break />\n</x-pdf::column>");
        self::assertCount(2, $node->children());
        self::assertInstanceOf(Spacer::class, $node->children()[0]);
        self::assertInstanceOf(PageBreak::class, $node->children()[1]);
    }

    public function test_bound_values_pass_through_typed(): void
    {
        $node = $this->nodeFrom('<x-pdf::view :padding="$p" />', ['p' => \Papercut\pt(3)]);
        self::assertSame(3.0, $node->style()->padding?->top->points);
    }

    public function test_unknown_attribute_names_the_tag(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "paddng" on <x-pdf::column>. Did you mean "padding"?');
        $this->nodeFrom('<x-pdf::column paddng="4pt" />');
    }

    public function test_invalid_value_names_attribute_and_tag(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Invalid edges "20 mm" for margin on <x-pdf::view>. Did you mean "20mm"?');
        $this->nodeFrom('<x-pdf::view margin="20 mm" />');
    }

    public function test_link_requires_href(): void
    {
        self::assertSame('https://x.test', $this->nodeFrom('<x-pdf::link href="https://x.test">go</x-pdf::link>')->uri());
        $this->expectException(\Papercut\Laravel\Exception\InvalidStructure::class);
        $this->nodeFrom('<x-pdf::link>go</x-pdf::link>');
    }

    public function test_app_component_with_extra_attributes_nests_cleanly(): void
    {
        $node = $this->nodeFrom('<x-pdf::column><x-student-card class="mb-4" :name="$n" /></x-pdf::column>', ['n' => 'Ada']);
        $card = $node->children()[0];
        self::assertInstanceOf(View::class, $card);
    }
}
