<?php

declare(strict_types=1);

namespace Papercut\Math\Font;

use Papercut\Math\Exception\UnsupportedFont;

/**
 * Minimal TrueType (glyf) reader: metrics, cmap (formats 4 and 12), outlines including composites,
 * and the OpenType MATH table. CFF outlines are not supported.
 */
final class TrueTypeFont
{
    private Binary $b;

    /** @var array<string, array{int, int}> tag => [offset, length] */
    private array $tables = [];

    public int $unitsPerEm;
    public int $numGlyphs;
    private int $locaFormat;
    private int $numHMetrics;

    /** @var list<array{int,int,int,int}> format 4 segments: end, start, delta, rangeOffsetPos(0 = none) */
    private array $seg4 = [];
    /** @var list<array{int,int,int}> format 12 groups: start, end, gid */
    private array $grp12 = [];

    /** @var array<int, list<list<array{float,float,bool}>>> */
    private array $contourCache = [];
    /** @var array<int, array{int,int,int,int}> */
    private array $bboxCache = [];
    private ?MathTable $math = null;
    private ?Gsub $gsub = null;

    private function __construct(string $data, string $path)
    {
        $this->b = new Binary($data);
        $tag = substr($data, 0, 4);
        if ($tag === 'OTTO') {
            throw UnsupportedFont::cff($path);
        }
        if ($tag === 'ttcf') {
            throw new UnsupportedFont($path, "$path is a font collection; pass a single font file");
        }
        $n = strlen($data) >= 12 ? $this->b->u16(4) : 0;
        if ($n === 0 || 12 + 16 * $n > strlen($data)) {
            throw new UnsupportedFont($path, "$path is not a TrueType/OpenType font");
        }
        for ($i = 0; $i < $n; $i++) {
            $o = 12 + $i * 16;
            $this->tables[substr($data, $o, 4)] = [$this->b->u32($o + 8), $this->b->u32($o + 12)];
        }
        if (isset($this->tables['CFF ']) || isset($this->tables['CFF2'])) {
            throw UnsupportedFont::cff($path);
        }
        if (! isset($this->tables['MATH'])) {
            throw UnsupportedFont::noMathTable($path);
        }
        foreach (['head', 'hhea', 'maxp', 'hmtx', 'cmap', 'loca', 'glyf'] as $t) {
            if (! isset($this->tables[$t])) {
                throw new \RuntimeException("Font has no $t table");
            }
        }
        $head = $this->tables['head'][0];
        $this->unitsPerEm = $this->b->u16($head + 18);
        $this->locaFormat = $this->b->i16($head + 50);
        $this->numGlyphs = $this->b->u16($this->tables['maxp'][0] + 4);
        $this->numHMetrics = $this->b->u16($this->tables['hhea'][0] + 34);
        $this->readCmap();
    }

    /** Fonts opened by fromFile(), by real path, so a font checked once (a config check, then the render) is parsed once. */
    private const int CACHED_FONTS = 4;

    /** @var array<string, array{int, int, self}> real path => modification time, size and font */
    private static array $opened = [];

