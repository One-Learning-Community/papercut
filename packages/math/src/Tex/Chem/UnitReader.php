<?php

declare(strict_types=1);

namespace Quoin\Math\Tex\Chem;

use Quoin\Math\NoteLevel;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Number;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Node\Space;
use Quoin\Math\Node\Text;

/**
 * Reads the argument of mhchem's `\pu` into the semantic tree: a number (with `e` exponents drawn as a power of ten),
 * then units as upright identifiers with exponents, products and slashes. A thin space separates the number from the
 * units and one unit from the next. Anything outside that grammar is drawn as its source text, with one note.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class UnitReader
{
    private const string NUMBER = '/^([+\-\x{2212}])?(?:([0-9]+(?:[.,][0-9]+)?|\.[0-9]+)(?:[eE]([+\-\x{2212}]?[0-9]+)|\^\{?([+\-\x{2212}]?[0-9]+)\}?)?)/u';

    /** Longest stretch of source quoted in a note. */
    private const int QUOTE = 40;

    /**
     * @param \Closure(NoteLevel, string, string): void $note records a note on the `\pu` command
     */
    public static function read(string $src, \Closure $note): Node
    {
        $s = trim($src);
        $out = self::parse($s);
        if ($out === null) {
            $note(NoteLevel::Approximated, '\\pu', 'not supported: ' . mb_substr($s, 0, self::QUOTE));

            return new Text($src);
        }

        return count($out) === 1 ? $out[0] : new Row($out);
    }

    /**
     * @return list<Node>|null null when the source is outside the grammar
     */
    private static function parse(string $s): ?array
    {
        $out = [];
        $rest = $s;
        if (preg_match(self::NUMBER, $rest, $m) === 1) {
            if ($m[1] !== '') {
                $out[] = new Operator($m[1] === '+' ? '+' : '−');
            }
            $out[] = new Number($m[2]);
            if (($m[3] ?? '') !== '') {
                $out[] = new Operator('⋅');
                $out[] = new Scripts(new Number('10'), null, self::signed($m[3]));
            } elseif (($m[4] ?? '') !== '') {
                $out[array_key_last($out)] = new Scripts(new Number($m[2]), null, self::signed($m[4]));
            }
            $rest = substr($rest, strlen($m[0]));
        }
        $units = self::units($rest);
        if ($units === null) {
            return null;
        }
        if ($out !== [] && $units !== [] && ! $units[0] instanceof Operator && ! $units[0] instanceof Space) {
            $out[] = new Space(1 / 3);
        }

        return [...$out, ...$units];
    }

    /**
     * Units as mhchem draws them: a thin space between units, a tight ordinary-spaced dot for a product, a slash
     * with thin spaces on either side after a numerator of several parts.
     *
     * @return list<Node>|null null when the source is outside the grammar, or ends on an operator
     */
    private static function units(string $s): ?array
    {
        $out = [];
        $space = false;
        $parts = 0;
        $operator = false;
        while ($s !== '') {
            if (preg_match('/^\s+/', $s, $m) === 1) {
                $space = true;
            } elseif (preg_match('/^[\/*.]/', $s, $m) === 1) {
                if ($m[0] === '/') {
                    $slash = new Operator('/');
                    array_push($out, ...($parts > 1 ? [new Space(1 / 6), $slash, new Space(1 / 6)] : [$slash]));
                    $parts = 0;
                } else {
                    array_push($out, new Space(1 / 18), new Operator('⋅', lspace: 0, rspace: 0), new Space(1 / 18));
                }
                $space = false;
                $operator = true;
            } elseif (preg_match('/^(?=°|\p{L})(?<deg>°)?(?<name>\p{L}*)(?:\^\{?([+\-\x{2212}]?[0-9]+)\}?|([+\-\x{2212}]?[0-9]+))?/u', $s, $m) === 1) {
                $exp = $m[3] ?? '';
                if ($exp === '') {
                    $exp = $m[4] ?? '';
                }
                if ($space && $out !== [] && ! $operator) {
                    $out[] = new Space(1 / 6);
                }
                $space = false;
                $operator = false;
                $parts++;
                $deg = $m['deg'] !== '';
                $name = $m['name'];
                // A degree sign is its own upright character in front of the unit letters, as `\mathrm{°C}` reads.
                if ($deg) {
                    $ring = new Identifier('°', false);
                    $out[] = $name === '' && $exp !== '' ? new Scripts($ring, null, self::signed($exp)) : $ring;
                }
                if ($name !== '') {
                    $base = new Identifier($name, false);
                    $out[] = $exp !== '' ? new Scripts($base, null, self::signed($exp)) : $base;
                }
            } else {
                return null;
            }
            $s = substr($s, strlen($m[0]));
        }

        return $operator ? null : $out;
    }

    private static function signed(string $digits): Node
    {
        $sign = $digits[0];
        if ($sign === '-' || $sign === '+' || str_starts_with($digits, '−')) {
            $num = new Number(ltrim($digits, '+-−'));

            return new Row([new Operator($sign === '+' ? '+' : '−'), $num]);
        }

        return new Number($digits);
    }
}
