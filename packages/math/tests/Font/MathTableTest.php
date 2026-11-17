<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Font;

use Quoin\Math\Font\Font;
use Quoin\Math\Math;
use PHPUnit\Framework\TestCase;

final class MathTableTest extends TestCase
{
    public function test_top_accent_attachment_of_italic_f(): void
    {
        $font = Font::fromFile(Math::stixTwoMathPath());
        $gid = $font->gid(0x1D453);
        $att = $font->math()->topAccentAttachment($gid);
        self::assertNotNull($att);
        self::assertGreaterThan($font->advance($gid) / 2, $att);
    }

    public function test_top_accent_attachment_is_null_when_not_covered(): void
    {
        $font = Font::fromFile(Math::stixTwoMathPath());
        self::assertNull($font->math()->topAccentAttachment($font->gid(0x20)));
    }
}
