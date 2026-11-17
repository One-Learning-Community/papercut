<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

/**
 * A laid-out formula (or part of one): advance width, ascent (height above the baseline) and
 * descent (depth below it), plus the display list. Coordinates: points, baseline at y = 0, y DOWN
 * (items above the baseline have negative y).
 *
 * The display list is a tree: place() keeps the child box with its offset instead of copying the child's items into the parent,
 * so laying out a formula costs time linear in its size however deeply it nests. Because the parent holds the child (not a copy),
 * a box must not be changed after it has been placed (no add() or place() on a placed child); every node builds a box fully before
 * placing it. Read the list through each() (no flat copy) or $items (flattened on every read).
 */
final class Box
{
    /** @var list<DisplayItem|array{Box, float, float}> own items and placed children (with their offsets), in drawing order */
    private array $entries = [];

    /**
     * Every leaf item at its absolute position, in drawing order; built anew on each read, so loop over it once or use each().
     *
     * @var list<DisplayItem>
     */
    public array $items {
        get {
            $out = [];
            $this->each(static function (DisplayItem $i, float $dx, float $dy) use (&$out): void {
                $out[] = ($dx === 0.0 && $dy === 0.0) ? $i : $i->translated($dx, $dy);
            });

            return $out;
        }
        set(array $v) {
            $this->entries = $v;
        }
    }

    /**
     * Glyph ids of the first and last glyph as drawn (an `ssty` alternate carries its own MathKernInfo), for MathKern; null when not a plain run.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public ?int $firstGid = null;
    /** @internal Engine detail, see $firstGid. */
    public ?int $lastGid = null;

    /**
     * Ascent and descent of the last token of a row (null elsewhere), which is all MathKern reads of a script's base.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public ?float $lastAscent = null;
    /** @internal Engine detail, see $lastAscent. */
    public ?float $lastDescent = null;

    /**
     * Space after the last glyph that is part of $width (an operator's rspace); scripts attach before it and it follows them.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public float $trail = 0.0;

    /**
     * @param float $italic italics correction of the last glyph, in points (used to place superscripts)
     * @param bool $simple true for a single token (letter, digit run, operator): scripts then ignore the base's height
     */
    public function __construct(
        public float $width = 0.0,
        public float $ascent = 0.0,
        public float $descent = 0.0,
        public float $italic = 0.0,
        public bool $simple = false,
    ) {
    }

    /**
     * Append a child's items shifted by (dx, dy); does not touch metrics.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function place(Box $child, float $dx, float $dy): void
    {
        $this->entries[] = [$child, $dx, $dy];
    }

    /**
     * Visit every leaf item in drawing order with its absolute offset: $fn($item, $dx, $dy), where the item itself is as laid out
     * in its own box and (dx, dy) is the sum of the offsets down to it. No flat list is built.
     *
     * @param callable(DisplayItem, float, float): void $fn
     */
    public function each(callable $fn): void
    {
        $this->walk($fn, 0.0, 0.0);
    }

    /** @param callable(DisplayItem, float, float): void $fn */
    private function walk(callable $fn, float $dx, float $dy): void
    {
        foreach ($this->entries as $e) {
            if (is_array($e)) {
                $e[0]->walk($fn, $dx + $e[1], $dy + $e[2]);
            } else {
                $fn($e, $dx, $dy);
            }
        }
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function add(DisplayItem $i): void
    {
        $this->entries[] = $i;
    }

    public function height(): float
    {
        return $this->ascent + $this->descent;
    }
}
