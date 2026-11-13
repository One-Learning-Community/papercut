<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

/**
 * A glyph placed by its origin (x, y), y being the baseline position, y down.
 * $text is the character drawn, for selection and ToUnicode: for a letter set in mathematical italic it is the italic code point,
 * not the letter typed; null for size-variant and assembly pieces
 * that stand for part of a larger symbol. $size is the font size in points.
 */
final readonly class Glyph implements DisplayItem
{
    public function __construct(
        public int $gid,
        public ?string $text,
        public float $x,
        public float $y,
        public float $size,
    ) {
    }

    public function translated(float $dx, float $dy): static
    {
        return new self($this->gid, $this->text, $this->x + $dx, $this->y + $dy, $this->size);
    }
}
