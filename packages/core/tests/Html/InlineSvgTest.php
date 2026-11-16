<?php

declare(strict_types=1);

namespace Papercut\Tests\Html;

use Papercut\Html\InlineSvg;
use PHPUnit\Framework\TestCase;

final class InlineSvgTest extends TestCase
{
    private const string NS = 'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"';

    /**
     * @param array<string, string> $style
     * @return array{html: string, approximated: list<string>}
     */
    private static function write(string $inner, string $alt = '', array $style = [], string $rootAttrs = ''): array
    {
        $xml = '<svg ' . self::NS . ' viewBox="0 0 10 10" width="10pt" height="10pt"' . $rootAttrs . '>' . $inner . '</svg>';

        return (new InlineSvg())->write($xml, 'pc-s1-', $alt, $style);
    }

    public function test_ids_are_prefixed_and_references_rewritten(): void
    {
        $r = self::write('<linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient>'
            . '<rect width="5" height="5" fill="url(#g)" style="stroke:url(#g)"/><use href="#g"/><use xlink:href="#g"/>'
            . '<rect fill="url(\'#g\')" width="1" height="1"/>');

        self::assertSame(1, substr_count($r['html'], 'id="pc-s1-g"'));
        self::assertSame(5, substr_count($r['html'], 'pc-s1-g') - 1);
        self::assertStringNotContainsString('#g', str_replace('#pc-s1-g', '', $r['html']));
    }

    public function test_unknown_references_are_left_alone(): void
    {
        $r = self::write('<rect id="a" width="1" height="1" fill="url(#other)"/><use href="#nope"/>');

        self::assertStringContainsString('url(#other)', $r['html']);
        self::assertStringContainsString('href="#nope"', $r['html']);
        self::assertStringContainsString('id="pc-s1-a"', $r['html']);
    }

    public function test_style_selectors_are_scoped(): void
    {
        $r = self::write('<linearGradient id="g"/><style>.a{fill:red} text{font-size:9px} svg{color:blue} .b,.c{fill:url(#g)}</style><rect class="a"/>');

        self::assertStringContainsString('class="pc-s1"', $r['html']);
        self::assertStringContainsString('.pc-s1 .a{fill:red}', str_replace([' {', '{ '], ['{', '{'], $r['html']));
        self::assertStringContainsString('.pc-s1 text', $r['html']);
        self::assertStringContainsString('svg.pc-s1', $r['html']);
        self::assertStringContainsString('.pc-s1 .b', $r['html']);
        self::assertStringContainsString('.pc-s1 .c', $r['html']);
        self::assertStringContainsString('url(#pc-s1-g)', $r['html']);
        self::assertSame([], $r['approximated']);
    }

    public function test_existing_root_class_is_kept(): void
    {
        $r = self::write('<style>.a{fill:red}</style>', '', [], ' class="logo"');

        self::assertStringContainsString('class="logo pc-s1"', $r['html']);
    }

    public function test_cdata_style_is_scoped_the_same_way(): void
    {
        $r = self::write('<style><![CDATA[.a > b{fill:red}]]></style>');

        self::assertStringContainsString('.pc-s1 .a > b', $r['html']);
    }

    public function test_unscopable_at_rule_is_dropped_and_reported(): void
    {
        $r = self::write('<style>@import url(x.css); @media print{.a{fill:red}} @font-face{font-family:X;src:url(x.woff)} .b{fill:blue}</style>');

        self::assertStringNotContainsString('@import', $r['html']);
        self::assertSame(['inline SVG style @import'], $r['approximated']);
        self::assertStringContainsString('@media print', $r['html']);
        self::assertStringContainsString('.pc-s1 .a', $r['html']);
        self::assertStringContainsString('@font-face', $r['html']);
        self::assertStringContainsString('.pc-s1 .b', $r['html']);
    }

    public function test_one_entry_per_dropped_kind(): void
    {
        $r = self::write('<style>@import url(a.css); @import url(b.css); @keyframes k{from{fill:red}to{fill:blue}}</style>');

        self::assertSame(['inline SVG style @import', 'inline SVG style @keyframes'], $r['approximated']);
    }

