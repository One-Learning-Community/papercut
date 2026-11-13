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

    private static function glyphWithText(Box $b, string $text): Glyph
    {
        foreach (self::glyphs($b) as $g) {
            if ($g->text === $text) {
                return $g;
            }
        }
        self::fail('No glyph with text ' . $text);
    }

    public function test_cramped_superscript_lower(): void
    {
        // The same superscript: at top level, then in a fraction's denominator (cramped).
        $top = Math::layout(Math::sup('x', '2'));
        $den = Math::layout(Math::frac('1', Math::sup('x', '2')));

        // The superscript's offset from its base (y points down, so a smaller rise is a larger offset): less rise when cramped.
        $topOffset = self::glyphWithText($top, '2')->y - self::glyphWithText($top, '𝑥')->y;
        $denOffset = self::glyphWithText($den, '2')->y - self::glyphWithText($den, '𝑥')->y;
        self::assertGreaterThan($topOffset, $denOffset);
    }

    public function test_a_row_base_reports_its_last_token_for_kerning(): void
    {
        // MathKern only reads the base's last glyph, so a tall token earlier in the row must not set the heights it is read at.
        $v = Math::layout(Math::row('V'));
        $row = Math::layout(Math::row(Math::frac('1', '2'), 'V'));

        self::assertGreaterThan($v->ascent + 1.0, $row->ascent);
        self::assertEqualsWithDelta($v->ascent, $row->lastAscent, 1e-9);
        self::assertEqualsWithDelta($v->descent, $row->lastDescent, 1e-9);
    }

    public function test_script_glyphs_use_ssty(): void
    {
        $f = Math::stixTwoMath();
        $prime = $f->gid(0x2032);
        $gids = array_map(fn (Glyph $g) => $g->gid, self::glyphs(Math::layout(Math::sup('x', '′'))));

        self::assertContains($f->scriptVariant($prime, 1), $gids);
        self::assertNotContains($prime, $gids);
        // The alternate still reports the character, as far as text extraction goes (x is set in mathematical italic).
        $texts = array_map(fn (Glyph $g) => $g->text, self::glyphs(Math::layout(Math::sup('x', '′'))));
        self::assertSame(['𝑥', '′'], $texts);
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
