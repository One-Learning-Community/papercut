<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Layout;

use Quoin\Math\Font\AssemblyPart;
use Quoin\Math\Font\Font;
use Quoin\Math\Layout\Stretcher;
use Quoin\Math\Math;
use PHPUnit\Framework\TestCase;

final class StretcherTest extends TestCase
{
    private function font(): Font
    {
        return Font::fromFile(Math::stixTwoMathPath());
    }

    public function test_horizontal_picks_variant(): void
    {
        $font = $this->font();
        $gid = $font->gid(0x0302);
        $con = $font->math()->horizontal($gid) ?? self::fail('no construction');
        $required = 2.0 * $con->variants[0][1];
        $r = Stretcher::horizontal($font, $gid, $required);
        self::assertCount(1, $r['pieces']);
        self::assertNotSame($gid, $r['pieces'][0][0]);
        self::assertGreaterThanOrEqual($required, $font->advance($r['pieces'][0][0]));
        $bb = $font->bbox($r['pieces'][0][0]);
        self::assertSame((float) $bb[0], $r['xmin']);
        self::assertSame((float) $bb[2], $r['xmax']);
    }

    public function test_unstretched_combining_accent_reports_its_ink_extent(): void
    {
        $font = $this->font();
        $gid = $font->gid(0x0302);
        $r = Stretcher::horizontal($font, $gid, 100.0);
        $bb = $font->bbox($gid);
        self::assertSame([[$gid, 0.0, 1.0]], $r['pieces']);
        self::assertSame((float) $bb[0], $r['xmin']);
        self::assertSame((float) $bb[2], $r['xmax']);
        self::assertLessThan(0.0, $r['xmin']);
    }

    public function test_assembly_extent_is_the_pieces_ink_extent(): void
    {
        $font = $this->font();
        $r = Stretcher::horizontal($font, $font->gid(0x2194), 5000.0);
        $min = INF;
        $max = -INF;
        foreach ($r['pieces'] as [$g, $x]) {
            $bb = $font->bbox($g);
            $min = min($min, $x + $bb[0]);
            $max = max($max, $x + $bb[2]);
        }
        self::assertSame($min, $r['xmin']);
        self::assertSame($max, $r['xmax']);
    }

    /**
     * The cap is the smaller connector at the joint, or the font minimum when a connector is shorter than that.
     *
     * @return list<array{int, float}> [cap, actual overlap] per joint
     */
    private function jointOverlaps(Font $font, int $gid, float $required): array
    {
        $r = Stretcher::horizontal($font, $gid, $required);
        $byGid = [];
        foreach ($font->math()->horizontal($gid)->parts ?? [] as $p) {
            $byGid[$p->gid] = $p;
        }
        $out = [];
        // A merged run is drawn once, scaled: its start is where the first repeat began and its end where the last one ended.
        $span = static function (array $piece) use ($font, $byGid): array {
            [$g, $x, $k] = $piece + [2 => 1.0];
            $bb = $font->bbox($g);
            $start = $x - $bb[0] + $bb[0] * $k;
            $ink = $bb[2] - $bb[0];

            return [$start, $start + ($k - 1.0) * $ink + $byGid[$g]->fullAdvance];
        };
        for ($i = 1; $i < count($r['pieces']); $i++) {
            $ng = $r['pieces'][$i][0];
            $pg = $r['pieces'][$i - 1][0];
            $overlap = $span($r['pieces'][$i - 1])[1] - $span($r['pieces'][$i])[0];
            $out[] = [max($font->math()->minConnectorOverlap, min($byGid[$pg]->endConnector, $byGid[$ng]->startConnector)), $overlap];
        }

        return $out;
    }

    /**
     * Origin-to-origin length of an assembly (ink extent is shorter by the end side bearings).
     *
     * @param list<array{int, float}> $pieces
     */
    private function runLength(Font $font, int $gid, array $pieces): float
    {
        $byGid = [];
        foreach ($font->math()->horizontal($gid)->parts ?? [] as $p) {
            $byGid[$p->gid] = $p;
        }
        [$lastGid, $lastX] = $pieces[count($pieces) - 1];

        return $lastX + $byGid[$lastGid]->fullAdvance - $pieces[0][1];
    }

