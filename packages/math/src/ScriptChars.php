<?php

declare(strict_types=1);

namespace Quoin\Math;

/**
 * The Unicode superscript and subscript characters quoin/math reads and writes, keyed by the plain
 * character they stand for. Shared by Tokenizer (characters to scripts) and Render\TextWriter (scripts to characters).
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class ScriptChars
{
    public const SUP = [
        '0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹',
        '+' => '⁺', '−' => '⁻', '-' => '⁻', '=' => '⁼', '(' => '⁽', ')' => '⁾',
        'a' => 'ᵃ', 'b' => 'ᵇ', 'c' => 'ᶜ', 'd' => 'ᵈ', 'e' => 'ᵉ', 'f' => 'ᶠ', 'g' => 'ᵍ', 'h' => 'ʰ', 'i' => 'ⁱ', 'j' => 'ʲ',
        'k' => 'ᵏ', 'l' => 'ˡ', 'm' => 'ᵐ', 'n' => 'ⁿ', 'o' => 'ᵒ', 'p' => 'ᵖ', 'r' => 'ʳ', 's' => 'ˢ', 't' => 'ᵗ', 'u' => 'ᵘ',
        'v' => 'ᵛ', 'w' => 'ʷ', 'x' => 'ˣ', 'y' => 'ʸ', 'z' => 'ᶻ',
    ];

    public const SUB = [
        '0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄', '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉',
        '+' => '₊', '−' => '₋', '-' => '₋', '=' => '₌', '(' => '₍', ')' => '₎',
        'a' => 'ₐ', 'e' => 'ₑ', 'h' => 'ₕ', 'i' => 'ᵢ', 'j' => 'ⱼ', 'k' => 'ₖ', 'l' => 'ₗ', 'm' => 'ₘ', 'n' => 'ₙ', 'o' => 'ₒ',
        'p' => 'ₚ', 'r' => 'ᵣ', 's' => 'ₛ', 't' => 'ₜ', 'u' => 'ᵤ', 'v' => 'ᵥ', 'x' => 'ₓ',
    ];

    /** @var array<string, int|string>|null script character to plain character, superscripts */
    private static ?array $supBack = null;

    /** @var array<string, int|string>|null script character to plain character, subscripts */
    private static ?array $subBack = null;

    /** The plain character for a superscript character, or null when $ch is not one. */
    public static function superscript(string $ch): ?string
    {
        self::$supBack ??= array_flip(self::SUP);

        return isset(self::$supBack[$ch]) ? (string) self::$supBack[$ch] : null;
    }

    /** The plain character for a subscript character, or null when $ch is not one. */
    public static function subscript(string $ch): ?string
    {
        self::$subBack ??= array_flip(self::SUB);

        return isset(self::$subBack[$ch]) ? (string) self::$subBack[$ch] : null;
    }

    public static function isScript(string $ch): bool
    {
        return self::superscript($ch) !== null || self::subscript($ch) !== null;
    }
}