    public function test_alt_and_decorative(): void
    {
        $named = self::write('<rect width="1" height="1"/>', 'Line')['html'];
        self::assertStringContainsString('role="img"', $named);
        self::assertStringContainsString('aria-label="Line"', $named);

        $decor = self::write('<rect width="1" height="1"/>', '')['html'];
        self::assertStringContainsString('aria-hidden="true"', $decor);
        self::assertStringNotContainsString('role=', $decor);
    }

    public function test_box_style_is_on_the_root(): void
    {
        $html = self::write('<rect width="1" height="1"/>', '', ['width' => '100pt', 'display' => 'block'])['html'];

        self::assertMatchesRegularExpression('/^<svg[^>]*style="[^"]*width:\s*100pt[^"]*display:\s*block/', $html);
    }

    public function test_output_is_an_svg_element_without_xml_declaration(): void
    {
        $html = self::write('<rect width="1" height="1"/>')['html'];

        self::assertStringStartsWith('<svg', $html);
        self::assertStringNotContainsString('<?xml', $html);
    }

    public function test_breakout_text_in_css_stays_text(): void
    {
        $html = self::write('<style>p{content:"&lt;/style&gt;&lt;img src=x onerror=alert(1)&gt;"}</style><rect width="1" height="1"/>')['html'];
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><body>' . $html . '<p id="x">z</p></body>', LIBXML_NOERROR);

        self::assertSame(0, $doc->getElementsByTagName('img')->length);
        self::assertNotNull($doc->getElementById('x'));
    }

    public function test_aria_lists_split_on_any_whitespace(): void
    {
        $html = self::write('<title id="t">T</title><desc id="d">D</desc><g aria-describedby="t&#10;d&#9;t"/>')['html'];

        self::assertMatchesRegularExpression('/aria-describedby="pc-s1-t pc-s1-d pc-s1-t"/', $html);
    }

    public function test_smil_offsets_and_digit_names_are_not_rewritten(): void
    {
        $html = self::write('<rect id="r" width="1" height="1"/><animate begin="2.5s" end="r.click" attributeName="x"/>')['html'];

        self::assertStringContainsString('begin="2.5s"', $html);
        self::assertStringContainsString('end="pc-s1-r.click"', $html);

        $digits = self::write('<rect id="2" width="1" height="1"/><animate begin="2.5s" attributeName="x"/>')['html'];
        self::assertStringContainsString('begin="2.5s"', $digits);
    }

    public function test_selectors_that_target_the_root_still_match_it(): void
    {
        $html = self::write('<style>.logo{fill:red} #r{fill:blue} :root{fill:green} svg .a{x:y} svg{z:z}</style><svg><rect class="a"/></svg>', '', [], ' class="logo" id="r"')['html'];

        self::assertStringContainsString('.pc-s1.logo', $html);
        self::assertStringContainsString('.pc-s1 .logo', $html);
        self::assertStringContainsString('.pc-s1#pc-s1-r', $html);
        self::assertStringContainsString('svg.pc-s1{fill:green}', $html);
        self::assertStringNotContainsString(':root', $html);
        self::assertStringContainsString('svg.pc-s1 .a', $html);
        self::assertStringContainsString('.pc-s1 svg', $html);
    }

    public function test_idref_attributes_are_rewritten(): void
    {
        $html = self::write('<title id="t">T</title><desc id="d">D</desc><rect id="r" width="1" height="1" aria-describedby="d missing" aria-controls="r"/>'
            . '<animate id="an" begin="r.click+1s; an.end; 2s" end="r.begin" attributeName="x"/>'
            . '<set begin="missing.click" attributeName="y"/>', '')['html'];

        self::assertStringContainsString('aria-describedby="pc-s1-d missing"', $html);
        self::assertStringContainsString('aria-controls="pc-s1-r"', $html);
        self::assertStringContainsString('begin="pc-s1-r.click+1s; pc-s1-an.end; 2s"', $html);
        self::assertStringContainsString('end="pc-s1-r.begin"', $html);
        self::assertStringContainsString('begin="missing.click"', $html);
    }

