<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use Papercut\Math\Math;
use Papercut\Math\Mathml\MathmlReader;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\UnderOver;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Styles;
use Papercut\Math\Tests\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MathmlWriterTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function fixtureNames(): iterable
    {
        foreach (array_keys(Fixtures::all()) as $name) {
            yield $name => [$name];
        }
    }

    private static function render(string $name): string
    {
        return MathmlWriter::write(Fixtures::all()[$name], ! str_ends_with($name, '-inline'));
    }

    #[DataProvider('fixtureNames')]
    public function test_snapshot(string $name): void
    {
        $path = __DIR__ . '/../snapshots/mathml/' . $name . '.xml';
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
        $doc = \Dom\XMLDocument::createFromString(self::render($name));
        self::assertSame('math', $doc->documentElement?->localName);
    }

    public function test_escapes_xml_specials(): void
    {
        $xml = MathmlWriter::write(Math::row(Math::text('a < b & c'), '< &'));
        self::assertStringContainsString('<mtext>a &lt; b &amp; c</mtext>', $xml);
        self::assertStringContainsString('&lt;</mo>', $xml);
        self::assertStringContainsString('&amp;</mo>', $xml);
        \Dom\XMLDocument::createFromString($xml);
    }

    public function test_empty_slots(): void
    {
        $xml = MathmlWriter::write(Math::frac('', '2'));
        self::assertStringContainsString('<mfrac><mrow></mrow><mn>2</mn></mfrac>', $xml);
        self::assertStringContainsString('<msqrt><mrow></mrow></msqrt>', MathmlWriter::write(Math::sqrt('')));
        self::assertStringContainsString('<msup><mi>x</mi><mrow></mrow></msup>', MathmlWriter::write(Math::sup('x', '')));
    }

    public function test_forms_are_written_when_they_differ_from_the_browser_default(): void
    {
        self::assertStringContainsString('<mo form="prefix">−</mo>', MathmlWriter::write(Math::row('a=-b')));
        self::assertStringContainsString('<mo form="postfix" stretchy="false">|</mo>', MathmlWriter::write(Math::row('|x|+1')));
        self::assertStringContainsString('<msup><mi>x</mi><mo form="prefix">+</mo></msup>', MathmlWriter::write(Math::sup('x', '+')));
    }

    public function test_function_names_are_upright(): void
    {
        self::assertStringContainsString('<mi mathvariant="normal">sin</mi>', MathmlWriter::write(Math::row('sin x')));
    }

    public function test_mtext_keeps_its_spaces(): void
    {
        $xml = MathmlWriter::write(Math::row('x', Math::text(' if  so '), 'x>0'));
        self::assertStringContainsString("<mtext>\u{A0}if\u{A0}\u{A0}so\u{A0}</mtext>", $xml);
    }

    public function test_math_italic_code_points_are_written_as_plain_letters(): void
    {
        $xml = MathmlWriter::write(Math::row(new Identifier("\u{1D465}"), new Identifier("\u{210E}"), new Identifier("\u{1D6FC}")));
        self::assertStringContainsString('<mi>x</mi><mi>h</mi><mi>α</mi>', $xml);
        foreach (array_merge(range(0x41, 0x5A), range(0x61, 0x7A), range(0x391, 0x3A9), range(0x3B1, 0x3C9)) as $cp) {
            if ($cp !== 0x3A2) {
                self::assertSame($cp, Styles::plain(Styles::italic($cp)));
            }
        }
    }

    public function test_only_fenced_delimiters_stretch(): void
    {
        $xml = MathmlWriter::write(Math::row('(a)'));
        self::assertStringContainsString('<mo stretchy="false">(</mo>', $xml);
        self::assertStringNotContainsString('stretchy="true"', $xml);
        self::assertStringContainsString('<mo stretchy="true">(</mo>', MathmlWriter::write(Math::fenced('(', ')', 'a')));
    }

    public function test_strips_characters_xml_cannot_hold(): void
    {
        $xml = MathmlWriter::write(Math::text("a\x01b\x00c\u{FFFE}d\u{FFFF}e\x0Bf"));
        self::assertStringContainsString('<mtext>abcdef</mtext>', $xml);
        \Dom\XMLDocument::createFromString($xml);
    }

    public function test_small_constructs(): void
    {
        self::assertStringContainsString('<mspace width="0.167em"/>', MathmlWriter::write(Math::space(1 / 6)));
        self::assertStringContainsString('<mspace width="-0.2em"/>', MathmlWriter::write(Math::space(-0.2)));
        self::assertStringContainsString('<mspace width="0.5em"/>', MathmlWriter::write(Math::space(0.5)));
        self::assertStringContainsString('<mphantom><mi>x</mi></mphantom>', MathmlWriter::write(Math::phantom('x')));
        self::assertStringContainsString('<mfrac linethickness="0"><mi>n</mi><mi>k</mi></mfrac>', MathmlWriter::write(Math::stack('n', 'k')));
        self::assertStringContainsString('<mi mathvariant="normal">ℝ</mi>', MathmlWriter::write(Math::styled('R', 'double-struck')));
        self::assertStringContainsString('<mi mathvariant="normal">𝑥</mi>', MathmlWriter::write(Math::styled('x', 'italic')));
    }

    public function test_limits(): void
    {
        self::assertStringContainsString('<munder><mi>x</mi><mi>y</mi></munder>', MathmlWriter::write(Math::under('x', 'y')));
        self::assertStringContainsString('<mover><mo>=</mo><mrow><mi>d</mi><mi>e</mi><mi>f</mi></mrow></mover>', MathmlWriter::write(Math::over('=', 'def')));
        self::assertStringContainsString('<munderover><mo form="prefix">∑</mo><mrow><mi>i</mi><mo>=</mo><mn>1</mn></mrow><mi>n</mi></munderover>', MathmlWriter::write(Math::sum('i=1', 'n')));
        self::assertStringContainsString('<msubsup><mo form="prefix">∫</mo><mn>0</mn><mn>1</mn></msubsup>', MathmlWriter::write(Math::integral('0', '1')));
    }

    public function test_accents_and_bars(): void
    {
        self::assertSame('<mover accent="true"><mi>x</mi><mo>' . "\u{0302}" . '</mo></mover>', preg_replace('/^<math[^>]*>|<\/math>$/', '', MathmlWriter::write(Math::hat('x'))));
        self::assertStringContainsString('<mover accent="true"><mi>x</mi><mo>¯</mo></mover>', MathmlWriter::write(Math::overline('x')));
        self::assertStringContainsString('<munder accentunder="true"><mi>x</mi><mo>_</mo></munder>', MathmlWriter::write(Math::underline('x')));
        self::assertStringContainsString('<munderover accent="true" accentunder="true">', MathmlWriter::write(new UnderOver(Math::row('x'), new Operator("\u{0332}"), new Operator("\u{0302}"), true, true)));
    }

    public function test_underbrace_with_label(): void
    {
        self::assertStringContainsString('<munder><munder><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow><mo>⏟</mo></munder><mi>n</mi></munder>', MathmlWriter::write(Math::underbrace('a+b', 'n')));
    }

    public function test_stretched_parts_are_not_told_not_to_stretch(): void
    {
        $arrow = MathmlWriter::write(Math::over(new Operator('→'), 'f'));
        self::assertStringContainsString('<mo>→</mo>', $arrow);
        self::assertStringNotContainsString('stretchy="false"', $arrow);
        foreach ([Math::overbrace('a'), Math::hat('x'), Math::overline('x')] as $node) {
            self::assertStringNotContainsString('stretchy="false"', MathmlWriter::write($node));
        }
    }

    public function test_explicit_stretchy_is_written_as_given(): void
    {
        $fixed = MathmlWriter::write(new UnderOver(Math::row('abc'), null, new Operator('→', false)));
        self::assertStringContainsString('<mo stretchy="false">→</mo>', $fixed);
        $forced = MathmlWriter::write(Math::row('a', new Operator('+', true), 'b'));
        self::assertStringContainsString('<mo stretchy="true">+</mo>', $forced);
    }

    public function test_largeop_and_movablelimits_overrides_are_written(): void
    {
        $xml = MathmlWriter::write(new UnderOver(new Operator('∑', null, false, false), Math::row('i'), null));
        self::assertStringContainsString('<mo largeop="false" movablelimits="false">∑</mo>', $xml);
        self::assertStringNotContainsString('largeop', MathmlWriter::write(Math::sum('i', 'n')));
    }

    public function test_lim_like_base_is_an_operator_with_movable_limits(): void
    {
        $xml = MathmlWriter::write(Math::under('lim', 'x → 0'));
        self::assertStringContainsString('<munder><mo movablelimits="true">lim</mo><mrow>', $xml);
        self::assertStringNotContainsString('<mi mathvariant="normal">lim</mi>', $xml);
        self::assertStringContainsString('<mo movablelimits="true">max</mo>', MathmlWriter::write(Math::under('max', 'x')));
    }

    public function test_wrapped_lim_base_keeps_its_name(): void
    {
        foreach ([
            '<math><munder><menclose notation="box"><mi>lim</mi></menclose><mi>x</mi></munder></math>',
            '<math><munder><mi>lim<mglyph alt="q"/></mi><mi>x</mi></munder></math>',
        ] as $src) {
            $out = MathmlWriter::write(MathmlReader::read($src)->node);
            self::assertStringContainsString('lim', $out, $src);
            self::assertStringNotContainsString('<mo movablelimits="true"></mo>', $out, $src);
        }
    }

    public function test_mathml_writes_lspace_rspace(): void
    {
        self::assertStringContainsString(
            '<mo lspace="0.2778em" rspace="0.2778em">x</mo>',
            MathmlWriter::write(Math::row('a', new Operator('x', lspace: 5, rspace: 5), 'b')),
        );
    }

    public function test_mathml_writes_minsize(): void
    {
        $out = MathmlWriter::write(Math::row(Math::big('(', 2), 'a'));
        self::assertStringContainsString('minsize="1.8em" maxsize="1.8em"', $out);
    }

    public function test_size_wins_over_stretchy_false(): void
    {
        $out = MathmlWriter::write(Math::row(new Operator('(', stretchy: false, minSize: 1.8, maxSize: 1.8), 'a'));
        self::assertStringNotContainsString('stretchy="false"', $out);
        self::assertStringContainsString('minsize="1.8em"', $out);
    }
}
