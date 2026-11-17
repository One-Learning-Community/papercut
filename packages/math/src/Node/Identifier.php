<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Run;
use Papercut\Math\Styles;
use Papercut\Math\Utf8;

/** mi: a variable. A single letter is drawn italic (Mathematical Italic codepoints); longer names and symbols upright. */
final class Identifier extends Node
{
    /** True when drawn upright: function names, multi-letter names and ordinary symbols. */
    public readonly bool $upright;

    public readonly string $name;

    /**
     * Invalid UTF-8 in $name is replaced silently here; the Math builders also record a note.
     *
     * $function marks a function name (sin, log, \operatorname{foo}): TeX's Op class, which takes a thin space after it and
     * before it next to an ordinary. A multi-letter upright name that is not a function (`\mathrm{Fe}`, `kg`) is a plain ordinary.
     */
    public function __construct(string $name, public readonly ?bool $italic = null, public readonly bool $function = false)
    {
        $this->name = Utf8::clean($name);
        $this->upright = $italic === null ? mb_strlen($this->name) > 1 : ! $italic;
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        $italic = ! $this->upright;
        $glyphs = [];
        foreach (mb_str_split($this->name) as $ch) {
            $glyphs[] = [$italic ? Styles::italic(mb_ord($ch)) : mb_ord($ch), $ch];
        }
        // Function names (sin, log) get a thin space after, so "sin x" does not run together. It is trailing space, as an
        // operator's is: a script sits before it.
        return Run::build($c, $glyphs, 0.0, $this->function && ! $italic && mb_strlen($this->name) > 1 ? $c->mu(3) : 0.0);
    }
}
