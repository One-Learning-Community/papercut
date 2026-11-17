<?php

declare(strict_types=1);

namespace Quoin\Math\Font;

/**
 * Glyph outlines from an OpenType `CFF ` table. The reader parses the INDEX structures and the top and private dicts,
 * and runs each Type 2 charstring (subroutines, hints, flex) to path segments, from which it derives exact bounds and
 * quadratic contours. Name-keyed fonts only: CID-keyed fonts are refused, and `seac` and the arithmetic operators are
 * not read (a glyph using them draws without the part they would add).
 *
 * Every offset is checked against the table, so a malformed table throws an \UnexpectedValueException (which the font
 * reader turns into UnsupportedFont) and never reads outside the table or emits a PHP warning. The interpreter keeps
 * the Type 2 limits: operands past a stack of 48 are dropped, subroutines nest at most 10 deep, and one glyph runs at
 * most MAX_OPERATORS operators, so a self-calling subroutine ends.
 *
 * It keeps no per-glyph cache; the font reader caches bounds, contours and path data.
 *
 * @internal Engine detail, not public API.
 */
final class CffOutlines
{
    private const int MAX_STACK = 48;
    private const int MAX_DEPTH = 10;
    private const int MAX_OPERATORS = 20000;

    /** Dict operators, as the operator number; the two-byte ones are 1200 + the second byte. */
    private const int OP_CHARSTRINGS = 17;
    private const int OP_PRIVATE = 18;
    private const int OP_SUBRS = 19;
    private const int OP_FONT_MATRIX = 1207;
    private const int OP_ROS = 1230;

    /** @var list<array{int,int}> [offset, length] of each charstring */
    private array $charStrings;
    /** @var list<array{int,int}> */
    private array $gsubrs;
    /** @var list<array{int,int}> */
    private array $lsubrs = [];
    private int $gBias;
    private int $lBias = 107;
    private readonly int $limit;

    // Interpreter state for the glyph being run.
    /** @var list<float> */
    private array $stack = [];
    private float $x = 0.0;
    private float $y = 0.0;
    private bool $open = false;
    private int $stems = 0;
    private bool $widthTaken = false;
    private bool $done = false;
    private int $budget = 0;
    /** @var list<array{string, list<float>}> */
    private array $segments = [];

    /**
     * @param int $base offset of the `CFF ` table in the file
     * @param int $length its length
     * @param int $unitsPerEm from `head`, which the font matrix must agree with
     * @param int $numGlyphs from `maxp`, which the charstring count must equal
     *
     * @throws \UnexpectedValueException for a table this reader cannot use, with the reason
     */
    public function __construct(private readonly Binary $b, private readonly int $base, int $length, int $unitsPerEm, int $numGlyphs)
    {
        $this->limit = $base + $length;
        if ($length < 4 || $this->limit > strlen($b->d)) {
            throw new \UnexpectedValueException('its CFF table lies outside the file');
        }
        if ($b->u8($base) !== 1) {
            throw new \UnexpectedValueException('its CFF table has an unsupported version');
        }
        [, $p] = $this->index($base + $b->u8($base + 2));
        [$tops, $p] = $this->index($p);
        [, $p] = $this->index($p); // strings
        [$this->gsubrs] = $this->index($p);
        $this->gBias = self::bias(count($this->gsubrs));
        if ($tops === []) {
            throw new \UnexpectedValueException('its CFF table has no top dict');
        }
        $top = $this->dict($tops[0][0], $tops[0][1]);
        if (isset($top[self::OP_ROS])) {
            throw new \UnexpectedValueException('it is a CID-keyed CFF font, which is not supported');
        }
        $this->checkMatrix($top[self::OP_FONT_MATRIX] ?? null, $unitsPerEm);
        if (! isset($top[self::OP_CHARSTRINGS][0])) {
            throw new \UnexpectedValueException('its CFF table has no CharStrings');
        }
        [$this->charStrings] = $this->index($this->offset($top[self::OP_CHARSTRINGS][0]));
        if (count($this->charStrings) !== $numGlyphs) {
            throw new \UnexpectedValueException('its CFF table has ' . count($this->charStrings) . " glyphs but maxp says $numGlyphs");
        }
        if (isset($top[self::OP_PRIVATE][1])) {
            $size = (int) $top[self::OP_PRIVATE][0];
            $off = $this->offset($top[self::OP_PRIVATE][1]);
            $priv = $this->dict($off, $size);
            if (isset($priv[self::OP_SUBRS][0])) {
                [$this->lsubrs] = $this->index($off + (int) $priv[self::OP_SUBRS][0]);
                $this->lBias = self::bias(count($this->lsubrs));
            }
        }
    }

