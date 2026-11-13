<?php

declare(strict_types=1);

namespace Papercut\Math\Exception;

/** The font file cannot be used for math layout: it is CFF-flavoured or has no MATH table. */
final class UnsupportedFont extends \InvalidArgumentException
{
    public function __construct(public readonly string $path, string $reason)
    {
        parent::__construct($reason);
    }

    public static function cff(string $path): self
    {
        return new self($path, "$path is a CFF (OpenType/PostScript) font; papercut/math reads TrueType math fonts only for now");
    }

    public static function noMathTable(string $path): self
    {
        return new self($path, "$path has no MATH table, so it is not a math font");
    }
}
