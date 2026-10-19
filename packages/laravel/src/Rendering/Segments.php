<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Components\HeaderRows;
use Papercut\TableCell;
use Papercut\TableRow;
use Papercut\Text;

final class Segments
{
    private const array PARENTS = [
        TableCell::class => ['td', 'tr'],
        TableRow::class => ['tr', 'table'],
        HeaderRows::class => ['thead', 'table'],
    ];

    /**
     * @param list<object|string> $segs
     * @param list<class-string> $allow table-structure classes (TableCell, TableRow, HeaderRows) this parent accepts
     * @return list<object>
     */
    public static function nodes(array $segs, string $tag, array $allow = []): array
    {
        $out = [];
        foreach ($segs as $seg) {
            if (is_string($seg)) {
                if (trim($seg) !== '') {
                    $out[] = Text::markup(trim($seg));
                }
            } else {
                foreach (self::PARENTS as $class => [$name, $parent]) {
                    if ($seg instanceof $class && !in_array($class, $allow, true)) {
                        throw new InvalidStructure("<x-pdf::$name> must be inside <x-pdf::$parent>");
                    }
                }
                $out[] = $seg;
            }
        }

        return $out;
    }

    /** @param list<object|string> $segs */
    public static function text(array $segs, string $tag): string
    {
        $text = '';
        foreach ($segs as $seg) {
            if (!is_string($seg)) {
                throw new InvalidStructure("<x-pdf::$tag> can only contain text and inline tags (<b>, <i>, <u>, <s>, <a>, <br>, <span>)");
            }
            $text .= $seg;
        }

        return $text;
    }
}
