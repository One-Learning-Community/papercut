<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests\Support;

use Papercut\Export\{ExportOptions, Fidelity, FidelityReport};
use Papercut\Node;
use Papercut\Style\{ComputedStyle, StyleResolver, Theme};
use Papercut\Tree\{Region, Visit};

/** Small helpers for mapper tests: a node's computed style, a visit, a recording Fidelity. */
trait Styles
{
    private static function styleOf(Node $node): ComputedStyle
    {
        $theme = Theme::default();

        return (new StyleResolver($theme))->resolve($node, ComputedStyle::root($theme));
    }

    private static function visit(string $path = 'page > body > text[0]', int $index = 0): Visit
    {
        return new Visit($path, $index, null, false, Region::Body);
    }

    private static function fidelity(bool $strict = false): Fidelity
    {
        return new Fidelity('docx', new ExportOptions(strict: $strict));
    }

    /** @return list<string> "level feature @ path" per report */
    private static function reports(Fidelity $f): array
    {
        return array_map(static fn (FidelityReport $r): string => $r->level->name . ' ' . $r->feature . ' @ ' . $r->path, $f->reports());
    }
}