    private static function bias(int $n): int
    {
        return $n < 1240 ? 107 : ($n < 33900 ? 1131 : 32768);
    }

    /** A dict operand as an absolute offset in the file, which must lie inside the table. */
    private function offset(float $relative): int
    {
        $at = $this->base + (int) $relative;
        if ($relative < 0 || $at > $this->limit) {
            throw new \UnexpectedValueException('an offset in its CFF table lies outside the table');
        }

        return $at;
    }

    /**
     * The font matrix, when the dict has one, must be the plain scale 1 / unitsPerEm; the default is 0.001.
     *
     * @param ?list<float> $m
     */
    private function checkMatrix(?array $m, int $unitsPerEm): void
    {
        $m ??= [0.001, 0.0, 0.0, 0.001, 0.0, 0.0];
        $scale = 1 / max(1, $unitsPerEm);
        $ok = count($m) === 6
            && abs($m[0] - $scale) <= $scale * 1e-3 && abs($m[3] - $scale) <= $scale * 1e-3
            && $m[1] == 0.0 && $m[2] == 0.0;
        if (! $ok) {
            throw new \UnexpectedValueException("its CFF font matrix does not match its $unitsPerEm units per em");
        }
    }

    /** @return array{list<array{int,int}>, int} entries as absolute [offset, length], and the offset after the INDEX */
    private function index(int $p): array
    {
        if ($p < $this->base || $p + 2 > $this->limit) {
            throw new \UnexpectedValueException('an INDEX in its CFF table lies outside the table');
        }
        $count = $this->b->u16($p);
        if ($count === 0) {
            return [[], $p + 2];
        }
        if ($p + 3 > $this->limit) {
            throw new \UnexpectedValueException('an INDEX in its CFF table lies outside the table');
        }
        $offSize = $this->b->u8($p + 2);
        if ($offSize < 1 || $offSize > 4) {
            throw new \UnexpectedValueException('an INDEX in its CFF table has an invalid offset size');
        }
        $arr = $p + 3;
        $data = $arr + ($count + 1) * $offSize - 1;
        if ($data >= $this->limit) {
            throw new \UnexpectedValueException('an INDEX in its CFF table lies outside the table');
        }
        $read = fn (int $at): int => match ($offSize) {
            1 => $this->b->u8($at),
            2 => $this->b->u16($at),
            3 => ($this->b->u8($at) << 16) | $this->b->u16($at + 1),
            default => $this->b->u32($at),
        };
        $out = [];
        $a = $read($arr);
        for ($i = 0; $i < $count; $i++) {
            $z = $read($arr + ($i + 1) * $offSize);
            if ($a < 1 || $z < $a || $data + $z > $this->limit) {
                throw new \UnexpectedValueException('an INDEX in its CFF table has offsets outside the table');
            }
            $out[] = [$data + $a, $z - $a];
            $a = $z;
        }

        return [$out, $data + $a];
    }

    /** @return array<int, list<float>> operator (two-byte operators as 1200 + the second byte) => operands */
    private function dict(int $p, int $len): array
    {
        $end = $p + $len;
        if ($len < 0 || $p < $this->base || $end > $this->limit) {
            throw new \UnexpectedValueException('a dict in its CFF table lies outside the table');
        }
        $b = $this->b;
        $stack = [];
        $out = [];
        while ($p < $end) {
            $b0 = $b->u8($p++);
            $need = match (true) {
                $b0 === 12, $b0 >= 247 && $b0 <= 254 => 1,
                $b0 === 28 => 2,
                $b0 === 29 => 4,
                default => 0,
            };
            if ($p + $need > $end) {
                throw new \UnexpectedValueException('a dict in its CFF table ends inside an operand');
            }
            if ($b0 <= 21) {
                $op = $b0 === 12 ? 1200 + $b->u8($p++) : $b0;
                $out[$op] = $stack;
                $stack = [];
                continue;
            }
            if ($b0 === 30) {
                [$v, $p] = $this->real($p, $end);
            } elseif ($b0 === 28) {
                $v = (float) $b->i16($p);
                $p += 2;
            } elseif ($b0 === 29) {
                $u = $b->u32($p);
                $v = (float) ($u >= 0x80000000 ? $u - 0x100000000 : $u);
                $p += 4;
            } elseif ($b0 >= 32 && $b0 <= 246) {
                $v = (float) ($b0 - 139);
            } elseif ($b0 >= 247 && $b0 <= 250) {
                $v = (float) (($b0 - 247) * 256 + $b->u8($p++) + 108);
            } elseif ($b0 >= 251 && $b0 <= 254) {
                $v = (float) (-($b0 - 251) * 256 - $b->u8($p++) - 108);
            } else {
                continue; // reserved byte
            }
            if (count($stack) < self::MAX_STACK) {
                $stack[] = $v;
            }
        }

        return $out;
    }

