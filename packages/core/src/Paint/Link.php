<?php

declare(strict_types=1);

namespace Papercut\Paint;

use Papercut\Exception\InvalidValue;

final readonly class Link implements Op
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $uri,
    ) {
        if (!is_finite($x) || !is_finite($y) || !is_finite($width) || !is_finite($height)) {
            throw new \InvalidArgumentException('Link received a non-finite number.');
        }
        if (preg_match('~^(?:(?:https?|mailto|tel):|#)~i', ltrim($uri)) !== 1) {
            throw InvalidValue::for('link', $uri);
        }
    }
}
