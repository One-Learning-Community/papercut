<?php

declare(strict_types=1);

namespace Quoin\Math\Node;

use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;
use Quoin\Math\MathNote;

/** Content a reader could not render faithfully: what stands in for it (or nothing) and the note saying why. */
final class Fallback extends Node
{
    public function __construct(public readonly ?Node $content, public readonly MathNote $note)
    {
    }

    /**
     * Sees through what only annotates a node (a Fallback with content, a DisplayStyle, a TexSource), so checks for an Operator or
     * a Fraction find it.
     *
     * @internal Engine detail, not public API.
     */
    public static function unwrap(Node $n): Node
    {
        while (true) {
            if ($n instanceof self && $n->content !== null) {
                $n = $n->content;
            } elseif ($n instanceof DisplayStyle || $n instanceof TexSource) {
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
     * @internal Engine detail, not public API.
     */
    public static function displays(Node $n): array
    {
        $out = [];
        while (true) {
            if ($n instanceof self && $n->content !== null) {
                $n = $n->content;
            } elseif ($n instanceof TexSource) {
                $n = $n->content;
            } elseif ($n instanceof DisplayStyle) {
                $out[] = $n->display;
                $n = $n->content;
            } else {
                return $out;
            }
        }
    }

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not public API.
     */
    public function children(): array
    {
        return $this->content === null ? [] : [$this->content];
    }

    /** @internal Engine detail, not public API. */
    public function layout(Context $c): Box
    {
        return $this->content === null ? new Box() : $this->content->layout($c);
    }
}
