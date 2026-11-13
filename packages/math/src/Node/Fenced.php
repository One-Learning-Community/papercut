<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Stretcher;

/** mfenced: open and close delimiters that stretch (size variant, then assembly) to the height of the content. */
final class Fenced extends Node
{
    public function __construct(public readonly string $open, public readonly string $close, public readonly Node $content)
    {
    }

    public function layout(Context $c): Box
    {
        $inner = $this->content->layout($c);
        $axis = $c->k('axisHeight');
        $req = 2 * max($inner->ascent - $axis, $inner->descent + $axis);
        // TeX's delimiterfactor (0.901) and delimitershortfall (5pt at 10pt): not stretched to the full height.
        $req = max($req * 0.901, $req - 0.5 * $c->size());

        $out = new Box();
        $x = 0.0;
        $asc = $inner->ascent;
        $desc = $inner->descent;
        if ($this->open !== '') {
            [$x, $asc, $desc] = $this->delimiter($c, $out, $this->open, $req, $x, $asc, $desc);
        }
        $out->place($inner, $x, 0);
        $x += $inner->width;
        if ($this->close !== '') {
            [$x, $asc, $desc] = $this->delimiter($c, $out, $this->close, $req, $x, $asc, $desc);
        }
        $out->width = $x;
        $out->ascent = $asc;
        $out->descent = $desc;

        return $out;
    }

    /** @return array{float, float, float} new x, ascent, descent */
    private function delimiter(Context $c, Box $out, string $ch, float $req, float $x, float $asc, float $desc): array
    {
        $f = $c->font;
        $u = $c->unit();
        $gid = $f->gid(mb_ord($ch));
        $s = Stretcher::vertical($f, $gid, $req / $u);
        $axisU = $f->math()->constant('axisHeight');
        $shift = $axisU - ($s['ymin'] + $s['ymax']) / 2; // centre on the math axis
        $single = count($s['pieces']) === 1 && $s['pieces'][0][0] === $gid;
        foreach ($s['pieces'] as [$pg, $off]) {
            $out->add(new Glyph($pg, $single ? $ch : null, $x, -($off + $shift) * $u, $c->size()));
        }
        $asc = max($asc, ($s['ymax'] + $shift) * $u);
        $desc = max($desc, -($s['ymin'] + $shift) * $u);

        return [$x + $s['advance'] * $u, $asc, $desc];
    }
}
