<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\MathNote;

/** Content a reader could not render faithfully: what stands in for it (or nothing) and the note saying why. */
final class Fallback extends Node
{
    public function __construct(public readonly ?Node $content, public readonly MathNote $note)
    {
    }

    /**
     * Sees through what only annotates a node (a Fallback with content, a DisplayStyle), so checks for an Operator or
     * a Fraction find it.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function unwrap(Node $n): Node
    {
        while (true) {
            if ($n instanceof self && $n->content !== null) {
                $n = $n->content;
            } elseif ($n instanceof DisplayStyle) {
                $n = $n->content;
            } else {
                return $n;
            }
        }
    }

    /**
     * The display switches of the DisplayStyle wrappers unwrap() peels, outermost first.
     *
     * @return list<bool>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function displays(Node $n): array
    {
        $out = [];
        while (true) {
            if ($n instanceof self && $n->content !== null) {
                $n = $n->content;
            } elseif ($n instanceof DisplayStyle) {
                $out[] = $n->display;
                $n = $n->content;
            } else {
                return $out;
            }
        }
    }

    /** @return list<Node> */
    public function children(): array
    {
        return $this->content === null ? [] : [$this->content];
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        return $this->content === null ? new Box() : $this->content->layout($c);
    }
}
