<?php

declare(strict_types=1);

namespace Papercut\Math\Mathml;

/**
 * Wiris/MathType "safeXml": MathML with its markup characters swapped for ones that survive HTML attributes and
 * plain-text fields (`«` `»` `¨` `§` and a backtick for `<` `>` `"` `&` `'`).
 */
final class SafeXml
{
    private const MAP = ['«' => '<', '»' => '>', '¨' => '"', '§' => '&', '`' => "'"];

    public static function decode(string $s): string
    {
        return strtr($s, self::MAP);
    }

    /**
     * Each `«math…«/math»` run in a text, as byte offset and length. `«math` must be followed by a space, `»` or `:`
     * (so «mathieu» is not a run); the close must carry the same prefix as the open (`«mml:math»` ends at `«/mml:math»`), and
     * a run never spans another opener, so an unterminated one is skipped.
     *
     * @return list<array{int, int}>
     */
    public static function find(string $text): array
    {
        $runs = [];
        $open = '«(?:[\w.-]+:)?math(?=[\s»:])';
        $re = '~«((?:[\w.-]+:)?)math(?=[\s»:])(?:(?!' . $open . ').)*?«/\1math»~isu';
        if (preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }
        foreach ($m[0] as [$found, $offset]) {
            $runs[] = [$offset, strlen($found)];
        }

        return $runs;
    }
}
