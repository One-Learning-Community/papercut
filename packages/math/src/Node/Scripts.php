<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

/** msub / msup / msubsup in one class: a base with a subscript, a superscript, or both. */
final class Scripts extends Node
{
    public function __construct(public readonly Node $base, public readonly ?Node $sub, public readonly ?Node $sup)
    {
        if ($sub === null && $sup === null) {
            throw new \InvalidArgumentException('Scripts needs a subscript or a superscript');
        }
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        $b = $this->base->layout($c);
        $sup = $this->sup?->layout($c->script());
        $sub = $this->sub?->layout($c->script(1, true)); // subscripts are cramped

        $up = $down = 0.0;
        if ($sup !== null) {
            $up = max($c->k($c->cramped ? 'superscriptShiftUpCramped' : 'superscriptShiftUp'), $sup->descent + $c->k('superscriptBottomMin'));
            if (! $b->simple) {
                $up = max($up, $b->ascent - $c->k('superscriptBaselineDropMax'));
            }
        }
        if ($sub !== null) {
            $down = max($c->k('subscriptShiftDown'), $sub->ascent - $c->k('subscriptTopMax'));
            if (! $b->simple) {
                $down = max($down, $b->descent + $c->k('subscriptBaselineDropMin'));
            }
        }
        if ($sup !== null && $sub !== null) {
            $gap = ($up - $sup->descent) - ($sub->ascent - $down);
            $min = $c->k('subSuperscriptGapMin');
            if ($gap < $min) {
                $down += $min - $gap;
            }
            $supBottom = $up - $sup->descent;
            $maxBottom = $c->k('superscriptBottomMaxWithSubscript');
            if ($supBottom < $maxBottom) {
                $adj = $maxBottom - $supBottom;
                $up += $adj;
                $down -= $adj;
            }
        }

        $out = new Box(0.0, $b->ascent, $b->descent);
        $out->place($b, 0, 0);
        $w = $b->width;
        if ($sup !== null) {
            $x = $b->width + $b->italic; // italics correction pushes the superscript clear of a slanted base
            $x += $this->kern($c, $b, $sup, true, $up);
            $out->place($sup, $x, -$up);
            $w = max($w, $x + $sup->width);
            $out->ascent = max($out->ascent, $up + $sup->ascent);
        }
        if ($sub !== null) {
            $sx = $b->width + $this->kern($c, $b, $sub, false, $down);
            $out->place($sub, $sx, $down);
            $w = max($w, $sx + $sub->width);
            $out->descent = max($out->descent, $down + $sub->descent);
        }
        $out->width = $w + $c->k('spaceAfterScript');

        return $out;
    }

    /**
     * Horizontal MathKern between the base's last glyph and the script's first, in points. Follows ziamath
     * (mathtable.py kernsuper/kernsub): the font's kern at the corners facing each other, read at the height of the script's near edge and at the
     * height of the base's near edge, the smaller of the two sums wins. $shift is how far the script baseline sits
     * above (superscript) or below (subscript) the base baseline.
     */
    private function kern(Context $c, Box $base, Box $script, bool $super, float $shift): float
    {
        if ($base->lastGid === null || $script->firstGid === null) {
            return 0.0;
        }
        $m = $c->font->math();
        $sc = $c->script();
        $ub = $c->unit();
        $us = $sc->unit();
        [$baseCorner, $scriptCorner] = $super ? ['topRight', 'bottomLeft'] : ['bottomRight', 'topLeft'];
        // Heights above the base baseline (h1: script's near edge), and above the script baseline (h2: base's near edge).
        // Only the base's last glyph is kerned, so a row base counts by its last token, not its tallest.
        if ($super) {
            $h1 = $shift - $script->descent;
            $h2 = ($base->lastAscent ?? $base->ascent) - $shift;
        } else {
            $h1 = $script->ascent - $shift;
            $h2 = $shift - ($base->lastDescent ?? $base->descent);
        }
        $sums = [];
        foreach ([$h1, $h2] as $h) {
            $sums[] = $m->kern($base->lastGid, $baseCorner, $h / $ub) * $ub
                + $m->kern($script->firstGid, $scriptCorner, $h / $us) * $us;
        }

        return min($sums);
    }
}
