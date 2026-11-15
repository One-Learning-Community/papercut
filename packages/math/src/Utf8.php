<?php

declare(strict_types=1);

namespace Papercut\Math;

/**
 * Invalid UTF-8 in a builder string becomes U+FFFD. The twin of Papercut\Support\Utf8 in papercut/core, which this
 * package cannot depend on; keep the two in step.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class Utf8
{
    public const string NOTE = 'invalid UTF-8 replaced with U+FFFD';

    /** $text with invalid UTF-8 replaced by U+FFFD; $changed is set when anything was. */
    public static function scrub(string $text, bool &$changed): string
    {
        if (mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }
        $changed = true;
        $previous = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        try {
            return mb_scrub($text, 'UTF-8');
        } finally {
            mb_substitute_character($previous);
        }
    }

    /** $text, scrubbed without a note (for node constructors; the Math builders record the note). */
    public static function clean(string $text): string
    {
        $changed = false;

        return self::scrub($text, $changed);
    }
}
