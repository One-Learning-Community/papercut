<?php

declare(strict_types=1);

namespace Papercut\Math;

/**
 * Mathematical alphanumeric mapping (ziamath styles.py, italic only).
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class Styles
{
    /** The mathvariant values variant() understands. */
    public const VARIANTS = [
        'normal', 'bold', 'italic', 'bold-italic', 'double-struck', 'script', 'bold-script', 'fraktur', 'bold-fraktur',
        'sans-serif', 'bold-sans-serif', 'sans-serif-italic', 'sans-serif-bold-italic', 'monospace',
    ];

    /**
     * Per variant: Latin capitals, Latin small, digits and Greek capitals and small bases (null where Unicode has none),
     * then the holes: letters whose styled form lives outside the block.
     */
    private const TABLE = [
        'bold' => [0x1D400, 0x1D41A, 0x1D7CE, 0x1D6A8, 0x1D6C2, []],
        'italic' => [0x1D434, 0x1D44E, null, 0x1D6E2, 0x1D6FC, [0x68 => 0x210E]],
        'bold-italic' => [0x1D468, 0x1D482, null, 0x1D71C, 0x1D736, []],
        'script' => [0x1D49C, 0x1D4B6, null, null, null, [
            0x42 => 0x212C, 0x45 => 0x2130, 0x46 => 0x2131, 0x48 => 0x210B, 0x49 => 0x2110, 0x4C => 0x2112, 0x4D => 0x2133,
            0x52 => 0x211B, 0x65 => 0x212F, 0x67 => 0x210A, 0x6F => 0x2134,
        ]],
        'bold-script' => [0x1D4D0, 0x1D4EA, null, null, null, []],
        'fraktur' => [0x1D504, 0x1D51E, null, null, null, [0x43 => 0x212D, 0x48 => 0x210C, 0x49 => 0x2111, 0x52 => 0x211C, 0x5A => 0x2128]],
        'double-struck' => [0x1D538, 0x1D552, 0x1D7D8, null, null, [
            0x43 => 0x2102, 0x48 => 0x210D, 0x4E => 0x2115, 0x50 => 0x2119, 0x51 => 0x211A, 0x52 => 0x211D, 0x5A => 0x2124,
        ]],
        'bold-fraktur' => [0x1D56C, 0x1D586, null, null, null, []],
        'sans-serif' => [0x1D5A0, 0x1D5BA, 0x1D7E2, null, null, []],
        'bold-sans-serif' => [0x1D5D4, 0x1D5EE, 0x1D7EC, 0x1D756, 0x1D770, []],
        'sans-serif-italic' => [0x1D608, 0x1D622, null, null, null, []],
        'sans-serif-bold-italic' => [0x1D63C, 0x1D656, null, 0x1D790, 0x1D7AA, []],
        'monospace' => [0x1D670, 0x1D68A, 0x1D7F6, null, null, []],
    ];

    /** Greek symbols after the small letters of a styled Greek block, in order: ϵ ϑ ϰ ϕ ϱ ϖ. */
    private const GREEK_SYMBOLS = [0x3F5 => 1, 0x3D1 => 2, 0x3F0 => 3, 0x3D5 => 4, 0x3F1 => 5, 0x3D6 => 6];

    /**
     * Map a letter or digit to its form in a MathML mathvariant, from the Mathematical Alphanumeric Symbols block
     * (and the letterlike holes outside it). A code point with no styled form, or an unknown variant, is returned unchanged.
     */
    public static function variant(int $cp, string $variant): int
    {
        $t = self::TABLE[$variant] ?? null;
        if ($t === null) {
            return $cp;
        }
        [$upper, $lower, $digit, $gUpper, $gLower, $holes] = $t;

        return match (true) {
            isset($holes[$cp]) => $holes[$cp],
            $cp >= 0x41 && $cp <= 0x5A => $upper + $cp - 0x41,
            $cp >= 0x61 && $cp <= 0x7A => $lower + $cp - 0x61,
            $digit !== null && $cp >= 0x30 && $cp <= 0x39 => $digit + $cp - 0x30,
            $gUpper !== null && $cp >= 0x391 && $cp <= 0x3A9 && $cp !== 0x3A2 => $gUpper + $cp - 0x391,
            $gLower !== null && $cp >= 0x3B1 && $cp <= 0x3C9 => $gLower + $cp - 0x3B1,
            $gUpper !== null && $cp === 0x3F4 => $gUpper + 17,
            $gUpper !== null && $cp === 0x2207 => $gUpper + 25,
            $gLower !== null && $cp === 0x2202 => $gLower + 25,
            $gLower !== null && isset(self::GREEK_SYMBOLS[$cp]) => $gLower + 25 + self::GREEK_SYMBOLS[$cp],
            default => $cp,
        };
    }

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

    /** Accent marks (spacing, combining and arrow forms) and the combining character each stands for. */
    private const ACCENTS = [
        '^' => "\u{0302}", "\u{02C6}" => "\u{0302}", "\u{0302}" => "\u{0302}",
        '~' => "\u{0303}", "\u{02DC}" => "\u{0303}", "\u{0303}" => "\u{0303}",
        "\u{2192}" => "\u{20D7}", "\u{20D7}" => "\u{20D7}",
        "\u{21C0}" => "\u{20D1}", "\u{20D1}" => "\u{20D1}",
        "\u{21BC}" => "\u{20D0}", "\u{20D0}" => "\u{20D0}",
        "\u{2190}" => "\u{20D6}", "\u{20D6}" => "\u{20D6}",
        "\u{2194}" => "\u{20E1}", "\u{20E1}" => "\u{20E1}",
        "\u{02D9}" => "\u{0307}", "\u{0307}" => "\u{0307}",
        "\u{00A8}" => "\u{0308}", "\u{0308}" => "\u{0308}",
        "\u{02C7}" => "\u{030C}", "\u{030C}" => "\u{030C}",
        "\u{00B4}" => "\u{0301}", "\u{0301}" => "\u{0301}",
        '`' => "\u{0300}", "\u{0300}" => "\u{0300}",
        "\u{02D8}" => "\u{0306}", "\u{0306}" => "\u{0306}",
    ];

    /** The combining form of an accent mark (^ to U+0302, an arrow to U+20D7), or null for a mark that is not an accent. */
    public static function accentMark(string $char): ?string
    {
        return self::ACCENTS[$char] ?? null;
    }
}