    public function test_joint_overlaps_never_exceed_connectors(): void
    {
        $font = $this->font();
        foreach ([[0x2194, 1000.0], [0x23DE, 2700.0], [0x2192, 20000.0]] as [$cp, $req]) {
            $joints = $this->jointOverlaps($font, $font->gid($cp), $req);
            self::assertNotEmpty($joints);
            foreach ($joints as [$cap, $overlap]) {
                self::assertLessThanOrEqual($cap + 0.001, $overlap, sprintf('U+%04X', $cp));
                self::assertGreaterThanOrEqual($font->math()->minConnectorOverlap - 0.001, $overlap);
            }
        }
    }

    public function test_a_very_tall_parenthesis_is_a_handful_of_scaled_pieces(): void
    {
        $font = $this->font();
        $gid = $font->gid(0x28);
        $r = Stretcher::vertical($font, $gid, 100.0 * $font->unitsPerEm);
        self::assertLessThanOrEqual(5, count($r['pieces']));
        self::assertGreaterThan(1, count($r['pieces']));
        // Ink is where the unmerged assembly put it: bottom of the lowest piece at 0, top of the highest at ymax.
        $lo = INF;
        $hi = -INF;
        foreach ($r['pieces'] as $pc) {
            [$g, $off] = $pc;
            $k = $pc[2];
            $bb = $font->bbox($g);
            $lo = min($lo, $off + $bb[1] * $k);
            $hi = max($hi, $off + $bb[3] * $k);
        }
        self::assertEqualsWithDelta(0.0, $lo, 0.01);
        // The top of the ink is the top piece's side bearing (1 unit) below the full size, exactly where the unmerged assembly had it.
        self::assertEqualsWithDelta(100.0 * $font->unitsPerEm, $r['ymax'], 0.01);
        self::assertEqualsWithDelta(99999.0, $hi, 0.01);
    }

    public function test_a_stretched_arrow_scales_its_shaft_along_x(): void
    {
        $font = $this->font();
        $r = Stretcher::horizontal($font, $font->gid(0x2192), 20.0 * $font->unitsPerEm);
        self::assertLessThanOrEqual(5, count($r['pieces']));
        self::assertGreaterThan(1.0, max([1.0, ...array_map(static fn (array $p): float => $p[2], $r['pieces'])]));
        // The ink is shorter than the origin-to-origin length by the end bearings.
        self::assertEqualsWithDelta(20.0 * $font->unitsPerEm, $r['xmax'] - $r['xmin'], 0.1 * $font->unitsPerEm);
    }

    public function test_a_repeated_extender_that_is_not_straight_is_repeated_not_scaled(): void
    {
        $font = $this->font();
        // A letter stands in for an extender with curved sides: the font's own extenders are all straight.
        $gid = $font->gid(0x61);
        $part = new AssemblyPart($gid, 0, 0, $font->advance($gid), true);
        $placed = [[$part, 0.0], [$part, 400.0], [$part, 800.0]];
        $merge = new \ReflectionMethod(Stretcher::class, 'merge');
        $merged = $merge->invoke(null, $font, $placed, true);

        self::assertCount(3, $merged);
        self::assertSame([1.0, 1.0, 1.0], array_map(static fn (array $m): float => $m[2], $merged));
        // The same run of a straight extender is one piece.
        $bar = $font->math()->vertical($font->gid(0x28))->parts ?? self::fail('no parts');
        $ext = array_values(array_filter($bar, static fn (AssemblyPart $p): bool => $p->extender))[0] ?? self::fail('no extender');
        $one = $merge->invoke(null, $font, [[$ext, 0.0], [$ext, 300.0], [$ext, 600.0]], true);
        self::assertCount(1, $one);
        self::assertGreaterThan(1.0, $one[0][2]);
    }

