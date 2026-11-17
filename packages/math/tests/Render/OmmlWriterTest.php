<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use Papercut\Math\Math;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\UnderOver;
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\RunFormat;
use Papercut\Math\Styles;
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

    public function test_middle_delimiters_become_separators(): void
    {
        $xml = self::oMath(Math::fenced('{', '}', 'x', Math::middle('|'), Math::frac('a', 'b')));
        self::assertStringContainsString('<m:begChr m:val="{"/><m:sepChr m:val="|"/><m:endChr m:val="}"/>', $xml);
        self::assertSame(2, substr_count($xml, '<m:e>'), 'one m:e per part, the fraction has none of its own');
        self::assertStringNotContainsString('<m:t xml:space="preserve">|</m:t>', $xml);
    }

    public function test_mixed_middle_delimiters_stay_runs(): void
    {
        $xml = self::oMath(Math::fenced('(', ')', 'x', Math::middle('|'), 'y', Math::middle('/'), 'z'));
        self::assertStringNotContainsString('sepChr', $xml);
        self::assertSame(1, substr_count($xml, '<m:e>'));
        self::assertStringContainsString('|</m:t>', $xml);
        self::assertStringContainsString('/</m:t>', $xml);
    }

    public function test_math_italic_written_plain(): void
    {
        $xml = OmmlWriter::oMath(new Identifier("\u{1D465}"));
        self::assertStringContainsString('>x</m:t>', $xml);
    }

    public function test_strips_characters_xml_cannot_hold(): void
    {
        $xml = OmmlWriter::oMath(Math::text("a\x01b\x00c\u{FFFE}d\u{FFFF}e\x0Bf"));
        self::assertStringContainsString('>abcdef</m:t>', $xml);
        \Dom\XMLDocument::createFromString('<w:root ' . self::NS . '>' . $xml . '</w:root>');
    }

    public function test_shading_goes_last_in_the_run_properties(): void
    {
        $xml = OmmlWriter::oMath(Math::row('x'), new RunFormat('ff0000', 24, 'ffee00'));
        self::assertStringContainsString('<w:sz w:val="24"/><w:szCs w:val="24"/><w:shd w:val="clear" w:color="auto" w:fill="ffee00"/></w:rPr>', $xml);
        $this->expectException(\InvalidArgumentException::class);
        new RunFormat(null, null, 'yellow');
    }

    public function test_small_constructs(): void
    {
        $w = fn ($n) => OmmlWriter::oMath($n);
        self::assertStringContainsString("<m:t xml:space=\"preserve\">\u{2005}</m:t>", $w(Math::space(0.25)));
        self::assertStringContainsString("<m:t xml:space=\"preserve\">\u{2009}</m:t>", $w(Math::space(0.167)));
        self::assertStringContainsString("<m:t xml:space=\"preserve\">\u{2004}</m:t>", $w(Math::space(0.4)));
        self::assertStringContainsString("<m:t xml:space=\"preserve\">\u{2002}</m:t>", $w(Math::space(0.5)));
        self::assertStringContainsString("<m:t xml:space=\"preserve\">\u{2003}\u{2003}</m:t>", $w(Math::space(2)));
        foreach ([[0.2, "\u{2009}"], [0.3, "\u{2005}"], [0.6, "\u{2002}"], [0.7, "\u{2003}"], [2.3, "\u{2003}\u{2003}\u{2005}"], [1.5, "\u{2003}\u{2002}"]] as [$em, $chars]) {
            self::assertStringContainsString('<m:t xml:space="preserve">' . $chars . '</m:t>', $w(Math::space($em)), (string) $em);
        }
        self::assertSame('<m:oMath></m:oMath>', $w(Math::space(-0.2)));
        self::assertStringContainsString('<m:phant><m:phantPr><m:show m:val="0"/></m:phantPr><m:e>', $w(Math::phantom('x')));
        self::assertStringContainsString('<m:f><m:fPr><m:type m:val="noBar"/></m:fPr><m:num>', $w(Math::stack('n', 'k')));
        self::assertStringContainsString('<m:r><m:rPr><m:sty m:val="p"/></m:rPr>', $w(Math::styled('R', 'double-struck')));
        self::assertStringContainsString('<m:t xml:space="preserve">ℝ</m:t>', $w(Math::styled('R', 'double-struck')));
    }

    private static function oMath(Node $n): string
    {
        return preg_replace('/<w:rPr>.*?<\/w:rPr>/', '', OmmlWriter::oMath($n)) ?? '';
    }

    private static function mr(string $t, string $pr = ''): string
    {
        return '<m:r>' . $pr . '<m:t xml:space="preserve">' . $t . '</m:t></m:r>';
    }

    public function test_sum_takes_the_next_sibling_as_its_body(): void
    {
        $xml = self::oMath(Math::row(Math::sum('i', 'n'), Math::sup('i', '2')));
        $expected = '<m:oMath><m:nary><m:naryPr><m:chr m:val="∑"/><m:limLoc m:val="undOvr"/></m:naryPr>'
            . '<m:sub>' . self::mr('i') . '</m:sub><m:sup>' . self::mr('n') . '</m:sup>'
            . '<m:e><m:sSup><m:e>' . self::mr('i') . '</m:e><m:sup>' . self::mr('2') . '</m:sup></m:sSup></m:e></m:nary></m:oMath>';
        self::assertSame($expected, $xml);
        self::assertSame(1, substr_count($xml, '<m:sSup>'));
    }

    public function test_sum_before_an_operator_has_an_empty_body(): void
    {
        $xml = self::oMath(Math::row(Math::sum('i', 'n'), '+', 'x'));
        self::assertStringContainsString('</m:sup><m:e/></m:nary>' . self::mr('+') . self::mr('x'), $xml);
        self::assertStringContainsString('<m:e/></m:nary>', self::oMath(Math::row('x', Math::sum('i', 'n'))));
    }

    public function test_integral_puts_limits_beside_it(): void
    {
        $xml = self::oMath(Math::row(Math::integral('0', '1'), 'x'));
        self::assertStringContainsString('<m:naryPr><m:chr m:val="∫"/><m:limLoc m:val="subSup"/></m:naryPr><m:sub>' . self::mr('0') . '</m:sub><m:sup>' . self::mr('1') . '</m:sup><m:e>' . self::mr('x') . '</m:e>', $xml);
    }

    public function test_missing_limit_is_hidden(): void
    {
        $xml = self::oMath(Math::row(Math::sum('i = 1'), 'x'));
        self::assertStringContainsString('<m:limLoc m:val="undOvr"/><m:supHide m:val="1"/></m:naryPr><m:sub>', $xml);
        self::assertStringContainsString('</m:sub><m:sup/><m:e>', $xml);
        $over = self::oMath(Math::row(Math::sum(null, 'n'), 'x'));
        self::assertStringContainsString('<m:subHide m:val="1"/></m:naryPr><m:sub/><m:sup>', $over);
    }

    public function test_large_operator_without_limits_is_a_plain_run(): void
    {
        self::assertSame('<m:oMath>' . self::mr('∑') . self::mr('x') . '</m:oMath>', self::oMath(Math::row(Math::sum(), 'x')));
    }

    public function test_accents_use_the_combining_form(): void
    {
        self::assertSame('<m:oMath><m:acc><m:accPr><m:chr m:val="' . "\u{0302}" . '"/></m:accPr><m:e>' . self::mr('x') . '</m:e></m:acc></m:oMath>', self::oMath(Math::hat('x')));
        self::assertStringContainsString('<m:chr m:val="' . "\u{20D7}" . '"/>', self::oMath(Math::accent('x', '→')));
        self::assertStringContainsString('<m:chr m:val="‵"/>', self::oMath(Math::accent('x', '‵')));
    }

    public function test_bars(): void
    {
        self::assertSame('<m:oMath><m:bar><m:barPr><m:pos m:val="top"/></m:barPr><m:e>' . self::mr('x') . '</m:e></m:bar></m:oMath>', self::oMath(Math::overline('x')));
        self::assertStringContainsString('<m:barPr><m:pos m:val="bot"/></m:barPr>', self::oMath(Math::underline('x')));
    }

    public function test_braces(): void
    {
        $xml = self::oMath(Math::underbrace('a', 'n'));
        $expected = '<m:oMath><m:limLow><m:e><m:groupChr><m:groupChrPr><m:chr m:val="⏟"/><m:pos m:val="bot"/><m:vertJc m:val="top"/></m:groupChrPr><m:e>'
            . self::mr('a') . '</m:e></m:groupChr></m:e><m:lim>' . self::mr('n') . '</m:lim></m:limLow></m:oMath>';
        self::assertSame($expected, $xml);
        self::assertStringContainsString('<m:groupChrPr><m:chr m:val="⏞"/><m:pos m:val="top"/><m:vertJc m:val="bot"/></m:groupChrPr>', self::oMath(Math::overbrace('a')));
        self::assertStringNotContainsString('m:limUpp', self::oMath(Math::overbrace('a')));
    }

    public function test_other_stacks_are_limits(): void
    {
        self::assertSame('<m:oMath><m:limUpp><m:e>' . self::mr('=') . '</m:e><m:lim>' . self::mr('d') . '</m:lim></m:limUpp></m:oMath>', self::oMath(Math::over('=', 'd')));
        self::assertSame('<m:oMath><m:limLow><m:e>' . self::mr('x') . '</m:e><m:lim>' . self::mr('y') . '</m:lim></m:limLow></m:oMath>', self::oMath(Math::under('x', 'y')));
        self::assertStringContainsString('<m:limUpp><m:e><m:limLow>', self::oMath(Math::underOver('x', 'y', 'z')));
    }

    public function test_accent_table(): void
    {
        self::assertSame("\u{0302}", Styles::accentMark('^'));
        self::assertSame("\u{0303}", Styles::accentMark("\u{02DC}"));
        self::assertSame("\u{20D7}", Styles::accentMark('→'));
        self::assertNull(Styles::accentMark('x'));
        self::assertNull(Styles::accentMark("\u{00AF}"));
    }

    public function test_nested_nary_operators_nest_their_bodies(): void
    {
        $xml = self::oMath(Math::row(Math::sum('i', 'n'), Math::sum('j', 'm'), Math::sub('a', 'ij')));
        self::assertSame(1, substr_count($xml, '<m:sSub>'));
        self::assertSame(2, substr_count($xml, '<m:nary>'));
        $inner = strpos($xml, '<m:e><m:nary>');
        self::assertNotFalse($inner, 'the inner nary is the outer body');
        self::assertGreaterThan(strpos($xml, '<m:sSub>'), strpos($xml, '</m:nary></m:e></m:nary>'));
        self::assertStringContainsString('</m:sSub></m:e></m:nary></m:e></m:nary>', $xml);
        $integrals = self::oMath(Math::row(Math::integral('0', '1'), Math::integral('0', '1'), 'f'));
        self::assertStringContainsString(self::mr('f') . '</m:e></m:nary></m:e></m:nary>', $integrals);
    }

    public function test_space_after_an_nary_does_not_become_its_body(): void
    {
        $xml = self::oMath(Math::row(Math::integral('0', '1'), Math::space(1 / 6), 'f'));
        self::assertSame(1, substr_count($xml, "\u{2009}"));
        self::assertStringContainsString('<m:e>' . self::mr("\u{2009}") . self::mr('f') . '</m:e></m:nary>', $xml);
        self::assertStringContainsString('<m:e/></m:nary>' . self::mr("\u{2009}"), self::oMath(Math::row(Math::integral('0', '1'), Math::space(1 / 6), '+')));
    }

    public function test_under_accent_is_a_group_character(): void
    {
        $xml = self::oMath(new UnderOver(Math::row('x'), new Operator("\u{0303}"), null, false, true));
        self::assertSame('<m:oMath><m:groupChr><m:groupChrPr><m:chr m:val="' . "\u{0303}" . '"/><m:pos m:val="bot"/><m:vertJc m:val="top"/></m:groupChrPr><m:e>' . self::mr('x') . '</m:e></m:groupChr></m:oMath>', $xml);
        self::assertStringNotContainsString('m:limLow', $xml);
    }

    public function test_unstretched_brace_is_a_plain_limit(): void
    {
        $xml = self::oMath(new UnderOver(Math::row('a'), null, new Operator('⏞', false)));
        self::assertStringContainsString('<m:limUpp>', $xml);
    }

    public function test_multi_character_delimiters_and_accents(): void
    {
        $cases = [
            Math::fenced('((', '))', 'x'),
            Math::fenced("\u{1F600}", "\u{1F600}", 'x'),
            Math::accent('x', 'abc'),
            Math::mathml('<math><mfenced open="[[" close="]]"><mi>x</mi></mfenced></math>'),
            Math::mathml("<math><mover accent=\"true\"><mi>x</mi><mo>\u{2192}\u{2192}</mo></mover></math>"),
            Math::mathml('<math><munder accentunder="true"><mi>x</mi><mo>ab</mo></munder></math>'),
        ];
        foreach ($cases as $i => $math) {
            $xml = self::oMath($math);
            preg_match_all('/<m:(?:chr|begChr|endChr) m:val="([^"]*)"/u', $xml, $m);
            foreach ($m[1] as $val) {
                self::assertLessThanOrEqual(1, strlen(mb_convert_encoding(html_entity_decode($val), 'UTF-16LE', 'UTF-8')) / 2, "case $i: $val");
            }
            self::assertStringContainsString('x', $xml);
        }
        $xml = OmmlWriter::oMath(Math::fenced('((', '))', 'x'));
        self::assertStringContainsString('((', $xml);
        self::assertStringContainsString('))', $xml);
        self::assertStringContainsString('abc', OmmlWriter::oMath(Math::accent('x', 'abc')));
    }

    public function test_labelled_arrow_is_a_group_character_over_a_phantom_of_the_wider_label(): void
    {
        $ph = '<m:phant><m:phantPr><m:show m:val="0"/><m:zeroAsc m:val="1"/><m:zeroDesc m:val="1"/></m:phantPr><m:e>';
        $arrow = '<m:groupChr><m:groupChrPr><m:chr m:val="→"/><m:pos m:val="top"/><m:vertJc m:val="bot"/></m:groupChrPr><m:e>';

        $over = self::oMath(Math::xarrow('→', 'f'));
        self::assertSame('<m:oMath><m:limUpp><m:e>' . $arrow . $ph . self::mr('f') . '</m:e></m:phant></m:e></m:groupChr></m:e><m:lim>' . self::mr('f') . '</m:lim></m:limUpp></m:oMath>', $over);

        $under = self::oMath(Math::xarrow('→', null, 'g'));
        self::assertSame('<m:oMath><m:limLow><m:e>' . $arrow . $ph . self::mr('g') . '</m:e></m:phant></m:e></m:groupChr></m:e><m:lim>' . self::mr('g') . '</m:lim></m:limLow></m:oMath>', $under);

        // Both labels: the phantom copies the wider one; the real labels are the limits.
        $both = self::oMath(Math::xarrow('→', 'f', 'n=0'));
        self::assertSame('<m:oMath><m:limLow><m:e><m:limUpp><m:e>' . $arrow . $ph . self::mr('n') . self::mr('=') . self::mr('0') . '</m:e></m:phant></m:e></m:groupChr></m:e><m:lim>' . self::mr('f') . '</m:lim></m:limUpp></m:e><m:lim>' . self::mr('n') . self::mr('=') . self::mr('0') . '</m:lim></m:limLow></m:oMath>', $both);
        $both = self::oMath(Math::xarrow('→', 'n=0', 'f'));
        self::assertSame('<m:oMath><m:limLow><m:e><m:limUpp><m:e>' . $arrow . $ph . self::mr('n') . self::mr('=') . self::mr('0') . '</m:e></m:phant></m:e></m:groupChr></m:e><m:lim>' . self::mr('n') . self::mr('=') . self::mr('0') . '</m:lim></m:limUpp></m:e><m:lim>' . self::mr('f') . '</m:lim></m:limLow></m:oMath>', $both);
    }

    public function test_xlongequal_label_is_a_group_character(): void
    {
        $xml = self::oMath(Math::xarrow('=', 'a'));
        self::assertStringContainsString('<m:groupChr><m:groupChrPr><m:chr m:val="="/>', $xml);
        self::assertStringContainsString('<m:phant>', $xml);
    }

    public function test_unstretched_arrow_label_stays_a_limit(): void
    {
        $xml = OmmlWriter::oMath(new UnderOver(new Operator('=', stretchy: false), null, Math::row('d')));
        self::assertStringContainsString('<m:limUpp>', $xml);
        self::assertStringNotContainsString('groupChr', $xml);
    }

    public function test_mathml_over_equals_is_not_a_stretched_arrow(): void
    {
        $xml = OmmlWriter::oMath(Math::mathml('<math><mover><mo>=</mo><mi>d</mi></mover></math>'));
        self::assertStringContainsString('<m:limUpp>', $xml);
        self::assertStringNotContainsString('groupChr', $xml);
        self::assertStringContainsString('groupChr', OmmlWriter::oMath(Math::mathml('<math><mover><mo>→</mo><mi>d</mi></mover></math>')));
    }

    public function test_label_width_ignores_xml_escapes(): void
    {
        // "&lt;&lt;" is two characters, shorter than "abc": the arrow groups the under label.
        $xml = OmmlWriter::oMath(Math::xarrow('→', '<<', 'abc'));
        self::assertMatchesRegularExpression('#<m:limUpp><m:e><m:groupChr>.*<m:phant>.*>a<.*>b<.*>c<.*</m:phant>#s', $xml);
    }
}
