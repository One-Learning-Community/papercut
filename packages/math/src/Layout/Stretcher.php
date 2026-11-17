<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

use Papercut\Math\Font\TrueTypeFont;

/**
 * Picks a size variant, or builds a GlyphAssembly, for a stretchy glyph, vertically (fences, radicals) or
 * horizontally (accents, arrows, braces).
 * Results are in font units, y up, with the glyph/assembly un-shifted; callers place them.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class Stretcher
{
    /** The most a glyph is stretched, in em of the font: nested fences otherwise double each level and grow without bound. */
    public const float MAX_STRETCH_EM = 100.0;

    /**
     * @return array{pieces: list<array{int,float,float}>, ymin: float, ymax: float, advance: float}
     *         pieces: [gid, y origin offset in font units, up, scale along y (assembly pieces only; 1.0 unless a run of one straight extender is drawn once)]. Assemblies span ymin = 0 .. ymax = size.
     */
    public static function vertical(TrueTypeFont $font, int $gid, float $requiredUnits): array
    {
        $requiredUnits = min($requiredUnits, self::MAX_STRETCH_EM * $font->unitsPerEm);
        $con = $font->math()->vertical($gid);
        $bb = $font->bbox($gid);
        $natural = ['pieces' => [[$gid, 0.0, 1.0]], 'ymin' => (float) $bb[1], 'ymax' => (float) $bb[3], 'advance' => (float) $font->advance($gid)];
        if ($con === null) {
            return $natural;
        }
        if (($bb[3] - $bb[1]) >= $requiredUnits) {
            return $natural;
        }
        foreach ($con->variants as [$vg, $adv]) {
            if ($adv >= $requiredUnits) {
                $vb = $font->bbox($vg);

                return ['pieces' => [[$vg, 0.0, 1.0]], 'ymin' => (float) $vb[1], 'ymax' => (float) $vb[3], 'advance' => (float) $font->advance($vg)];
            }
        }
        if ($con->parts === null || $con->parts === []) {
            [$vg] = $con->variants[count($con->variants) - 1];
            $vb = $font->bbox($vg);

            return ['pieces' => [[$vg, 0.0, 1.0]], 'ymin' => (float) $vb[1], 'ymax' => (float) $vb[3], 'advance' => (float) $font->advance($vg)];
        }

        return self::assemble($font, $con->parts, $requiredUnits);
    }

    /**
     * A size variant (or assembly) of $gid at least $targetUnits tall, centered on the math axis, with $lead before it and
     * $trail after it (both pt). $char is the text a backend sets for a single glyph; $italic gives the box the glyph's
     * italics correction.
     */
    public static function axisCentred(Context $c, string $char, int $gid, float $targetUnits, float $lead, float $trail, bool $italic = false): Box
    {
        $u = $c->unit();
        $m = $c->font->math();
        $s = self::vertical($c->font, $gid, $targetUnits);
        $shift = $m->constant('axisHeight') - ($s['ymin'] + $s['ymax']) / 2;
        $single = count($s['pieces']) === 1;
        $b = new Box($lead + $s['advance'] * $u + $trail, ($s['ymax'] + $shift) * $u, -($s['ymin'] + $shift) * $u);
        $b->trail = $trail;
        foreach ($s['pieces'] as $pc) {
            [$pg, $off] = $pc;
            $b->add(new Glyph($pg, $single ? $char : null, $lead, -($off + $shift) * $u, $c->size(), 1.0, $pc[2]));
        }
        if ($single) {
            $b->firstGid = $b->lastGid = $s['pieces'][0][0];
            if ($italic) {
                $b->italic = $m->italicsCorrection($s['pieces'][0][0]) * $u;
            }
        }

        return $b;
    }

    /**
     * @return array{pieces: list<array{int,float,float}>, xmin: float, xmax: float, ascent: float, descent: float}
     *         pieces: [gid, x origin offset in font units, scale along x (assembly pieces only)], left to right. xmin/xmax are always the ink extent of the
     *         result along x, relative to the first piece's origin (a glyph's bbox x-range, or the min/max of the
     *         pieces' bbox x-ranges at their offsets); ascent and descent are the ink extent above and below the
     *         baseline. An assembly can come out wider than requested when the parts cannot overlap enough to shrink
     *         to it, and shorter when the extenders add no length or the repetition ceiling is hit.
     */
    public static function horizontal(TrueTypeFont $font, int $gid, float $requiredUnits): array
    {
        $requiredUnits = min($requiredUnits, self::MAX_STRETCH_EM * $font->unitsPerEm);
        $con = $font->math()->horizontal($gid);
        $single = static function (int $g) use ($font): array {
            $b = $font->bbox($g);

            return ['pieces' => [[$g, 0.0, 1.0]], 'xmin' => (float) $b[0], 'xmax' => (float) $b[2], 'ascent' => (float) $b[3], 'descent' => (float) -$b[1]];
        };
        // A combining accent has no advance of its own: its construction's first variant is the natural width.
        $naturalWidth = $con !== null && $con->variants !== [] ? $con->variants[0][1] : $font->advance($gid);
        if ($con === null || $naturalWidth >= $requiredUnits) {
            return $single($gid);
        }
        foreach ($con->variants as [$vg, $adv]) {
            if ($adv >= $requiredUnits) {
                return $single($vg);
            }
        }
        if ($con->parts === null || $con->parts === []) {
            return $single($con->variants[count($con->variants) - 1][0] ?? $gid);
        }
        [$placed, $size] = self::solveParts($con->parts, $requiredUnits, $font->math()->minConnectorOverlap);
        $pieces = [];
        $ascent = -INF;
        $descent = -INF;
        $xmin = INF;
        $xmax = -INF;
        foreach (self::merge($font, $placed, false) as [$p, $x, $k]) {
            $gb = $font->bbox($p->gid);
            $pieces[] = [$p->gid, $x + $gb[0] - $gb[0] * $k, $k]; // the scaled ink still starts at $x + $gb[0]
            $xmin = min($xmin, $x + $gb[0]);
            $xmax = max($xmax, $x + $gb[0] + ($gb[2] - $gb[0]) * $k);
            $ascent = max($ascent, (float) $gb[3]);
            $descent = max($descent, (float) -$gb[1]);
        }

        return ['pieces' => $pieces, 'xmin' => $xmin, 'xmax' => $xmax, 'ascent' => $ascent, 'descent' => $descent];
    }

    /**
     * Each run of one repeated straight extender becomes one piece: [part, start of the run, scale of its ink along the axis].
     * The scale is 1.0 for everything else, so the ink of the run is where the repeated pieces' ink was, minus the seams.
     *
     * @param list<array{\Papercut\Math\Font\AssemblyPart, float}> $placed
     * @return list<array{\Papercut\Math\Font\AssemblyPart, float, float}>
     */
    private static function merge(TrueTypeFont $font, array $placed, bool $vertical): array
    {
        $out = [];
        $last = -1;
        foreach ($placed as [$p, $pos]) {
            if ($last >= 0 && $out[$last][0] === $p && $p->extender && $font->straightAlong($p->gid, $vertical)) {
                $bb = $font->bbox($p->gid);
                $ink = $vertical ? $bb[3] - $bb[1] : $bb[2] - $bb[0];
                $out[$last][2] = ($pos - $out[$last][1] + $ink) / $ink;
                continue;
            }
            $out[] = [$p, $pos, 1.0];
            $last++;
        }

        return $out;
    }

    /**
     * @param list<\Papercut\Math\Font\AssemblyPart> $parts bottom to top
     * @return array{pieces: list<array{int,float,float}>, ymin: float, ymax: float, advance: float}
     */
    private static function assemble(TrueTypeFont $font, array $parts, float $required): array
    {
        [$placed, $size] = self::solveParts($parts, $required, $font->math()->minConnectorOverlap);
        $pieces = [];
        $adv = 0.0;
        foreach (self::merge($font, $placed, true) as [$p, $y, $k]) {
            $gb = $font->bbox($p->gid);
            $pieces[] = [$p->gid, $y - $gb[1] * $k, $k];
            $adv = max($adv, (float) $font->advance($p->gid));
        }

        return ['pieces' => $pieces, 'ymin' => 0.0, 'ymax' => $size, 'advance' => $adv];
    }

    /**
     * Axis-independent part-size solver: repeats extenders until the run is long enough, then spreads the surplus
     * over the joints within each joint's connector limits. The total matches the requested size when the limits allow;
     * it is longer when every joint is at its cap, and shorter when extenders add no length or the ceiling is hit.
     *
     * @param list<\Papercut\Math\Font\AssemblyPart> $parts in reading order along the axis
     * @return array{0: list<array{0: \Papercut\Math\Font\AssemblyPart, 1: float}>, 1: float} parts with their start
     *         positions along the axis, and the total size
     */
    private static function solveParts(array $parts, float $required, int $minOverlap): array
    {
        $overlap = (float) $minOverlap;
        $extCount = count(array_filter($parts, fn ($p) => $p->extender));
        $baseSum = 0.0;
        $extSum = 0.0;
        $fixed = 0;
        foreach ($parts as $p) {
            if ($p->extender) {
                $extSum += $p->fullAdvance;
            } else {
                $baseSum += $p->fullAdvance;
                $fixed++;
            }
        }
        // Fewest extender repetitions whose fully stretched (minimum overlap) run reaches the requested size. The
        // ceiling only guards absurd requests; a stretch needing more repetitions comes out shorter than required. So
        // does one whose extenders add no length (fullAdvance <= minimum overlap, gain <= 0): n stays 1, silently.
        $n = 1;
        $gain = $extSum - $extCount * $overlap;
        $base = $baseSum - ($fixed + $extCount - 1) * $overlap;
        if ($extCount > 0 && $gain > 0 && $base + $gain < $required) {
            $n = min(10000, (int) ceil(($required - $base) / $gain));
        }

        $seq = [];
        foreach ($parts as $p) {
            for ($r = 0, $reps = $p->extender ? $n : 1; $r < $reps; $r++) {
                $seq[] = $p;
            }
        }
        $count = count($seq);
        $sum = $baseSum + $n * $extSum;
        // Each joint may overlap by at most the smaller of the two connectors that meet there (and at least the
        // font's minimum). Spread the surplus evenly over the joints within those limits, so the result matches the
        // requested size; if every joint is at its limit and the run is still too long, it stays longer than required.
        $caps = [];
        for ($i = 1; $i < $count; $i++) {
            $caps[$i] = max($overlap, (float) min($seq[$i - 1]->endConnector, $seq[$i]->startConnector));
        }
        $target = $sum - $required;
        $lo = $overlap;
        $hi = $caps === [] ? $overlap : max($caps);
        for ($it = 0; $it < 60; $it++) {
            $mid = ($lo + $hi) / 2;
            $tot = 0.0;
            foreach ($caps as $cap) {
                $tot += min($mid, $cap);
            }
            if ($tot < $target) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        $o = $hi;
        $placed = [];
        $pos = 0.0;
        foreach ($seq as $i => $p) {
            if ($i > 0) {
                $pos -= min($o, $caps[$i]);
            }
            $placed[] = [$p, $pos];
            $pos += $p->fullAdvance;
        }

        return [$placed, $pos];
    }
}
