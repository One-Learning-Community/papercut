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
    public function testGlyphPathsOnlyGivenGlyphs(): void
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

    public function testRenderParses(): void
    {
        $box = Math::layout(Math::frac('a', Math::sqrt('b')), 12.0);
        $svg = (new SvgRenderer(Math::stixTwoMath()))->render($box, 1.0);
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        self::assertGreaterThan(0, $doc->getElementsByTagName('path')->length);
    }

    public function testOpacityBecomesFillOpacity(): void
    {
        $font = Math::stixTwoMath();
        $box = new Box(20.0, 10.0, 4.0);
        $glyphs = [new Glyph($font->gid(0x78), 'x', 0.0, 0.0, 12.0)];
        self::assertStringContainsString('fill="#ff0000" fill-opacity="0.5"', (new SvgRenderer($font, '#ff0000', 0.5))->glyphPaths($glyphs, $box));
        self::assertStringNotContainsString('fill-opacity', (new SvgRenderer($font, '#ff0000'))->glyphPaths($glyphs, $box));
    }
}
