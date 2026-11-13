<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

use Papercut\Math\Font\TrueTypeFont;

/**
 * Picks a vertical size variant, or builds a GlyphAssembly, for a stretchy glyph.
 * Result is in font units, y up, with the glyph/assembly un-shifted; callers place it.
 */
final class Stretcher
{
    /**
     * @return array{pieces: list<array{int,float}>, ymin: float, ymax: float, advance: float}
     *         pieces: [gid, y origin offset in font units, up]. Assemblies span ymin = 0 .. ymax = size.
     */
    public static function vertical(TrueTypeFont $font, int $gid, float $requiredUnits): array
    {
        $con = $font->math()->vertical($gid);
        $bb = $font->bbox($gid);
        $natural = ['pieces' => [[$gid, 0.0]], 'ymin' => (float) $bb[1], 'ymax' => (float) $bb[3], 'advance' => (float) $font->advance($gid)];
        if ($con === null) {
            return $natural;
        }
        if (($bb[3] - $bb[1]) >= $requiredUnits) {
            return $natural;
        }
        foreach ($con->variants as [$vg, $adv]) {
            if ($adv >= $requiredUnits) {
                $vb = $font->bbox($vg);

                return ['pieces' => [[$vg, 0.0]], 'ymin' => (float) $vb[1], 'ymax' => (float) $vb[3], 'advance' => (float) $font->advance($vg)];
            }
        }
        if ($con->parts === null || $con->parts === []) {
            [$vg] = $con->variants[count($con->variants) - 1];
            $vb = $font->bbox($vg);

            return ['pieces' => [[$vg, 0.0]], 'ymin' => (float) $vb[1], 'ymax' => (float) $vb[3], 'advance' => (float) $font->advance($vg)];
        }

        return self::assemble($font, $con->parts, $requiredUnits);
    }

    /**
     * @param list<\Papercut\Math\Font\AssemblyPart> $parts bottom to top
     * @return array{pieces: list<array{int,float}>, ymin: float, ymax: float, advance: float}
     */
    private static function assemble(TrueTypeFont $font, array $parts, float $required): array
    {
        $overlap = (float) $font->math()->minConnectorOverlap;
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
        $n = 1;
        do {
            $count = $fixed + $n * $extCount;
            $total = $baseSum + $n * $extSum - ($count - 1) * $overlap;
        } while ($total < $required && $extCount > 0 && ++$n < 200);

        $seq = [];
        foreach ($parts as $p) {
            for ($r = 0, $reps = $p->extender ? $n : 1; $r < $reps; $r++) {
                $seq[] = $p;
            }
        }
        $count = count($seq);
        $sum = $baseSum + $n * $extSum;
        // Spread the surplus over the joints so the result is exactly the requested size.
        $o = $count > 1 ? ($sum - $required) / ($count - 1) : 0.0;
        $o = max($o, $overlap);
        $pieces = [];
        $y = 0.0;
        $adv = 0.0;
        foreach ($seq as $i => $p) {
            if ($i > 0) {
                $y -= $o;
            }
            $gb = $font->bbox($p->gid);
            $pieces[] = [$p->gid, $y - $gb[1]];
            $y += $p->fullAdvance;
            $adv = max($adv, (float) $font->advance($p->gid));
        }

        return ['pieces' => $pieces, 'ymin' => 0.0, 'ymax' => $y, 'advance' => $adv];
    }
}
