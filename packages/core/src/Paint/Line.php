<?php

declare(strict_types=1);

namespace Papercut\Paint;

use Papercut\Value\Color;

final readonly class Line implements Op
{
    public function __construct(
        public float $x1,
        public float $y1,
        public float $x2,
        public float $y2,
        public Color $color,
        public float $width = 1.0,
        public bool $dashed = false,
    ) {
    }
}
