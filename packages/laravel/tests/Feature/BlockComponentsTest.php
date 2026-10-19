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

    public function test_spacer_grow_is_numeric_and_non_negative(): void
    {
        self::assertSame(2.0, $this->nodeFrom('<x-pdf::spacer grow="2" />')->style()->grow);
        self::assertSame(0.0, $this->nodeFrom('<x-pdf::spacer grow="0" />')->style()->grow);
        self::assertSame(1.0, $this->nodeFrom('<x-pdf::spacer />')->style()->grow);
    }

    /** @return iterable<string, array{string}> */
    public static function badGrow(): iterable
    {
        yield 'text' => ['<x-pdf::spacer grow="abc" />'];
        yield 'presence' => ['<x-pdf::spacer grow />'];
        yield 'negative' => ['<x-pdf::spacer grow="-1" />'];
        yield 'bound array' => ['<x-pdf::spacer :grow="[1]" />'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badGrow')]
    public function test_spacer_rejects_invalid_grow(string $blade): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('for grow on <x-pdf::spacer>');
        $this->nodeFrom($blade);
    }

    public function test_alias_error_reports_the_users_key(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('for align on <x-pdf::row>');
        $this->nodeFrom('<x-pdf::row align="bogus" />');
    }

    public function test_align_alias_is_scoped_to_containers(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "align" on <x-pdf::link>');
        $this->nodeFrom('<x-pdf::link href="x" align="center">go</x-pdf::link>');
    }

    public function test_named_style_and_style_object(): void
    {
        self::assertSame('card', $this->nodeFrom('<x-pdf::view style="card" />')->styleName());
        $node = $this->nodeFrom('<x-pdf::view :style="$s" />', ['s' => \Papercut\Style\Style::fromAttributes(['padding' => '4pt'])]);
        self::assertSame(4.0, $node->style()->padding?->top->points);
    }

    public function test_non_string_style_is_invalid(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Invalid style "5" for style on <x-pdf::view>');
        $this->nodeFrom('<x-pdf::view :style="5" />');
    }

    public function test_nested_file_component_errors_are_unwrapped(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Unknown attribute "paddng" on <x-pdf::column>');
        $this->nodeFrom('<x-pdf::column><x-bad-card /></x-pdf::column>');
    }
}
