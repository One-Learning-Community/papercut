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

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * @return list<?string> operator form of each child (prefix, infix, postfix) or null for non-operators
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function forms(): array
    {
        return Forms::of($this->children);
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        $boxes = null;

        return $this->build($c, [], $boxes);
    }

    /**
     * The row laid out, with the box of every child in `$boxes` and, for a child index in `$reuse`, that box in place of
     * laying the child out again (a fence re-places its Middles at their stretched size without redoing the rest).
     *
     * @param array<int, Box> $reuse
     * @param ?array<int, Box> $boxes filled when given as an array, left alone when null
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function build(Context $c, array $reuse, ?array &$boxes): Box
    {
        $out = new Box();
        $x = 0.0;
        $prev = null;
        $last = null;
        $forms = $this->forms();
        foreach ($this->children as $i => $child) {
            // A fraction next to an operand (mixed number "3 1/2", "2 x/y") gets a thin space so it does not read as one blob.
            $seen = Fallback::unwrap($child);
            // A Middle spaces itself like an operator.
            if ($prev !== null && ! ($prev instanceof Operator) && ! ($prev instanceof Middle) && ! ($prev instanceof Space) && ! ($seen instanceof Space)) {
                if (! ($seen instanceof Operator) && ! ($seen instanceof Middle) && ($seen instanceof Fraction || $prev instanceof Fraction)) {
                    $x += $c->mu(3);
                }
            }
            $form = $forms[$i];
            // Forms::of gives every operator a form and no one else.
            $ctx = $c;
            foreach (Fallback::displays($child) as $display) {
                $ctx = $ctx->withDisplay($display);
            }
            $b = $reuse[$i] ?? ($seen instanceof Operator ? $seen->layoutAs($ctx, $form ?? throw new \LogicException('Forms::of gave an operator no form')) : $child->layout($c));
            if ($boxes !== null) {
                $boxes[$i] = $b;
            }
            $out->place($b, $x, 0.0);
            $x += $b->width;
            $out->ascent = max($out->ascent, $b->ascent);
            $out->descent = max($out->descent, $b->descent);
            $prev = $seen;
            if ($i === 0) {
                $out->firstGid = $b->firstGid;
            }
            $last = $b;
        }
        $out->width = $x;
        if ($last !== null) {
            $out->italic = $last->italic;
            $out->trail = $last->trail;
            $out->lastGid = $last->lastGid;
            $out->lastAscent = $last->lastAscent ?? $last->ascent;
            $out->lastDescent = $last->lastDescent ?? $last->descent;
            $out->simple = count($this->children) === 1 && $last->simple;
        }

        return $out;
    }
}
