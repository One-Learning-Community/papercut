<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use Papercut\Math\Exception\MissingExtension;
use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Math;
use Papercut\Math\Render\PngRenderer;
use Papercut\Math\Render\SvgRenderer;
use PHPUnit\Framework\TestCase;

final class PngRendererTest extends TestCase
{
    /** RMSE (0 to 1) against librsvg on the same formula; measured 0.021 to 0.027 at scale 2; Imagick rasterizing the SVG is visibly heavier. */
    private const float FIDELITY_RMSE = 0.06;

    protected function setUp(): void
    {
        if (! PngRenderer::available()) {
            self::markTestSkipped('ext-imagick is not loaded');
        }
    }

    public function test_png_signature_and_exact_dimensions(): void
    {
        $box = new Box(30.0, 10.0, 4.0);
        $png = (new PngRenderer(Math::stixTwoMath()))->render($box, 2.0, 2.0);

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        [$w, $h] = self::dims($png);
        self::assertSame(68, $w); // (30 + 4) * 2
        self::assertSame(36, $h); // (10 + 4 + 4) * 2
    }

    public function test_dimensions_round_and_are_at_least_one_pixel(): void
    {
        $r = new PngRenderer(Math::stixTwoMath());
        self::assertSame([15, 10], self::dims($r->render(new Box(5.1, 2.0, 0.9), 0.5, 2.5)));
        self::assertSame([1, 1], self::dims($r->render(new Box(0.0, 0.0, 0.0))));
    }

    public function test_corners_are_transparent_and_ink_is_dark(): void
    {
        $box = new Box(20.0, 10.0, 0.0);
        $box->add(new Rule(4.0, -6.0, 12.0, 3.0));
        $im = self::image((new PngRenderer(Math::stixTwoMath()))->render($box, 3.0, 2.0));

        foreach ([[0, 0], [$im->getImageWidth() - 1, 0], [0, $im->getImageHeight() - 1], [$im->getImageWidth() - 1, $im->getImageHeight() - 1]] as [$x, $y]) {
            self::assertSame(0.0, $im->getImagePixelColor($x, $y)->getColorValue(\Imagick::COLOR_ALPHA), "corner $x,$y");
        }
        // Rule centre: x = pad + 4 + 6 = 13 pt, y = pad + ascent - 6 + 1.5 = 8.5 pt.
        $c = $im->getImagePixelColor(26, 17);
        self::assertSame(1.0, $c->getColorValue(\Imagick::COLOR_ALPHA));
        self::assertLessThan(0.05, $c->getColorValue(\Imagick::COLOR_RED));
    }

    public function test_baseline_offset(): void
    {
        $box = new Box(10.0, 8.0, 3.0);
        self::assertSame(20.0, PngRenderer::baselineOffset($box, 2.0, 2.0));
        self::assertSame(16.0, PngRenderer::baselineOffset($box));
        self::assertSame(12.0, PngRenderer::baselineOffset($box, 0.0, 1.5));

        // A rule that sits on the baseline starts at that row.
        $box->add(new Rule(0.0, 0.0, 10.0, 2.0));
        $im = self::image((new PngRenderer(Math::stixTwoMath()))->render($box, 2.0, 2.0));
        $row = (int) PngRenderer::baselineOffset($box, 2.0, 2.0);
        self::assertSame(0.0, $im->getImagePixelColor(14, $row - 1)->getColorValue(\Imagick::COLOR_ALPHA));
        self::assertSame(1.0, $im->getImagePixelColor(14, $row)->getColorValue(\Imagick::COLOR_ALPHA));
    }

    public function test_colour_and_opacity(): void
    {
        $box = new Box(10.0, 5.0, 0.0);
        $box->add(new Rule(0.0, -5.0, 10.0, 5.0));
        $font = Math::stixTwoMath();

        $red = self::image((new PngRenderer($font, '#ff0000'))->render($box))->getImagePixelColor(10, 5);
        self::assertSame(1.0, $red->getColorValue(\Imagick::COLOR_RED));
        self::assertSame(0.0, $red->getColorValue(\Imagick::COLOR_GREEN));
        self::assertSame(1.0, $red->getColorValue(\Imagick::COLOR_ALPHA));

        $half = self::image((new PngRenderer($font, '#ff0000', 0.5))->render($box))->getImagePixelColor(10, 5);
        self::assertEqualsWithDelta(0.5, $half->getColorValue(\Imagick::COLOR_ALPHA), 0.01);
        self::assertSame(1.0, $half->getColorValue(\Imagick::COLOR_RED));
    }

    public function test_a_scaled_glyph_draws_taller_or_wider(): void
    {
        $font = Math::stixTwoMath();
        $gid = $font->gid(0x7C);
        $inkBox = static function (float $sx, float $sy) use ($font, $gid): array {
            $box = new Box(100.0, 80.0, 40.0);
            $box->add(new Glyph($gid, null, 10.0, 0.0, 48.0, $sx, $sy));
            $im = self::image((new PngRenderer($font))->render($box, 0.0, 2.0));
            $im->trimImage(0.0);
            $page = $im->getImagePage();

            return [$im->getImageWidth(), $im->getImageHeight(), $page['x'], $page['y']];
        };
        [$w1, $h1] = $inkBox(1.0, 1.0);
        [$w2, $h2] = $inkBox(1.0, 2.0);
        [$w3, $h3] = $inkBox(3.0, 1.0);

        self::assertEqualsWithDelta(2 * $h1, $h2, 3);
        self::assertEqualsWithDelta($w1, $w2, 1);
        self::assertEqualsWithDelta(3 * $w1, $w3, 3);
        self::assertEqualsWithDelta($h1, $h3, 1);
    }

