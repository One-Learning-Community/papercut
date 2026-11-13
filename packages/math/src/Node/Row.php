<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;

/** mrow: children side by side on a shared baseline. Operator forms come from Forms::of(). */
final class Row extends Node
{
    /** @param list<Node> $children */
    public function __construct(public readonly array $children)
    {
    }

    /** @return list<?string> operator form of each child (prefix, infix, postfix) or null for non-operators */
    public function forms(): array
    {
        return Forms::of($this->children);
    }

    public function layout(Context $c): Box
    {
        $out = new Box();
        $x = 0.0;
        $prev = null;
        $last = null;
        $forms = $this->forms();
        foreach ($this->children as $i => $child) {
            // A fraction next to an operand (mixed number "3 1/2", "2 x/y") gets a thin space so it does not read as one blob.
            if ($prev !== null && ! ($prev instanceof Operator)) {
                if (! ($child instanceof Operator) && ($child instanceof Fraction || $prev instanceof Fraction)) {
                    $x += $c->mu(3);
                }
            }
            $form = $forms[$i];
            $b = $child instanceof Operator && $form !== null ? $child->layoutAs($c, $form) : $child->layout($c);
            $out->place($b, $x, 0.0);
            $x += $b->width;
            $out->ascent = max($out->ascent, $b->ascent);
            $out->descent = max($out->descent, $b->descent);
            $prev = $child;
            if ($i === 0) {
                $out->firstGid = $b->firstGid;
            }
            $last = $b;
        }
        $out->width = $x;
        if ($last !== null) {
            $out->italic = $last->italic;
            $out->lastGid = $last->lastGid;
            $out->simple = count($this->children) === 1 && $last->simple;
        }

        return $out;
    }
}
