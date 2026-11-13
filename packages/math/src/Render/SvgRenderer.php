<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

use Papercut\Math\Font\TrueTypeFont;
use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;

/**
 * Standalone SVG. Units are points (1 SVG user unit = 1 pt); the SVG's width/height are the box
 * plus $pad on every side. $opacity below 1 is written as fill-opacity. The baseline sits $pad + ascent below the top edge, see baselineOffset().
 */
final class SvgRenderer
{
    public function __construct(private readonly TrueTypeFont $font, private readonly string $color = '#000000', private readonly float $opacity = 1.0)
    {
    }

    /** Distance from the top of the rendered SVG to the formula baseline, in points. */
    public static function baselineOffset(Box $box, float $pad = 0.0): float
    {
        return $pad + $box->ascent;
    }

    public function render(Box $box, float $pad = 0.0): string
    {
        $w = $box->width + 2 * $pad;
        $h = $box->ascent + $box->descent + 2 * $pad;
        $ox = $pad;
        $oy = $pad + $box->ascent; // baseline in SVG coordinates
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 %s %s">',
            self::n($w), self::n($h), self::n($w), self::n($h),
        );
        $svg .= $this->group();
        foreach ($box->items as $item) {
            if ($item instanceof Glyph) {
                $d = $this->glyphPath($item, $ox, $oy);
                if ($d !== '') {
                    $svg .= '<path d="' . $d . '"/>';
                }
            } elseif ($item instanceof Rule) {
                $svg .= sprintf('<rect x="%s" y="%s" width="%s" height="%s"/>', self::n($ox + $item->x), self::n($oy + $item->y), self::n($item->w), self::n($item->h));
            }
        }

        return $svg . '</g></svg>';
    }

    /**
     * A standalone SVG holding only $glyphs, one <path> each, for glyphs a backend cannot draw as text
     * (stretched variants, assembly pieces). The glyphs are in the coordinate space of $box: points, baseline at y = 0,
     * y down. The viewBox is that box, "0 -ascent width ascent+descent", and width/height are the box size in pt,
     * so placing the SVG with its top-left at the box's top-left reproduces the glyph positions exactly.
     * The root has overflow="visible", so ink outside the box metrics (a deep radical, a tall accent) is not clipped.
     *
     * @param list<Glyph> $glyphs
     */
    public function glyphPaths(array $glyphs, Box $box): string
    {
        $w = self::n($box->width);
        $h = self::n($box->ascent + $box->descent);
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 %s %s %s" overflow="visible">',
            $w, $h, self::n(-$box->ascent), $w, $h,
        );
        $svg .= $this->group();
        foreach ($glyphs as $g) {
            $d = $this->glyphPath($g, 0.0, 0.0);
            if ($d !== '') {
                $svg .= '<path d="' . $d . '"/>';
            }
        }

        return $svg . '</g></svg>';
    }

    private function group(): string
    {
        return $this->opacity < 1.0
            ? sprintf('<g fill="%s" fill-opacity="%s">', $this->color, self::n($this->opacity))
            : sprintf('<g fill="%s">', $this->color);
    }

    private function glyphPath(Glyph $g, float $ox, float $oy): string
    {
        $s = $g->size / $this->font->unitsPerEm;
        $x0 = $ox + $g->x;
        $y0 = $oy + $g->y;
        $d = '';
        foreach ($this->font->contours($g->gid) as $pts) {
            $n = count($pts);
            if ($n < 2) {
                continue;
            }
            // Find a start point on the curve; if none, start at the midpoint of the first two off-curve points.
            $startIdx = null;
            foreach ($pts as $i => $p) {
                if ($p[2]) {
                    $startIdx = $i;
                    break;
                }
            }
            if ($startIdx === null) {
                $a = $pts[0];
                $b = $pts[1];
                $start = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
                $seq = array_merge(array_slice($pts, 1), [$pts[0]]);
            } else {
                $start = [$pts[$startIdx][0], $pts[$startIdx][1]];
                $seq = array_merge(array_slice($pts, $startIdx + 1), array_slice($pts, 0, $startIdx));
            }
            $f = fn (float $x, float $y): string => self::n($x0 + $x * $s) . ' ' . self::n($y0 - $y * $s);
            $d .= 'M' . $f($start[0], $start[1]);
            $ctrl = null;
            foreach ($seq as [$x, $y, $on]) {
                if ($on) {
                    $d .= $ctrl === null ? 'L' . $f($x, $y) : 'Q' . $f($ctrl[0], $ctrl[1]) . ' ' . $f($x, $y);
                    $ctrl = null;
                } elseif ($ctrl === null) {
                    $ctrl = [$x, $y];
                } else {
                    $mx = ($ctrl[0] + $x) / 2;
                    $my = ($ctrl[1] + $y) / 2;
                    $d .= 'Q' . $f($ctrl[0], $ctrl[1]) . ' ' . $f($mx, $my);
                    $ctrl = [$x, $y];
                }
            }
            if ($ctrl !== null) {
                $d .= 'Q' . $f($ctrl[0], $ctrl[1]) . ' ' . $f($start[0], $start[1]);
            }
            $d .= 'Z';
        }

        return $d;
    }

    private static function n(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
