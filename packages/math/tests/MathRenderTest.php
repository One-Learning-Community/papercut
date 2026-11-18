<?php

declare(strict_types=1);

namespace Quoin\Math\Tests;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Font\Font;
use Quoin\Math\Math;
use Quoin\Math\MathRender;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\RunFormat;
use Quoin\Math\Render\SvgRenderer;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Render\TextWriter;

final class MathRenderTest extends TestCase
{
    public function test_tex_dollars_and_brackets_start_in_display(): void
    {
        self::assertTrue(MathRender::fromTex('$$x$$')->isDisplay());
        self::assertTrue(MathRender::fromTex('\[x\]')->isDisplay());
        self::assertFalse(MathRender::fromTex('x')->isDisplay());
        self::assertFalse(MathRender::fromTex('$x$')->isDisplay());
    }

    public function test_mathml_block_starts_in_display(): void
    {
        self::assertTrue(MathRender::fromMathml('<math display="block"><mi>x</mi></math>')->isDisplay());
        self::assertFalse(MathRender::fromMathml('<math><mi>x</mi></math>')->isDisplay());

        $doc = \Dom\HTMLDocument::createFromString('<p><math display="block"><mi>x</mi></math></p>', LIBXML_NOERROR);
        $math = $doc->querySelector('math');
        self::assertNotNull($math);
        self::assertTrue(MathRender::fromMathml($math)->isDisplay());
    }

    public function test_of_starts_inline(): void
    {
        $r = MathRender::of(Math::frac('a', 'b'));

        self::assertFalse($r->isDisplay());
        self::assertSame('\frac{a}{b}', $r->tex());
    }

    public function test_modifiers_return_new_instances(): void
    {
        $a = MathRender::fromTex('x');
        $b = $a->display();

        self::assertNotSame($a, $b);
        self::assertFalse($a->isDisplay());
        self::assertTrue($b->isDisplay());
        self::assertFalse($b->inline()->isDisplay());
        self::assertTrue($b->isDisplay());
    }

    public function test_outputs_match_the_writers(): void
    {
        $f = '\frac{a}{b}';
        $node = Math::tex($f);
        $r = MathRender::fromTex($f);

        self::assertSame(TexWriter::write($node), $r->tex());
        self::assertSame(TextWriter::write($node), $r->text());
        self::assertSame(MathmlWriter::write($node, display: false), $r->mathml());
        self::assertSame(MathmlWriter::write($node, display: true), $r->display()->mathml());
        self::assertSame(OmmlWriter::oMath($node), $r->omml());
        self::assertSame(OmmlWriter::oMathPara($node, 'center'), $r->display()->omml());
        self::assertSame(OmmlWriter::oMathPara($node, 'left'), $r->display()->omml('left'));

        $format = new RunFormat(colorHex: 'C00000');
        self::assertSame(OmmlWriter::oMath($node, $format), $r->omml(format: $format));
        self::assertSame(OmmlWriter::oMathPara($node, 'center', $format), $r->display()->omml(format: $format));
    }

    public function test_node_is_the_read_tree(): void
    {
        self::assertEquals(Math::tex('x^2'), MathRender::fromTex('x^2')->node());
    }

    public function test_notes_report_what_the_reader_could_not_draw(): void
    {
        $tex = '\nosuchcommand x';
        $notes = MathRender::fromTex($tex)->notes();

        self::assertNotSame([], $notes);
        self::assertEquals(Math::notes(Math::tex($tex)), $notes);
    }

    public function test_hostile_tex_does_not_throw(): void
    {
        self::assertNotSame([], MathRender::fromTex('\frac{')->notes());
        self::assertNotSame([], MathRender::fromTex(str_repeat('{', 20000))->notes());
    }

    public function test_svg_matches_the_renderer_apart_from_the_root_attributes(): void
    {
        $font = Math::stixTwoMath();
        $inline = (new SvgRenderer($font, 'currentColor'))->render(Math::layout(Math::tex('x^2'), 16.0, false));
        self::assertSame(self::stripped($inline), self::stripped(MathRender::fromTex('x^2')->svg()));

        $display = (new SvgRenderer($font, '#123456'))->render(Math::layout(Math::tex('x^2'), 18.0, true), 2.0);
        self::assertSame(self::stripped($display), self::stripped(MathRender::fromTex('x^2')->display()->svg(size: 18, color: '#123456', pad: 2)));
    }

    public function test_inline_svg_carries_role_label_and_vertical_align(): void
    {
        $r = MathRender::fromTex('x_1');
        $root = self::root($r->svg(pad: 1.5));
        $descent = Math::layout(Math::tex('x_1'), 16.0, false)->descent;

        self::assertSame('img', $root->getAttribute('role'));
        self::assertSame($r->text(), $root->getAttribute('aria-label'));
        self::assertSame('vertical-align:' . rtrim(rtrim(number_format(-($descent + 1.5), 3, '.', ''), '0'), '.') . 'px', $root->getAttribute('style'));
    }

    public function test_display_svg_has_no_vertical_align(): void
    {
        $root = self::root(MathRender::fromTex('x_1')->display()->svg());

        self::assertFalse($root->hasAttribute('style'));
        self::assertSame('img', $root->getAttribute('role'));
    }

    public function test_label_is_escaped(): void
    {
        $r = MathRender::fromTex('\text{a<b & "c"}');
        self::assertSame($r->text(), self::root($r->svg())->getAttribute('aria-label'));
        self::assertSame('é <x> "y"', self::root($r->svg(label: 'é <x> "y"'))->getAttribute('aria-label'));
    }

    public function test_empty_label_hides_the_svg(): void
    {
        $root = self::root(MathRender::fromTex('x')->svg(label: ''));

        self::assertSame('true', $root->getAttribute('aria-hidden'));
        self::assertFalse($root->hasAttribute('aria-label'));
        self::assertFalse($root->hasAttribute('role'));
    }

    public function test_font_reaches_layout_and_renderer(): void
    {
        $fira = Font::fromFile(__DIR__ . '/fixtures/FiraMath-Regular.otf');
        $r = MathRender::fromTex('\sqrt{x^2}');
        $expected = (new SvgRenderer($fira, 'currentColor'))->render(Math::layout(Math::tex('\sqrt{x^2}'), 16.0, false, $fira));

        self::assertNotSame(self::stripped($r->svg()), self::stripped($r->font($fira)->svg()));
        self::assertSame(self::stripped($expected), self::stripped($r->font($fira)->svg()));
        self::assertSame(self::stripped($expected), self::stripped($r->font($fira)->display()->inline()->svg()));
    }

    public function test_hostile_tex_svg_is_well_formed(): void
    {
        self::root(MathRender::fromTex('\frac{')->svg());
        self::root(MathRender::fromTex("\\text{a\x01b}")->svg());
        self::root(MathRender::fromTex('x')->svg(label: "a\x02b"));
        self::root(MathRender::fromTex(str_repeat('\frac{a}{b}', 1500))->svg());
        $this->addToAssertionCount(1);
    }

    private static function root(string $svg): \DOMElement
    {
        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($svg));
        $root = $doc->documentElement;
        self::assertNotNull($root);

        return $root;
    }

    private static function stripped(string $svg): string
    {
        $root = self::root($svg);
        foreach (['role', 'aria-label', 'aria-hidden', 'style'] as $name) {
            $root->removeAttribute($name);
        }

        return (string) $root->ownerDocument?->saveXML($root);
    }
}
