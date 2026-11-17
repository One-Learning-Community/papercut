<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

use Papercut\Math\Font\TrueTypeFont;
use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\DisplayItem;
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
        $box->each(function (DisplayItem $item, float $dx, float $dy) use (&$svg, $ox, $oy): void {
            if ($item instanceof Glyph) {
                $svg .= $this->glyphPath($item, $ox + $dx, $oy + $dy);
            } elseif ($item instanceof Rule) {
                $svg .= sprintf('<rect x="%s" y="%s" width="%s" height="%s"/>', self::n($ox + $dx + $item->x), self::n($oy + $dy + $item->y), self::n($item->w), self::n($item->h));
            }
        });

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
            $svg .= $this->glyphPath($g, 0.0, 0.0);
        }

        return $svg . '</g></svg>';
    }

    private function group(): string
    {
        return $this->opacity < 1.0
            ? sprintf('<g fill="%s" fill-opacity="%s">', $this->color, self::n($this->opacity))
            : sprintf('<g fill="%s">', $this->color);
    }

    /**
     * The glyph as one <path>: its outline in font units (built once per glyph by the font), placed and scaled by a transform
     * that flips y; empty for a glyph with no outline.
     */
    private function glyphPath(Glyph $g, float $ox, float $oy): string
    {
        $d = $this->font->outlinePath($g->gid);
        if ($d === '') {
            return '';
        }
        $s = $g->size / $this->font->unitsPerEm;

        return '<path transform="matrix(' . self::scale($s * $g->sx) . ' 0 0 ' . self::scale(-$s * $g->sy) . ' ' . self::n($ox + $g->x) . ' ' . self::n($oy + $g->y) . ')" d="' . $d . '"/>';
    }

    /** A scale factor, to seven decimals: on a glyph 3,000 units tall that is within 0.001 pt, as the positions are. */
    private static function scale(float $v): string
    {
        $r = round($v, 7);

        return $r == 0.0 ? '0' : (string) $r;
    }

    private static function n(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
