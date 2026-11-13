<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Run;
use Papercut\Math\Styles;

/** mi: a variable. A single letter is drawn italic (Mathematical Italic codepoints); longer names and symbols upright. */
final class Identifier extends Node
{
    /** True when drawn upright: function names, multi-letter names and ordinary symbols. */
    public readonly bool $upright;

    public function __construct(public readonly string $name, public readonly ?bool $italic = null)
    {
        $this->upright = $italic === null ? mb_strlen($name) > 1 : ! $italic;
    }

    public function layout(Context $c): Box
    {
        $italic = ! $this->upright;
        $glyphs = [];
        foreach (mb_str_split($this->name) as $ch) {
            $glyphs[] = [$italic ? Styles::italic(mb_ord($ch)) : mb_ord($ch), $ch];
        }
        $b = Run::build($c, $glyphs);
        // Multi-letter names (sin, log) get a thin space after, so "sin x" does not run together.
        if (! $italic && mb_strlen($this->name) > 1) {
            $b->width += $c->mu(3);
        }

        return $b;
    }
}
