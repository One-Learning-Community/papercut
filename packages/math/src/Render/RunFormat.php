<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

/** Colour and size that equation runs inherit from the surrounding text. */
final readonly class RunFormat
{
    public function __construct(public ?string $colorHex = null, public ?int $sizeHalfPoints = null)
    {
        if ($colorHex !== null && preg_match('/^[0-9A-Fa-f]{6}$/', $colorHex) !== 1) {
            throw new \InvalidArgumentException('Colour must be six hex digits, got "' . $colorHex . '"');
        }
        if ($sizeHalfPoints !== null && $sizeHalfPoints <= 0) {
            throw new \InvalidArgumentException('Size must be positive half-points, got ' . $sizeHalfPoints);
        }
    }
}
