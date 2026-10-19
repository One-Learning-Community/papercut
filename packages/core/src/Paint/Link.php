<?php

declare(strict_types=1);

namespace Papercut\Paint;

final readonly class Link implements Op
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $uri,
    ) {
    }
}
