<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Font;

use Papercut\Math\Font\Binary;
use Papercut\Math\Font\Gsub;
use Papercut\Math\Math;
use PHPUnit\Framework\TestCase;

final class GsubTest extends TestCase
{
    public function test_ssty_alternate_for_prime(): void
    {
        $f = Math::stixTwoMath();
        $prime = $f->gid(0x2032);

        self::assertNotSame($prime, $f->scriptVariant($prime, 1));
        self::assertNotSame($f->scriptVariant($prime, 1), $f->scriptVariant($prime, 2));
        self::assertSame($prime, $f->scriptVariant($prime, 0));
    }

    public function test_glyph_without_alternate_is_unchanged(): void
    {
        $f = Math::stixTwoMath();
        $space = $f->gid(0x20);

        self::assertSame($space, $f->scriptVariant($space, 1));
    }

    public function test_math_kern_read(): void
    {
        $m = Math::stixTwoMath()->math();
        $f = Math::stixTwoMath();
        $italicF = $f->gid(0x1D453);
        $italicT = $f->gid(0x1D447);

        // Steps by height: the italic f tucks a subscript in under its hook.
        self::assertSame(-199.0, $m->kern($italicF, 'bottomRight', 0));
        self::assertSame(16.0, $m->kern($italicF, 'bottomRight', 900));
        // Italic T leans a superscript out; a glyph without a kern table gives 0.
        self::assertSame(30.0, $m->kern($italicT, 'topRight', 300));
        self::assertSame(0.0, $m->kern($italicT, 'topLeft', 300));
        self::assertSame(0.0, $m->kern($f->gid(0x1D465), 'topRight', 300));
    }

    /** A GSUB table with one ssty feature and one type 3 lookup: glyph 5 has the alternate 9; coverage lists glyphs 5 and 6. */
    private static function alternateTable(int $setCount, int $alternateCount): string
    {
        return pack('N', 0x00010000) . pack('nnn', 0, 10, 24)   // header
            . pack('n', 1) . 'ssty' . pack('n', 8)                // feature list: one record
            . pack('nnn', 0, 1, 0)                                // the feature: lookup 0
            . pack('nn', 1, 4)                                    // lookup list: one lookup
            . pack('nnnn', 3, 0, 1, 8)                            // lookup: type 3, one subtable
            . pack('nnnnn', 1, 10, $setCount, 18, 0xFFFF)         // subtable: format, coverage, set count, set offsets
            . pack('nnnn', 1, 2, 5, 6)                            // coverage: glyphs 5 and 6
            . pack('nn', $alternateCount, 9);                     // the set of glyph 5
    }

    public function test_alternate_set_offsets_beyond_the_set_count_are_ignored(): void
    {
        $data = self::alternateTable(1, 1);
        $gsub = new Gsub(new Binary($data), 0);

        self::assertSame(9, $gsub->variant(5, 1));
        self::assertSame(6, $gsub->variant(6, 1));
    }

    public function test_an_alternate_set_longer_than_the_table_is_ignored(): void
    {
        $gsub = new Gsub(new Binary(self::alternateTable(2, 0x7FFF)), 0);

        self::assertSame(5, $gsub->variant(5, 1));
        self::assertSame(6, $gsub->variant(6, 1));
    }
}