    public function test_only_a_straight_glyph_may_be_scaled(): void
    {
        $font = $this->font();
        $bar = $font->math()->vertical($font->gid(0x28))->parts ?? self::fail('no parts');
        $ext = null;
        foreach ($bar as $p) {
            if ($p->extender) {
                $ext = $p->gid;
            }
        }
        self::assertTrue($font->straightAlong($ext ?? self::fail('no extender'), true));
        // A letter has curved sides along both axes: scaling it would distort it, so it must be repeated instead.
        self::assertFalse($font->straightAlong($font->gid(0x61), true));
        self::assertFalse($font->straightAlong($font->gid(0x61), false));
    }

    public function test_arrow_head_is_at_the_pointing_end(): void
    {
        $font = $this->font();
        $right = Stretcher::horizontal($font, $font->gid(0x2192), 20000.0);
        self::assertSame(1511, $right['pieces'][count($right['pieces']) - 1][0]);
        $left = Stretcher::horizontal($font, $font->gid(0x2190), 20000.0);
        $head = $font->math()->horizontal($font->gid(0x2190))->parts[0]->gid ?? self::fail('no parts');
        self::assertSame($head, $left['pieces'][0][0]);
    }

    public function test_a_very_long_stretch_still_reaches_required(): void
    {
        $font = $this->font();
        $required = 100.0 * $font->unitsPerEm;
        $gid = $font->gid(0x2192);
        $r = Stretcher::horizontal($font, $gid, $required);
        self::assertGreaterThanOrEqual($required - 1, $this->runLength($font, $gid, $r['pieces']));
        self::assertGreaterThan(0.0, $r['xmax'] - $r['xmin']);
    }

    public function test_horizontal_assembles_arrow(): void
    {
        $font = $this->font();
        $required = 20.0 * $font->unitsPerEm;
        $gid = $font->gid(0x2192);
        $r = Stretcher::horizontal($font, $gid, $required);
        self::assertGreaterThan(1, count($r['pieces']));
        self::assertGreaterThanOrEqual($required - 1, $this->runLength($font, $gid, $r['pieces']));
        self::assertGreaterThan(0.0, $r['xmax'] - $r['xmin']);
        self::assertGreaterThan(0.0, $r['ascent'] + $r['descent']);
        self::assertSame(0.0, $r['pieces'][0][1]);
    }

    public function test_horizontal_without_construction_returns_natural(): void
    {
        $font = $this->font();
        $gid = $font->gid(ord('x'));
        $r = Stretcher::horizontal($font, $gid, 10000.0);
        self::assertSame([[$gid, 0.0, 1.0]], $r['pieces']);
    }

    public function test_assembly_ink_extent_may_lie_wholly_above_the_baseline(): void
    {
        $font = $this->font();
        $r = Stretcher::horizontal($font, $font->gid(0x23DE), 20.0 * $font->unitsPerEm);
        self::assertGreaterThan(1, count($r['pieces']));
        // An overbrace's ink sits above the baseline: its depth below it is negative, not clamped to zero.
        self::assertLessThan(0.0, $r['descent']);
        self::assertGreaterThan(0.0, $r['ascent']);
    }

    public function test_a_stretch_request_is_capped(): void
    {
        $font = $this->font();
        $paren = $font->gid(0x28);
        $cap = Stretcher::vertical($font, $paren, Stretcher::MAX_STRETCH_EM * $font->unitsPerEm);
        $huge = Stretcher::vertical($font, $paren, 1e9);
        self::assertSame($cap, $huge);
        $arrow = $font->gid(0x2192);
        self::assertSame(
            Stretcher::horizontal($font, $arrow, Stretcher::MAX_STRETCH_EM * $font->unitsPerEm),
            Stretcher::horizontal($font, $arrow, 1e9),
        );
    }

    public function test_nested_fences_do_not_grow_without_bound(): void
    {
        memory_reset_peak_usage();
        $mem = memory_get_usage();
        $t = microtime(true);
        $n = Math::sup('x', '1');
        for ($i = 0; $i < 60; $i++) {
            $n = Math::fenced('(', ')', Math::sup($n, 'x'));
        }
        $box = Math::layout($n);
        self::assertLessThan(1.0, microtime(true) - $t);
        self::assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $mem);
        self::assertLessThan(60 * 100 * 12.0 * 2, $box->ascent + $box->descent);
    }
}
