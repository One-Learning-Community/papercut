<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Font;

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
}
