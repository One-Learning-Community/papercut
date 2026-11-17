<?php

declare(strict_types=1);

namespace Papercut\Math;

use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;

/**
 * Plain string to mi/mn/mo nodes. Letters become identifiers (one per letter, italic), digit runs
 * with an optional decimal point become numbers, whitespace is dropped (spacing comes from the
 * operator dictionary), every other character is an operator. A known function name (sin, log...)
 * stays one upright identifier only when it is a whole run of letters: "sin x" and "sin(x)" are
 * functions, while "cost", "minutes" and "sinx" are single letters, so words in a formula are not split.
 * A run of Unicode superscript or subscript characters (², ₁, ⁻¹, ⁿ) becomes a real script on the token
 * before it, or on the whole bracketed group when that token closes one, as in "(a+b)²". With nothing
 * to attach to, the run stays a number drawn as the characters it is.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class Tokenizer
{
    public const FUNCTIONS = ['sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'sinh', 'cosh', 'tanh', 'log', 'ln', 'exp', 'lim', 'min', 'max', 'det', 'arcsin', 'arccos', 'arctan'];
    private const CLOSING = [')' => '(', ']' => '[', '}' => '{'];
    /** Symbols that behave as operands, not operators. */
    private const ORDINARY = '∞∂∇ℓℏ∅′″‴⁗°';
    private const BARS = ['|', '‖'];
    private const PRIMES = ['′', '″', '‴', '⁗'];
    private const OP_MAP = ['-' => '−', '*' => '∗'];

    /** @return list<Node> */
    public static function tokenize(string $s): array
    {
        $out = [];
        self::tokenizeInto($out, $s);

        return $out;
    }

    /**
     * Tokenizes $s and appends to $out, letting a script character at the start of $s attach to the last
     * node already in $out (as in Math::row(Math::frac('a', 'b'), '²')).
     *
     * @param list<Node> $out
     */
    public static function tokenizeInto(array &$out, string $s): void
    {
        $chars = mb_str_split($s);
        $n = count($chars);
        $i = 0;
        while ($i < $n) {
            $ch = $chars[$i];
            if (ctype_space($ch) || $ch === "\u{00A0}") {
                $i++;
            } elseif (ScriptChars::isScript($ch)) {
                $j = $i;
                while ($j < $n && ScriptChars::isScript($chars[$j])) {
                    $j++;
                }
                self::attachScripts($out, array_slice($chars, $i, $j - $i));
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
                $j = $i + 1;
                $script = self::letterScript($ch);
                while ($j < $n && $script !== null && self::letterScript($chars[$j]) === $script) {
                    $j++;
                }
                $run = array_slice($chars, $i, $j - $i);
                if (in_array(implode('', $run), self::FUNCTIONS, true)) {
                    $out[] = new Identifier(implode('', $run), false, true);
                } else {
                    foreach ($run as $letter) {
                        $out[] = new Identifier($letter);
                    }
                }
                $i = $j;
            } elseif (mb_strpos(self::ORDINARY, $ch) !== false) {
                $out[] = new Identifier($ch, false);
                $i++;
            } else {
                $out[] = new Operator(self::OP_MAP[$ch] ?? $ch);
                $i++;
            }
        }
    }

    /**
     * The Unicode script of a letter that can be part of a word ('Latin', 'Greek', 'Other'), or null for
     * modifier letters (category Lm) and script characters, which never join a run.
     */
    private static function letterScript(string $ch): ?string
    {
        if (! preg_match('/^\p{L}$/u', $ch) || preg_match('/^\p{Lm}$/u', $ch) || ScriptChars::isScript($ch)) {
            return null;
        }

        return preg_match('/^\p{Latin}$/u', $ch) ? 'Latin' : (preg_match('/^\p{Greek}$/u', $ch) ? 'Greek' : 'Other');
    }

    /**
     * Turns a run of script characters into msup/msub/msubsup on the last token (or bracketed group) in $out.
     *
     * @param list<Node> $out
     * @param list<string> $run
     */
    private static function attachScripts(array &$out, array $run): void
    {
        $base = self::popBase($out);
        if ($base === null) {
            $out[] = new Number(implode('', $run));

            return;
        }
        $sup = $sub = '';
        $usedSup = $usedSub = false;
        $last = null;
        $taken = 0;
        foreach ($run as $ch) {
            $plain = ScriptChars::superscript($ch);
            $isSup = $plain !== null;
            $plain ??= ScriptChars::subscript($ch) ?? '';
            // A kind that was already used and then interrupted by the other kind ends the run (x²₁³).
            if ($last !== null && $last !== $isSup && ($isSup ? $usedSup : $usedSub)) {
                break;
            }
            if ($isSup) {
                $sup .= $plain;
                $usedSup = true;
            } else {
                $sub .= $plain;
                $usedSub = true;
            }
            $last = $isSup;
            $taken++;
        }
        $out[] = new Scripts($base, self::script($sub), self::script($sup));
        if ($taken < count($run)) {
            self::attachScripts($out, array_slice($run, $taken));
        }
    }

    /**
     * Removes and returns the base a script attaches to: the last token, or the whole group when that token is a
     * closing bracket with a matching opener. Null (and $out untouched) when there is nothing to attach to.
     *
     * @param list<Node> $out
     */
    private static function popBase(array &$out): ?Node
    {
        $last = end($out);
        if ($last === false || $last instanceof Scripts) {
            return null;
        }
        if ($last instanceof Number && self::allScriptChars($last->text)) {
            return null;
        }
        if ($last instanceof Identifier && in_array($last->name, self::PRIMES, true)) {
            return self::popPrimed($out);
        }
        if (! $last instanceof Operator) {
            array_pop($out);

            return $last;
        }
        if (in_array($last->char, self::BARS, true)) {
            for ($k = count($out) - 2; $k >= 0; $k--) {
                $node = $out[$k];
                if ($node instanceof Operator && $node->char === $last->char) {
                    $group = array_slice($out, $k);
                    array_splice($out, $k);

                    return new Row($group);
                }
            }

            return null;
        }
        $open = self::CLOSING[$last->char] ?? null;
        if ($open === null) {
            return null;
        }
        $depth = 0;
        for ($k = count($out) - 1; $k >= 0; $k--) {
            $node = $out[$k];
            if ($node instanceof Operator && $node->char === $last->char) {
                $depth++;
            } elseif ($node instanceof Operator && $node->char === $open && --$depth === 0) {
                $group = array_slice($out, $k);
                array_splice($out, $k);

                return new Row($group);
            }
        }

        return null;
    }

    /**
     * The base for a script after primes: the token before the primes together with them, as in f′².
     * With nothing before the primes, the primes alone.
     *
     * @param list<Node> $out
     */
    private static function popPrimed(array &$out): Node
    {
        $primes = [];
        while (($last = end($out)) instanceof Identifier && in_array($last->name, self::PRIMES, true)) {
            array_pop($out);
            array_unshift($primes, $last);
        }
        $inner = self::popBase($out);
        if ($inner === null) {
            return count($primes) === 1 ? $primes[0] : new Row($primes);
        }

        return new Row([$inner, ...$primes]);
    }

    private static function allScriptChars(string $text): bool
    {
        foreach (mb_str_split($text) as $ch) {
            if (! ScriptChars::isScript($ch)) {
                return false;
            }
        }

        return $text !== '';
    }

    private static function script(string $ascii): ?Node
    {
        if ($ascii === '') {
            return null;
        }
        $nodes = self::tokenize($ascii);

        return count($nodes) === 1 ? $nodes[0] : new Row($nodes);
    }
}
