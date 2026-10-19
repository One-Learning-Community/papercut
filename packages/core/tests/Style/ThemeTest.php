<?php

declare(strict_types=1);

namespace Papercut\Tests\Style;

use Papercut\Exception\UnknownAttribute;
use Papercut\Style\{Style, Theme};
use Papercut\Value\Color;
use PHPUnit\Framework\TestCase;

final class ThemeTest extends TestCase
{
    public function test_heading_presets(): void
    {
        $t = Theme::default();
        $sizes = [1 => 24.0, 2 => 18.0, 3 => 14.0, 4 => 12.0];
        foreach ($sizes as $level => $size) {
            $h = $t->heading($level);
            self::assertSame($size, $h->fontSize?->points);
            self::assertSame(700, $h->fontWeight);
            self::assertSame($size * 0.5, $h->margin?->bottom->points);
        }
        self::assertSame(6.0, $t->paragraph()->margin?->bottom->points);
    }

    public function test_named_styles_are_immutable_additions(): void
    {
        $t = Theme::default();
        $t2 = $t->withStyle('card', Style::make(background: Color::white()));
        self::assertSame('#ffffff', (string) $t2->named('card')->background);
        $this->expectException(UnknownAttribute::class);
        $t->named('card');
    }

    public function test_unknown_style_suggestion(): void
    {
        $t = Theme::default()->withStyle('card', Style::make());
        $this->expectExceptionMessage('Unknown style "crad". Did you mean "card"?');
        $t->named('crad');
    }

    public function test_with_overrides(): void
    {
        $t = Theme::default()->with(fontSize: 11.0, fontFamily: 'Brand');
        self::assertSame(11.0, $t->fontSize);
        self::assertSame('Brand', $t->fontFamily);
        self::assertSame(10.0, Theme::default()->fontSize);
    }

    public function test_bad_heading_level(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Theme::default()->heading(5);
    }
}
