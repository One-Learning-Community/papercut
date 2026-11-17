<?php

declare(strict_types=1);

namespace Papercut\Math\Exception;

/** A PHP extension this feature needs is not loaded. */
final class MissingExtension extends \RuntimeException
{
    public function __construct(public readonly string $extension, string $needs)
    {
        parent::__construct("$needs needs the $extension extension, which is not loaded");
    }

    public static function imagick(): self
    {
        return new self('imagick', 'PngRenderer');
    }
}
