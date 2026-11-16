<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use Papercut\Math\Math;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\TextWriter;
use Papercut\Math\Tokenizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function functionNames(): array
    {
        $out = [];
        foreach (['sin', 'cos', 'tan', 'log', 'ln', 'exp', 'lim', 'max', 'min'] as $f) {
            $out[$f] = [$f];
        }

        return $out;
    }

    #[DataProvider('functionNames')]
    public function test_function_names_upright(string $name): void
    {
        $t = Tokenizer::tokenize($name);
        self::assertCount(1, $t);
        self::assertInstanceOf(Identifier::class, $t[0]);
        self::assertSame($name, $t[0]->name);
        self::assertTrue($t[0]->upright);
    }

    /** @return array<string, array{string}> */
    public static function wordsContainingFunctionNames(): array
    {
        $out = [];
        foreach (['cost', 'minutes', 'expense', 'maximum', 'cosine', 'tangent', 'logic', 'login', 'determine', 'limit', 'mint', 'sinx', 'arcsine'] as $w) {
            $out[$w] = [$w];
        }

        return $out;
    }

    #[DataProvider('wordsContainingFunctionNames')]
    public function test_function_name_inside_a_longer_letter_run_is_not_matched(string $word): void
    {
        $t = Tokenizer::tokenize($word);
        self::assertCount(mb_strlen($word), $t);
        foreach ($t as $i => $node) {
            self::assertInstanceOf(Identifier::class, $node);
            self::assertSame(mb_substr($word, $i, 1), $node->name);
            self::assertFalse($node->upright);
        }
    }

    public function test_function_name_is_matched_when_it_is_the_whole_letter_run(): void
    {
        $names = static fn (string $s): array => array_map(
            static fn ($n): string => $n instanceof Identifier ? $n->name . ($n->upright ? '!' : '') : $n::class,
            Tokenizer::tokenize($s),
        );
        self::assertSame(['sin!', 'x'], $names('sin x'));
        self::assertSame(['sin!', Operator::class, 'x', Operator::class], $names('sin(x)'));
        self::assertSame(['sin!', Number::class, 'x'], $names('sin2x'));
        self::assertSame(['arcsin!', 'θ'], $names('arcsin θ'));
        self::assertSame(['t', 'o', 't', 'a', 'l', 'c', 'o', 's', 't', Operator::class, Number::class], $names('total cost = 5'));
    }

    public function test_unicode_superscript_becomes_a_real_superscript(): void
    {
        $t = Tokenizer::tokenize('x²');
        self::assertCount(1, $t);
        self::assertInstanceOf(Scripts::class, $t[0]);
        self::assertInstanceOf(Identifier::class, $t[0]->base);
        self::assertSame('x', $t[0]->base->name);
        self::assertNull($t[0]->sub);
        self::assertInstanceOf(Number::class, $t[0]->sup);
        self::assertSame('2', $t[0]->sup->text);
    }

    /** @return array<string, array{string, string}> */
    public static function scriptMathml(): array
    {
        return [
            'superscript' => ['x²', '<msup><mi>x</mi><mn>2</mn></msup>'],
            'subscript' => ['y₂', '<msub><mi>y</mi><mn>2</mn></msub>'],
            'multi-digit' => ['x¹⁰', '<msup><mi>x</mi><mn>10</mn></msup>'],
            'sub and sup' => ['x₁²', '<msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup>'],
            'sup and sub' => ['x²₁', '<msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup>'],
            'negative' => ['x⁻¹', '<msup><mi>x</mi><mrow><mo>−</mo><mn>1</mn></mrow></msup>'],
            'letter n' => ['eⁿ', '<msup><mi>e</mi><mi>n</mi></msup>'],
            'number base' => ['10³', '<msup><mn>10</mn><mn>3</mn></msup>'],
            'group base' => ['(a+b)²', '<msup><mrow><mo>(</mo><mi>a</mi><mo>+</mo><mi>b</mi><mo>)</mo></mrow><mn>2</mn></msup>'],
            'nested group base' => ['((a))²', '<msup><mrow><mo>(</mo><mo>(</mo><mi>a</mi><mo>)</mo><mo>)</mo></mrow><mn>2</mn></msup>'],
            'function base' => ['sin²', '<msup><mi>sin</mi><mn>2</mn></msup>'],
            'quadratic' => ['b² − 4ac', '<msup><mi>b</mi><mn>2</mn></msup><mo>−</mo><mn>4</mn><mi>a</mi><mi>c</mi>'],
        ];
    }

    #[DataProvider('scriptMathml')]
    public function test_unicode_scripts_attach_to_the_token_before(string $input, string $expected): void
    {
        $xml = MathmlWriter::write(Math::row($input), false);
        self::assertStringContainsString($expected, preg_replace('~\s+(mathvariant|stretchy|form)="[^"]*"~', '', $xml) ?? '');
    }

    public function test_unicode_script_with_nothing_before_stays_a_character(): void
    {
        foreach (['²', '+ ²', '( ²'] as $s) {
            $t = Tokenizer::tokenize($s);
            $last = $t[count($t) - 1];
            self::assertInstanceOf(Number::class, $last, $s);
            self::assertSame('²', $last->text, $s);
        }
    }

    public function test_unicode_scripts_round_trip_through_text_writer(): void
    {
        foreach (['b² − 4ac', 'x₁ + x₂', 'x⁻¹', '(a + b)²'] as $s) {
            self::assertSame($s, TextWriter::write(Math::row($s)), $s);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function moreScripts(): array
    {
        return [
            'greek after function' => ['sinθ', '<mi>sin</mi><mi>θ</mi>'],
            'greek after cos' => ['cosα', '<mi>cos</mi><mi>α</mi>'],
            'function with letter subscript' => ['maxᵢ', '<msub><mi>max</mi><mi>i</mi></msub>'],
            'log base' => ['logₑ x', '<msub><mi>log</mi><mi>e</mi></msub><mi>x</mi>'],
            'letter superscript' => ['e⁻ˣ', '<msup><mi>e</mi><mrow><mo>−</mo><mi>x</mi></mrow></msup>'],
            'letter k superscript' => ['2⁻ᵏ', '<msup><mn>2</mn><mrow><mo>−</mo><mi>k</mi></mrow></msup>'],
            'subscript expression' => ['aₙ₊₁', '<msub><mi>a</mi><mrow><mi>n</mi><mo>+</mo><mn>1</mn></mrow></msub>'],
            'sub then sup' => ['xᵢ²', '<msubsup><mi>x</mi><mi>i</mi><mn>2</mn></msubsup>'],
            'abs bars' => ['|x|²', '<msup><mrow><mo>|</mo><mi>x</mi><mo>|</mo></mrow><mn>2</mn></msup>'],
            'norm bars' => ['‖v‖²', '<msup><mrow><mo>‖</mo><mi>v</mi><mo>‖</mo></mrow><mn>2</mn></msup>'],
            'second abs' => ['|a|+|b|²', '<mo>|</mo><mi>a</mi><mo>|</mo><mo>+</mo><msup><mrow><mo>|</mo><mi>b</mi><mo>|</mo></mrow><mn>2</mn></msup>'],
            'prime' => ['f′²', '<msup><mrow><mi>f</mi><mi>′</mi></mrow><mn>2</mn></msup>'],
            'double prime' => ['f″²', '<msup><mrow><mi>f</mi><mi>″</mi></mrow><mn>2</mn></msup>'],
            'out of order' => ['x²₁³', '<msubsup><mi>x</mi><mn>1</mn><mn>2</mn></msubsup><mn>³</mn>'],
            'loose scripts' => ['² ³', '<mn>²</mn><mn>³</mn>'],
        ];
    }

    #[DataProvider('moreScripts')]
    public function test_more_script_and_letter_cases(string $input, string $expected): void
    {
        $xml = MathmlWriter::write(Math::row($input), false);
        self::assertStringContainsString($expected, preg_replace('~\s+(mathvariant|stretchy|form|fence|separator)="[^"]*"~', '', $xml) ?? '');
    }

    public function test_scripts_attach_across_row_arguments(): void
    {
        $strip = static fn (string $xml): string => preg_replace('~\s+(mathvariant|stretchy|form|fence|separator)="[^"]*"~', '', $xml) ?? '';
        $a = $strip(MathmlWriter::write(Math::row(Math::frac('a', 'b'), '²'), false));
        self::assertStringContainsString('<msup><mfrac>', $a);
        self::assertStringContainsString('</mfrac><mn>2</mn></msup>', $a);
        $b = $strip(MathmlWriter::write(Math::row('(', Math::frac('a', 'b'), ')²'), false));
        self::assertMatchesRegularExpression('~<msup><mrow><mo>\(</mo><mfrac>.*</mfrac><mo>\)</mo></mrow><mn>2</mn></msup>~s', $b);
    }

    public function test_script_character_tables_match_text_writer(): void
    {
        foreach (['0123456789+−=()abcdefghijklmnoprstuvwxyz', '0123456789+−=()aehijklmnoprstuvx'] as $k => $chars) {
            foreach (mb_str_split($chars) as $c) {
                $text = TextWriter::write(new Scripts(new Identifier('q'), $k === 1 ? new Identifier($c) : null, $k === 0 ? new Identifier($c) : null));
                $back = Tokenizer::tokenize($text);
                self::assertCount(1, $back, $text);
                self::assertInstanceOf(Scripts::class, $back[0], $text);
            }
        }
    }

    public function test_whitespace_only_is_empty_row(): void
    {
        $row = Math::row('  ');
        self::assertInstanceOf(Row::class, $row);
        self::assertSame([], $row->children);
        self::assertSame(0.0, Math::layout($row, 12)->width);
    }
}
