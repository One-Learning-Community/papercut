<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Font;

use Quoin\Math\Exception\UnsupportedFont;
use Quoin\Math\Font\Font;
use Quoin\Math\Font\TrueTypeFont;
use Quoin\Math\Math;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class FontTest extends TestCase
{
    public function test_the_same_unchanged_file_is_opened_once(): void
    {
        $path = Math::stixTwoMathPath();
        self::assertSame(Font::fromFile($path), Font::fromFile(dirname($path) . '/../fonts/' . basename($path)));
    }

    public function test_a_changed_file_is_read_again(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'chg') ?: self::fail('no temp file');
        copy(Math::stixTwoMathPath(), $path);
        try {
            Font::fromFile($path);
            file_put_contents($path, 'no longer a font');
            clearstatcache();
            $this->expectException(UnsupportedFont::class);
            Font::fromFile($path);
        } finally {
            unlink($path);
        }
    }

    public function test_rejects_font_collection(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ttc') ?: self::fail('no temp file');
        file_put_contents($path, 'ttcf' . pack('NN', 0x00010000, 2) . str_repeat("\0", 16));
        try {
            Font::fromFile($path);
            self::fail('Expected UnsupportedFont');
        } catch (UnsupportedFont $e) {
            self::assertStringContainsString('font collection', $e->getMessage());
            self::assertSame($path, $e->path);
        } finally {
            unlink($path);
        }
    }

    public function test_rejects_font_without_math_table(): void
    {
        $path = __DIR__ . '/../fixtures/Inter-Regular.ttf';
        try {
            Font::fromFile($path);
            self::fail('Expected UnsupportedFont');
        } catch (UnsupportedFont $e) {
            self::assertStringContainsString('no MATH table', $e->getMessage());
            self::assertSame($path, $e->path);
        }
    }

    public function test_rejects_a_font_cut_short_without_warnings(): void
    {
        $bytes = (string) file_get_contents(Math::stixTwoMathPath());
        $path = tempnam(sys_get_temp_dir(), 'cut') ?: self::fail('no temp file');
        $warnings = [];
        set_error_handler(static function (int $no, string $str) use (&$warnings): bool {
            $warnings[] = $str;

            return true;
        });
        try {
            foreach ([200, 5_000, 300_000, strlen($bytes) - 1] as $keep) {
                file_put_contents($path, substr($bytes, 0, $keep));
                try {
                    Font::fromFile($path);
                    self::fail("Expected UnsupportedFont at $keep bytes");
                } catch (UnsupportedFont $e) {
                    self::assertSame($path, $e->path);
                }
            }
        } finally {
            restore_error_handler();
            unlink($path);
        }
        self::assertSame([], $warnings);
    }

    public function test_rejects_font_missing_a_required_table_naming_the_file(): void
    {
        // A TrueType font whose directory lists only a MATH table: head, hhea and the rest are absent.
        $path = tempnam(sys_get_temp_dir(), 'req') ?: self::fail('no temp file');
        file_put_contents($path, "\0\x01\0\0" . pack('nnnn', 1, 16, 0, 0) . 'MATH' . pack('NNN', 0, 28, 4) . "\0\0\0\0");
        try {
            Font::fromFile($path);
            self::fail('Expected UnsupportedFont');
        } catch (UnsupportedFont $e) {
            self::assertStringContainsString('no head table', $e->getMessage());
            self::assertSame($path, $e->path);
        } finally {
            unlink($path);
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
            Font::fromFile($path);
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
        $font = Font::fromFile(Math::stixTwoMathPath());
        self::assertSame(1000, $font->unitsPerEm);
    }

    #[RunInSeparateProcess]
    public function test_deprecated_alias_satisfies_a_type_declaration_without_being_autoloaded(): void
    {
        $takesAlias = static fn (TrueTypeFont $font): int => $font->unitsPerEm;
        self::assertSame(1000, $takesAlias(Math::stixTwoMath()));
    }

    #[RunInSeparateProcess]
    public function test_deprecated_alias_is_found_by_instanceof_without_being_autoloaded(): void
    {
        $isAlias = static fn (mixed $value): bool => $value instanceof TrueTypeFont;
        self::assertTrue($isAlias(Math::stixTwoMath()));
    }

    #[RunInSeparateProcess]
    public function test_deprecated_alias_can_open_a_font(): void
    {
        self::assertInstanceOf(Font::class, TrueTypeFont::fromFile(Math::stixTwoMathPath()));
    }
}
