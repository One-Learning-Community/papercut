<?php

declare(strict_types=1);

namespace Quoin\Math\Tests;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\MathPng;
use Quoin\Math\MathRender;
use Quoin\Math\Render\PngRenderer;

final class MathPngTest extends TestCase
{
    public function test_png_matches_the_renderer(): void
    {
        self::requireImagick();
        $expected = (new PngRenderer(Math::stixTwoMath()))->render(Math::layout(Math::tex('E = mc^2'), 16.0, false), 0.0, 2.0);

        self::assertSame($expected, MathRender::fromTex('E = mc^2')->png()->bytes);
    }

    public function test_display_png_is_laid_out_in_display_style(): void
    {
        self::requireImagick();
        $expected = (new PngRenderer(Math::stixTwoMath(), '#336699'))->render(Math::layout(Math::tex('\sum_{i=1}^n i'), 20.0, true), 1.0, 3.0);

        self::assertSame($expected, MathRender::fromTex('\sum_{i=1}^n i')->display()->png(size: 20, scale: 3, color: '#336699', pad: 1)->bytes);
    }

    public function test_png_size_and_baseline(): void
    {
        self::requireImagick();
        $png = MathRender::fromTex('x_1')->png(scale: 2.0, pad: 1.0);
        $size = getimagesizefromstring($png->bytes);
        self::assertIsArray($size);
        $box = Math::layout(Math::tex('x_1'), 16.0, false);

        self::assertSame((float) $size[0], $png->width * 2);
        self::assertSame((float) $size[1], $png->height * 2);
        self::assertSame(-round($png->height - PngRenderer::baselineOffset($box, 1.0, 2.0) / 2.0, 1), $png->verticalAlign);
        self::assertLessThan(0.0, $png->verticalAlign);
    }

    public function test_png_throws_what_the_renderer_throws(): void
    {
        self::requireImagick();
        $r = MathRender::fromTex('x');

        try {
            $r->png(color: 'not-a-color');
            self::fail('A bad color must throw');
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        $r->png(scale: 0.0);
    }

    public function test_png_alt_defaults_to_the_formula_text(): void
    {
        self::requireImagick();
        $r = MathRender::fromTex('a < b');

        self::assertStringContainsString(' alt="' . htmlspecialchars($r->text(), ENT_QUOTES | ENT_HTML5) . '"', $r->png()->img('x.png'));
    }

    public function test_data_uri(): void
    {
        $png = new MathPng("\x89PNG", 10.5, 8.0, -2.5, 'a < b');

        self::assertSame('data:image/png;base64,' . base64_encode("\x89PNG"), $png->dataUri());
    }

    public function test_img_markup(): void
    {
        $png = new MathPng("\x89PNG", 10.5, 8.0, -2.5, 'a < b');

        self::assertSame('<img src="cid:f1" width="10.5" height="8" alt="a &lt; b" style="vertical-align:-2.5px">', $png->img('cid:f1'));
        self::assertSame('<img src="x&quot; onload=&quot;y" width="10.5" height="8" alt="q" style="vertical-align:-2.5px">', $png->img('x" onload="y', 'q'));
    }

    private static function requireImagick(): void
    {
        if (! PngRenderer::available()) {
            self::markTestSkipped('Imagick is not loaded');
        }
    }
}