    /**
     * A nibble-encoded real number: digits, '.', 'E', 'E-', '-', and 0xF to end.
     *
     * @return array{float, int} the value and the offset after it
     */
    private function real(int $p, int $end): array
    {
        $s = '';
        $table = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', 'E', 'E-', '', '-'];
        while ($p < $end) {
            $byte = $this->b->u8($p++);
            foreach ([$byte >> 4, $byte & 0x0F] as $nibble) {
                if ($nibble === 0x0F) {
                    break 2;
                }
                $s .= $table[$nibble] ?? '';
            }
        }

        return [is_numeric($s) ? (float) $s : 0.0, $p];
    }

    /** @return list<array{string, list<float>}> segments: M x y | L x y | C x1 y1 x2 y2 x y | Z */
    public function path(int $gid): array
    {
        if (! isset($this->charStrings[$gid])) {
            return [];
        }
        $this->stack = [];
        $this->x = $this->y = 0.0;
        $this->open = false;
        $this->stems = 0;
        $this->widthTaken = false;
        $this->done = false;
        $this->budget = self::MAX_OPERATORS;
        $this->segments = [];
        $this->run($this->charStrings[$gid][0], $this->charStrings[$gid][1], 0);
        if ($this->open) {
            $this->segments[] = ['Z', []];
        }
        $out = $this->segments;
        $this->segments = [];

        return $out;
    }

    private function push(float $v): void
    {
        if (count($this->stack) < self::MAX_STACK) {
            $this->stack[] = $v;
        }
    }

