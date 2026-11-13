<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

/**
 * The OpenType MATH table: MathConstants, MathItalicsCorrectionInfo and MathVariants (vertical and
 * horizontal glyph constructions with GlyphAssembly). MathKernInfo is read; top-accent attachment is
 * not.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class MathTable
{
    /** Constants in table order. The first two are plain percentages, the rest are font units. */
    private const NAMES = [
        'scriptPercentScaleDown', 'scriptScriptPercentScaleDown', 'delimitedSubFormulaMinHeight', 'displayOperatorMinHeight',
        'mathLeading', 'axisHeight', 'accentBaseHeight', 'flattenedAccentBaseHeight',
        'subscriptShiftDown', 'subscriptTopMax', 'subscriptBaselineDropMin',
        'superscriptShiftUp', 'superscriptShiftUpCramped', 'superscriptBottomMin', 'superscriptBaselineDropMax',
        'subSuperscriptGapMin', 'superscriptBottomMaxWithSubscript', 'spaceAfterScript',
        'upperLimitGapMin', 'upperLimitBaselineRiseMin', 'lowerLimitGapMin', 'lowerLimitBaselineDropMin',
        'stackTopShiftUp', 'stackTopDisplayStyleShiftUp', 'stackBottomShiftDown', 'stackBottomDisplayStyleShiftDown',
        'stackGapMin', 'stackDisplayStyleGapMin',
        'stretchStackTopShiftUp', 'stretchStackBottomShiftDown', 'stretchStackGapAboveMin', 'stretchStackGapBelowMin',
        'fractionNumeratorShiftUp', 'fractionNumeratorDisplayStyleShiftUp',
        'fractionDenominatorShiftDown', 'fractionDenominatorDisplayStyleShiftDown',
        'fractionNumeratorGapMin', 'fractionNumDisplayStyleGapMin', 'fractionRuleThickness',
        'fractionDenominatorGapMin', 'fractionDenomDisplayStyleGapMin',
        'skewedFractionHorizontalGap', 'skewedFractionVerticalGap',
        'overbarVerticalGap', 'overbarRuleThickness', 'overbarExtraAscender',
        'underbarVerticalGap', 'underbarRuleThickness', 'underbarExtraDescender',
        'radicalVerticalGap', 'radicalDisplayStyleVerticalGap', 'radicalRuleThickness', 'radicalExtraAscender',
        'radicalKernBeforeDegree', 'radicalKernAfterDegree', 'radicalDegreeBottomRaisePercent',
    ];

    /** @var array<string, int> */
    public readonly array $constants;

    /** @var array<int, int> glyph id => italics correction in font units */
    private array $italics = [];

    public int $minConnectorOverlap = 0;

    /** @var array<int, array{topRight: ?MathKern, topLeft: ?MathKern, bottomRight: ?MathKern, bottomLeft: ?MathKern}> */
    private array $kerns = [];

    /** @var array<int, GlyphConstruction> */
    private array $vertical = [];
    /** @var array<int, GlyphConstruction> */
    private array $horizontal = [];

    public function __construct(private readonly Binary $b, int $base)
    {
        $constOff = $base + $b->u16($base + 4);
        $infoOff = $base + $b->u16($base + 6);
        $varOff = $base + $b->u16($base + 8);

        $c = [];
        $p = $constOff;
        foreach (self::NAMES as $i => $name) {
            if ($i < 2) {
                $c[$name] = $b->i16($p);
                $p += 2;
            } elseif ($i < 4) {
                $c[$name] = $b->u16($p);
                $p += 2;
            } elseif ($name === 'radicalDegreeBottomRaisePercent') {
                $c[$name] = $b->i16($p);
            } else {
                $c[$name] = $b->i16($p); // MathValueRecord: value, then a device offset we ignore
                $p += 4;
            }
        }
        $this->constants = $c;

        $italOff = $b->u16($infoOff);
        if ($italOff !== 0) {
            $o = $infoOff + $italOff;
            $covered = Coverage::read($b, $o + $b->u16($o));
            $n = $b->u16($o + 2);
            foreach ($covered as $i => $gid) {
                if ($i < $n) {
                    $this->italics[$gid] = $b->i16($o + 4 + $i * 4);
                }
            }
        }

        $kernOff = $infoOff !== 0 ? $b->u16($infoOff + 6) : 0;
        if ($kernOff !== 0) {
            $k = $infoOff + $kernOff;
            $covered = Coverage::read($b, $k + $b->u16($k));
            $n = $b->u16($k + 2);
            foreach ($covered as $i => $gid) {
                if ($i >= $n) {
                    break;
                }
                $rec = [];
                foreach (['topRight', 'topLeft', 'bottomRight', 'bottomLeft'] as $j => $corner) {
                    $off = $b->u16($k + 4 + $i * 8 + $j * 2);
                    $rec[$corner] = $off === 0 ? null : MathKern::read($b, $k + $off);
                }
                $this->kerns[$gid] = $rec;
            }
        }

        $this->minConnectorOverlap = $b->u16($varOff);
        $vc = $b->u16($varOff + 2);
        $hc = $b->u16($varOff + 4);
        $vn = $b->u16($varOff + 6);
        $hn = $b->u16($varOff + 8);
        $p = $varOff + 10;
        if ($vc !== 0) {
            $cov = Coverage::read($b, $varOff + $vc);
            for ($i = 0; $i < $vn && $i < count($cov); $i++) {
                $this->vertical[$cov[$i]] = $this->construction($varOff + $b->u16($p + $i * 2));
            }
        }
        $p += $vn * 2;
        if ($hc !== 0) {
            $cov = Coverage::read($b, $varOff + $hc);
            for ($i = 0; $i < $hn && $i < count($cov); $i++) {
                $this->horizontal[$cov[$i]] = $this->construction($varOff + $b->u16($p + $i * 2));
            }
        }
    }

    public function constant(string $name): int
    {
        return $this->constants[$name] ?? throw new \InvalidArgumentException("Unknown MATH constant $name");
    }

    /** Italics correction in font units (0 when the glyph has none). */
    public function italicsCorrection(int $gid): int
    {
        return $this->italics[$gid] ?? 0;
    }

    /**
     * MathKern at a corner (topRight, topLeft, bottomRight or bottomLeft) at a height, in font units
     * (0 when the glyph has no kern table for that corner).
     */
    public function kern(int $gid, string $corner, float $height): float
    {
        $rec = $this->kerns[$gid] ?? null;
        if ($rec === null) {
            return 0.0;
        }
        $k = $rec[$corner] ?? null;

        return $k === null ? 0.0 : (float) $k->at($height);
    }

    public function vertical(int $gid): ?GlyphConstruction
    {
        return $this->vertical[$gid] ?? null;
    }

    public function horizontal(int $gid): ?GlyphConstruction
    {
        return $this->horizontal[$gid] ?? null;
    }

    private function construction(int $o): GlyphConstruction
    {
        $b = $this->b;
        $asm = $b->u16($o);
        $n = $b->u16($o + 2);
        $variants = [];
        for ($i = 0; $i < $n; $i++) {
            $variants[] = [$b->u16($o + 4 + $i * 4), $b->u16($o + 6 + $i * 4)];
        }
        $parts = null;
        if ($asm !== 0) {
            $a = $o + $asm;
            $np = $b->u16($a + 4);
            $parts = [];
            for ($i = 0; $i < $np; $i++) {
                $q = $a + 6 + $i * 10;
                $parts[] = new AssemblyPart($b->u16($q), $b->u16($q + 2), $b->u16($q + 4), $b->u16($q + 6), ($b->u16($q + 8) & 1) === 1);
            }
        }

        return new GlyphConstruction($variants, $parts);
    }
}