    public function test_root_labelledby_is_dropped_when_alt_is_set_and_rewritten_otherwise(): void
    {
        $inner = '<title id="t">T</title>';
        $named = self::write($inner, 'Alt', [], ' aria-labelledby="t"')['html'];
        self::assertStringNotContainsString('aria-labelledby', $named);
        self::assertStringContainsString('aria-label="Alt"', $named);

        $decor = self::write($inner, '', [], ' aria-labelledby="t"')['html'];
        self::assertStringContainsString('aria-hidden="true"', $decor);
        self::assertStringContainsString('aria-labelledby="pc-s1-t"', $decor);

        $inner2 = self::write('<g aria-labelledby="t u"/><title id="t">T</title>', '')['html'];
        self::assertStringContainsString('aria-labelledby="pc-s1-t u"', $inner2);
    }

    /** @param array<string, string> $style */
    private static function writeFamilies(string $inner, array $style = [], string $rootAttrs = ''): string
    {
        $xml = '<svg ' . self::NS . ' viewBox="0 0 10 10"' . $rootAttrs . '>' . $inner . '</svg>';
        $family = static fn (?string $list, int $weight, bool $italic): string => 'FAMILY(' . ($list ?? '-') . '|' . $weight . '|' . ($italic ? 'i' : 'n') . ')';

        return (new InlineSvg($family))->write($xml, 'pc-s1-', '', $style)['html'];
    }

    public function test_font_families_are_rewritten(): void
    {
        $html = self::writeFamilies(
            '<text font-family="Arial" font-weight="bold">a</text>'
            . '<text style="font-family:\'Open Sans\'; fill:red">b</text>'
            . '<g font-family="X" font-weight="700"><text>c</text><text font-family="Z" font-style="italic">d</text></g>'
            . '<style>text{font-family:Y}</style>',
        );

        self::assertStringContainsString('font-family="FAMILY(Arial|700|n)"', $html);
        self::assertStringContainsString('font-family:FAMILY(\'Open Sans\'|400|n)', $html);
        self::assertStringContainsString('fill:red', $html);
        self::assertStringContainsString('<g font-family="FAMILY(X|700|n)"', $html);
        self::assertStringContainsString('font-family="FAMILY(Z|700|i)"', $html);
        self::assertStringContainsString('font-family:FAMILY(Y|400|n)', $html);
        self::assertStringNotContainsString('Arial"', str_replace('FAMILY(Arial|700|n)', '', $html));
    }

    public function test_weight_and_style_come_from_the_nearest_declaration(): void
    {
        $html = self::writeFamilies(
            '<g font-weight="bold"><text font-weight="normal" font-family="A">a</text><text style="font-weight:600;font-style:oblique;font-family:B">b</text></g>'
            . '<style>.t{font-weight:bold;font-style:italic;font-family:C}.u{font-family:D}</style>',
        );

        self::assertStringContainsString('FAMILY(A|400|n)', $html);
        self::assertStringContainsString('FAMILY(B|700|i)', $html);
        self::assertStringContainsString('FAMILY(C|700|i)', $html);
        self::assertStringContainsString('FAMILY(D|400|n)', $html);
    }

    public function test_the_root_gets_the_default_family_unless_it_sets_one(): void
    {
        $plain = self::writeFamilies('<text>a</text>', ['width' => '10pt']);
        self::assertSame(1, substr_count($plain, 'FAMILY(-|400|n)'));

        $own = self::writeFamilies('<text>a</text>', [], ' font-family="Mine"');
        self::assertStringContainsString('font-family="FAMILY(Mine|400|n)"', $own);
        self::assertStringNotContainsString('FAMILY(-', $own);
    }

    public function test_font_face_rules_in_the_svg_are_left_alone(): void
    {
        $html = self::writeFamilies('<style>@font-face{font-family:Mine;src:url(x.woff)}text{font-family:Mine}</style><text>a</text>');

        self::assertStringContainsString('@font-face{font-family:Mine;src:url(x.woff)}', $html);
        self::assertStringContainsString('text{font-family:FAMILY(Mine|400|n)}', $html);
    }

    public function test_families_are_left_alone_without_a_closure(): void
    {
        $r = self::write('<text font-family="Arial" style="font-family:Arial">a</text><style>text{font-family:Arial}</style>');

        self::assertSame(3, substr_count($r['html'], 'Arial'));
    }
}
