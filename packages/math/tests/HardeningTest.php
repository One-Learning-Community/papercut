<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Math;
use Papercut\Math\Node\Space;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\OmmlWriter;
use PHPUnit\Framework\TestCase;

final class HardeningTest extends TestCase
{
    protected function setUp(): void
    {
        set_error_handler(static function (int $no, string $msg, string $file, int $line): never {
            throw new \ErrorException($msg, 0, $no, $file, $line);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function test_space_rejects_non_finite_numbers(): void
    {
        foreach ([INF, -INF, NAN] as $bad) {
            try {
                Math::space($bad);
                self::fail('expected an exception');
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('space', $e->getMessage());
            }
        }
    }

    public function test_space_is_clamped(): void
    {
        self::assertSame(100.0, Math::space(1e12)->em);
        self::assertSame(-100.0, Math::space(-1e300)->em);
        self::assertSame(2.5, Math::space(2.5)->em);
    }

    public function test_huge_mspace_is_clamped_with_one_note_and_exports(): void
    {
        foreach (['99999999999999em', '99999999999999999999em', '1000000in'] as $w) {
            $node = Math::mathml('<math><mspace width="' . $w . '"/><mspace width="-' . $w . '"/></math>');
            $notes = Math::notes($node);
            self::assertCount(1, $notes, $w);
            self::assertSame('mspace', $notes[0]->element);
            self::assertSame(\Papercut\Math\NoteLevel::Approximated, $notes[0]->level);
            self::assertLessThan(10_000, strlen(OmmlWriter::oMath($node)), $w);
            self::assertStringContainsString('width="100em"', MathmlWriter::write($node));
            self::assertLessThanOrEqual(100 * 12, Math::layout($node)->width);
        }
    }

    public function test_dropped_child_keeps_the_arity_in_mathml(): void
    {
        $xml = MathmlWriter::write(Math::mathml('<math><msup><mglyph/><mi>2</mi></msup></math>'));
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        self::assertSame(2.0, (new \DOMXPath($doc))->evaluate('count(//*[local-name()="msup"]/*)'));
        self::assertStringContainsString('<mrow></mrow>', $xml);
        self::assertStringContainsString('<mi>2</mi>', $xml);
        self::assertStringContainsString('<mfrac><mi>a</mi><mrow></mrow></mfrac>', MathmlWriter::write(Math::mathml('<math><mfrac><mi>a</mi><img src="x"/></mfrac></math>')));
    }

    public function test_mlabeledtr_reports_the_label(): void
    {
        $node = Math::mathml('<math><mtable><mlabeledtr><mtd><mtext>LBL1</mtext></mtd><mtd><mtext>BODY1</mtext></mtd></mlabeledtr></mtable></math>');
        $reasons = array_map(static fn ($n) => $n->element . ': ' . $n->reason, Math::notes($node));
        self::assertContains('mlabeledtr: row label not drawn', $reasons);
    }

    public function test_space_class_exposes_the_limit(): void
    {
        self::assertSame(100.0, Space::MAX_EM);
    }

    public function test_nested_stretchy_fences_lay_out_quickly(): void
    {
        $n = Math::row('x');
        for ($i = 0; $i < 12; ++$i) {
            $n = Math::fenced('(', ')', Math::frac($n, 'x'));
        }
        $t = microtime(true);
        Math::layout($n);
        self::assertLessThan(0.2, microtime(true) - $t);
    }
}
