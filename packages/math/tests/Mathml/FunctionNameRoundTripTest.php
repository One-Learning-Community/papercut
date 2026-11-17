<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Mathml;

use Quoin\Math\Math;
use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Row;
use Quoin\Math\OperatorDictionary;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tex\TexReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** TeX to MathML (with ApplyFunction) and back keeps a function name a function. */
final class FunctionNameRoundTripTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function formulas(): iterable
    {
        $names = array_map(static fn (string $n): string => '\\' . $n, array_diff(OperatorDictionary::FUNCTION_NAMES, ['sgn']));
        $names[] = '\operatorname{rank}';
        $names[] = '\operatorname{sgn}';
        foreach ($names as $n) {
            foreach (['2' . $n . ' x', $n . '_x x', $n . '^2 x', '2' . $n . '(a,b)'] as $tex) {
                yield $tex => [$tex];
            }
        }
        yield 'declared' => ['\DeclareMathOperator{\rk}{rk} 2\rk A'];
        yield 'declared star' => ['\DeclareMathOperator*{\argmax}{arg\,max} \argmax_x f'];
    }

    #[DataProvider('formulas')]
    public function test_the_function_flag_and_spacing_survive_mathml(string $tex): void
    {
        $a = TexReader::read($tex);
        $xml = MathmlWriter::write($a->node);
        self::assertStringContainsString('&#x2061;', $xml);
        $back = MathmlReader::read($xml)->node;
        $w = TexWriter::write($a->node);
        self::assertStringNotContainsString('\mathrm', TexWriter::write($back), $xml);
        self::assertSame($w, TexWriter::write($back), $xml);
        self::assertEqualsWithDelta(Math::layout($a->node, 10.0)->width, Math::layout($back, 10.0)->width, 0.01, $xml);
    }

    public function test_application_after_a_scripted_base_and_with_a_styled_variant(): void
    {
        $x = Math::layout(MathmlReader::read('<math><mn>2</mn><msup><mi>foo</mi><mn>2</mn></msup><mo>&#x2061;</mo><mi>x</mi></math>')->node, 10.0)->width;
        $y = Math::layout(MathmlReader::read('<math><mn>2</mn><msup><mi>foo</mi><mn>2</mn></msup><mi>x</mi></math>')->node, 10.0)->width;
        self::assertGreaterThan($y + 1.0, $x);
        $row = MathmlReader::read('<math><mi mathvariant="bold">sin</mi><mi>x</mi></math>')->node;
        self::assertInstanceOf(Row::class, $row);
        self::assertTrue($row->children[0] instanceof Identifier && $row->children[0]->function);
        $row = MathmlReader::read('<math><mi mathvariant="bold">kg</mi><mi>x</mi></math>')->node;
        self::assertInstanceOf(Row::class, $row);
        self::assertTrue($row->children[0] instanceof Identifier && ! $row->children[0]->function);
    }

    /** @return iterable<string, array{string, string, int}> tex, the fixed-arity element, its child count */
    public static function slots(): iterable
    {
        yield 'frac sin' => ['\frac{\sin}{x}', 'mfrac', 2];
        yield 'frac rank' => ['\frac{\operatorname{rank}}{x}', 'mfrac', 2];
        yield 'sup log' => ['x^{\log}', 'msup', 2];
        yield 'sup rank' => ['x^{\operatorname{rank}}', 'msup', 2];
        yield 'sub max' => ['e_{\max}', 'msub', 2];
        yield 'root index' => ['\sqrt[\log]{x}', 'mroot', 2];
        yield 'underset max' => ['\underset{\max}{x}', 'munder', 2];
        yield 'underset rank' => ['\underset{\operatorname{rank}}{x}', 'munder', 2];
        yield 'scripted rank in frac' => ['\frac{\operatorname{rank}_n}{x}', 'mfrac', 2];
    }

    #[DataProvider('slots')]
    public function test_a_function_name_alone_in_a_slot_keeps_the_arity(string $tex, string $element, int $children): void
    {
        $xml = MathmlWriter::write(TexReader::read($tex)->node);
        $dom = simplexml_load_string($xml);
        self::assertNotFalse($dom);
        $found = $dom->xpath('//*[local-name()="' . $element . '"]');
        self::assertIsArray($found);
        self::assertSame($children, count($found[0]->children()), $xml);
        $back = MathmlReader::read($xml)->node;
        self::assertSame(TexWriter::write(TexReader::read($tex)->node), TexWriter::write($back), $xml);
        self::assertStringNotContainsString('\mathrm', TexWriter::write($back), $xml);
    }

    /** @return iterable<string, array{string}> */
    public static function alone(): iterable
    {
        yield 'lone sin' => ['\sin'];
        yield 'lone rank' => ['\operatorname{rank}'];
        yield 'a sin' => ['a \sin'];
        yield 'a rank' => ['a \operatorname{rank}'];
        yield 'matrix sin' => ['\begin{pmatrix}\sin & 1 \\\\ 2 & \operatorname{rank}\end{pmatrix}'];
    }

    #[DataProvider('alone')]
    public function test_a_trailing_or_lone_function_name_round_trips_without_a_dangling_mark(string $tex): void
    {
        $xml = MathmlWriter::write(TexReader::read($tex)->node);
        self::assertNotFalse(simplexml_load_string($xml));
        self::assertDoesNotMatchRegularExpression('#<mo>&\#x2061;</mo></math>#', $xml);
        $back = MathmlReader::read($xml)->node;
        self::assertSame(TexWriter::write(TexReader::read($tex)->node), TexWriter::write($back), $xml);
        self::assertStringNotContainsString('\mathrm', TexWriter::write($back), $xml);
    }
}
