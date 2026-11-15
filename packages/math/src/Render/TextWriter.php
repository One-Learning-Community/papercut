<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Phantom;
use Papercut\Math\Node\Root;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\Node\Space;
use Papercut\Math\Node\Text;
use Papercut\Math\Node\UnderOver;
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
            $n instanceof UnderOver => self::underOver($n),
            $n instanceof Fenced => $n->open . ($n->content instanceof Fraction && ! $n->content->bar ? self::stacked($n->content) : self::node($n->content)) . $n->close,
            $n instanceof Space => $n->em >= 0.15 ? ' ' : '',
            $n instanceof Phantom => '',
            $n instanceof Row => self::row($n),
            $n instanceof Fallback => $n->content === null ? '' : self::node($n->content),
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
                // A function name set apart from its operand: "sin x", not "sinx"; so is a body after limits.
                if (($prev instanceof Identifier && mb_strlen($prev->name) > 1) || ($prev !== null && self::endsInLimits($prev))) {
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
            $n instanceof Identifier, $n instanceof Number, $n instanceof Fenced, $n instanceof Root, $n instanceof Scripts, $n instanceof UnderOver => true,
            $n instanceof Fraction => ! $n->bar,
            $n instanceof Text => preg_match('/\s/u', $n->text) !== 1,
            $n instanceof Fallback => $n->content !== null && self::atom($n->content),
            $n instanceof Row => count($n->children) === 1 && self::atom($n->children[0]),
            default => false,
        };
    }

    private static function wrap(Node $n): string
    {
        $s = self::node($n);

        return self::atom($n) ? $s : '(' . $s . ')';
    }

    private static function stacked(Fraction $f): string
    {
        return trim(self::node($f->numerator)) . '; ' . trim(self::node($f->denominator));
    }

    private static function fraction(Fraction $f): string
    {
        if (! $f->bar) {
            return '(' . self::stacked($f) . ')';
        }

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

    /**
     * An accent or bar follows its base as a combining mark, a brace is dropped (its label reads as a limit), and other
     * parts read as limits: `∑_(i=1)^n`.
     */
    private static function underOver(UnderOver $u): string
    {
        $over = UnderOver::operatorOf($u->over);
        $under = UnderOver::operatorOf($u->under);
        $marks = '';
        $braced = false;
        $overPlain = $u->over;
        $underPlain = $u->under;
        if ($over !== null) {
            $isBar = in_array($over->char, UnderOver::BARS, true);
            if ($isBar || $u->accent) {
                $marks .= $isBar ? "\u{0304}" : (Styles::accentMark($over->char) ?? $over->char);
                $overPlain = null;
            } elseif (UnderOver::isBrace($over, true)) {
                $braced = true;
                $overPlain = null;
            }
        }
        if ($under !== null) {
            $isBar = in_array($under->char, UnderOver::BARS, true);
            if ($isBar || $u->accentUnder) {
                $marks .= $isBar ? "\u{0332}" : (Styles::accentMark($under->char) ?? $under->char);
                $underPlain = null;
            } elseif (UnderOver::isBrace($under, false)) {
                $braced = true;
                $underPlain = null;
            }
        }

        $base = $braced ? self::wrap($u->base) : trim(self::node($u->base));
        if ($marks !== '') {
            $base = (mb_strlen($base) === 1 ? $base : '(' . $base . ')') . $marks;
        }
        if ($underPlain === null && $overPlain === null) {
            return $base;
        }
        if ($marks === '' && ! $braced && ! self::atom($u->base) && UnderOver::operatorOf($u->base) === null) {
            $base = '(' . $base . ')';
        }

        return $base . ($underPlain !== null ? self::limit($underPlain, '_') : '') . ($overPlain !== null ? self::limit($overPlain, '^') : '');
    }

    /** A limit as `_(i=1)` or `^n`: always the marked form, with the spaces of math dropped. */
    private static function limit(Node $n, string $mark): string
    {
        $s = trim(self::node($n));
        if (! self::hasText($n)) {
            $s = str_replace(' ', '', $s);
        }

        return $mark . (self::atom($n) ? $s : '(' . $s . ')');
    }

    private static function hasText(Node $n): bool
    {
        return $n instanceof Text || array_any($n->children(), self::hasText(...));
    }

    /** True when the node ends in limits that read as text: a body after it needs a space. */
    private static function endsInLimits(Node $n): bool
    {
        if ($n instanceof Scripts) {
            $op = UnderOver::operatorOf($n->base);

            return $op !== null && $op->isLargeop(OperatorDictionary::FORM_PREFIX);
        }

        if (! ($n instanceof UnderOver)) {
            return false;
        }
        $limit = static function (?Node $part, bool $accent): bool {
            $op = UnderOver::operatorOf($part);

            return $part !== null && ! ($op !== null && ($accent || in_array($op->char, UnderOver::BARS, true) || UnderOver::isBrace($op, true) || UnderOver::isBrace($op, false)));
        };

        return $limit($n->under, $n->accentUnder) || $limit($n->over, $n->accent);
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
