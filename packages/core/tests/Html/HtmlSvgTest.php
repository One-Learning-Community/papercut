<?php

declare(strict_types=1);

namespace Papercut\Tests\Html;

use Papercut\{Column, Document, Node, Page, Row, Svg, Text};
use Papercut\Export\{FontMode, HtmlOptions};
use Papercut\Vector\SvgDocument;
use PHPUnit\Framework\TestCase;
use function Papercut\pt;

final class HtmlSvgTest extends TestCase
{
    private const string LOGO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 20"><title>Acme logo</title><rect width="40" height="20" fill="#1d4ed8"/></svg>';

    /** @param list<string> $warnings only those about SVG */
    private static function html(Node $node, array &$warnings = []): string
    {
        $options = new HtmlOptions(onWarning: static function (string $w) use (&$warnings): void {
            if (str_contains($w, 'SVG')) {
                $warnings[] = $w;
            }
        });

        return Document::make(Page::a4()->body($node))->toHtml($options)->bytes();
    }

    /** @return list<\DOMElement> */
    private static function imgs(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $out = [];
        foreach ((new \DOMXPath($dom))->query('//img') ?: [] as $node) {
            self::assertInstanceOf(\DOMElement::class, $node);
            $out[] = $node;
        }
        return $out;
    }

    public function test_svg_is_an_img_with_a_data_uri_never_inline_markup(): void
    {
        $html = self::html(Svg::fromString(self::LOGO));

        $imgs = self::imgs($html);
        self::assertCount(1, $imgs);
        $src = $imgs[0]->getAttribute('src');
        self::assertStringStartsWith('data:image/svg+xml;base64,', $src);
        self::assertSame(SvgDocument::parse(self::LOGO)->xml, base64_decode(substr($src, strlen('data:image/svg+xml;base64,')), true));
        self::assertStringNotContainsString('<svg', $html);
    }

    public function test_the_title_is_the_alt_text(): void
    {
        self::assertSame('Acme logo', self::imgs(self::html(Svg::fromString(self::LOGO)))[0]->getAttribute('alt'));
    }

    public function test_svg_alt_overrides_title(): void
    {
        $html = self::html(Svg::fromString(self::LOGO)->alt('Number line'));
        self::assertSame('Number line', self::imgs($html)[0]->getAttribute('alt'));
    }

    public function test_svg_falls_back_to_title(): void
    {
        self::assertSame('Acme logo', self::imgs(self::html(Svg::fromString(self::LOGO)))[0]->getAttribute('alt'));
    }

    public function test_empty_alt_marks_svg_decorative(): void
    {
        $html = self::html(Svg::fromString(self::LOGO)->alt(''));
        self::assertSame('', self::imgs($html)[0]->getAttribute('alt'));
        self::assertStringContainsString(' alt=""', $html);
    }

    public function test_box_styles_are_kept(): void
    {
        $img = self::imgs(self::html(Svg::fromString(self::LOGO)->width(pt(120))->padding(pt(4))))[0];

        self::assertStringContainsString('width:120pt', $img->getAttribute('style'));
        self::assertStringContainsString('padding:4pt', $img->getAttribute('style'));
    }

    public function test_in_a_row_it_gets_flex_item_declarations(): void
    {
        $img = self::imgs(self::html(Row::make(Svg::fromString(self::LOGO)->grow(1))))[0];

        self::assertStringContainsString('flex:', $img->getAttribute('style'));
    }

    public function test_scripts_and_event_handlers_are_removed(): void
    {
        $svg = Svg::fromString('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10" onclick="alert(3)"/></svg>');
        $src = self::imgs(self::html($svg))[0]->getAttribute('src');
        $xml = (string) base64_decode(substr($src, strlen('data:image/svg+xml;base64,')), true);

        self::assertStringContainsString('<rect', $xml);
        self::assertStringNotContainsString('alert', $xml);
        self::assertStringNotContainsString('script', $xml);
    }

