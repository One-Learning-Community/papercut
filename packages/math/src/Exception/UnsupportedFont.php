<?php

declare(strict_types=1);

namespace Quoin\Math\Exception;

/** The font file cannot be used for math layout: it is a collection, CFF2 or CID-keyed, malformed, or has no MATH table. */
final class UnsupportedFont extends \InvalidArgumentException
{
    public function __construct(public readonly string $path, string $reason)
    {
        parent::__construct($reason);
    }

    public static function cff(string $path): self
    {
        return new self($path, "$path is a CFF2 (variable CFF) font, which the math font reader does not read; use a static font");
    }

    public static function noMathTable(string $path): self
    {
        return new self($path, "$path has no MATH table, so it is not a math font");
    }
}
