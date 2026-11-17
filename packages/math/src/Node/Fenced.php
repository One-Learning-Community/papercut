<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Stretcher;
use Papercut\Math\Utf8;

/** mfenced: open and close delimiters that stretch (size variant, then assembly) to the height of the content. */
final class Fenced extends Node
{
    public readonly string $open;
    public readonly string $close;

    /** Invalid UTF-8 in a delimiter is replaced silently here; the Math builders also record a note. */
    public function __construct(string $open, string $close, public readonly Node $content)
    {
        $this->open = Utf8::clean($open);
        $this->close = Utf8::clean($close);
    }

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function children(): array
    {
        return [$this->content];
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        $inner = $this->content->layout($c);
        $axis = $c->k('axisHeight');
        $req = 2 * max($inner->ascent - $axis, $inner->descent + $axis);
        // TeX's delimiterfactor (0.901) and delimitershortfall (5pt at 10pt): not stretched to the full height.
        $req = max($req * 0.901, $req - 0.5 * $c->size());

        $out = new Box();
        $x = 0.0;
        if (self::hasMiddles($this->content)) {
            $inner = self::stretchMiddles($this->content, $req)->layout($c);
        }
        $asc = $inner->ascent;
        $desc = $inner->descent;
        if ($this->open !== '') {
            [$x, $asc, $desc] = $this->delimiter($c, $out, $this->open, $req, $x, $asc, $desc);
        }
        if ($this->open === '{' && $this->close === '' && Fallback::unwrap($this->content) instanceof Grid) {
            $x += $c->mu(3); // a piecewise brace sits a little off its rows
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

    /** Whether the content is a Row with a Middle among its own children. */
    private static function hasMiddles(Node $content): bool
    {
        $row = Fallback::unwrap($content);

        return $row instanceof Row && array_filter($row->children, static fn (Node $n): bool => Fallback::unwrap($n) instanceof Middle) !== [];
    }

    /**
     * A copy of the content with each top-level Middle told to stretch to $req pt.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function stretchMiddles(Node $n, float $req): Node
    {
        return match (true) {
            $n instanceof Row => new Row(array_map(static fn (Node $child): Node => self::stretchMiddle($child, $req), $n->children)),
            $n instanceof Fallback && $n->content !== null => new Fallback(self::stretchMiddles($n->content, $req), $n->note),
            $n instanceof DisplayStyle => new DisplayStyle(self::stretchMiddles($n->content, $req), $n->display),
            default => $n,
        };
    }

    private static function stretchMiddle(Node $n, float $req): Node
    {
        return match (true) {
            $n instanceof Middle => new Middle($n->char, $req),
            $n instanceof Fallback && $n->content !== null => new Fallback(self::stretchMiddle($n->content, $req), $n->note),
            $n instanceof DisplayStyle => new DisplayStyle(self::stretchMiddle($n->content, $req), $n->display),
            default => $n,
        };
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