    private function run(int $p, int $len, int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            return;
        }
        $end = $p + $len;
        $b = $this->b;
        while ($p < $end && ! $this->done) {
            $b0 = $b->u8($p++);
            if ($b0 >= 32 || $b0 === 28) {
                $need = $b0 === 28 ? 2 : ($b0 <= 246 ? 0 : ($b0 <= 254 ? 1 : 4));
                if ($p + $need > $end) {
                    return; // an operand cut off by the end of the charstring
                }
                if ($b0 === 28) {
                    $this->push((float) $b->i16($p));
                    $p += 2;
                } elseif ($b0 <= 246) {
                    $this->push((float) ($b0 - 139));
                } elseif ($b0 <= 250) {
                    $this->push((float) (($b0 - 247) * 256 + $b->u8($p++) + 108));
                } elseif ($b0 <= 254) {
                    $this->push((float) (-($b0 - 251) * 256 - $b->u8($p++) - 108));
                } else {
                    $v = $b->u32($p);
                    $p += 4;
                    $this->push(($v >= 0x80000000 ? $v - 0x100000000 : $v) / 65536);
                }
                continue;
            }
            if (--$this->budget < 0) {
                $this->done = true;

                return;
            }
            $a = $this->stack;
            $n = count($a);
            switch ($b0) {
                case 1:
                case 3:
                case 18:
                case 23:
                    $this->takeWidth($n % 2 === 1);
                    $this->stems += intdiv(count($this->stack), 2);
                    $this->stack = [];
                    break;
                case 19:
                case 20:
                    $this->takeWidth($n % 2 === 1);
                    $this->stems += intdiv(count($this->stack), 2);
                    $this->stack = [];
                    $p = min($end, $p + intdiv($this->stems + 7, 8));
                    break;
                case 21:
                    $this->takeWidth($n > 2);
                    $this->moveTo($this->stack[0] ?? 0.0, $this->stack[1] ?? 0.0);
                    break;
                case 22:
                    $this->takeWidth($n > 1);
                    $this->moveTo($this->stack[0] ?? 0.0, 0.0);
                    break;
                case 4:
                    $this->takeWidth($n > 1);
                    $this->moveTo(0.0, $this->stack[0] ?? 0.0);
                    break;
                case 5:
                    for ($i = 0; $i + 1 < $n; $i += 2) {
                        $this->line($a[$i], $a[$i + 1]);
                    }
                    $this->stack = [];
                    break;
                case 6:
                case 7:
                    $h = $b0 === 6;
                    foreach ($a as $v) {
                        $h ? $this->line($v, 0.0) : $this->line(0.0, $v);
                        $h = ! $h;
                    }
                    $this->stack = [];
                    break;
                case 8:
                    for ($i = 0; $i + 5 < $n; $i += 6) {
                        $this->curve($a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $a[$i + 4], $a[$i + 5]);
                    }
                    $this->stack = [];
                    break;
                case 24:
                    $i = 0;
                    for (; $i + 7 < $n; $i += 6) {
                        $this->curve($a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $a[$i + 4], $a[$i + 5]);
                    }
                    if ($i + 1 < $n) {
                        $this->line($a[$i], $a[$i + 1]);
                    }
                    $this->stack = [];
                    break;
                case 25:
                    $i = 0;
                    for (; $i + 7 < $n; $i += 2) {
                        $this->line($a[$i], $a[$i + 1]);
                    }
                    if ($i + 5 < $n) {
                        $this->curve($a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $a[$i + 4], $a[$i + 5]);
                    }
                    $this->stack = [];
                    break;
                case 26:
                    $i = 0;
                    $dx1 = 0.0;
                    if ($n % 4 === 1) {
                        $dx1 = $a[$i++];
                    }
                    for (; $i + 3 < $n; $i += 4) {
                        $this->curve($dx1, $a[$i], $a[$i + 1], $a[$i + 2], 0.0, $a[$i + 3]);
                        $dx1 = 0.0;
                    }
                    $this->stack = [];
                    break;
                case 27:
                    $i = 0;
                    $dy1 = 0.0;
                    if ($n % 4 === 1) {
                        $dy1 = $a[$i++];
                    }
                    for (; $i + 3 < $n; $i += 4) {
                        $this->curve($a[$i], $dy1, $a[$i + 1], $a[$i + 2], $a[$i + 3], 0.0);
                        $dy1 = 0.0;
                    }
                    $this->stack = [];
                    break;
                case 30:
                case 31:
                    $i = 0;
                    $horiz = $b0 === 31;
                    while ($i + 3 < $n) {
                        $last = ($n - $i === 5) ? $a[$i + 4] : 0.0;
                        if ($horiz) {
                            $this->curve($a[$i], 0.0, $a[$i + 1], $a[$i + 2], $last, $a[$i + 3]);
                        } else {
                            $this->curve(0.0, $a[$i], $a[$i + 1], $a[$i + 2], $a[$i + 3], $last);
                        }
                        $horiz = ! $horiz;
                        $i += 4;
                    }
                    $this->stack = [];
                    break;
                case 10:
                case 29:
                    if ($n === 0) {
                        break;
                    }
                    $idx = (int) array_pop($this->stack) + ($b0 === 10 ? $this->lBias : $this->gBias);
                    $list = $b0 === 10 ? $this->lsubrs : $this->gsubrs;
                    if (isset($list[$idx])) {
                        $this->run($list[$idx][0], $list[$idx][1], $depth + 1);
                    }
                    break;
                case 11:
                    return;
                case 14:
                    $this->takeWidth($n === 1 || $n === 5);
                    if ($this->open) {
                        $this->segments[] = ['Z', []];
                        $this->open = false;
                    }
                    $this->done = true;

                    return;
                case 12:
                    if ($p >= $end) {
                        return;
                    }
                    $b1 = $b->u8($p++);
                    match (true) {
                        $b1 === 35 && $n >= 12 => $this->flex($a),
                        $b1 === 34 && $n >= 7 => $this->hflex($a),
                        $b1 === 36 && $n >= 9 => $this->hflex1($a),
                        $b1 === 37 && $n >= 11 => $this->flex1($a),
                        default => null,
                    };
                    $this->stack = [];
                    break;
                default:
                    $this->stack = [];
            }
        }
    }

    /** The first stack-clearing operator of a glyph may carry the advance width as an extra first operand. */
    private function takeWidth(bool $has): void
    {
        if (! $this->widthTaken) {
            if ($has) {
                array_shift($this->stack);
            }
            $this->widthTaken = true;
        }
    }

    private function moveTo(float $dx, float $dy): void
    {
        if ($this->open) {
            $this->segments[] = ['Z', []];
        }
        $this->x += $dx;
        $this->y += $dy;
        $this->segments[] = ['M', [$this->x, $this->y]];
        $this->open = true;
        $this->stack = [];
    }

    private function line(float $dx, float $dy): void
    {
        $this->x += $dx;
        $this->y += $dy;
        $this->segments[] = ['L', [$this->x, $this->y]];
    }

    private function curve(float $a, float $b, float $c, float $d, float $e, float $f): void
    {
        $x1 = $this->x + $a;
        $y1 = $this->y + $b;
        $x2 = $x1 + $c;
        $y2 = $y1 + $d;
        $this->x = $x2 + $e;
        $this->y = $y2 + $f;
        $this->segments[] = ['C', [$x1, $y1, $x2, $y2, $this->x, $this->y]];
    }

    /**
     * flex: dx1 dy1 dx2 dy2 dx3 dy3 dx4 dy4 dx5 dy5 dx6 dy6 fd
     *
     * @param list<float> $f
     */
    private function flex(array $f): void
    {
        $this->curve($f[0], $f[1], $f[2], $f[3], $f[4], $f[5]);
        $this->curve($f[6], $f[7], $f[8], $f[9], $f[10], $f[11]);
    }

    /**
     * hflex: dx1 dx2 dy2 dx3 dx4 dx5 dx6
     *
     * @param list<float> $f
     */
    private function hflex(array $f): void
    {
        $this->curve($f[0], 0.0, $f[1], $f[2], $f[3], 0.0);
        $this->curve($f[4], 0.0, $f[5], -$f[2], $f[6], 0.0);
    }

    /**
     * hflex1: dx1 dy1 dx2 dy2 dx3 dx4 dx5 dy5 dx6
     *
     * @param list<float> $f
     */
    private function hflex1(array $f): void
    {
        $this->curve($f[0], $f[1], $f[2], $f[3], $f[4], 0.0);
        $this->curve($f[5], 0.0, $f[6], $f[7], $f[8], -($f[1] + $f[3] + $f[7]));
    }

    /**
     * flex1: dx1 dy1 dx2 dy2 dx3 dy3 dx4 dy4 dx5 dy5 d6
     *
     * @param list<float> $f
     */
    private function flex1(array $f): void
    {
        $dx = $f[0] + $f[2] + $f[4] + $f[6] + $f[8];
        $dy = $f[1] + $f[3] + $f[5] + $f[7] + $f[9];
        $this->curve($f[0], $f[1], $f[2], $f[3], $f[4], $f[5]);
        if (abs($dx) > abs($dy)) {
            $this->curve($f[6], $f[7], $f[8], $f[9], $f[10], -$dy);
        } else {
            $this->curve($f[6], $f[7], $f[8], $f[9], -$dx, $f[10]);
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
                foreach (self::extrema($cx, $v[0], $v[2], $v[4]) as $val) {
                    $xs[] = $val;
                }
                foreach (self::extrema($cy, $v[1], $v[3], $v[5]) as $val) {
                    $ys[] = $val;
                }
                [$cx, $cy] = [$v[4], $v[5]];
                $xs[] = $cx;
                $ys[] = $cy;
            }
        }
        if ($xs === [] || $ys === []) {
            return [0, 0, 0, 0];
        }

        return [(int) floor(min($xs)), (int) floor(min($ys)), (int) ceil(max($xs)), (int) ceil(max($ys))];
    }

    /** @return list<float> the values of one coordinate of a cubic, at the interior points where its derivative is zero */
    private static function extrema(float $p0, float $p1, float $p2, float $p3): array
    {
        $a = -$p0 + 3 * $p1 - 3 * $p2 + $p3;
        $b = 2 * ($p0 - 2 * $p1 + $p2);
        $c = $p1 - $p0;
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
        $out = [];
        foreach ($ts as $t) {
            if ($t > 0.0 && $t < 1.0) {
                $m = 1 - $t;
                $out[] = $m ** 3 * $p0 + 3 * $m * $m * $t * $p1 + 3 * $m * $t * $t * $p2 + $t ** 3 * $p3;
            }
        }

        return $out;
    }

    /**
     * Contours in TrueType point form (quadratic): each cubic is approximated by two quadratics. Fine for the readers
     * that look only at extremes and straightness (a straight cubic stays straight); not for drawing, which uses path().
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
                // Split at t = 0.5 (de Casteljau), then replace each half cubic P0 C1 C2 P3 by the quadratic with the
                // same end points and control (3 * (C1 + C2) - (P0 + P3)) / 4, which matches the cubic's end tangents.
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
