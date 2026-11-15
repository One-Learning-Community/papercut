<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Font;

use Papercut\Math\Font\TrueTypeFont;
use Papercut\Math\Math;
use PHPUnit\Framework\TestCase;

final class MathTableTest extends TestCase
{
    public function test_top_accent_attachment_of_italic_f(): void
    {
        $font = TrueTypeFont::fromFile(Math::stixTwoMathPath());
        $gid = $font->gid(0x1D453);
        $att = $font->math()->topAccentAttachment($gid);
        self::assertNotNull($att);
        self::assertGreaterThan($font->advance($gid) / 2, $att);
    }

    public function test_top_accent_attachment_is_null_when_not_covered(): void
    {
        $font = TrueTypeFont::fromFile(Math::stixTwoMathPath());
        self::assertNull($font->math()->topAccentAttachment($font->gid(0x20)));
    }
}