    /**
     * Opens a font file. The same unchanged file gives the same instance within a process (the newest few are kept).
     *
     * @throws UnsupportedFont for a CFF font, a collection, a font without a MATH table, or a file that is no font
     */
    public static function fromFile(string $path): self
    {
        $real = realpath($path);
        $stat = $real === false ? false : @stat($real);
        if ($real !== false && $stat !== false && isset(self::$opened[$real]) && self::$opened[$real][0] === $stat['mtime'] && self::$opened[$real][1] === $stat['size']) {
            return self::$opened[$real][2];
        }
        $d = @file_get_contents($path);
        if ($d === false) {
            throw new \RuntimeException("Cannot read font $path");
        }
        $font = new self($d, $path);
        if ($real !== false && $stat !== false) {
            unset(self::$opened[$real]);
            self::$opened[$real] = [$stat['mtime'], $stat['size'], $font];
            self::$opened = array_slice(self::$opened, -self::CACHED_FONTS, null, true);
        }

        return $font;
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function math(): MathTable
    {
        if ($this->math === null) {
            $this->math = new MathTable($this->b, $this->tables['MATH'][0]);
        }

        return $this->math;
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function hasScriptAlternates(): bool
    {
        return isset($this->tables['GSUB']);
    }

    /**
     * The `ssty` alternate of a glyph for script level 1 or 2 (smaller, sturdier forms); the glyph itself otherwise.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function scriptVariant(int $gid, int $level): int
    {
        if ($level < 1 || ! isset($this->tables['GSUB'])) {
            return $gid;
        }
        $this->gsub ??= new Gsub($this->b, $this->tables['GSUB'][0]);

        return $this->gsub->variant($gid, $level);
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function advance(int $gid): int
    {
        $hm = $this->tables['hmtx'][0];
        $i = $gid < $this->numHMetrics ? $gid : $this->numHMetrics - 1;

        return $this->b->u16($hm + $i * 4);
    }

    private function readCmap(): void
    {
        $c = $this->tables['cmap'][0];
        $n = $this->b->u16($c + 2);
        $best = null;
        $bestRank = -1;
        for ($i = 0; $i < $n; $i++) {
            $pid = $this->b->u16($c + 4 + $i * 8);
            $eid = $this->b->u16($c + 6 + $i * 8);
            $off = $c + $this->b->u32($c + 8 + $i * 8);
            $fmt = $this->b->u16($off);
            $rank = match (true) {
                $fmt === 12 && (($pid === 3 && $eid === 10) || $pid === 0) => 4,
                $fmt === 4 && (($pid === 3 && $eid === 1) || $pid === 0) => 2,
                default => -1,
            };
            // Prefer a format 12 table, keep a format 4 table as the fallback.
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = [$fmt, $off];
            }
        }
        if ($best === null) {
            throw new \RuntimeException('No usable cmap (need format 4 or 12)');
        }
        [$fmt, $o] = $best;
        if ($fmt === 12) {
            $ng = $this->b->u32($o + 12);
            for ($g = 0; $g < $ng; $g++) {
                $p = $o + 16 + $g * 12;
                $this->grp12[] = [$this->b->u32($p), $this->b->u32($p + 4), $this->b->u32($p + 8)];
            }

            return;
        }
        $sc = intdiv($this->b->u16($o + 6), 2);
        $endP = $o + 14;
        $startP = $endP + $sc * 2 + 2;
        $deltaP = $startP + $sc * 2;
        $rangeP = $deltaP + $sc * 2;
        for ($s = 0; $s < $sc; $s++) {
            $ro = $this->b->u16($rangeP + $s * 2);
            $this->seg4[] = [
                $this->b->u16($endP + $s * 2),
                $this->b->u16($startP + $s * 2),
                $this->b->i16($deltaP + $s * 2),
                $ro === 0 ? 0 : $rangeP + $s * 2 + $ro,
            ];
        }
    }

    /** Glyph id for a codepoint, or 0 when the font has none. */
    public function gid(int $cp): int
    {
        if ($this->grp12 !== []) {
            $lo = 0;
            $hi = count($this->grp12) - 1;
            while ($lo <= $hi) {
                $mid = ($lo + $hi) >> 1;
                [$s, $e, $g] = $this->grp12[$mid];
                if ($cp < $s) {
                    $hi = $mid - 1;
                } elseif ($cp > $e) {
                    $lo = $mid + 1;
                } else {
                    return $g + $cp - $s;
                }
            }

            return 0;
        }
        foreach ($this->seg4 as [$end, $start, $delta, $rp]) {
            if ($cp > $end) {
                continue;
            }
            if ($cp < $start) {
                return 0;
            }
            if ($rp === 0) {
                return ($cp + $delta) & 0xFFFF;
            }
            $g = $this->b->u16($rp + ($cp - $start) * 2);

            return $g === 0 ? 0 : ($g + $delta) & 0xFFFF;
        }

        return 0;
    }

    /** @return array{int,int} [offset, length] of a glyph in glyf; length 0 = empty */
    private function glyphRange(int $gid): array
    {
        $loca = $this->tables['loca'][0];
        if ($this->locaFormat === 0) {
            $a = $this->b->u16($loca + $gid * 2) * 2;
            $z = $this->b->u16($loca + $gid * 2 + 2) * 2;
        } else {
            $a = $this->b->u32($loca + $gid * 4);
            $z = $this->b->u32($loca + $gid * 4 + 4);
        }

        return [$this->tables['glyf'][0] + $a, $z - $a];
    }

    /**
     * @return array{int,int,int,int} xMin, yMin, xMax, yMax in font units (zeros for empty glyphs)
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function bbox(int $gid): array
    {
        if (isset($this->bboxCache[$gid])) {
            return $this->bboxCache[$gid];
        }
        [$o, $len] = $this->glyphRange($gid);
        if ($len <= 0) {
            return $this->bboxCache[$gid] = [0, 0, 0, 0];
        }

        return $this->bboxCache[$gid] = [
            $this->b->i16($o + 2), $this->b->i16($o + 4), $this->b->i16($o + 6), $this->b->i16($o + 8),
        ];
    }

    /**
     * Outline as contours of [x, y, onCurve] points (quadratic TrueType semantics), font units, y up.
     *
     * @return list<list<array{float,float,bool}>>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function contours(int $gid, int $depth = 0): array
    {
        if (isset($this->contourCache[$gid])) {
            return $this->contourCache[$gid];
        }
        [$o, $len] = $this->glyphRange($gid);
        if ($len <= 0 || $depth > 6) {
            return $this->contourCache[$gid] = [];
        }
        $b = $this->b;
        $nc = $b->i16($o);
        $p = $o + 10;
        if ($nc >= 0) {
            return $this->contourCache[$gid] = $this->simple($nc, $p);
        }
        $out = [];
        do {
            $flags = $b->u16($p);
            $sub = $b->u16($p + 2);
            $p += 4;
            if ($flags & 1) {
                $dx = $b->i16($p);
                $dy = $b->i16($p + 2);
                $p += 4;
            } else {
                $dx = $b->u8($p) >= 128 ? $b->u8($p) - 256 : $b->u8($p);
                $dy = $b->u8($p + 1) >= 128 ? $b->u8($p + 1) - 256 : $b->u8($p + 1);
                $p += 2;
            }
            if (! ($flags & 2)) {
                $dx = $dy = 0; // point matching: unsupported
            }
            [$a, $bb, $c, $d] = [1.0, 0.0, 0.0, 1.0];
            if ($flags & 8) {
                $a = $d = $b->i16($p) / 16384;
                $p += 2;
            } elseif ($flags & 0x40) {
                $a = $b->i16($p) / 16384;
                $d = $b->i16($p + 2) / 16384;
                $p += 4;
            } elseif ($flags & 0x80) {
                $a = $b->i16($p) / 16384;
                $bb = $b->i16($p + 2) / 16384;
                $c = $b->i16($p + 4) / 16384;
                $d = $b->i16($p + 6) / 16384;
                $p += 8;
            }
            foreach ($this->contours($sub, $depth + 1) as $contour) {
                $nc2 = [];
                foreach ($contour as [$x, $y, $on]) {
                    $nc2[] = [$a * $x + $c * $y + $dx, $bb * $x + $d * $y + $dy, $on];
                }
                $out[] = $nc2;
            }
        } while ($flags & 0x20);

        return $this->contourCache[$gid] = $out;
    }

    /** @return list<list<array{float,float,bool}>> */
    private function simple(int $nc, int $p): array
    {
        $b = $this->b;
        if ($nc === 0) {
            return [];
        }
        $ends = [];
        for ($i = 0; $i < $nc; $i++) {
            $ends[] = $b->u16($p + $i * 2);
        }
        $p += $nc * 2;
        $np = ($ends[$nc - 1] ?? -1) + 1;
        $p += 2 + $b->u16($p); // instructions
        $flags = [];
        while (count($flags) < $np) {
            $f = $b->u8($p++);
            $flags[] = $f;
            if ($f & 8) {
                $r = $b->u8($p++);
                while ($r-- > 0) {
                    $flags[] = $f;
                }
            }
        }
        $xs = [];
        $x = 0;
        for ($i = 0; $i < $np; $i++) {
            $f = $flags[$i];
            if ($f & 2) {
                $v = $b->u8($p++);
                $x += ($f & 16) ? $v : -$v;
            } elseif (! ($f & 16)) {
                $x += $b->i16($p);
                $p += 2;
            }
            $xs[] = $x;
        }
        $ys = [];
        $y = 0;
        for ($i = 0; $i < $np; $i++) {
            $f = $flags[$i];
            if ($f & 4) {
                $v = $b->u8($p++);
                $y += ($f & 32) ? $v : -$v;
            } elseif (! ($f & 32)) {
                $y += $b->i16($p);
                $p += 2;
            }
            $ys[] = $y;
        }
        $contours = [];
        $start = 0;
        foreach ($ends as $e) {
            $c = [];
            for ($i = $start; $i <= $e; $i++) {
                $c[] = [(float) $xs[$i], (float) $ys[$i], (bool) ($flags[$i] & 1)];
            }
            $contours[] = $c;
            $start = $e + 1;
        }

        return $contours;
    }
}
