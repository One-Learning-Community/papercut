<?php

declare(strict_types=1);

namespace Quoin\Math\Render;

use Quoin\Math\Exception\MissingExtension;
use Quoin\Math\Font\Font;
use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\DisplayItem;
use Quoin\Math\Layout\Glyph;
use Quoin\Math\Layout\Rule;

/**
 * PNG with a transparent background, drawn from the display list with Imagick. Needs ext-imagick, see available().
 *
 * $scale is pixels per point: 2.0 stays sharp on a high-DPI screen when the image is shown at the box's size in points.
 * The image is round((width + 2 * pad) * scale) by round((ascent + descent + 2 * pad) * scale) pixels, at least 1 by 1,
 * with the baseline $pad + ascent below the top edge as in SvgRenderer, see baselineOffset().
 *
 * The formula is drawn at 4 times that size and reduced with a box filter. Rasterizing an SVG with Imagick instead
 * makes the glyphs visibly heavier. The factor drops to 3, 2 or 1 for a large image, so that the drawing never holds
 * more than MAX_PIXELS; an image that is over the limit even at 1 times throws.
 */
final class PngRenderer
{
    /** Pixel limit of the drawing surface, before reduction. */
    public const int MAX_PIXELS = 8_000_000;

    private const int SUPERSAMPLE = 4;

    /** @var array<int, list<array{string, list<float>}>> parsed outline commands by glyph id */
    private array $outlines = [];

    /**
     * $color is an ImageMagick color: #rgb, #rrggbb, rgb(...) or a name such as "navy". SVG's "currentColor" is not one.
     * With Imagick loaded it is checked here.
     *
     * @throws \InvalidArgumentException when ImageMagick does not know the color
     */
    public function __construct(private readonly Font $font, private readonly string $color = '#000000', private readonly float $opacity = 1.0)
    {
        if (self::available()) {
            try {
                new \ImagickPixel($color);
            } catch (\ImagickPixelException $e) {
                throw new \InvalidArgumentException("PNG color \"$color\" is not an ImageMagick color", 0, $e);
            }
        }
    }

    public static function available(): bool
    {
        return extension_loaded('imagick');
    }

    /** Distance from the top of the rendered image to the formula baseline, in pixels (not rounded). */
    public static function baselineOffset(Box $box, float $pad = 0.0, float $scale = 2.0): float
    {
        return ($pad + $box->ascent) * $scale;
    }

    /**
     * @throws MissingExtension when Imagick is not loaded
     * @throws \InvalidArgumentException when $scale is not above zero and finite, or the image would exceed MAX_PIXELS
     */
    public function render(Box $box, float $pad = 0.0, float $scale = 2.0): string
    {
        if (! self::available()) {
            throw MissingExtension::imagick();
        }
        if (! is_finite($scale) || $scale <= 0.0) {
            throw new \InvalidArgumentException('PNG scale must be above zero and finite');
        }
        $wf = round(($box->width + 2 * $pad) * $scale);
        $hf = round(($box->ascent + $box->descent + 2 * $pad) * $scale);
        if (! is_finite($wf) || ! is_finite($hf)) {
            throw new \InvalidArgumentException('PNG size is not finite');
        }
        $w = max(1.0, $wf);
        $h = max(1.0, $hf);
        $k = self::SUPERSAMPLE;
        while ($k > 1 && $w * $k * $h * $k > self::MAX_PIXELS) {
            $k--;
        }
        if ($w * $h > self::MAX_PIXELS) {
            throw new \InvalidArgumentException(sprintf('PNG of %s by %s pixels is over the limit of %d pixels', number_format($w, 0, '.', ''), number_format($h, 0, '.', ''), self::MAX_PIXELS));
        }
        $w = (int) $w;
        $h = (int) $h;
        $f = $scale * $k;
        $ox = $pad * $f;
        $oy = ($pad + $box->ascent) * $f;
        $upem = $this->font->unitsPerEm;

        $draw = new \ImagickDraw();
        $draw->setFillColor(new \ImagickPixel($this->color));
        $draw->setFillOpacity(max(0.0, min(1.0, $this->opacity)));
        $draw->setStrokeOpacity(0.0);
        $draw->setStrokeWidth(0.0);
        $box->each(function (DisplayItem $item, float $dx, float $dy) use ($draw, $f, $ox, $oy, $upem): void {
            if ($item instanceof Rule) {
                $x = $ox + ($dx + $item->x) * $f;
                $y = $oy + ($dy + $item->y) * $f;
                $draw->rectangle($x, $y, $x + $item->w * $f, $y + $item->h * $f);
            } elseif ($item instanceof Glyph) {
                $commands = $this->outline($item->gid);
                if ($commands === []) {
                    return;
                }
                $gx = $ox + ($dx + $item->x) * $f;
                $gy = $oy + ($dy + $item->y) * $f;
                $s = $item->size / $upem * $f;
                $sx = $s * $item->sx;
                $sy = $s * $item->sy;
                $draw->pathStart();
                foreach ($commands as [$c, $n]) {
                    match ($c) {
                        'M' => $draw->pathMoveToAbsolute($gx + $n[0] * $sx, $gy - $n[1] * $sy),
                        'L' => $draw->pathLineToAbsolute($gx + $n[0] * $sx, $gy - $n[1] * $sy),
                        'Q' => $draw->pathCurveToQuadraticBezierAbsolute($gx + $n[0] * $sx, $gy - $n[1] * $sy, $gx + $n[2] * $sx, $gy - $n[3] * $sy),
                        default => $draw->pathClose(),
                    };
                }
                $draw->pathFinish();
            }
        });

        $image = new \Imagick();
        try {
            $image->newImage($w * $k, $h * $k, new \ImagickPixel('transparent'), 'png');
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
            $image->drawImage($draw);
            if ($k > 1) {
                $image->resizeImage($w, $h, \Imagick::FILTER_BOX, 1.0);
            }
            $image->setImageFormat('png');

            return $image->getImageBlob();
        } finally {
            $image->clear();
            $draw->clear();
        }
    }

    /**
     * The glyph's outline as commands in font units, parsed once per glyph id.
     *
     * @return list<array{string, list<float>}>
     */
    private function outline(int $gid): array
    {
        if (isset($this->outlines[$gid])) {
            return $this->outlines[$gid];
        }
        $commands = [];
        $d = $this->font->outlinePath($gid);
        if ($d !== '' && preg_match_all('/([MLQZ])([^MLQZ]*)/', $d, $m, PREG_SET_ORDER) > 0) {
            foreach ($m as [, $c, $args]) {
                $args = trim($args);
                $commands[] = [$c, $args === '' ? [] : array_map('floatval', preg_split('/[\s,]+/', $args) ?: [])];
            }
        }

        return $this->outlines[$gid] = $commands;
    }
}
