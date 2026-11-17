<?php

declare(strict_types=1);

namespace Quoin\Math\Font;

/**
 * SPIKE (throwaway): glyph outlines from an OpenType `CFF ` table. Parses the INDEX structures and runs
 * Type 2 charstrings (subroutines, hints, flex) to path segments, then derives exact bounds and quadratic contours.
 * Name-keyed fonts only; CID-keyed and CFF2 are refused.
 *
 * @internal
 */
final class CffOutlines
{
    /** @var list<array{int,int}> [offset, length] of each charstring */
    private array $charStrings;
    /** @var list<array{int,int}> */
    private array $gsubrs;
    /** @var list<array{int,int}> */
    private array $lsubrs = [];
    private int $gBias;
    private int $lBias = 107;

    /** @var array<int, list<array{string, list<float>}>> */
    private array $pathCache = [];

    public function __construct(private readonly Binary $b, private readonly int $base, private readonly int $length)
    {
        $hdr = $b->u8($base + 2);
        [$names, $p] = $this->index($base + $hdr);
        [$tops, $p] = $this->index($p);
        [, $p] = $this->index($p); // strings
        [$this->gsubrs] = $this->index($p);
        $this->gBias = self::bias(count($this->gsubrs));
        $top = $this->dict($tops[0][0], $tops[0][1]);
        if (isset($top['1230'])) {
            throw new \RuntimeException('CID-keyed CFF fonts are not supported yet');
        }
        [$this->charStrings] = $this->index($base + (int) $top['17'][0]);
        if (isset($top['18'])) {
            [$size, $off] = [(int) $top['18'][0], (int) $top['18'][1]];
            $priv = $this->dict($base + $off, $size);
            if (isset($priv['19'])) {
                [$this->lsubrs] = $this->index($base + $off + (int) $priv['19'][0]);
                $this->lBias = self::bias(count($this->lsubrs));
            }
        }
    }

    private static function bias(int $n): int
    {
        return $n < 1240 ? 107 : ($n < 33900 ? 1131 : 32768);
    }

    /** @return array{list<array{int,int}>, int} entries as absolute [offset, length], and the offset after the INDEX */
    private function index(int $p): array
    {
        $count = $this->b->u16($p);
        if ($count === 0) {
            return [[], $p + 2];
        }
        $offSize = $this->b->u8($p + 2);
        $read = fn (int $at): int => match ($offSize) {
            1 => $this->b->u8($at),
            2 => $this->b->u16($at),
            3 => ($this->b->u8($at) << 16) | $this->b->u16($at + 1),
            default => $this->b->u32($at),
        };
        $arr = $p + 3;
        $data = $arr + ($count + 1) * $offSize - 1;
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $a = $read($arr + $i * $offSize);
            $z = $read($arr + ($i + 1) * $offSize);
            $out[] = [$data + $a, $z - $a];
        }

