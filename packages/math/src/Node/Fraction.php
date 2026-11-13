<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Rule;

/** mfrac. Display style keeps numerator/denominator at the current size; inline style shrinks them one script level. */
final class Fraction extends Node
{
    public function __construct(public readonly Node $numerator, public readonly Node $denominator)
    {
    }

    /**
     * Horizontal padding on each side of the fraction, in points.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function padding(Context $c): float
    {
        return $c->mu(1);
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        $fc = $c->fractionChild();
        $num = $this->numerator->layout($fc);
        $den = $this->denominator->layout($c->fractionChild(true));

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
}
