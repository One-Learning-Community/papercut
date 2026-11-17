<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Mathml;

use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Mathml\SafeXml;
use Quoin\Math\Render\TextWriter;
use PHPUnit\Framework\TestCase;

final class SafeXmlTest extends TestCase
{
    public function test_decode(): void
    {
        self::assertSame('<mi mathvariant="x" a=\'b\'>&amp;</mi>', SafeXml::decode('«mi mathvariant=¨x¨ a=`b`»§amp;«/mi»'));
    }

    public function test_find_two_runs_in_text(): void
    {
        $s = 'one «math xmlns=¨http://www.w3.org/1998/Math/MathML¨»«mi»x«/mi»«/math» and «MATH»«mn»2«/mn»«/math» end «mml:math»«mi»y«/mi»«/mml:math»';
        $runs = SafeXml::find($s);
        self::assertCount(3, $runs);
        self::assertStringStartsWith('«math xmlns', substr($s, $runs[0][0], $runs[0][1]));
        self::assertStringEndsWith('«/math»', substr($s, $runs[0][0], $runs[0][1]));
        self::assertSame('«MATH»«mn»2«/mn»«/math»', substr($s, $runs[1][0], $runs[1][1]));
        self::assertStringEndsWith('«/mml:math»', substr($s, $runs[2][0], $runs[2][1]));
    }

    public function test_french_quotes_untouched(): void
    {
        self::assertSame([], SafeXml::find('Il a dit « bonjour » puis « mathématiques » ou «mathieu»'));
    }

    public function test_reader_accepts_safexml(): void
    {
        $r = MathmlReader::read("  «math»«mfrac»«mn»1«/mn»«mn»2«/mn»«/mfrac»«/math»");
        self::assertSame([], $r->notes());
        self::assertSame('1/2', TextWriter::write($r->node));
    }

    public function test_unterminated_opener_does_not_swallow_the_next_run(): void
    {
        $s = '«math »«mi»x«/mi» then «math»«mn»2«/mn»«/math»';
        $runs = SafeXml::find($s);
        self::assertCount(1, $runs);
        self::assertSame('«math»«mn»2«/mn»«/math»', substr($s, $runs[0][0], $runs[0][1]));
    }

    public function test_close_must_match_the_open_prefix(): void
    {
        self::assertSame([], SafeXml::find('«math»«mi»x«/mi»«/mml:math»'));
        self::assertSame([], SafeXml::find('«mml:math»«mi»x«/mi»«/math»'));
        self::assertCount(1, SafeXml::find('«MML:MATH»«mi»x«/mi»«/mml:math»'));
    }
}