        return [$out, $data + $read($arr + $count * $offSize)];
    }

    /** @return array<string, list<float>> operator ("12 30" as '1230') => operands */
    private function dict(int $p, int $len): array
    {
        $end = $p + $len;
        $stack = [];
        $out = [];
        while ($p < $end) {
            $b0 = $this->b->u8($p++);
            if ($b0 <= 21) {
                $op = $b0;
                if ($b0 === 12) {
                    $op = '12' . $this->b->u8($p++);
                }
                $out[(string) $op] = $stack;
                $stack = [];
            } elseif ($b0 === 28) {
                $stack[] = (float) $this->b->i16($p);
                $p += 2;
            } elseif ($b0 === 29) {
                $v = $this->b->u32($p);
                $stack[] = (float) ($v >= 0x80000000 ? $v - 0x100000000 : $v);
                $p += 4;
            } elseif ($b0 === 30) {
                do {
                    $n = $this->b->u8($p++);
                } while (($n & 0x0F) !== 0x0F && ($n >> 4) !== 0x0F && $p < $end);
                $stack[] = 0.0; // real operands (matrices, blue values) are not needed
            } elseif ($b0 >= 32 && $b0 <= 246) {
                $stack[] = (float) ($b0 - 139);
            } elseif ($b0 >= 247 && $b0 <= 250) {
                $stack[] = (float) (($b0 - 247) * 256 + $this->b->u8($p++) + 108);
            } elseif ($b0 >= 251 && $b0 <= 254) {
                $stack[] = (float) (-($b0 - 251) * 256 - $this->b->u8($p++) - 108);
            }
        }

        return $out;
    }

    /** @return list<array{string, list<float>}> segments: M x y | L x y | C x1 y1 x2 y2 x y | Z */
    public function path(int $gid): array
    {
        if (isset($this->pathCache[$gid])) {
            return $this->pathCache[$gid];
        }
        if (! isset($this->charStrings[$gid])) {
            return $this->pathCache[$gid] = [];
        }
        $st = (object) ['stack' => [], 'x' => 0.0, 'y' => 0.0, 'open' => false, 'stems' => 0, 'width' => false, 'done' => false, 'path' => []];
        [$o, $l] = $this->charStrings[$gid];
        $this->run($o, $l, $st, 0);
        if ($st->open) {
            $st->path[] = ['Z', []];
        }

        return $this->pathCache[$gid] = $st->path;
    }

    private function run(int $p, int $len, object $s, int $depth): void
    {
        if ($depth > 10) {
            return;
        }
        $end = $p + $len;
        $b = $this->b;
        while ($p < $end && ! $s->done) {
            $b0 = $b->u8($p++);
            if ($b0 >= 32 || $b0 === 28) {
                if ($b0 === 28) {
                    $s->stack[] = (float) $b->i16($p);
                    $p += 2;
                } elseif ($b0 <= 246) {
                    $s->stack[] = (float) ($b0 - 139);
                } elseif ($b0 <= 250) {
                    $s->stack[] = (float) (($b0 - 247) * 256 + $b->u8($p++) + 108);
                } elseif ($b0 <= 254) {
                    $s->stack[] = (float) (-($b0 - 251) * 256 - $b->u8($p++) - 108);
                } else {
                    $v = $b->u32($p);
                    $p += 4;
                    $s->stack[] = ($v >= 0x80000000 ? $v - 0x100000000 : $v) / 65536;
                }
                continue;
            }
            $a = &$s->stack;
            $n = count($a);
            switch ($b0) {
                case 1: case 3: case 18: case 23:
                    $this->takeWidth($s, $n % 2 === 1);
                    $s->stems += intdiv(count($s->stack), 2);
                    $s->stack = [];
                    break;
                case 19: case 20:
                    $this->takeWidth($s, $n % 2 === 1);
                    $s->stems += intdiv(count($s->stack), 2);
                    $s->stack = [];
                    $p += intdiv($s->stems + 7, 8);
                    break;
                case 21:
                    $this->takeWidth($s, $n > 2);
                    $this->moveTo($s, $s->stack[0] ?? 0.0, $s->stack[1] ?? 0.0);
                    break;
                case 22:
                    $this->takeWidth($s, $n > 1);
                    $this->moveTo($s, $s->stack[0] ?? 0.0, 0.0);
                    break;
                case 4:
                    $this->takeWidth($s, $n > 1);
                    $this->moveTo($s, 0.0, $s->stack[0] ?? 0.0);
                    break;
                case 5:
                    for ($i = 0; $i + 1 < $n; $i += 2) {
                        $this->line($s, $a[$i], $a[$i + 1]);
                    }
                    $s->stack = [];
                    break;
                case 6: case 7:
                    $h = $b0 === 6;
                    foreach ($a as $v) {
                        $h ? $this->line($s, $v, 0.0) : $this->line($s, 0.0, $v);
                        $h = ! $h;
                    }
                    $s->stack = [];
                    break;
                case 8:
                    for ($i = 0; $i + 5 < $n; $i += 6) {
                        $this->curve($s, $a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $a[$i + 4], $a[$i + 5]);
                    }
                    $s->stack = [];
                    break;
                case 24:
                    $i = 0;
                    for (; $i + 7 < $n; $i += 6) {
                        $this->curve($s, $a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $a[$i + 4], $a[$i + 5]);
                    }
                    $this->line($s, $a[$i], $a[$i + 1]);
                    $s->stack = [];
                    break;
                case 25:
                    $i = 0;
                    for (; $i + 7 < $n; $i += 2) {
                        $this->line($s, $a[$i], $a[$i + 1]);
                    }
                    $this->curve($s, $a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $a[$i + 4], $a[$i + 5]);
                    $s->stack = [];
                    break;
                case 26:
                    $i = 0;
                    $dx1 = 0.0;
                    if ($n % 4 === 1) {
                        $dx1 = $a[$i++];
                    }
                    for (; $i + 3 < $n; $i += 4) {
                        $this->curve($s, $dx1, $a[$i], $a[$i + 1], $a[$i + 2], 0.0, $a[$i + 3]);
                        $dx1 = 0.0;
                    }
                    $s->stack = [];
                    break;
                case 27:
                    $i = 0;
                    $dy1 = 0.0;
                    if ($n % 4 === 1) {
                        $dy1 = $a[$i++];
                    }
                    for (; $i + 3 < $n; $i += 4) {
                        $this->curve($s, $a[$i], $dy1, $a[$i + 1], $a[$i + 2], $a[$i + 3], 0.0);
                        $dy1 = 0.0;
                    }
                    $s->stack = [];
                    break;
                case 30: case 31:
                    $i = 0;
                    $horiz = $b0 === 31;
                    while ($i + 3 < $n) {
                        $last = ($n - $i === 5) ? $a[$i + 4] : 0.0;
                        if ($horiz) {
                            $this->curve($s, $a[$i], 0.0, $a[$i + 1], $a[$i + 2], $last, $a[$i + 3]);
                        } else {
                            $this->curve($s, 0.0, $a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $last);
                        }
                        $horiz = ! $horiz;
                        $i += 4;
                    }
                    $s->stack = [];
                    break;
                case 10: case 29:
                    $idx = (int) array_pop($s->stack) + ($b0 === 10 ? $this->lBias : $this->gBias);
                    $list = $b0 === 10 ? $this->lsubrs : $this->gsubrs;
                    if (isset($list[$idx])) {
                        $this->run($list[$idx][0], $list[$idx][1], $s, $depth + 1);
                    }
                    break;
                case 11:
                    return;
                case 14:
                    $this->takeWidth($s, $n === 1 || $n === 5);
                    if ($s->open) {
                        $s->path[] = ['Z', []];
                        $s->open = false;
                    }
                    $s->done = true;
                    return;
                case 12:
                    $b1 = $b->u8($p++);
                    $f = $s->stack;
                    match ($b1) {
                        35 => $this->flex($s, $f),
                        34 => $this->curveTwo($s, $f),
                        36 => $this->hflex1($s, $f),
                        37 => $this->flex1($s, $f),
                        default => null,
                    };
                    $s->stack = [];
                    break;
                default:
                    $s->stack = [];
            }
            unset($a);
        }
    }

    private function takeWidth(object $s, bool $has): void
    {
        if (! $s->width) {
            if ($has) {
                array_shift($s->stack);
            }
            $s->width = true;
        }
    }

    private function moveTo(object $s, float $dx, float $dy): void
    {
        if ($s->open) {
            $s->path[] = ['Z', []];
        }
        $s->x += $dx;
        $s->y += $dy;
        $s->path[] = ['M', [$s->x, $s->y]];
        $s->open = true;
        $s->stack = [];
    }

    private function line(object $s, float $dx, float $dy): void
    {
        $s->x += $dx;
        $s->y += $dy;
        $s->path[] = ['L', [$s->x, $s->y]];
    }

    private function curve(object $s, float $a, float $b, float $c, float $d, float $e, float $f): void
    {
        $x1 = $s->x + $a;
        $y1 = $s->y + $b;
        $x2 = $x1 + $c;
        $y2 = $y1 + $d;
        $s->x = $x2 + $e;
        $s->y = $y2 + $f;
        $s->path[] = ['C', [$x1, $y1, $x2, $y2, $s->x, $s->y]];
    }

    /** @param list<float> $f */
    private function flex(object $s, array $f): void
    {
        $this->curve($s, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5]);
        $this->curve($s, $f[6], $f[7], $f[8], $f[9], $f[10], $f[11]);
    }

    /** hflex: dx1 dx2 dy2 dx3 dx4 dx5 dx6 */
    private function curveTwo(object $s, array $f): void
    {
        $this->curve($s, $f[0], 0.0, $f[1], $f[2], $f[3], 0.0);
        $this->curve($s, $f[4], 0.0, $f[5], -$f[2], $f[6], 0.0);
    }

    /** hflex1: dx1 dy1 dx2 dy2 dx3 dx4 dx5 dy5 dx6 */
    private function hflex1(object $s, array $f): void
    {
        $this->curve($s, $f[0], $f[1], $f[2], $f[3], $f[4], 0.0);
        $this->curve($s, $f[5], 0.0, $f[6], $f[7], $f[8], -($f[1] + $f[3] + $f[7]));
    }

    /** flex1: dx1 dy1 dx2 dy2 dx3 dy3 dx4 dy4 dx5 dy5 d6 */
    private function flex1(object $s, array $f): void
    {
        $dx = $f[0] + $f[2] + $f[4] + $f[6] + $f[8];
        $dy = $f[1] + $f[3] + $f[5] + $f[7] + $f[9];
        $this->curve($s, $f[0], $f[1], $f[2], $f[3], $f[4], $f[5]);
        if (abs($dx) > abs($dy)) {
            $this->curve($s, $f[6], $f[7], $f[8], $f[9], $f[10], -$dy);
        } else {
            $this->curve($s, $f[6], $f[7], $f[8], $f[9], -$dx, $f[10]);
        }
    }

    /** @return array{int,int,int,int} exact bounds, rounded outwards to font units */
    public function bbox(int $gid): array
    {
        $xs = [];
        $ys = [];
        $cx = $cy = 0.0;
        foreach ($this->path($gid) as [$op, $v]) {
            if ($op === 'M' || $op === 'L') {
                [$cx, $cy] = $v;
                $xs[] = $cx;
                $ys[] = $cy;
            } elseif ($op === 'C') {
                foreach ([[$cx, $v[0], $v[2], $v[4]], [$cy, $v[1], $v[3], $v[5]]] as $axis => $p) {
                    foreach (self::extrema($p) as $t) {
                        $m = 1 - $t;
                        $val = $m ** 3 * $p[0] + 3 * $m * $m * $t * $p[1] + 3 * $m * $t * $t * $p[2] + $t ** 3 * $p[3];
                        $axis === 0 ? $xs[] = $val : $ys[] = $val;
                    }
                }
                [$cx, $cy] = [$v[4], $v[5]];
                $xs[] = $cx;
                $ys[] = $cy;
            }
        }
        if ($xs === []) {
            return [0, 0, 0, 0];
        }

        return [(int) floor(min($xs)), (int) floor(min($ys)), (int) ceil(max($xs)), (int) ceil(max($ys))];
    }

    /** @param array{float,float,float,float} $p @return list<float> t in (0,1) where a cubic's derivative is zero */
    private static function extrema(array $p): array
    {
        $a = -$p[0] + 3 * $p[1] - 3 * $p[2] + $p[3];
        $b = 2 * ($p[0] - 2 * $p[1] + $p[2]);
        $c = $p[1] - $p[0];
        $ts = [];
        if (abs($a) < 1e-12) {
            if (abs($b) > 1e-12) {
                $ts[] = -$c / $b;
            }
        } else {
            $d = $b * $b - 4 * $a * $c;
            if ($d >= 0) {
                $q = sqrt($d);
                $ts[] = (-$b + $q) / (2 * $a);
                $ts[] = (-$b - $q) / (2 * $a);
            }
        }

        return array_values(array_filter($ts, static fn (float $t): bool => $t > 0.0 && $t < 1.0));
    }

    /**
     * Contours in TrueType point form (quadratic): each cubic is approximated by two quadratics.
     *
     * @return list<list<array{float,float,bool}>>
     */
    public function contours(int $gid): array
    {
        $out = [];
        $cur = [];
        $cx = $cy = 0.0;
        foreach ($this->path($gid) as [$op, $v]) {
            if ($op === 'M') {
                if (count($cur) > 1) {
                    $out[] = $cur;
                }
                $cur = [[$v[0], $v[1], true]];
                [$cx, $cy] = $v;
            } elseif ($op === 'L') {
                $cur[] = [$v[0], $v[1], true];
                [$cx, $cy] = $v;
            } elseif ($op === 'C') {
                // Split at t = 0.5, then approximate each half by one quadratic whose control is (3(c1-p0)+3(c2-p3)... simplified).
                $p0 = [$cx, $cy];
                $p1 = [$v[0], $v[1]];
                $p2 = [$v[2], $v[3]];
                $p3 = [$v[4], $v[5]];
                $m01 = [($p0[0] + $p1[0]) / 2, ($p0[1] + $p1[1]) / 2];
                $m12 = [($p1[0] + $p2[0]) / 2, ($p1[1] + $p2[1]) / 2];
                $m23 = [($p2[0] + $p3[0]) / 2, ($p2[1] + $p3[1]) / 2];
                $a = [($m01[0] + $m12[0]) / 2, ($m01[1] + $m12[1]) / 2];
                $b = [($m12[0] + $m23[0]) / 2, ($m12[1] + $m23[1]) / 2];
                $mid = [($a[0] + $b[0]) / 2, ($a[1] + $b[1]) / 2];
                // Quadratic control for a cubic half: (3*c1 - p0 + 3*c2 - p3) / 4.
                $q1 = [(3 * $m01[0] - $p0[0] + 3 * $a[0] - $mid[0]) / 4, (3 * $m01[1] - $p0[1] + 3 * $a[1] - $mid[1]) / 4];
                $q2 = [(3 * $b[0] - $mid[0] + 3 * $m23[0] - $p3[0]) / 4, (3 * $b[1] - $mid[1] + 3 * $m23[1] - $p3[1]) / 4];
                $cur[] = [$q1[0], $q1[1], false];
                $cur[] = [$mid[0], $mid[1], true];
                $cur[] = [$q2[0], $q2[1], false];
                $cur[] = [$p3[0], $p3[1], true];
                [$cx, $cy] = $p3;
            }
        }
        if (count($cur) > 1) {
            $out[] = $cur;
        }

        return $out;
    }
}
