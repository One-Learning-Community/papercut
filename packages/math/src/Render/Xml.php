<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

/**
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 *
 * Escapes text for XML output, dropping the characters XML 1.0 cannot hold at all.
 */
final class Xml
{
    public static function text(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]|\x{FFFE}|\x{FFFF}/u', '', $s) ?? $s;

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
