<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use PHPUnit\Framework\TestCase;
use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Math;
use Papercut\Math\Render\SvgRenderer;

final class SvgRendererTest extends TestCase
{
    public function test_glyph_paths_only_given_glyphs(): void
    {
        $font = Math::stixTwoMath();
        $box = new Box(20.0, 10.0, 4.0);
        $glyphs = [new Glyph($font->gid(0x78), 'x', 0.0, 0.0, 12.0), new Glyph($font->gid(0x79), null, 8.0, 0.0, 12.0)];
        $svg = (new SvgRenderer($font))->glyphPaths($glyphs, $box);

        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        self::assertSame(2, $doc->getElementsByTagName('path')->length);
        self::assertSame(0, $doc->getElementsByTagName('rect')->length);
        $root = $doc->documentElement;
        self::assertSame('http://www.w3.org/2000/svg', $root?->namespaceURI);
        self::assertSame('0 -10 20 14', $root->getAttribute('viewBox'));
        self::assertSame('visible', $root->getAttribute('overflow'));
        self::assertSame('20', $root->getAttribute('width'));
        self::assertSame('14', $root->getAttribute('height'));
    }

    public function test_a_scaled_glyph_is_drawn_stretched_along_its_axis(): void
    {
        $font = Math::stixTwoMath();
        $svg = static function (Glyph $g) use ($font): string {
            return (new SvgRenderer($font))->glyphPaths([$g], new Box(20.0, 10.0, 4.0));
        };
        $extent = static function (string $s, int $axis): float {
            $v = array_map(static fn (array $p): float => $p[$axis], self::points($s));
            if ($v === []) {
                self::fail('no points');
            }

            return max($v) - min($v);
        };
        $gid = $font->gid(0x7C);
        $plain = $svg(new Glyph($gid, null, 0.0, 0.0, 12.0));
        $tall = $svg(new Glyph($gid, null, 0.0, 0.0, 12.0, 1.0, 3.0));
        $wide = $svg(new Glyph($gid, null, 0.0, 0.0, 12.0, 3.0, 1.0));
        self::assertEqualsWithDelta(3 * $extent($plain, 1), $extent($tall, 1), 0.01);
        self::assertEqualsWithDelta($extent($plain, 0), $extent($tall, 0), 0.01);
        self::assertEqualsWithDelta(3 * $extent($plain, 0), $extent($wide, 0), 0.01);
        self::assertEqualsWithDelta($extent($plain, 1), $extent($wide, 1), 0.01);
    }

    public function test_render_parses(): void
    {
        $box = Math::layout(Math::frac('a', Math::sqrt('b')), 12.0);
        $svg = (new SvgRenderer(Math::stixTwoMath()))->render($box, 1.0);
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        self::assertGreaterThan(0, $doc->getElementsByTagName('path')->length);
    }

    public function test_opacity_becomes_fill_opacity(): void
    {
        $font = Math::stixTwoMath();
        $box = new Box(20.0, 10.0, 4.0);
        $glyphs = [new Glyph($font->gid(0x78), 'x', 0.0, 0.0, 12.0)];
        self::assertStringContainsString('fill="#ff0000" fill-opacity="0.5"', (new SvgRenderer($font, '#ff0000', 0.5))->glyphPaths($glyphs, $box));
        self::assertStringNotContainsString('fill-opacity', (new SvgRenderer($font, '#ff0000'))->glyphPaths($glyphs, $box));
    }

    public function test_color_is_escaped(): void
    {
        $font = Math::stixTwoMath();
        $svg = (new SvgRenderer($font, '"/><script>x</script><g fill="red'))->render(Math::layout(Math::row('x'), 12.0), 0.0);
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        self::assertSame(0, $doc->getElementsByTagName('script')->length);
        self::assertSame('"/><script>x</script><g fill="red', $doc->getElementsByTagName('g')->item(0)?->getAttribute('fill'));
    }

    /** @return list<string> each path's transform and data */
    private static function pathData(string $svg): array
    {
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        $out = [];
        foreach ($doc->getElementsByTagName('path') as $path) {
            $out[] = $path->getAttribute('transform') . ' ' . $path->getAttribute('d');
        }

        return $out;
    }

    /**
     * Every point of every path in $svg, in SVG coordinates (the path's transform applied).
     *
     * @return list<array{float, float}>
     */
    private static function points(string $svg): array
    {
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        $out = [];
        foreach ($doc->getElementsByTagName('path') as $path) {
            if (preg_match('/^matrix\(([^)]+)\)$/', $path->getAttribute('transform'), $m) !== 1) {
                self::fail('a path without a matrix transform');
            }
            [$a, $b, $c, $d, $e, $f] = array_map('floatval', explode(' ', $m[1]));
            preg_match_all('/(-?[\d.]+) (-?[\d.]+)/', $path->getAttribute('d'), $pts, PREG_SET_ORDER);
            foreach ($pts as $p) {
                $x = (float) $p[1];
                $y = (float) $p[2];
                $out[] = [$a * $x + $c * $y + $e, $b * $x + $d * $y + $f];
            }
        }

        return $out;
    }

    public function test_outline_is_built_once_and_placed_by_a_transform(): void
    {
        $font = Math::stixTwoMath();
        $gid = $font->gid(0x78);
        $glyphs = [new Glyph($gid, 'x', 0.0, 0.0, 12.0), new Glyph($gid, 'x', 10.0, -2.0, 6.0)];
        $paths = self::pathData((new SvgRenderer($font))->glyphPaths($glyphs, new Box(20.0, 10.0, 4.0)));

        self::assertSame(['matrix(0.012 0 0 -0.012 0 0) ' . $font->outlinePath($gid), 'matrix(0.006 0 0 -0.006 10 -2) ' . $font->outlinePath($gid)], $paths);
        // The outline is in font units, y up: the x sits on the baseline, so its points reach up to about 0.45 em.
        $ys = array_map(static fn (array $p): float => $p[1], self::points((new SvgRenderer($font))->glyphPaths([$glyphs[0]], new Box(20.0, 10.0, 4.0))));
        if ($ys === []) {
            self::fail('no points');
        }
        self::assertEqualsWithDelta(0.0, max($ys), 0.2);
        self::assertLessThan(-4.0, min($ys));
    }

    public function test_an_empty_glyph_writes_no_path(): void
    {
        $font = Math::stixTwoMath();
        $space = $font->gid(0x20);
        self::assertSame('', $font->outlinePath($space));
        self::assertSame([], self::pathData((new SvgRenderer($font))->glyphPaths([new Glyph($space, ' ', 0.0, 0.0, 12.0)], new Box(20.0, 10.0, 4.0))));
    }

    public function test_glyph_paths_match_render_shifted_by_the_ascent(): void
    {
        $font = Math::stixTwoMath();
        $renderer = new SvgRenderer($font);
        $box = Math::layout(Math::frac('a', Math::sqrt('b')), 12.0);
        $glyphs = array_values(array_filter($box->items, static fn ($i): bool => $i instanceof Glyph));
        self::assertNotSame([], $glyphs);

        // render() puts the baseline at y = ascent; glyphPaths() keeps it at y = 0 under a viewBox that starts at -ascent.
        $shifted = array_map(static fn (Glyph $g): Glyph => new Glyph($g->gid, $g->text, $g->x, $g->y + $box->ascent, $g->size), $glyphs);

        self::assertSame(self::pathData($renderer->render($box, 0.0)), self::pathData($renderer->glyphPaths($shifted, $box)));
        self::assertCount(count($glyphs), self::pathData($renderer->glyphPaths($glyphs, $box)));
    }
}
