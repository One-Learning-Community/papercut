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

    /** @return list<string> */
    private static function pathData(string $svg): array
    {
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        $out = [];
        foreach ($doc->getElementsByTagName('path') as $path) {
            $out[] = $path->getAttribute('d');
        }

        return $out;
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
