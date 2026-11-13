<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

/** OpenType Coverage tables (format 1 glyph lists, format 2 ranges). */
final class Coverage
{
    /** @return list<int> glyph ids in coverage order (coverage index = list index) */
    public static function read(Binary $b, int $o): array
    {
        $fmt = $b->u16($o);
        $n = $b->u16($o + 2);
        $out = [];
        if ($fmt !== 1 && $fmt !== 2) {
            return [];
        }
        if ($fmt === 1) {
            for ($i = 0; $i < $n; $i++) {
                $out[] = $b->u16($o + 4 + $i * 2);
            }

            return $out;
        }
        for ($i = 0; $i < $n; $i++) {
            $s = $b->u16($o + 4 + $i * 6);
            $e = $b->u16($o + 6 + $i * 6);
            $idx = $b->u16($o + 8 + $i * 6);
            for ($g = $s; $g <= $e; $g++) {
                $out[$idx + $g - $s] = $g;
            }
        }
        ksort($out);

        return array_values($out);
    }
}
