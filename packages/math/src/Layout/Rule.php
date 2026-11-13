<?php

declare(strict_types=1);

namespace Papercut\Math\Layout;

/** A filled rectangle (fraction bar, radical overbar). (x, y) is its top-left corner, y down. */
final readonly class Rule implements DisplayItem
{
    public function __construct(
        public float $x,
        public float $y,
        public float $w,
        public float $h,
    ) {
    }

    public function translated(float $dx, float $dy): static
    {
        return new self($this->x + $dx, $this->y + $dy, $this->w, $this->h);
    }
}