    public function test_text_in_the_browser_is_reported_as_approximated(): void
    {
        $warnings = [];
        self::html(Svg::fromString('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><text y="8">Hi</text></svg>'), $warnings);

        self::assertCount(1, $warnings);
        self::assertStringContainsString('SVG text', $warnings[0]);
    }

    public function test_no_report_without_text(): void
    {
        $warnings = [];
        self::html(Svg::fromString(self::LOGO), $warnings);

        self::assertSame([], $warnings);
    }

    private const string GRADIENT = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 20"><defs><linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient></defs><rect width="40" height="20" fill="url(#g)"/><text x="1" y="10">Hi</text></svg>';

    /** @param list<string> $warnings only those about SVG */
    private static function inlineHtml(Node $node, array &$warnings = [], bool $strict = false): string
    {
        $options = new HtmlOptions(onWarning: static function (string $w) use (&$warnings): void {
            if (str_contains($w, 'SVG')) {
                $warnings[] = $w;
            }
        }, strict: $strict, inlineSvgs: true);

        return Document::make(Page::a4()->body($node))->toHtml($options)->bytes();
    }

    public function test_svgs_are_images_by_default(): void
    {
        self::assertStringContainsString('<img src="data:image/svg+xml;base64,', self::html(Svg::fromString(self::GRADIENT)));
    }

    public function test_inline_svgs_writes_svg_markup(): void
    {
        $html = self::inlineHtml(Svg::fromString(self::LOGO));

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('data:image/svg+xml', $html);
        self::assertTrue((new HtmlOptions())->withInlineSvgs(true)->inlineSvgs());
        self::assertTrue((new HtmlOptions(inlineSvgs: true))->withFullPage(true)->inlineSvgs());
        self::assertTrue(HtmlOptions::from(new HtmlOptions(inlineSvgs: true))->inlineSvgs());
    }

    public function test_same_svg_twice_gets_two_prefixes(): void
    {
        $html = self::inlineHtml(Row::make(Svg::fromString(self::GRADIENT), Svg::fromString(self::GRADIENT)));

        self::assertSame(1, substr_count($html, 'id="pc-s1-g"'));
        self::assertSame(1, substr_count($html, 'id="pc-s2-g"'));
        self::assertSame(1, substr_count($html, 'url(#pc-s1-g)'));
        self::assertSame(1, substr_count($html, 'url(#pc-s2-g)'));
    }

    public function test_inline_svg_text_note_only_when_not_inline(): void
    {
        $off = [];
        self::html(Svg::fromString(self::GRADIENT), $off);
        self::assertCount(1, $off);
        self::assertStringContainsString("SVG text in the browser's own fonts (set inlineSvgs to use the page's fonts)", $off[0]);

        $on = [];
        self::inlineHtml(Svg::fromString(self::GRADIENT), $on, strict: true);
        self::assertSame([], $on);
    }

    /** @return array<string, array{string, string}> */
    public static function unsafeInlineContent(): array
    {
        $bomb = 'p{content:"&lt;/style&gt;&lt;img src=x onerror=alert(1)&gt;"}';

        return [
            'foreignObject' => ['<foreignObject width="10" height="10"><div xmlns="http://www.w3.org/1999/xhtml"></div></foreignObject>', "<foreignObject> can't be written inline"],
            'lowercase foreignobject' => ['<foreignobject width="10" height="10"><textarea/></foreignobject>', "<foreignobject> can't be written inline"],
            'style in title' => ['<title>T<style>' . $bomb . '</style></title>', '<title> holds elements'],
            'style in desc' => ['<desc><style>' . $bomb . '</style></desc>', '<desc> holds elements'],
            'svg namespace p' => ['<p/><textarea/>', "<p> can't be written inline"],
            'xhtml img outside foreignObject' => ['<img xmlns="http://www.w3.org/1999/xhtml" src="x.png"/>', "<img> can't be written inline"],
            'textarea in desc' => ['<desc><textarea/></desc>', '<desc> holds elements'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeInlineContent')]
    public function test_unsafe_content_keeps_the_svg_an_image(string $inner, string $reason): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">' . $inner . '<text x="1" y="5">Hi</text></svg>';
        $notes = [];
        $html = self::inlineHtml(Svg::fromString($svg)->alt('Box'), $notes);

        self::assertStringContainsString('<img src="data:image/svg+xml;base64,', $html);
        self::assertStringContainsString('alt="Box"', $html);
        self::assertCount(2, $notes);
        self::assertStringContainsString('SVG kept as an image (' . $reason . ')', $notes[0]);
        self::assertStringContainsString("SVG text in the browser's own fonts", $notes[1]);
        self::assertStringNotContainsString('inlineSvgs', $notes[1]);
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><body>' . $html . '<p id="after">x</p>', LIBXML_NOERROR);
        self::assertSame('BODY', $doc->getElementById('after')?->parentNode?->nodeName);
        foreach ($doc->getElementsByTagName('img') as $img) {
            self::assertFalse($img->hasAttribute('onerror'));
        }
        self::assertSame(1, $doc->getElementsByTagName('img')->length);
    }

    public function test_a_normal_svg_is_inlined_and_metadata_is_dropped(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10"><metadata><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/></metadata>'
            . '<defs><linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient></defs><rect id="r" width="5" height="5" fill="url(#g)"><animate attributeName="x" from="0" to="1" dur="1s"/></rect>'
            . '<use xlink:href="#r"/><text x="1" y="5">Hi</text></svg>';
        $notes = [];
        $html = self::inlineHtml(Svg::fromString($svg), $notes);

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('metadata', $html);
        self::assertStringNotContainsString('data:image/svg+xml', $html);
        self::assertSame([], $notes);
    }

    /** @return array<string, array{string}> */
    public static function commentsInline(): array
    {
        return [
            'abrupt comment at the top' => ['<!--><img src=x onerror=alert(1)>-->'],
            'abrupt dash comment at the top' => ['<!---><img src=x onerror=alert(1)>-->'],
            'abrupt comment in title' => ['<title>T<!--><img src=x onerror=alert(1)>--></title>'],
            'abrupt comment in desc' => ['<desc><!--><p>x</p>--></desc>'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('commentsInline')]
    public function test_comments_are_dropped_from_inline_svg(string $inner): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">' . $inner . '<rect width="1" height="1"/></svg>';
        $notes = [];
        $html = self::inlineHtml(Svg::fromString($svg)->alt('Box'), $notes);

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('data:image/svg+xml', $html);
        self::assertStringNotContainsString('<!--', $html);
        self::assertSame([], $notes);
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><body>' . $html . '<p id="after">x</p>', LIBXML_NOERROR);
        self::assertSame('BODY', $doc->getElementById('after')?->parentNode?->nodeName);
        self::assertSame(0, $doc->getElementsByTagName('img')->length);
        self::assertSame(1, $doc->getElementsByTagName('p')->length);
        $svgs = $doc->getElementsByTagName('svg');
        self::assertSame(1, $svgs->length);
        self::assertSame(1, $svgs->item(0)?->getElementsByTagName('rect')->length);
    }

    /** @return array<string, array{string, string}> */
    public static function cdataInline(): array
    {
        $bomb = 'x&gt;&lt;/svg&gt;&lt;img src=x onerror=alert(1)&gt;&lt;!--';

        return [
            'title' => ['<title><![CDATA[x></svg><img src=x onerror=alert(1)><!--]]></title>', 'title'],
            'desc' => ['<desc><![CDATA[x></svg><img src=x onerror=alert(1)><!--]]></desc>', 'desc'],
            'text' => ['<text x="1" y="5"><![CDATA[x></svg><img src=x onerror=alert(1)><!--]]></text>', 'text'],
            'escaped text with comment and cdata end' => ['<text x="1" y="5">' . $bomb . ']]&gt;</text>', 'text'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cdataInline')]
    public function test_cdata_and_markup_like_text_stay_text_inline(string $inner, string $holder): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">' . $inner . '<rect width="1" height="1"/></svg>';
        $html = self::inlineHtml(Svg::fromString($svg)->alt('Box'));

        self::assertStringContainsString('<svg', $html);
        self::assertStringNotContainsString('<![CDATA[', $html);
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><body>' . $html . '<p id="after">x</p>', LIBXML_NOERROR);
        self::assertSame('BODY', $doc->getElementById('after')?->parentNode?->nodeName);
        self::assertSame(0, $doc->getElementsByTagName('img')->length);
        $svgEl = $doc->getElementsByTagName('svg')->item(0);
        self::assertNotNull($svgEl);
        self::assertSame(1, $svgEl->getElementsByTagName('rect')->length);
        self::assertStringContainsString('<img src=x onerror=alert(1)>', (string) $svgEl->getElementsByTagName($holder)->item(0)?->textContent);
    }

    public function test_an_undeclared_entity_from_an_external_dtd_keeps_the_svg_safe(): void
    {
        $svg = '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>a&nbsp;b</title><rect width="1" height="1"/></svg>';
        $html = self::inlineHtml(Svg::fromString($svg)->alt('Box'));

        self::assertStringNotContainsString('<!DOCTYPE', $html);
        self::assertStringNotContainsString('&nbsp;', $html);
        $doc = \Dom\HTMLDocument::createFromString('<!doctype html><body>' . $html . '<p id="after">x</p>', LIBXML_NOERROR);
        self::assertSame('BODY', $doc->getElementById('after')?->parentNode?->nodeName);
    }

    private static function fontSvg(string $family): Svg
    {
        return Svg::fromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 20"><text x="1" y="10" font-family="' . $family . '">Label</text></svg>');
    }

    private static function fontHtml(Svg $svg, FontMode $mode = FontMode::Embed): string
    {
        $body = Column::make(Text::make('Body')->font('Cousine'), $svg->font('Cousine'));

        return Document::make(Page::a4()->body($body))->toHtml(new HtmlOptions(fontMode: $mode, inlineSvgs: true))->bytes();
    }

    private static function svgFamily(string $html): string
    {
        $dom = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $text = $dom->querySelector('svg text');
        self::assertNotNull($text);

        return $text->getAttribute('font-family') ?? '';
    }

    public function test_registered_svg_family_gets_a_font_face(): void
    {
        $html = self::fontHtml(self::fontSvg('Inter'));

        self::assertSame('"Inter", system-ui, sans-serif', self::svgFamily($html));
        self::assertStringContainsString('@font-face{font-family:"Inter";', $html);
        self::assertStringContainsString('@font-face{font-family:"Cousine";', $html);
    }

    public function test_unregistered_svg_family_becomes_the_pdf_family(): void
    {
        $html = self::fontHtml(self::fontSvg('Arial'));

        self::assertSame('"Cousine", monospace', self::svgFamily($html));
        self::assertStringNotContainsString('Arial', $html);
    }

    public function test_first_registered_family_in_the_list_wins(): void
    {
        self::assertSame('"Inter", system-ui, sans-serif', self::svgFamily(self::fontHtml(self::fontSvg('Arial, inter'))));
    }

    public function test_svg_family_is_added_to_the_font_set_when_no_other_text_uses_it(): void
    {
        $svg = self::fontSvg('Inter');
        $html = Document::make(Page::a4()->body($svg->font('Cousine')))->toHtml(new HtmlOptions(fontMode: FontMode::Embed, inlineSvgs: true))->bytes();

        self::assertStringContainsString('@font-face{font-family:"Inter";', $html);
    }

    public function test_no_font_faces_mode_uses_page_stack(): void
    {
        $warnings = [];
        $options = new HtmlOptions(fontMode: FontMode::None, inlineSvgs: true, onWarning: static function (string $w) use (&$warnings): void {
            $warnings[] = $w;
        });
        $html = Document::make(Page::a4()->body(self::fontSvg('Arial')->font('Cousine')))->toHtml($options)->bytes();

        self::assertSame('"Cousine", monospace', self::svgFamily($html));
        self::assertStringNotContainsString('@font-face', $html);
        self::assertSame([], array_values(array_filter($warnings, static fn (string $w): bool => str_contains($w, 'font'))));
    }
}
