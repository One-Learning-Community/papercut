<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Font;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Exception\UnsupportedFont;
use Quoin\Math\Font\Binary;
use Quoin\Math\Font\CffOutlines;
use Quoin\Math\Font\Font;
use Quoin\Math\Math;
use Quoin\Math\Render\PngRenderer;
use Quoin\Math\Render\SvgRenderer;

/** CFF (OpenType/PostScript) math fonts: Fira Math as the real font, and small synthetic tables for the refusals and guards. */
final class CffFontTest extends TestCase
{
    private const FIRA = __DIR__ . '/../fixtures/FiraMath-Regular.otf';

    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            @unlink($file);
        }
    }

    // Fira Math, a real CFF font.

    public function test_the_cff_fixture_loads(): void
    {
        $font = Font::fromFile(self::FIRA);
        self::assertSame(1000, $font->unitsPerEm);
        self::assertGreaterThan(1000, $font->numGlyphs);
        self::assertGreaterThan(0, $font->advance($font->gid(ord('x'))));
    }

    public function test_bounds_match_the_fonttools_oracle(): void
    {
        $font = Font::fromFile(self::FIRA);
        $oracle = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/fira-math-bounds.json'), true, 4, JSON_THROW_ON_ERROR);
        self::assertIsArray($oracle);
        self::assertGreaterThanOrEqual(200, count($oracle));
        foreach ($oracle as $gid => $box) {
            self::assertSame($box, $font->bbox((int) $gid), "bounds of glyph $gid");
        }
    }

    public function test_a_curved_glyph_has_cubic_path_data_and_no_quadratics(): void
    {
        $font = Font::fromFile(self::FIRA);
        foreach (['o', '('] as $char) {
            $d = $font->outlinePath($font->gid(ord($char)));
            self::assertStringStartsWith('M', $d);
            self::assertStringContainsString('C', $d, $char);
            self::assertStringNotContainsString('Q', $d, $char);
            self::assertStringEndsWith('Z', $d);
        }
    }

    public function test_a_truetype_glyph_has_quadratic_path_data_and_no_cubics(): void
    {
        $font = Math::stixTwoMath();
        $d = $font->outlinePath($font->gid(ord('o')));
        self::assertStringContainsString('Q', $d);
        self::assertStringNotContainsString('C', $d);
    }

    public function test_every_glyph_reads_without_warnings(): void
    {
        $font = Font::fromFile(self::FIRA);
        $warnings = [];
        set_error_handler(static function (int $no, string $str) use (&$warnings): bool {
            $warnings[] = $str;

            return true;
        });
        try {
            for ($gid = 0; $gid < $font->numGlyphs; $gid++) {
                $font->bbox($gid);
                $font->contours($gid);
                $font->outlinePath($gid);
            }
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
    }

    public function test_an_equation_in_a_cff_font_renders_to_svg(): void
    {
        $font = Font::fromFile(self::FIRA);
        $box = Math::layout(Math::tex('\sum_{i=1}^{n} \sqrt{x^2 + 1} = \int_0^1 \left( \frac{a}{b} \right) dx'), 12.0, true, $font);
        $svg = (new SvgRenderer($font))->render($box, 1.0);

        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        self::assertGreaterThan(10, $doc->getElementsByTagName('path')->length);
        self::assertStringContainsString('C', $svg);
        self::assertStringNotContainsString('NaN', $svg);
    }

    public function test_an_equation_in_a_cff_font_renders_to_png(): void
    {
        if (! PngRenderer::available()) {
            self::markTestSkipped('ext-imagick is not loaded');
        }
        $font = Font::fromFile(self::FIRA);
        $box = Math::layout(Math::tex('\frac{-b \pm \sqrt{b^2 - 4ac}}{2a}'), 12.0, true, $font);
        $png = (new PngRenderer($font))->render($box, 2.0);

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $im = new \Imagick();
        $im->readImageBlob($png);
        $inked = false;
        foreach ($im->exportImagePixels(0, 0, $im->getImageWidth(), $im->getImageHeight(), 'A', \Imagick::PIXEL_CHAR) as $alpha) {
            if ($alpha > 0) {
                $inked = true;
                break;
            }
        }
        self::assertTrue($inked);
    }

    // Refusals.

    public function test_a_file_with_no_font_tag_is_not_a_font(): void
    {
        foreach (["wOFF\0\0\0\0\0\0\0\0\0\0\0\0" . str_repeat("\0", 32), "<!DOCTYPE html>\n<html><body>" . str_repeat('x', 100)] as $bytes) {
            $this->expectRefusal($bytes, 'not a TrueType/OpenType font');
        }
    }

    public function test_a_cff2_font_is_refused(): void
    {
        $this->expectRefusal(self::sfnt('OTTO', ['CFF2' => str_repeat("\0", 8), 'MATH' => str_repeat("\0", 8)]), 'CFF2');
    }

    public function test_a_cid_keyed_font_is_refused(): void
    {
        $cff = self::cff([self::cs(self::num(0), self::END)], topExtra: self::int(0) . self::int(0) . self::int(0) . "\x0c\x1e");
        $this->expectRefusal(self::cffFont($cff), 'CID-keyed');
    }

    public function test_a_font_matrix_that_disagrees_with_units_per_em_is_refused(): void
    {
        $matrix = self::real('0.0005') . self::real('0') . self::real('0') . self::real('0.0005') . self::real('0') . self::real('0') . "\x0c\x07";
        $this->expectRefusal(self::cffFont(self::cff([self::cs(self::END)], topExtra: $matrix)), 'font matrix');
        $this->expectRefusal(self::cffFont(self::cff([self::cs(self::END)]), 2048), 'font matrix'); // the default 0.001 against 2048
    }

    public function test_a_font_matrix_with_a_real_number_in_exponent_form_is_read(): void
    {
        $matrix = self::real('1E-3') . self::real('0') . self::real('0') . self::real('.001') . self::real('0') . self::real('0') . "\x0c\x07";
        $cff = self::cff([self::cs(self::END)], topExtra: $matrix);
        $outlines = new CffOutlines(new Binary($cff), 0, strlen($cff), 1000, 1);
        self::assertSame([], $outlines->path(0));
    }

    public function test_a_charstring_count_that_differs_from_maxp_is_refused(): void
    {
        $this->expectRefusal(self::cffFont(self::cff([self::cs(self::END)]), 1000, 5), 'maxp');
    }

    public function test_a_truncated_cff_table_is_refused_without_warnings(): void
    {
        $cff = self::cff([self::cs(self::num(1), self::num(2), "\x15", self::END)]);
        foreach ([3, 10, strlen($cff) - 6, strlen($cff) - 1] as $keep) {
            $this->expectRefusal(self::sfnt('OTTO', self::tables(substr($cff, 0, $keep), 1000, 1)), 'CFF table');
        }
    }

    public function test_a_charstrings_offset_outside_the_table_is_refused(): void
    {
        $cff = self::cff([self::cs(self::END)], charStringsAt: 99999);
        $this->expectRefusal(self::cffFont($cff), 'outside the table');
    }

    public function test_a_cff_table_with_no_charstrings_is_refused(): void
    {
        $cff = self::cff([self::cs(self::END)], charStringsAt: null);
        $this->expectRefusal(self::cffFont($cff), 'no CharStrings');
    }

    public function test_a_cff_table_longer_than_the_file_is_refused(): void
    {
        $cff = self::cff([self::cs(self::END)]);
        $font = self::sfnt('OTTO', self::tables($cff, 1000, 1));
        $this->expectRefusal(substr($font, 0, strlen($font) - 5), 'outside the file');
    }

    // Interpreter guards.

    public function test_a_subroutine_that_calls_itself_finishes(): void
    {
        $self = self::cs(self::num(-107), self::CALLGSUBR); // bias 107: subroutine 0
        $glyph = self::cs(self::num(10), self::num(10), self::RMOVETO, self::num(-107), self::CALLGSUBR, self::END);
        $path = self::outlines([$glyph], [$self])->path(0);
        self::assertSame('M', $path[0][0]);
    }

    public function test_a_subroutine_that_calls_itself_three_times_stops_at_the_operator_budget(): void
    {
        $call = self::num(-107) . self::CALLGSUBR;
        $sub = self::cs(self::num(1), self::num(0), "\x05", $call, $call, $call);
        $glyph = self::cs(self::num(10), self::num(10), self::RMOVETO, $call, self::END);
        $path = self::outlines([$glyph], [$sub])->path(0);
        // Unbounded, nesting to depth 10 draws about 88,000 lines; the budget of 20,000 operators ends it far sooner.
        self::assertLessThan(20001, count($path));
        self::assertGreaterThan(100, count($path));
    }

    public function test_operands_past_the_stack_limit_are_dropped(): void
    {
        $glyph = self::cs(self::num(10), self::num(10), self::RMOVETO, str_repeat(self::num(1), 60), "\x05", self::END);
        $path = self::outlines([$glyph])->path(0);
        $lines = array_filter($path, static fn (array $s): bool => $s[0] === 'L');
        self::assertCount(24, $lines); // 48 operands kept, 24 coordinate pairs
    }

    public function test_a_charstring_cut_off_inside_an_operand_ends_without_warnings(): void
    {
        $warnings = [];
        set_error_handler(static function (int $no, string $str) use (&$warnings): bool {
            $warnings[] = $str;

            return true;
        });
        try {
            foreach (["\x1c\x01", "\xff\x00\x01", "\xf7", self::cs(self::num(1), self::num(1), "\x13") . "\x00", self::cs(self::num(1), "\x0c")] as $bytes) {
                self::assertSame([], self::outlines([$bytes])->path(0));
            }
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
    }

    public function test_hintmask_does_not_skip_past_the_end_of_its_charstring(): void
    {
        // 16 stem hints make a two-byte mask; the charstring ends after the operator, and a second glyph follows it.
        $stems = str_repeat(self::num(1), 32) . "\x01";
        $first = $stems . "\x13";
        $second = self::cs(self::num(5), self::num(5), self::RMOVETO, self::END);
        $outlines = self::outlines([$first, $second]);
        self::assertSame([], $outlines->path(0));
        self::assertSame('M', $outlines->path(1)[0][0]);
    }

    // Builders.

    private const string END = "\x0e";
    private const string RMOVETO = "\x15";
    private const string CALLGSUBR = "\x1d";

    private function expectRefusal(string $fontBytes, string $reason): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cff') ?: self::fail('no temp file');
        $this->temp[] = $path;
        file_put_contents($path, $fontBytes);
        $warnings = [];
        set_error_handler(static function (int $no, string $str) use (&$warnings): bool {
            $warnings[] = $str;

            return true;
        });
        try {
            Font::fromFile($path);
            self::fail('Expected UnsupportedFont');
        } catch (UnsupportedFont $e) {
            self::assertStringContainsString($reason, $e->getMessage());
            self::assertStringContainsString($path, $e->getMessage());
            self::assertSame($path, $e->path);
        } finally {
            restore_error_handler();
        }
        self::assertSame([], $warnings);
    }

    /** A charstring: the parts joined (operands and operators as encoded bytes). */
    private static function cs(string ...$parts): string
    {
        return implode('', $parts);
    }

    /** A charstring operand from -107 to 107. */
    private static function num(int $v): string
    {
        return chr(($v + 139) & 0xFF);
    }

    /** A dict operand, five bytes whatever its value, so the layout does not depend on it. */
    private static function int(int $v): string
    {
        return "\x1d" . pack('N', $v);
    }

    /** A dict real number from its text: digits, '.', 'E', 'E-' and '-' as nibbles, ended by 0xF. */
    private static function real(string $text): string
    {
        $nibbles = [];
        for ($i = 0; $i < strlen($text); $i++) {
            if ($text[$i] === 'E' && ($text[$i + 1] ?? '') === '-') {
                $nibbles[] = 0xc;
                $i++;
            } else {
                $nibbles[] = match ($text[$i]) {
                    '.' => 0xa,
                    'E' => 0xb,
                    '-' => 0xe,
                    default => (int) $text[$i],
                };
            }
        }
        $nibbles[] = 0xf;
        if (count($nibbles) % 2 === 1) {
            $nibbles[] = 0xf;
        }
        $out = "\x1e";
        for ($i = 0; $i < count($nibbles); $i += 2) {
            $out .= chr((($nibbles[$i] << 4) | $nibbles[$i + 1]) & 0xFF);
        }

        return $out;
    }

    /** @param list<string> $items */
    private static function index(array $items): string
    {
        if ($items === []) {
            return pack('n', 0);
        }
        $out = pack('nC', count($items), 2);
        $at = 1;
        $out .= pack('n', $at);
        foreach ($items as $item) {
            $at += strlen($item);
            $out .= pack('n', $at);
        }

        return $out . implode('', $items);
    }

    /**
     * A CFF table: header, name, top dict, strings, global subroutines, charstrings, a private dict.
     *
     * @param list<string> $charStrings
     * @param list<string> $gsubrs
     * @param list<string> $lsubrs
     * @param ?int $charStringsAt a wrong offset to write, or null to leave the operator out
     */
    private static function cff(array $charStrings, array $gsubrs = [], array $lsubrs = [], string $topExtra = '', ?int $charStringsAt = -1): string
    {
        $head = "\x01\x00\x04\x02" . self::index(['T']);
        $strings = self::index([]);
        $gsub = self::index($gsubrs);
        $topFor = static fn (int $cs, int $privOff, int $privSize): string => ($cs === -2 ? '' : self::int($cs) . "\x11")
            . self::int($privSize) . self::int($privOff) . "\x12" . $topExtra;
        $topLength = strlen(self::index([$topFor(0, 0, 0)]));
        $csOffset = strlen($head) + $topLength + strlen($strings) + strlen($gsub);
        $csIndex = self::index($charStrings);
        $private = $lsubrs === [] ? '' : self::int(strlen(self::int(0)) + 1) . "\x13";
        $privOffset = $csOffset + strlen($csIndex);
        $csWritten = $charStringsAt === null ? -2 : ($charStringsAt === -1 ? $csOffset : $charStringsAt);
        $top = self::index([$topFor($csWritten, $privOffset, strlen($private))]);

        return $head . $top . $strings . $gsub . $csIndex . $private . ($lsubrs === [] ? '' : self::index($lsubrs));
    }

    /**
     * @param list<string> $charStrings
     * @param list<string> $gsubrs
     */
    private static function outlines(array $charStrings, array $gsubrs = []): CffOutlines
    {
        $cff = self::cff($charStrings, $gsubrs);

        return new CffOutlines(new Binary($cff), 0, strlen($cff), 1000, count($charStrings));
    }

    /** @return array<string, string> */
    private static function tables(string $cff, int $unitsPerEm, int $numGlyphs): array
    {
        return [
            'MATH' => str_repeat("\0", 8),
            'head' => str_repeat("\0", 18) . pack('n', $unitsPerEm) . str_repeat("\0", 36),
            'hhea' => str_repeat("\0", 36),
            'maxp' => pack('Nn', 0x00005000, $numGlyphs),
            'hmtx' => str_repeat("\0", 4),
            'cmap' => str_repeat("\0", 4),
            'CFF ' => $cff, // last, so cutting the file short cuts this table
        ];
    }

    private static function cffFont(string $cff, int $unitsPerEm = 1000, int $numGlyphs = 1): string
    {
        return self::sfnt('OTTO', self::tables($cff, $unitsPerEm, $numGlyphs));
    }

    /** @param array<string, string> $tables */
    private static function sfnt(string $tag, array $tables): string
    {
        $n = count($tables);
        $dir = $tag . pack('nnnn', $n, 0, 0, 0);
        $body = '';
        $at = 12 + 16 * $n;
        foreach ($tables as $name => $data) {
            $dir .= $name . pack('NNN', 0, $at + strlen($body), strlen($data));
            $body .= $data;
        }

        return $dir . $body;
    }
}
