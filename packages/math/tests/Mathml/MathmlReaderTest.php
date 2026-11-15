<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Mathml;

use Papercut\Math\Math;
use Papercut\Math\Mathml\MathmlReader;
use Papercut\Math\NoteLevel;
use Papercut\Math\Node\DisplayStyle;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Space;
use Papercut\Math\Node\Text;
use Papercut\Math\Node\UnderOver;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\TextWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MathmlReaderTest extends TestCase
{
    private static function dump(Node $n): string
    {
        return match (true) {
            $n instanceof Fenced => '<' . $n->open . self::dump($n->content) . $n->close . '>',
            $n instanceof Row => 'R[' . implode(', ', array_map(self::dump(...), $n->children)) . ']',
            $n instanceof Operator => $n->char,
            $n instanceof Identifier => $n->name,
            $n instanceof Number, $n instanceof Text => $n->text,
            default => $n::class,
        };
    }

    private static function body(string $xml): string
    {
        return (string) preg_replace('/^<math[^>]*>|<\/math>$/', '', $xml);
    }

    private static function read(string $s): Node
    {
        $r = MathmlReader::read($s);
        self::assertSame([], $r->notes());

        return $r->node;
    }

    public function test_reads_quadratic_formula(): void
    {
        $node = self::read('<math display="block"><mrow><mi>x</mi><mo>=</mo><mfrac><mrow><mo>−</mo><mi>b</mi><mo>±</mo><msqrt><mrow><msup><mi>b</mi><mn>2</mn></msup><mo>−</mo><mn>4</mn><mi>a</mi><mi>c</mi></mrow></msqrt></mrow><mrow><mn>2</mn><mi>a</mi></mrow></mfrac></mrow></math>');
        $snapshot = rtrim((string) file_get_contents(__DIR__ . '/../snapshots/mathml/quadratic-display.xml'));
        self::assertSame($snapshot, MathmlWriter::write($node, true));
        self::assertTrue(MathmlReader::read('<math display="BLOCK"><mi>x</mi></math>')->display);
    }

    public function test_reads_dom_element(): void
    {
        $doc = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><body><p><math><mfrac><mi>a</mi><mi>b</mi></mfrac></math></p>');
        $math = $doc->getElementsByTagName('math')->item(0);
        self::assertInstanceOf(\Dom\Element::class, $math);
        self::assertInstanceOf(Fraction::class, Math::mathml($math));
    }

    public function test_prefixed_word_mathml(): void
    {
        $node = self::read('<mml:math xmlns:mml="http://www.w3.org/1998/Math/MathML"><mml:mfrac><mml:mi>a</mml:mi><mml:mi>b</mml:mi></mml:mfrac></mml:math>');
        self::assertInstanceOf(Fraction::class, $node);
    }

    public function test_entities_and_invisible_operators(): void
    {
        $node = self::read('<math><mi>f</mi><mo>&ApplyFunction;</mo><mo>(</mo><mi>x</mi><mo>)</mo></math>');
        self::assertInstanceOf(Row::class, $node);
        self::assertCount(2, $node->children);
        self::assertInstanceOf(Identifier::class, $node->children[0]);
        self::assertInstanceOf(Fenced::class, $node->children[1]);
        self::assertSame([], array_filter($node->children, static fn (Node $n): bool => $n instanceof Operator));
    }

    public function test_xml_declaration_and_bom(): void
    {
        $node = self::read("\u{FEFF}<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<math><mfrac><mn>1</mn><mn>2</mn></mfrac></math>");
        self::assertInstanceOf(Fraction::class, $node);
    }

    public function test_semantics_ignores_tex_annotation(): void
    {
        $node = self::read('<math><semantics><mi>x</mi><annotation encoding="application/x-tex">\frac{a}{b} &lt; c &amp; d</annotation></semantics></math>');
        self::assertStringNotContainsString('frac', MathmlWriter::write($node));
        self::assertInstanceOf(Identifier::class, $node);
    }

    public function test_mfenced_less_than_greater_than_are_angle_brackets(): void
    {
        // MathType writes angle brackets as <mfenced open="&lt;" close="&gt;">.
        $node = self::read('<math><mfenced open="&lt;" close="&gt;"><mi>x</mi></mfenced></math>');
        self::assertInstanceOf(Fenced::class, $node);
        self::assertSame('⟨', $node->open);
        self::assertSame('⟩', $node->close);
    }

    public function test_mfenced_with_separators(): void
    {
        $node = self::read('<math><mfenced open="[" close="]" separators="; ,"><mi>a</mi><mi>b</mi><mi>c</mi><mi>d</mi></mfenced></math>');
        self::assertInstanceOf(Fenced::class, $node);
        self::assertSame(['[', ']'], [$node->open, $node->close]);
        self::assertInstanceOf(Row::class, $node->content);
        $chars = array_map(static fn (Node $n): string => $n instanceof Operator ? $n->char : '.', $node->content->children);
        self::assertSame(['.', ';', '.', ',', '.', ',', '.'], $chars);

        $default = self::read('<math><mfenced><mi>a</mi><mi>b</mi></mfenced></math>');
        self::assertInstanceOf(Fenced::class, $default);
        self::assertSame(['(', ')'], [$default->open, $default->close]);

        $none = self::read('<math><mfenced separators=""><mi>a</mi><mi>b</mi></mfenced></math>');
        self::assertInstanceOf(Fenced::class, $none);
        self::assertInstanceOf(Row::class, $none->content);
        self::assertCount(2, $none->content->children);
    }

    /** @return iterable<string, array{string, string}> */
    public static function fencePairing(): iterable
    {
        $mo = static fn (string $s): string => implode('', array_map(static fn (string $c): string => $c === ' ' ? '' : (ctype_alpha($c) ? '<mi>' . $c . '</mi>' : '<mo>' . $c . '</mo>'), mb_str_split($s)));

        yield 'paren' => [$mo('(a)'), '<(a)>'];
        yield 'nested' => [$mo('( [ a ] )'), '<(<[a]>)>'];
        yield 'unmatched' => [$mo('(a'), 'R[(, a]'];
        yield 'bars' => [$mo('|x|'), '<|x|>'];
        yield 'interval' => [$mo('[0,1)'), '<[R[0, ,, 1])>'];
        yield 'brace in row' => [$mo('a{b}c'), 'R[a, <{b}>, c]'];
        yield 'stray closer' => [$mo('a)'), 'R[a, )]'];
        yield 'mismatched kinds' => [$mo('(a}'), 'R[(, a, }]'];
        yield 'two bar pairs' => [$mo('|a||b|'), 'R[<|a|>, <|b|>]'];
        yield 'bar around paren' => [$mo('|(a|'), '<|R[(, a]|>'];
        yield 'conditional probability' => [$mo('|P(A|B)|'), '<|R[P, <(R[A, |, B])>]|>'];
        yield 'conditional expectation' => [$mo('|E[X|Y]|'), '<|R[E, <[R[X, |, Y]]>]|>'];
        yield 'set builder' => [$mo('|{x|x}|'), '<|<{R[x, |, x]}>|>'];
        yield 'norm' => [$mo('‖v‖'), '<‖v‖>'];
        yield 'two abs values' => [$mo('|a|+|b|'), 'R[<|a|>, +, <|b|>]'];
        yield 'single bar' => [$mo('a|b'), 'R[a, |, b]'];
        yield 'norm inside abs' => [$mo('|a‖b|c‖'), 'R[<|R[a, ‖, b]|>, c, ‖]'];
        yield 'unmatched opener inside pair' => [$mo('(a|b)'), '<(R[a, |, b])>'];
    }

    #[DataProvider('fencePairing')]
    public function test_fence_pairing(string $inner, string $expected): void
    {
        self::assertSame($expected, self::dump(self::read('<math>' . $inner . '</math>')));
    }

    /** @return iterable<string, array{string, float}> */
    public static function spaces(): iterable
    {
        yield 'em' => ['1.5em', 1.5];
        yield 'ex' => ['2ex', 1.0];
        yield 'pt' => ['6pt', 0.5];
        yield 'px' => ['16px', 1.0];
        yield 'mm' => ['25.4mm', 6.0];
        yield 'cm' => ['2.54cm', 6.0];
        yield 'in' => ['1in', 6.0];
        yield 'negative em' => ['-0.5em', -0.5];
        yield 'veryverythin' => ['veryverythinmathspace', 1 / 18];
        yield 'thin' => ['thinmathspace', 3 / 18];
        yield 'medium' => ['mediummathspace', 4 / 18];
        yield 'veryveryhigh' => ['veryveryhighmathspace', 7 / 18];
        yield 'veryverythick' => ['veryverythickmathspace', 7 / 18];
        yield 'negative thick' => ['negativethickmathspace', -5 / 18];
        yield 'unparseable' => ['wide', 0.0];
        yield 'unitless' => ['3', 0.0];
    }

    #[DataProvider('spaces')]
    public function test_mspace_units(string $width, float $em): void
    {
        $node = self::read('<math><mspace width="' . $width . '"/></math>');
        self::assertInstanceOf(Space::class, $node);
        self::assertEqualsWithDelta($em, $node->em, 1e-9);
    }

    public function test_mspace_without_width(): void
    {
        $none = self::read('<math><mspace/></math>');
        self::assertInstanceOf(Space::class, $none);
        self::assertSame(0.0, $none->em);
    }

    public function test_linethickness_zero(): void
    {
        foreach (['0', '0px', '0em', '0%'] as $t) {
            $f = self::read('<math><mfrac linethickness="' . $t . '"><mi>a</mi><mi>b</mi></mfrac></math>');
            self::assertInstanceOf(Fraction::class, $f);
            self::assertFalse($f->bar, $t);
        }
        foreach (['1px', 'thin', '2'] as $t) {
            $f = self::read('<math><mfrac linethickness="' . $t . '"><mi>a</mi><mi>b</mi></mfrac></math>');
            self::assertInstanceOf(Fraction::class, $f);
            self::assertTrue($f->bar, $t);
        }
    }

    public function test_arrows_and_harpoons_are_accents(): void
    {
        foreach (['⇀', '↼', '←', '↔', "\u{20D1}", "\u{20D0}", "\u{20D6}", "\u{20E1}"] as $mark) {
            $n = self::read('<math><mover><mrow><mi>A</mi><mi>B</mi></mrow><mo>' . $mark . '</mo></mover></math>');
            self::assertInstanceOf(UnderOver::class, $n, $mark);
            self::assertTrue($n->accent, $mark);
        }
    }

    public function test_accent_inferred_from_mo(): void
    {
        $inferred = self::read('<math><mover><mi>x</mi><mo>^</mo></mover></math>');
        self::assertInstanceOf(UnderOver::class, $inferred);
        self::assertTrue($inferred->accent);

        $limit = self::read('<math><mover><mo>∑</mo><mi>n</mi></mover></math>');
        self::assertInstanceOf(UnderOver::class, $limit);
        self::assertFalse($limit->accent);

        $explicitOff = self::read('<math><mover accent="false"><mi>x</mi><mo>^</mo></mover></math>');
        self::assertInstanceOf(UnderOver::class, $explicitOff);
        self::assertFalse($explicitOff->accent);

        $under = self::read('<math><munderover accentunder="true"><mi>x</mi><mi>a</mi><mi>b</mi></munderover></math>');
        self::assertInstanceOf(UnderOver::class, $under);
        self::assertTrue($under->accentUnder);
    }

    public function test_mathvariant_double_struck(): void
    {
        $r = self::read('<math><mi mathvariant="double-struck">R</mi></math>');
        self::assertInstanceOf(Identifier::class, $r);
        self::assertSame("\u{211D}", $r->name);
        self::assertTrue($r->upright);

        $n = self::read('<math><mi mathvariant="normal">x</mi></math>');
        self::assertInstanceOf(Identifier::class, $n);
        self::assertTrue($n->upright);
    }

    public function test_display_block(): void
    {
        self::assertTrue(MathmlReader::read('<math display="block"><mi>x</mi></math>')->display);
        self::assertFalse(MathmlReader::read('<math display="inline"><mi>x</mi></math>')->display);
        self::assertFalse(MathmlReader::read('<math><mi>x</mi></math>')->display);
    }

    public function test_mstyle_displaystyle_false_makes_inline_fraction(): void
    {
        $plain = self::read('<math><mfrac><mn>1</mn><mn>2</mn></mfrac></math>');
        $styled = self::read('<math><mstyle displaystyle="false"><mfrac><mn>1</mn><mn>2</mn></mfrac></mstyle></math>');
        self::assertInstanceOf(DisplayStyle::class, $styled);
        $a = Math::layout($plain, display: true);
        $b = Math::layout($styled, display: true);
        self::assertLessThan($a->ascent + $a->descent, $b->ascent + $b->descent);
        self::assertStringContainsString('<mstyle displaystyle="false">', MathmlWriter::write($styled));
        self::assertSame(TextWriter::write($plain), TextWriter::write($styled));
        self::assertSame(OmmlWriter::oMath($plain), OmmlWriter::oMath($styled));
    }

    public function test_empty_math(): void
    {
        $node = self::read('<math></math>');
        self::assertInstanceOf(Row::class, $node);
        self::assertSame([], $node->children);
        $empty = self::read('');
        self::assertInstanceOf(Row::class, $empty);
    }

    public function test_bare_markup_without_math_wrapper(): void
    {
        self::assertInstanceOf(Fraction::class, self::read('<mfrac><mi>a</mi><mi>b</mi></mfrac>'));
    }

    public function test_wrong_child_count(): void
    {
        $r = MathmlReader::read('<math><mrow><mi>x</mi><mfrac><mi>a</mi></mfrac></mrow></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('mfrac', $notes[0]->element);
        self::assertSame('<mfrac> with 1 child', $notes[0]->reason);
        self::assertSame('math > mrow[0] > mfrac[1]', $notes[0]->where);

        $extra = MathmlReader::read('<math><mfrac><mi>a</mi><mi>b</mi><mi>c</mi></mfrac></math>');
        self::assertSame('<mfrac> with 3 children', $extra->notes()[0]->reason);
        self::assertStringContainsString('<mi>c</mi>', MathmlWriter::write($extra->node));

        $twice = MathmlReader::read('<math><mfrac><mi>a</mi></mfrac><mfrac><mi>b</mi></mfrac></math>');
        self::assertCount(1, $twice->notes());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function selfClosing(): iterable
    {
        $cases = [
            'mspace' => '<P:mi>a</P:mi><P:mspace width="1em"/><P:mi>b</P:mi><P:mi>c</P:mi>',
            'empty mrow' => '<P:msub><P:mrow/><P:mi>x</P:mi></P:msub>',
            'none' => '<P:mrow><P:none/><P:mi>x</P:mi><P:mi>y</P:mi></P:mrow>',
            'empty mi' => '<P:mfrac><P:mi/><P:mn>2</P:mn></P:mfrac>',
        ];
        foreach ($cases as $name => $markup) {
            yield $name . ' (xml)' => [$markup, false];
            yield $name . ' (html)' => [$markup, true];
        }
    }

    #[DataProvider('selfClosing')]
    public function test_prefixed_self_closing_elements_keep_their_siblings(string $markup, bool $forceHtml): void
    {
        // An &nbsp; entity is not XML, which sends the input down the HTML5 path; it becomes a Space in both forms.
        $tail = static fn (string $p): string => $forceHtml ? '<' . $p . 'mo>&nbsp;</' . $p . 'mo>' : '';
        $prefixed = '<mml:math xmlns:mml="http://www.w3.org/1998/Math/MathML">' . str_replace('P:', 'mml:', $markup) . $tail('mml:') . '</mml:math>';
        $plain = '<math>' . str_replace('P:', '', $markup) . $tail('') . '</math>';
        $expected = MathmlWriter::write(self::read($plain));
        self::assertSame($expected, MathmlWriter::write(self::read($prefixed)));
        self::assertStringNotContainsString('<mrow></mrow><mrow', $expected);
    }

    public function test_prefixed_self_closing_keeps_every_sibling(): void
    {
        $node = self::read('<mml:math xmlns:mml="http://www.w3.org/1998/Math/MathML"><mml:mi>a</mml:mi><mml:mspace width="1em"/><mml:mi>b</mml:mi><mml:mi>c</mml:mi></mml:math>');
        self::assertInstanceOf(Row::class, $node);
        self::assertCount(4, $node->children);
    }

    public function test_raw_less_than_in_annotation_does_not_end_the_formula(): void
    {
        $in = '<math><mi>a</mi><mo>+</mo><semantics><mi>z</mi><annotation encoding="application/x-tex">x<b y</annotation></semantics><mo>=</mo><mi>w</mi></math>';
        self::assertSame('<mrow><mi>a</mi><mo>+</mo><mi>z</mi><mo>=</mo><mi>w</mi></mrow>', self::body(MathmlWriter::write(self::read($in))));
        $prefixed = '<mml:math xmlns:mml="http://www.w3.org/1998/Math/MathML"><mml:mi>a</mml:mi><mml:semantics><mml:mi>z</mml:mi><mml:ANNOTATION-XML><p>q<r</p></mml:ANNOTATION-XML><mml:annotation/></mml:semantics><mml:mi>w</mml:mi></mml:math>';
        self::assertSame('<mrow><mi>a</mi><mi>z</mi><mi>w</mi></mrow>', self::body(MathmlWriter::write(self::read($prefixed))));
    }

    public function test_stray_text_counts_as_a_child(): void
    {
        $r = MathmlReader::read('<math><mfrac>1<mn>2</mn><mn>3</mn></mfrac></math>');
        self::assertSame('<mfrac> with 3 children', $r->notes()[0]->reason);
        self::assertStringContainsString('<mtext>1</mtext>', MathmlWriter::write($r->node));

        $fenced = self::read('<math><mfenced>x<mi>a</mi></mfenced></math>');
        self::assertInstanceOf(Fenced::class, $fenced);
        self::assertInstanceOf(Row::class, $fenced->content);
        self::assertCount(3, $fenced->content->children);
    }

    public function test_semantics_maps_only_its_first_child(): void
    {
        $r = MathmlReader::read('<math><semantics><mi>x</mi><mfrac><mi>a</mi></mfrac></semantics><mfrac><mi>b</mi></mfrac></math>');
        self::assertCount(1, $r->notes());
        self::assertSame('math > mfrac[1]', $r->notes()[0]->where);
    }

    public function test_garbage_never_throws(): void
    {
        foreach (['<', '<<<>', '</math>', '<math', "\0", '   ', '<mml:math><mml:mi>'] as $s) {
            self::assertInstanceOf(Node::class, MathmlReader::read($s)->node);
        }
    }

    public function test_cdata_in_prefixed_mtext(): void
    {
        $node = self::read('<mml:math xmlns:mml="http://www.w3.org/1998/Math/MathML"><mml:mtext><![CDATA[a<b]]></mml:mtext></mml:math>');
        self::assertInstanceOf(Text::class, $node);
        self::assertSame('a<b', $node->text);
    }

    public function test_whitespace_only_mo_is_dropped(): void
    {
        foreach (['<mo> </mo>', '<mo></mo>', '<mo>&#x2062;</mo>', '<mo>&#x2061;</mo>'] as $mo) {
            $node = self::read('<math><mi>a</mi>' . $mo . '<mi>b</mi></math>');
            self::assertInstanceOf(Row::class, $node, $mo);
            self::assertCount(2, $node->children, $mo);
        }
    }

    public function test_no_break_space_mo_is_a_space(): void
    {
        $node = self::read('<math><mi>a</mi><mo>&nbsp;</mo><mi>b</mi><mo>&#xA0;&#xA0;</mo><mi>c</mi></math>');
        self::assertInstanceOf(Row::class, $node);
        self::assertCount(5, $node->children);
        self::assertInstanceOf(Space::class, $node->children[1]);
        self::assertEqualsWithDelta(0.25, $node->children[1]->em, 1e-9);
        self::assertInstanceOf(Space::class, $node->children[3]);
        self::assertEqualsWithDelta(0.5, $node->children[3]->em, 1e-9);
    }
}
