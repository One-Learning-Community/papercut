<?php

declare(strict_types=1);

namespace Quoin\Math\Node;

use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;
use Quoin\Math\Layout\Run;
use Quoin\Math\Layout\Stretcher;
use Quoin\Math\Utf8;

/**
 * A delimiter inside a fence's content (TeX's \middle) that stretches to the fence's height, centered on the math
 * axis, with 3 mu of space on each side (none in scripts). Outside a Fenced it is the plain glyph.
 */
final class Middle extends Node
{
    public readonly string $char;

    /**
     * Invalid UTF-8 in $char is replaced silently here; the Math builders also record a note.
     *
     * @param ?float $height total height in pt to stretch to; set by Fenced only, null draws the plain glyph
     */
    public function __construct(string $char, private readonly ?float $height = null)
    {
        $this->char = Utf8::clean($char);
    }

    /**
     * Whether a Fenced has told this to stretch (the writers use it to tell the Middles that stretch from the plain ones).
     *
     * @internal Engine detail, not public API.
     */
    public function stretches(): bool
    {
        // Layout draws a glyph of several characters plain (nothing to stretch), so only one character counts.
        return $this->height !== null && mb_strlen($this->char) === 1;
    }

    /** @internal Engine detail, not public API. */
    public function layout(Context $c): Box
    {
        $space = $c->tight ? 0.0 : $c->mu(3);
        $gid = mb_strlen($this->char) === 1 ? $c->font->gid(mb_ord($this->char)) : 0;
        if ($this->height !== null && $gid !== 0) {
            return Stretcher::axisCentered($c, $this->char, $gid, $this->height / $c->unit(), $space, $space);
        }

        return Run::build($c, array_map(static fn (string $ch): array => [mb_ord($ch), $ch], mb_str_split($this->char)), $space, $space);
    }
}
