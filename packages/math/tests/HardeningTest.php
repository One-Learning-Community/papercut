<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Math;
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

    /** @return iterable<string, array{string}> */
    public static function utf16(): iterable
    {
        yield 'UTF-16LE' => ["\xFF\xFE"];
        yield 'UTF-16BE' => ["\xFE\xFF"];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('utf16')]
    public function test_utf16_mathml_with_an_entity_doctype_does_not_expand(string $bom): void
    {
        $xml = '<!DOCTYPE math [<!ENTITY boom "PCEXPANDED">]><math><mi>&boom;</mi></math>';
        $input = $bom . mb_convert_encoding($xml, $bom === "\xFF\xFE" ? 'UTF-16LE' : 'UTF-16BE', 'UTF-8');

        $node = Math::mathml($input);

        self::assertStringNotContainsString('PCEXPANDED', (new MathmlWriter())->write($node));
    }

    public function test_utf16_mathml_without_a_doctype_still_reads(): void
    {
        $input = "\xFF\xFE" . mb_convert_encoding('<math><mi>x</mi></math>', 'UTF-16LE', 'UTF-8');

        self::assertStringContainsString('x', (new MathmlWriter())->write(Math::mathml($input)));
    }

    /** @return iterable<string, array{string}> */
    public static function bomless(): iterable
    {
        yield 'UTF-16LE' => ['UTF-16LE'];
        yield 'UTF-16BE' => ['UTF-16BE'];
        yield 'UTF-32LE' => ['UTF-32LE'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bomless')]
    public function test_bomless_wide_mathml_with_an_xml_declaration_does_not_expand(string $encoding): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE math [<!ENTITY boom "PCEXPANDED">]><math><mi>&boom;</mi></math>';
        $node = Math::mathml(mb_convert_encoding($xml, $encoding, 'UTF-8'));

        self::assertStringNotContainsString('PCEXPANDED', (new MathmlWriter())->write($node));
    }
}
