<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Root;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\Node\Text;
use Papercut\Math\OperatorDictionary;
use Papercut\Math\Styles;

/**
 * A formula as linear Unicode text with plain letters, for outputs that have no math: table-of-contents entries, PDF
 * outline titles, slugs, a text fallback. `x = (−b ± √(b² − 4ac))/(2a)`. Infix operators are set off by spaces and
 * prefix and postfix ones are not. Scripts become Unicode superscript and subscript characters when they are
 * digits, signs, parentheses or a single letter, and `^(…)` and `_(…)` otherwise.
 */
final class TextWriter
{
    private const SUP = [
        '0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹',
        '+' => '⁺', '−' => '⁻', '-' => '⁻', '=' => '⁼', '(' => '⁽', ')' => '⁾',
        'a' => 'ᵃ', 'b' => 'ᵇ', 'c' => 'ᶜ', 'd' => 'ᵈ', 'e' => 'ᵉ', 'f' => 'ᶠ', 'g' => 'ᵍ', 'h' => 'ʰ', 'i' => 'ⁱ', 'j' => 'ʲ',
        'k' => 'ᵏ', 'l' => 'ˡ', 'm' => 'ᵐ', 'n' => 'ⁿ', 'o' => 'ᵒ', 'p' => 'ᵖ', 'r' => 'ʳ', 's' => 'ˢ', 't' => 'ᵗ', 'u' => 'ᵘ',
        'v' => 'ᵛ', 'w' => 'ʷ', 'x' => 'ˣ', 'y' => 'ʸ', 'z' => 'ᶻ',
    ];

    private const SUB = [
        '0' => '₀', '1' => '₁', '2' => '₂', '3' => '₃', '4' => '₄', '5' => '₅', '6' => '₆', '7' => '₇', '8' => '₈', '9' => '₉',
        '+' => '₊', '−' => '₋', '-' => '₋', '=' => '₌', '(' => '₍', ')' => '₎',
        'a' => 'ₐ', 'e' => 'ₑ', 'h' => 'ₕ', 'i' => 'ᵢ', 'j' => 'ⱼ', 'k' => 'ₖ', 'l' => 'ₗ', 'm' => 'ₘ', 'n' => 'ₙ', 'o' => 'ₒ',
        'p' => 'ₚ', 'r' => 'ᵣ', 's' => 'ₛ', 't' => 'ₜ', 'u' => 'ᵤ', 'v' => 'ᵥ', 'x' => 'ₓ',
    ];

    public static function write(Node $root): string
    {
        return trim(self::node($root));
    }

    private static function node(Node $n): string
    {
        return match (true) {
            $n instanceof Identifier => self::plain($n->name),
            $n instanceof Number => $n->text,
            $n instanceof Operator => $n->char,
            $n instanceof Text => $n->text,
            $n instanceof Fraction => self::fraction($n),
            $n instanceof Root => self::root($n),
            $n instanceof Scripts => self::scripts($n),
            $n instanceof Fenced => $n->open . self::node($n->content) . $n->close,
            $n instanceof Row => self::row($n),
            default => throw new \InvalidArgumentException('Unknown node ' . $n::class),
        };
    }

    private static function plain(string $name): string
    {
        $out = '';
        foreach (mb_str_split($name) as $ch) {
            $cp = mb_ord($ch);
            $out .= $cp === 0x210E ? 'h' : mb_chr(Styles::plain($cp));
        }

        return $out;
    }

    private static function row(Row $r): string
    {
        $forms = $r->forms();
        $out = '';
        $prev = null;
        foreach ($r->children as $i => $child) {
            if ($child instanceof Operator) {
                $out .= match (true) {
                    $forms[$i] === OperatorDictionary::FORM_INFIX && ($child->char === ',' || $child->char === ';') => $child->char . ' ',
                    $forms[$i] === OperatorDictionary::FORM_INFIX => ' ' . $child->char . ' ',
                    default => $child->char,
                };
            } else {
                // A function name set apart from its operand: "sin x", not "sinx".
                if ($prev instanceof Identifier && mb_strlen($prev->name) > 1) {
                    $out .= ' ';
                }
                $out .= self::node($child);
            }
            $prev = $child;
        }

        return $out;
    }

    /** True for a node that reads as one token: no operator or space of its own to bind it into the text around it. */
    private static function atom(Node $n): bool
    {
        return match (true) {
            $n instanceof Identifier, $n instanceof Number, $n instanceof Fenced, $n instanceof Root, $n instanceof Scripts => true,
            $n instanceof Text => preg_match('/\s/u', $n->text) !== 1,
            $n instanceof Row => count($n->children) === 1 && self::atom($n->children[0]),
            default => false,
        };
    }

    private static function wrap(Node $n): string
    {
        $s = self::node($n);

        return self::atom($n) ? $s : '(' . $s . ')';
    }

    private static function fraction(Fraction $f): string
    {
        return self::wrap($f->numerator) . '/' . self::wrap($f->denominator);
    }

    private static function root(Root $r): string
    {
        $inner = self::wrap($r->radicand);
        if ($r->index === null) {
            return '√' . $inner;
        }
        $index = trim(self::node($r->index));

        return match ($index) {
            '3' => '∛' . $inner,
            '4' => '∜' . $inner,
            default => '(' . $index . ')√(' . trim(self::node($r->radicand)) . ')',
        };
    }

    private static function scripts(Scripts $s): string
    {
        $base = self::node($s->base);

        return $base . ($s->sub !== null ? self::script($s->sub, self::SUB, '_') : '') . ($s->sup !== null ? self::script($s->sup, self::SUP, '^') : '');
    }

    /** @param array<int|string, string> $map */
    private static function script(Node $n, array $map, string $mark): string
    {
        $s = str_replace(' ', '', self::node($n));
        $chars = mb_str_split($s);
        $letters = count(array_filter($chars, static fn (string $c): bool => preg_match('/\p{L}/u', $c) === 1));
        // More than one letter would read as a word in tiny letters, so that stays ^(kt).
        if ($s !== '' && $letters <= 1 && array_all($chars, static fn (string $c): bool => isset($map[$c]))) {
            return implode('', array_map(static fn (string $c): string => $map[$c], $chars));
        }

        return $mark . (self::atom($n) ? $s : '(' . trim(self::node($n)) . ')');
    }
}
