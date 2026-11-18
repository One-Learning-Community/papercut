<?php

declare(strict_types=1);

namespace Quoin\Math\Render;

/**
 * @internal Engine detail, not public API. It may change in any release.
 *
 * Escapes text for XML output, dropping the characters XML 1.0 cannot hold at all.
 */
final class Xml
{
    public static function text(string $s): string
    {
        return htmlspecialchars(self::strip($s), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** $s without the characters XML 1.0 cannot hold at all (C0 controls other than tab, newline and return; U+FFFE; U+FFFF). */
    public static function strip(string $s): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]|\x{FFFE}|\x{FFFF}/u', '', $s) ?? $s;
    }

    /** $v to three places without trailing zeros, as SVG and HTML attributes carry numbers. */
    public static function number(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
