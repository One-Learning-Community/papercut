<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

/**
 * The part of GSUB that math layout needs: the `ssty` (script style) feature, read from single (type 1)
 * and alternate (type 3) substitution lookups, including ones wrapped in extension lookups (type 7).
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class Gsub
{
    /** @var array<int, list<int>> glyph id => alternates (index 0 is the level 1 form) */
    private array $alternates = [];

    public function __construct(Binary $b, int $base)
    {
        $featureList = $base + $b->u16($base + 6);
        $lookupList = $base + $b->u16($base + 8);

        $lookups = [];
        $nf = $b->u16($featureList);
        for ($i = 0; $i < $nf; $i++) {
            if (substr($b->d, $featureList + 2 + $i * 6, 4) !== 'ssty') {
                continue;
            }
            $f = $featureList + $b->u16($featureList + 6 + $i * 6);
            $nl = $b->u16($f + 2);
            for ($j = 0; $j < $nl; $j++) {
                $lookups[$b->u16($f + 4 + $j * 2)] = true;
            }
        }
        $count = $b->u16($lookupList);
        foreach (array_keys($lookups) as $li) {
            if ($li >= $count) {
                continue;
            }
            $l = $lookupList + $b->u16($lookupList + 2 + $li * 2);
            $type = $b->u16($l);
            $ns = $b->u16($l + 4);
            for ($s = 0; $s < $ns; $s++) {
                $sub = $l + $b->u16($l + 6 + $s * 2);
                $t = $type;
                if ($t === 7) { // extension: format, real type, 32-bit offset
                    $t = $b->u16($sub + 2);
                    $sub += $b->u32($sub + 4);
                }
                if ($t === 1 || $t === 3) {
                    $this->readSubtable($b, $sub, $t);
                }
            }
        }
    }

    /** The `ssty` alternate of a glyph for script level 1 or 2; the glyph itself when it has none. */
    public function variant(int $gid, int $level): int
    {
        $alts = $this->alternates[$gid] ?? null;
        if ($alts === null || $level < 1) {
            return $gid;
        }

        return $alts[min($level, count($alts)) - 1];
    }

    private function readSubtable(Binary $b, int $o, int $type): void
    {
        $format = $b->u16($o);
        $cov = Coverage::read($b, $o + $b->u16($o + 2));
        if ($type === 1) {
            if ($format === 1) {
                $delta = $b->i16($o + 4);
                foreach ($cov as $g) {
                    $this->alternates[$g] ??= [($g + $delta) & 0xFFFF];
                }
            } else {
                $count = $b->u16($o + 4);
                foreach ($cov as $i => $g) {
                    if ($i < $count) {
                        $this->alternates[$g] ??= [$b->u16($o + 6 + $i * 2)];
                    }
                }
            }

            return;
        }
        $count = $b->u16($o + 4);
        $size = strlen($b->d);
        foreach ($cov as $i => $g) {
            if ($i >= $count) {
                break;
            }
            $set = $o + $b->u16($o + 6 + $i * 2);
            // A set whose header or glyph list runs past the table is damaged: skip it.
            if ($set + 2 > $size || $set + 2 + 2 * $b->u16($set) > $size) {
                continue;
            }
            $n = $b->u16($set);
            $alts = [];
            for ($k = 0; $k < $n; $k++) {
                $alts[] = $b->u16($set + 2 + $k * 2);
            }
            if ($alts !== []) {
                $this->alternates[$g] ??= $alts;
            }
        }
    }
}
