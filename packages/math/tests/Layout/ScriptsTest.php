<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Math;
use PHPUnit\Framework\TestCase;

final class ScriptsTest extends TestCase
{
    /** @return list<Glyph> */
    private static function glyphs(Box $b): array
    {
        return array_values(array_filter($b->items, fn ($i) => $i instanceof Glyph));
    }

    public function test_cramped_superscript_lower(): void
    {
        // The same superscript: at top level, then in a fraction's denominator (cramped).
        $top = Math::layout(Math::sup('x', '2'));
        $den = Math::layout(Math::frac('1', Math::sup('x', '2')));

        $t = self::glyphs($top);
        $d = self::glyphs($den);
        $n = count($d);
        // How far the superscript sits above its base: less when cramped.
        self::assertGreaterThan($t[1]->y - $t[0]->y, $d[$n - 1]->y - $d[$n - 2]->y);
    }

    public function test_script_glyphs_use_ssty(): void
    {
        $f = Math::stixTwoMath();
        $prime = $f->gid(0x2032);
        $gids = array_map(fn (Glyph $g) => $g->gid, self::glyphs(Math::layout(Math::sup('x', '′'))));

        self::assertContains($f->scriptVariant($prime, 1), $gids);
        self::assertNotContains($prime, $gids);
        // The alternate is still the source character as far as text extraction goes.
        $texts = array_map(fn (Glyph $g) => $g->text, self::glyphs(Math::layout(Math::sup('x', '′'))));
        self::assertSame(['x', '′'], $texts);
    }

    public function test_script_alternates_can_be_disabled(): void
    {
        $f = Math::stixTwoMath();
        $prime = $f->gid(0x2032);
        $box = Math::layout(Math::sup('x', '′'), 12.0, true, null, false);
        $gids = array_map(fn (Glyph $g) => $g->gid, self::glyphs($box));

        self::assertContains($prime, $gids);
        self::assertNotContains($f->scriptVariant($prime, 1), $gids);
    }

    public function test_subscript_kern_uses_the_drawn_alternate(): void
    {
        // The ssty alternate of x carries its own topLeft kern (-43), which pulls the subscript left.
        $with = self::glyphs(Math::layout(Math::sub('x', 'x')));
        $without = self::glyphs(Math::layout(Math::sub('x', 'x'), 12.0, true, null, false));

        self::assertLessThan($without[1]->x, $with[1]->x);
    }

    public function test_subscript_kerns_in_under_a_hooked_base(): void
    {
        // Italic f has a bottomRight kern: the subscript starts left of the plain advance.
        $f = Math::stixTwoMath();
        $box = Math::layout(Math::sub('f', 'i'));
        $g = self::glyphs($box);
        $baseW = $f->advance($f->gid(0x1D453)) * 12.0 / $f->unitsPerEm;

        self::assertLessThan($baseW, $g[1]->x);
    }
}
