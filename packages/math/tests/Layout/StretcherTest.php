<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Layout;

use Papercut\Math\Font\TrueTypeFont;
use Papercut\Math\Layout\Stretcher;
use Papercut\Math\Math;
use PHPUnit\Framework\TestCase;

final class StretcherTest extends TestCase
{
    private function font(): TrueTypeFont
    {
        return TrueTypeFont::fromFile(Math::stixTwoMathPath());
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
        self::assertSame([[$gid, 0.0]], $r['pieces']);
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
    private function jointOverlaps(TrueTypeFont $font, int $gid, float $required): array
    {
        $r = Stretcher::horizontal($font, $gid, $required);
        $byGid = [];
        foreach ($font->math()->horizontal($gid)->parts ?? [] as $p) {
            $byGid[$p->gid] = $p;
        }
        $out = [];
        for ($i = 1; $i < count($r['pieces']); $i++) {
            [$pg, $px] = $r['pieces'][$i - 1];
            [$ng, $nx] = $r['pieces'][$i];
            $overlap = $px + $byGid[$pg]->fullAdvance - $nx;
            $out[] = [max($font->math()->minConnectorOverlap, min($byGid[$pg]->endConnector, $byGid[$ng]->startConnector)), $overlap];
        }

        return $out;
    }

    /**
     * Origin-to-origin length of an assembly (ink extent is shorter by the end side bearings).
     *
     * @param list<array{int, float}> $pieces
     */
    private function runLength(TrueTypeFont $font, int $gid, array $pieces): float
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
        self::assertSame([[$gid, 0.0]], $r['pieces']);
    }
}
