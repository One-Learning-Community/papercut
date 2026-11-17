<?php

declare(strict_types=1);

namespace Quoin\Math\Node;

use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;
use Quoin\Math\Layout\Rule;

/** mfrac. Display style keeps numerator/denominator at the current size; inline style shrinks them one script level. */
final class Fraction extends Node
{
    public function __construct(public readonly Node $numerator, public readonly Node $denominator, public readonly bool $bar = true)
    {
    }

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not public API.
     */
    public function children(): array
    {
        return [$this->numerator, $this->denominator];
    }

    /**
     * Horizontal padding on each side of the fraction, in points.
     *
     * @internal Engine detail, not public API.
     */
    public static function padding(Context $c): float
    {
        return $c->mu(1);
    }

    /** @internal Engine detail, not public API. */
    public function layout(Context $c): Box
    {
        $fc = $c->fractionChild();
        $num = $this->numerator->layout($fc);
        $den = $this->denominator->layout($c->fractionChild(true));

        if (! $this->bar) {
            return $this->stack($c, $num, $den);
        }

        $axis = $c->k('axisHeight');
        $thick = $c->k('fractionRuleThickness');
        if ($c->display) {
            $up = $c->k('fractionNumeratorDisplayStyleShiftUp');
            $down = $c->k('fractionDenominatorDisplayStyleShiftDown');
            $numGap = $c->k('fractionNumDisplayStyleGapMin');
            $denGap = $c->k('fractionDenomDisplayStyleGapMin');
        } else {
            $up = $c->k('fractionNumeratorShiftUp');
            $down = $c->k('fractionDenominatorShiftDown');
            $numGap = $c->k('fractionNumeratorGapMin');
            $denGap = $c->k('fractionDenominatorGapMin');
        }
        // Keep at least the minimum gap between each part and the bar.
        $up = max($up, $axis + $thick / 2 + $numGap + $num->descent);
        $down = max($down, $den->ascent + $denGap + $thick / 2 - $axis);

        $pad = self::padding($c);
        $w = max($num->width, $den->width);
        $out = new Box($w + 2 * $pad, $up + $num->ascent, $down + $den->descent);
        $out->place($num, $pad + ($w - $num->width) / 2, -$up);
        $out->place($den, $pad + ($w - $den->width) / 2, $down);
        $out->add(new Rule($pad, -($axis + $thick / 2), $w, $thick));

        return $out;
    }

    /** A fraction without a rule: numerator and denominator stacked with the font's stack constants. */
    private function stack(Context $c, Box $num, Box $den): Box
    {
        if ($c->display) {
            $up = $c->k('stackTopDisplayStyleShiftUp');
            $down = $c->k('stackBottomDisplayStyleShiftDown');
            $gap = $c->k('stackDisplayStyleGapMin');
        } else {
            $up = $c->k('stackTopShiftUp');
            $down = $c->k('stackBottomShiftDown');
            $gap = $c->k('stackGapMin');
        }
        // Too close together: push each part away from the other by half the shortfall.
        $shortfall = $gap - (($up - $num->descent) + ($down - $den->ascent));
        if ($shortfall > 0) {
            $up += $shortfall / 2;
            $down += $shortfall / 2;
        }

        $pad = self::padding($c);
        $w = max($num->width, $den->width);
        $out = new Box($w + 2 * $pad, $up + $num->ascent, $down + $den->descent);
        $out->place($num, $pad + ($w - $num->width) / 2, -$up);
        $out->place($den, $pad + ($w - $den->width) / 2, $down);

        return $out;
    }
}
