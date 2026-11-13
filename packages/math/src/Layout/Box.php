<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

/**
 * A laid-out formula (or part of one): advance width, ascent (height above the baseline) and
 * descent (depth below it), plus the display list. Coordinates: points, baseline at y = 0, y DOWN
 * (items above the baseline have negative y).
 */
final class Box
{
    /** @var list<DisplayItem> */
    public array $items = [];

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
        foreach ($child->items as $i) {
            $this->items[] = $i->translated($dx, $dy);
        }
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function add(DisplayItem $i): void
    {
        $this->items[] = $i;
    }

    public function height(): float
    {
        return $this->ascent + $this->descent;
    }
}
