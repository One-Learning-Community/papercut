<?php

declare(strict_types=1);

namespace Papercut\Math;

/** Mathematical alphanumeric mapping (ziamath styles.py, italic only). */
final class Styles
{
    /** Map a Latin or Greek letter to its Mathematical Italic codepoint; anything else is returned unchanged. */
    public static function italic(int $cp): int
    {
        if ($cp === 0x68) {
            return 0x210E; // italic h is a hole in the block: Planck constant
        }
        if ($cp >= 0x41 && $cp <= 0x5A) {
            return 0x1D434 + $cp - 0x41;
        }
        if ($cp >= 0x61 && $cp <= 0x7A) {
            return 0x1D44E + $cp - 0x61;
        }
        if ($cp >= 0x391 && $cp <= 0x3A9 && $cp !== 0x3A2) {
            return 0x1D6E2 + $cp - 0x391;
        }
        if ($cp >= 0x3B1 && $cp <= 0x3C9) {
            return 0x1D6FC + $cp - 0x3B1;
        }

        return $cp;
    }

    /** The inverse of italic(): a Mathematical Italic codepoint back to the plain letter; anything else is returned unchanged. */
    public static function plain(int $cp): int
    {
        return match (true) {
            $cp === 0x210E => 0x68,
            $cp >= 0x1D434 && $cp <= 0x1D44D => 0x41 + $cp - 0x1D434,
            $cp >= 0x1D44E && $cp <= 0x1D467 => 0x61 + $cp - 0x1D44E,
            $cp >= 0x1D6E2 && $cp <= 0x1D6FA && $cp !== 0x1D6F3 => 0x391 + $cp - 0x1D6E2,
            $cp >= 0x1D6FC && $cp <= 0x1D714 => 0x3B1 + $cp - 0x1D6FC,
            default => $cp,
        };
    }
}
