<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Font;

use Papercut\Math\Exception\UnsupportedFont;
use Papercut\Math\Font\TrueTypeFont;
use Papercut\Math\Math;
use PHPUnit\Framework\TestCase;

final class TrueTypeFontTest extends TestCase
{
    public function test_rejects_cff_font(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cff') ?: self::fail('no temp file');
        // A minimal sfnt: tag OTTO, one table directory entry named "CFF ".
        file_put_contents($path, 'OTTO' . pack('nnnn', 1, 16, 0, 0) . 'CFF ' . pack('NNN', 0, 28, 0));
        try {
            TrueTypeFont::fromFile($path);
            self::fail('Expected UnsupportedFont');
        } catch (UnsupportedFont $e) {
            self::assertStringContainsString('CFF', $e->getMessage());
            self::assertSame($path, $e->path);
        } finally {
            unlink($path);
        }
    }

    public function test_rejects_font_without_math_table(): void
    {
        $path = __DIR__ . '/../fixtures/Inter-Regular.ttf';
        try {
            TrueTypeFont::fromFile($path);
            self::fail('Expected UnsupportedFont');
        } catch (UnsupportedFont $e) {
            self::assertStringContainsString('no MATH table', $e->getMessage());
            self::assertSame($path, $e->path);
        }
    }

    public function test_rejects_garbage_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad') ?: self::fail('no temp file');
        file_put_contents($path, 'this is not a font!!');
        $warnings = [];
        set_error_handler(function (int $no, string $str) use (&$warnings): bool {
            $warnings[] = $str;

            return true;
        });
        try {
            TrueTypeFont::fromFile($path);
            self::fail('Expected UnsupportedFont');
        } catch (UnsupportedFont $e) {
            self::assertStringContainsString('not a TrueType/OpenType font', $e->getMessage());
        } finally {
            restore_error_handler();
            unlink($path);
        }
        self::assertSame([], $warnings);
    }

    public function test_stix_two_math_opens(): void
    {
        self::assertFileExists(Math::stixTwoMathPath());
        $font = TrueTypeFont::fromFile(Math::stixTwoMathPath());
        self::assertSame(1000, $font->unitsPerEm);
    }
}
