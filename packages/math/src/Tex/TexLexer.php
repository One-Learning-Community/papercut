<?php

declare(strict_types=1);

namespace Quoin\Math\Tex;

/**
 * LaTeX source to tokens. A token is [type, value, offset] with the offset in bytes: cs (control word or symbol,
 * without its backslash), ch (one character), sp (a whitespace run), `{` `}` `^` `_` `&`, nl (a `\\` row break) and
 * param (`#1`). Comments are dropped; spaces after a control word are eaten.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class TexLexer
{
    /** @return list<array{string, string, int}> */
    public static function lex(string $s): array
    {
        $chars = mb_str_split($s);
        $n = count($chars);
        // Byte offset of each character, for the offsets in tokens.
        $bytes = [];
        $b = 0;
        foreach ($chars as $k => $ch) {
            $bytes[$k] = $b;
            $b += strlen($ch);
        }
        $bytes[$n] = $b;
        $out = [];
        $i = 0;
        while ($i < $n) {
            $c = $chars[$i];
            $at = $bytes[$i];
            if ($c === '%') {
                while ($i < $n && $chars[$i] !== "\n") {
                    $i++;
                }
                $i++;
                continue;
            }
            if (self::isSpace($c)) {
                while ($i < $n && self::isSpace($chars[$i])) {
                    $i++;
                }
                $out[] = ['sp', ' ', $at];
                continue;
            }
            if ($c === '\\') {
                $i++;
                if ($i >= $n) {
                    break;
                }
                $d = $chars[$i];
                if (ctype_alpha($d)) {
                    $name = '';
                    while ($i < $n && ctype_alpha($chars[$i])) {
                        $name .= $chars[$i++];
                    }
                    while ($i < $n && self::isSpace($chars[$i])) {
                        $i++;
                    }
                    $out[] = ['cs', $name, $at];
                } elseif ($d === '\\') {
                    $i++;
                    $out[] = ['nl', '', $at];
                } else {
                    $i++;
                    $out[] = ['cs', $d, $at];
                }
                continue;
            }
            if ($c === '{' || $c === '}' || $c === '^' || $c === '_' || $c === '&') {
                $out[] = [$c, $c, $at];
                $i++;
                continue;
            }
            if ($c === '#' && $i + 1 < $n && ctype_digit($chars[$i + 1])) {
                $out[] = ['param', $chars[$i + 1], $at];
                $i += 2;
                continue;
            }
            $out[] = ['ch', $c, $at];
            $i++;
        }

        return $out;
    }

    private static function isSpace(string $c): bool
    {
        return ctype_space($c);
    }
}
