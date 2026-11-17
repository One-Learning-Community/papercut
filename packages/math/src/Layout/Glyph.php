<?php

declare(strict_types=1);

namespace Quoin\Math\Layout;

/**
 * A glyph placed by its origin (x, y), y being the baseline position, y down.
 * $text is the character drawn, for selection and ToUnicode: for a letter set in mathematical italic it is the italic code point,
 * not the letter typed; null for size-variant and assembly pieces
 * that stand for part of a larger symbol. $size is the font size in points.
 * $sx and $sy scale the outline along x and y about the glyph origin (1.0 unless a stretch assembly draws a run of one straight extender once).
 */
final readonly class Glyph implements DisplayItem
{
    public function __construct(
        public int $gid,
        public ?string $text,
        public float $x,
        public float $y,
        public float $size,
        public float $sx = 1.0,
        public float $sy = 1.0,
    ) {
    }

    /** @internal Engine detail, not public API. */
    public function translated(float $dx, float $dy): static
    {
        return new self($this->gid, $this->text, $this->x + $dx, $this->y + $dy, $this->size, $this->sx, $this->sy);
    }
}
