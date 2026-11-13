<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use Papercut\Math\Math;
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\RunFormat;
use Papercut\Math\Tests\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OmmlWriterTest extends TestCase
{
    private const NS = 'xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';

    /** @return iterable<string, array{string}> */
    public static function fixtureNames(): iterable
    {
        foreach (array_keys(Fixtures::all()) as $name) {
            yield $name => [$name];
        }
    }

    private static function render(string $name): string
    {
        return OmmlWriter::oMathPara(Fixtures::all()[$name], 'center');
    }

    #[DataProvider('fixtureNames')]
    public function test_snapshot(string $name): void
    {
        $path = __DIR__ . '/../snapshots/omml/' . $name . '.xml';
        $actual = self::render($name);
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($path, $actual . "\n");
        }
        self::assertFileExists($path);
        self::assertSame(rtrim((string) file_get_contents($path)), $actual);
    }

    #[DataProvider('fixtureNames')]
    public function test_output_parses(string $name): void
    {
        $doc = \Dom\XMLDocument::createFromString('<w:root ' . self::NS . '>' . self::render($name) . '</w:root>');
        self::assertSame('oMathPara', $doc->documentElement?->firstElementChild?->localName);
    }

    public function test_square_root_hides_degree(): void
    {
        $xml = OmmlWriter::oMath(Math::sqrt('x'));
        self::assertStringContainsString('<m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:deg/><m:e>', $xml);
    }

    public function test_indexed_root_shows_degree(): void
    {
        $xml = OmmlWriter::oMath(Math::root('27', '3'));
        self::assertStringContainsString('<m:radPr><m:degHide m:val="0"/></m:radPr><m:deg><m:r>', $xml);
    }

    #[DataProvider('alignments')]
    public function test_para_always_writes_jc(string $jc): void
    {
        $xml = OmmlWriter::oMathPara(Math::row('x'), $jc);
        self::assertStringContainsString('<m:oMathParaPr><m:jc m:val="' . $jc . '"/></m:oMathParaPr><m:oMath>', $xml);
        self::assertStringStartsWith('<m:oMathPara>', $xml);
    }

    /** @return iterable<string, array{string}> */
    public static function alignments(): iterable
    {
        yield 'left' => ['left'];
        yield 'center' => ['center'];
        yield 'right' => ['right'];
    }

    public function test_para_default_is_left(): void
    {
        self::assertStringContainsString('<m:jc m:val="left"/>', OmmlWriter::oMathPara(Math::row('x')));
    }

    public function test_escapes_xml_specials(): void
    {
        $xml = OmmlWriter::oMath(Math::row(Math::text('a < b & c'), '< &'));
        self::assertStringContainsString('a &lt; b &amp; c</m:t>', $xml);
        self::assertStringContainsString('&lt;</m:t>', $xml);
        self::assertStringContainsString('&amp;</m:t>', $xml);
        \Dom\XMLDocument::createFromString('<w:root ' . self::NS . '>' . $xml . '</w:root>');
    }

    public function test_empty_slots(): void
    {
        $xml = OmmlWriter::oMath(Math::frac('', '2'));
        self::assertStringContainsString('<m:num></m:num>', $xml);
        self::assertStringContainsString('<m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:deg/><m:e></m:e></m:rad>', OmmlWriter::oMath(Math::sqrt('')));
        self::assertStringContainsString('<m:sSup><m:e><m:r>', OmmlWriter::oMath(Math::sup('x', '')));
        self::assertStringContainsString('<m:sup></m:sup>', OmmlWriter::oMath(Math::sup('x', '')));
        foreach ([Math::frac('', '2'), Math::sqrt(''), Math::sup('x', '')] as $n) {
            \Dom\XMLDocument::createFromString('<w:root ' . self::NS . '>' . OmmlWriter::oMath($n) . '</w:root>');
        }
    }

    public function test_run_format(): void
    {
        $xml = OmmlWriter::oMath(Math::row('x +', Math::frac('1', Math::text('hi')), Math::sub('y', '2')), new RunFormat('C0FFEE', 28));
        $runs = substr_count($xml, '<m:r>');
        self::assertGreaterThan(5, $runs);
        self::assertSame($runs, substr_count($xml, '<w:color w:val="C0FFEE"/>'));
        self::assertSame($runs, substr_count($xml, '<w:sz w:val="28"/><w:szCs w:val="28"/>'));
        self::assertSame($runs, substr_count($xml, '<w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/>'));
    }

    public function test_run_format_oMath_parses(): void
    {
        $xml = OmmlWriter::oMath(Math::frac('1', 'x'), new RunFormat('C0FFEE', 28));
        \Dom\XMLDocument::createFromString('<w:root ' . self::NS . '>' . $xml . '</w:root>');
        self::assertStringContainsString('<w:color w:val="C0FFEE"/>', $xml);
    }

    public function test_text_runs_are_normal_text(): void
    {
        $text = OmmlWriter::oMath(Math::text('mass'));
        self::assertStringContainsString('<m:rPr><m:nor/></m:rPr><w:rPr>', $text);
        self::assertStringNotContainsString('m:sty', $text);
        $fn = OmmlWriter::oMath(Math::row('sin'));
        self::assertStringContainsString('<m:rPr><m:sty m:val="p"/></m:rPr>', $fn);
        self::assertStringNotContainsString('m:nor', $fn);
    }

    public function test_jc_is_validated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OmmlWriter::oMathPara(Math::row('x'), 'justify');
    }

    #[DataProvider('badFormats')]
    public function test_run_format_is_validated(?string $color, ?int $size): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RunFormat($color, $size);
    }

    /** @return iterable<string, array{?string, ?int}> */
    public static function badFormats(): iterable
    {
        yield 'short colour' => ['FFF', null];
        yield 'non-hex colour' => ['GGGGGG', null];
        yield 'injection' => ['"/><x', null];
        yield 'zero size' => [null, 0];
        yield 'negative size' => [null, -4];
    }

    public function test_default_format_has_no_color_or_size(): void
    {
        $xml = OmmlWriter::oMath(Math::row('x'));
        self::assertStringNotContainsString('w:color', $xml);
        self::assertStringNotContainsString('w:sz', $xml);
    }

    public function test_run_shape(): void
    {
        self::assertSame(
            '<m:oMath><m:r><m:rPr><m:sty m:val="p"/></m:rPr><w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/></w:rPr><m:t xml:space="preserve">sin</m:t></m:r></m:oMath>',
            OmmlWriter::oMath(Math::row('sin')),
        );
    }

    public function test_fenced_and_scripts(): void
    {
        $xml = OmmlWriter::oMath(Math::fenced('[', ']', Math::subSup('x', '1', '2')));
        self::assertStringContainsString('<m:d><m:dPr><m:begChr m:val="["/><m:endChr m:val="]"/></m:dPr><m:e><m:sSubSup>', $xml);
        self::assertStringContainsString('<m:sub>', $xml);
        self::assertStringContainsString('<m:sup>', $xml);
        self::assertStringContainsString('<m:sSub>', OmmlWriter::oMath(Math::sub('x', '1')));
    }

    public function test_math_italic_written_plain(): void
    {
        $xml = OmmlWriter::oMath(new \Papercut\Math\Node\Identifier("\u{1D465}"));
        self::assertStringContainsString('>x</m:t>', $xml);
    }
}
