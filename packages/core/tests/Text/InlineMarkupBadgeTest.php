<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use Papercut\Exception\InvalidValue;
use Papercut\Span;
use Papercut\Text\InlineMarkup;
use PHPUnit\Framework\TestCase;

final class InlineMarkupBadgeTest extends TestCase
{
    public function test_plain_badge(): void
    {
        self::assertEquals([Span::badge('201')], InlineMarkup::parse('<badge>201</badge>'));
    }

    public function test_type_and_variant(): void
    {
        self::assertEquals(
            [Span::text('Returns '), Span::badge('v2', 'tip', 'outline'), Span::text(' ok')],
            InlineMarkup::parse('Returns <badge type="tip" variant="outline">v2</badge> ok'),
        );
    }

    public function test_entities_decode(): void
    {
        self::assertSame('a & b', InlineMarkup::parse('<badge>a &amp; b</badge>')[0]->text);
    }

    public function test_nested_markup_is_rejected(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('plain text');
        InlineMarkup::parse('<badge><b>x</b></badge>');
    }

    public function test_unknown_attribute_is_rejected(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('not allowed');
        InlineMarkup::parse('<badge colour="x">x</badge>');
    }

    public function test_badge_in_link_carries_href(): void
    {
        $spans = InlineMarkup::parse('<a href="https://example.com"><badge>docs</badge></a>');
        self::assertCount(1, $spans);
        self::assertSame('https://example.com', $spans[0]->href);
        self::assertSame(Span::badge('docs')->styleName, $spans[0]->styleName);
        self::assertSame('docs', $spans[0]->text);
    }

    public function test_badge_after_link_has_no_href(): void
    {
        $spans = InlineMarkup::parse('<a href="https://example.com">x</a><badge>y</badge>');
        self::assertNull($spans[1]->href);
    }

    public function test_bad_tone_is_rejected(): void
    {
        $this->expectException(InvalidValue::class);
        InlineMarkup::parse('<badge type="two words">x</badge>');
    }
}
