<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

/**
 * Big-endian reads over a font file held in memory.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class Binary
{
    public function __construct(public readonly string $d)
    {
    }

    public function u8(int $o): int
    {
        return ord($this->d[$o]);
    }

    public function u16(int $o): int
    {
        return (ord($this->d[$o]) << 8) | ord($this->d[$o + 1]);
    }

    public function i16(int $o): int
    {
        $v = $this->u16($o);

        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    public function u32(int $o): int
    {
        return ($this->u16($o) << 16) | $this->u16($o + 2);
    }
}