    public function test_an_empty_glyph_box_is_blank(): void
    {
        $font = Math::stixTwoMath();
        $box = new Box(10.0, 8.0, 2.0);
        $box->add(new Glyph($font->gid(0x20), null, 0.0, 0.0, 12.0));
        $im = self::image((new PngRenderer($font))->render($box, 1.0));
        $max = $im->getImageChannelRange(\Imagick::CHANNEL_ALPHA)['maxima'];

        self::assertSame([24, 24], [$im->getImageWidth(), $im->getImageHeight()]);
        self::assertSame(0.0, (float) $max);
    }

    public function test_formula_draws_ink(): void
    {
        $box = Math::layout(Math::frac('a', Math::sqrt('b')), 24.0);
        $im = self::image((new PngRenderer(Math::stixTwoMath()))->render($box, 2.0));
        $im->trimImage(0.0);

        self::assertGreaterThan(10, $im->getImageWidth());
        self::assertLessThanOrEqual((int) round(($box->width + 4) * 2), $im->getImageWidth());
    }

    /** @return iterable<string, array{float}> */
    public static function badScales(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-1.0];
        yield 'nan' => [NAN];
        yield 'infinite' => [INF];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badScales')]
    public function test_an_invalid_scale_throws(float $scale): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PngRenderer(Math::stixTwoMath()))->render(new Box(10.0, 5.0, 1.0), 0.0, $scale);
    }

    public function test_an_unknown_colour_throws_in_the_constructor(): void
    {
        foreach (['currentColor', 'not-a-colour'] as $color) {
            try {
                new PngRenderer(Math::stixTwoMath(), $color);
                self::fail("no exception for \"$color\"");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString($color, $e->getMessage());
            }
        }
        new PngRenderer(Math::stixTwoMath(), 'navy');
        new PngRenderer(Math::stixTwoMath(), 'rgb(10, 20, 30)');
        $this->addToAssertionCount(1);
    }

    public function test_an_image_over_the_pixel_cap_throws(): void
    {
        $r = new PngRenderer(Math::stixTwoMath());
        foreach ([[100000.0, 1.0], [10.0, 1e9], [1e300, 1e300]] as [$w, $scale]) {
            try {
                $r->render(new Box($w, $w, 2.0), 0.0, $scale);
                self::fail("no exception for width $w, scale $scale");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_a_large_image_lowers_the_supersample_factor_and_still_renders(): void
    {
        // 1400 x 1400 px is 2M px final, 31M at 4x: drawn at 2x (7.8M).
        $box = new Box(1400.0, 1000.0, 400.0);
        $box->add(new Rule(0.0, -10.0, 1400.0, 10.0));
        $png = (new PngRenderer(Math::stixTwoMath()))->render($box, 0.0, 1.0);

        self::assertSame([1400, 1400], self::dims($png));
    }

    public function test_fidelity_against_librsvg(): void
    {
        $rsvg = trim((string) shell_exec('command -v rsvg-convert 2>/dev/null'));
        if ($rsvg === '') {
            self::markTestSkipped('rsvg-convert is not installed');
        }
        $font = Math::stixTwoMath();
        $formulas = [
            '\frac{-b \pm \sqrt{b^2 - 4ac}}{2a}',
            '\int_0^\infty e^{-x^2}\,dx = \frac{\sqrt{\pi}}{2}',
            '\sum_{k=1}^{n} k^2 = \frac{n(n+1)(2n+1)}{6}',
        ];
        $dir = sys_get_temp_dir() . '/papercut-png-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            foreach ($formulas as $i => $tex) {
                $box = Math::layout(Math::tex($tex), 24.0);
                $svgFile = "$dir/f$i.svg";
                $refFile = "$dir/f$i.png";
                file_put_contents($svgFile, (new SvgRenderer($font))->render($box, 2.0));
                exec(sprintf('%s -z 2 -b white %s -o %s 2>&1', escapeshellarg($rsvg), escapeshellarg($svgFile), escapeshellarg($refFile)), $out, $code);
                self::assertSame(0, $code, implode("\n", $out));

                $ref = new \Imagick($refFile);
                $mine = self::image((new PngRenderer($font))->render($box, 2.0, 2.0));
                $mine->setImageBackgroundColor('white');
                $mine = $mine->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                // librsvg rounds the size up, we round to nearest: compare on the common area (at most a pixel apart).
                self::assertEqualsWithDelta($ref->getImageWidth(), $mine->getImageWidth(), 1, $tex);
                self::assertEqualsWithDelta($ref->getImageHeight(), $mine->getImageHeight(), 1, $tex);
                $cw = min($ref->getImageWidth(), $mine->getImageWidth());
                $ch = min($ref->getImageHeight(), $mine->getImageHeight());
                $ref->cropImage($cw, $ch, 0, 0);
                $mine->cropImage($cw, $ch, 0, 0);
                $rmse = $mine->compareImages($ref, \Imagick::METRIC_ROOTMEANSQUAREDERROR)[1];
                self::assertLessThan(self::FIDELITY_RMSE, $rmse, "$tex: RMSE $rmse");
            }
        } finally {
            array_map('unlink', glob("$dir/*") ?: []);
            rmdir($dir);
        }
    }

    public function test_without_imagick_render_throws_a_clear_exception(): void
    {
        // The class of the exception and its message are what callers rely on.
        $e = MissingExtension::imagick();
        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('imagick', $e->extension);
        self::assertStringContainsString('imagick', $e->getMessage());
    }

    /** @return array{int, int} */
    private static function dims(string $png): array
    {
        $im = self::image($png);

        return [$im->getImageWidth(), $im->getImageHeight()];
    }

    private static function image(string $png): \Imagick
    {
        $im = new \Imagick();
        $im->readImageBlob($png);

        return $im;
    }
}
