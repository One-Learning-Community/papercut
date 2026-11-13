<?php

declare(strict_types=1);

namespace Papercut\Math;

use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;

/**
 * Plain string to mi/mn/mo nodes. Letters become identifiers (one per letter, italic), digit runs
 * with an optional decimal point become numbers, whitespace is dropped (spacing comes from the
 * operator dictionary), every other character is an operator. Known function names (sin, log...) are matched
 * greedily, longest first, at any letter position and stay upright ("sinx" is "sin" then "x").
 * Unicode super/subscript digits become Number tokens drawn as the ordinary characters they are,
 * so "b²" stays an italic identifier "b" followed by a Number "²" with no space between them
 * (a single identifier "b²" would be drawn upright).
 */
final class Tokenizer
{
    private const FUNCTIONS = ['sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'sinh', 'cosh', 'tanh', 'log', 'ln', 'exp', 'lim', 'min', 'max', 'det', 'arcsin', 'arccos', 'arctan'];
    private const SCRIPT_DIGITS = '⁰¹²³⁴⁵⁶⁷⁸⁹₀₁₂₃₄₅₆₇₈₉⁺⁻⁼⁽⁾ⁿ₊₋₌₍₎';
    /** Symbols that behave as operands, not operators. */
    private const ORDINARY = '∞∂∇ℓℏ∅′″°';
    private const OP_MAP = ['-' => '−', '*' => '∗'];

    /**
     * The longest function name starting at $i, or null.
     *
     * @param list<string> $chars
     */
    private static function functionAt(array $chars, int $i): ?string
    {
        static $sorted = null;
        $sorted ??= (function (): array {
            $f = self::FUNCTIONS;
            usort($f, fn ($a, $b) => strlen($b) <=> strlen($a));

            return $f;
        })();
        $rest = implode('', array_slice($chars, $i, max(array_map('strlen', self::FUNCTIONS))));
        foreach ($sorted as $fn) {
            if (str_starts_with($rest, $fn)) {
                return $fn;
            }
        }

        return null;
    }

    /** @return list<Node> */
    public static function tokenize(string $s): array
    {
        $out = [];
        $chars = mb_str_split($s);
        $n = count($chars);
        $i = 0;
        while ($i < $n) {
            $ch = $chars[$i];
            if (ctype_space($ch) || $ch === "\u{00A0}") {
                $i++;
            } elseif (mb_strpos(self::SCRIPT_DIGITS, $ch) !== false) {
                $j = $i;
                while ($j < $n && mb_strpos(self::SCRIPT_DIGITS, $chars[$j]) !== false) {
                    $j++;
                }
                $out[] = new Number(implode('', array_slice($chars, $i, $j - $i)));
                $i = $j;
            } elseif (ctype_digit($ch) || ($ch === '.' && $i + 1 < $n && ctype_digit($chars[$i + 1]))) {
                $j = $i;
                $dot = false;
                while ($j < $n && (ctype_digit($chars[$j]) || ($chars[$j] === '.' && ! $dot && $j + 1 < $n && ctype_digit($chars[$j + 1])))) {
                    $dot = $dot || $chars[$j] === '.';
                    $j++;
                }
                $out[] = new Number(implode('', array_slice($chars, $i, $j - $i)));
                $i = $j;
            } elseif (preg_match('/^\p{L}$/u', $ch)) {
                $fn = self::functionAt($chars, $i);
                if ($fn !== null) {
                    $out[] = new Identifier($fn, false);
                    $i += strlen($fn);
                    continue;
                }
                $out[] = new Identifier($ch);
                $i++;
            } elseif (mb_strpos(self::ORDINARY, $ch) !== false) {
                $out[] = new Identifier($ch, false);
                $i++;
            } else {
                $out[] = new Operator(self::OP_MAP[$ch] ?? $ch);
                $i++;
            }
        }

        return $out;
    }
}
