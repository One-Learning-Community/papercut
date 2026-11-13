<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Layout\Stretcher;

/** msqrt (no index) and mroot (with index). */
final class Root extends Node
{
    public function __construct(public readonly Node $radicand, public readonly ?Node $index = null)
    {
    }

    public function layout(Context $c): Box
    {
        $f = $c->font;
        $u = $c->unit();
        $rad = $this->radicand->layout($c->cramped());

        $gap = $c->display ? $c->k('radicalDisplayStyleVerticalGap') : $c->k('radicalVerticalGap');
        $thick = $c->k('radicalRuleThickness');
        $needed = $rad->ascent + $rad->descent + $gap + $thick;

        $surd = Stretcher::vertical($f, $f->gid(0x221A), $needed / $u);
        $glyphH = ($surd['ymax'] - $surd['ymin']) * $u;
        // A surd taller than needed: split the surplus above and below the radicand.
        $pad = max(0.0, ($glyphH - $needed) / 2);
        $ruleTop = $rad->ascent + $gap + $pad + $thick; // above baseline
        $dy = $surd['ymax'] * $u - $ruleTop;               // glyph origin shift, y down
        $surdDescent = $dy - $surd['ymin'] * $u;
        $surdW = $surd['advance'] * $u;

        $out = new Box();
        $x = 0.0;
        $index = null;
        if ($this->index !== null) {
            // MathML Core mroot: the before-kern is clamped at 0 and the after-kern at minus the
            // index width, so the surd never starts before the box. The index sits at the before-kern
            // and the surd begins at index width + both kerns.
            $index = $this->index->layout($c->script(2));
            $before = max(0.0, $c->k('radicalKernBeforeDegree'));
            $after = max($c->k('radicalKernAfterDegree'), -$index->width);
            $indexX = $before;
            $x = $before + $index->width + $after;

            // The index's bottom is raised by raisePercent of the radical's total height above its bottom.
            $bottom = max($rad->descent, $surdDescent);
            $radicalHeight = $ruleTop + $c->k('radicalExtraAscender') + $bottom;
            $raise = $c->font->math()->constant('radicalDegreeBottomRaisePercent') / 100 * $radicalHeight;
            $indexY = -(-$bottom + $raise + $index->descent);
            $out->place($index, $indexX, $indexY);
        }

        foreach ($surd['pieces'] as [$gid, $off]) {
            $single = count($surd['pieces']) === 1 && $gid === $f->gid(0x221A);
            $out->add(new Glyph($gid, $single ? '√' : null, $x, $dy - $off * $u, $c->size()));
        }
        $x += $surdW;
        $out->place($rad, $x, 0);
        $barW = $rad->width + $rad->italic;
        $out->add(new Rule($x, -$ruleTop, $barW, $thick));

        $out->width = $x + $barW;
        $out->ascent = $ruleTop + $c->k('radicalExtraAscender');
        $out->descent = max($rad->descent, $surdDescent);
        if ($index !== null) {
            $out->ascent = max($out->ascent, -$indexY + $index->ascent);
        }

        return $out;
    }
}
