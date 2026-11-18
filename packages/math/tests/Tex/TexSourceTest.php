<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use Quoin\Math\Math;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\TextWriter;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tests\TreeAssert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Constructs the reader takes in but does not draw are written back as the TeX they came from. */
final class TexSourceTest extends TestCase
{
    #[DataProvider('kept')]
    public function test_not_drawn_constructs_are_written_back(string $tex): void
    {
        self::assertSame($tex, TexWriter::write(Math::tex($tex)));
    }

    /** @return iterable<string, array{string}> */
    public static function kept(): iterable
    {
        foreach ([
            '\boxed{x^2}', '\fbox{text}', '\textcolor{red}{x}', '{\color{blue} a + b}', '\cancel{x}', '\bcancel{x}',
            '\xcancel{x}', '\sout{x}', 'a = b \tag{1}', '\boxed{x} + \boxed{y}',
        ] as $t) {
            yield $t => [$t];
        }
    }

    public function test_macros_inside_are_written_expanded(): void
    {
        self::assertSame('\boxed{x}', TexWriter::write(Math::tex('\newcommand\v{x}\boxed{\v}')));
    }

    public function test_other_writers_see_through(): void
    {
        $plain = Math::tex('x');
        $boxed = Math::tex('\boxed{x}');
        self::assertSame(MathmlWriter::write($plain), MathmlWriter::write($boxed));
        self::assertSame(OmmlWriter::oMath($plain), OmmlWriter::oMath($boxed));
        self::assertSame(TextWriter::write($plain), TextWriter::write($boxed));
        self::assertSame(Math::layout($plain, 12, false)->width, Math::layout($boxed, 12, false)->width);
    }

    public function test_round_trip_keeps_source(): void
    {
        $t = Math::tex('\frac{\boxed{a}}{\color{red} b}');
        $tex = TexWriter::write($t);
        self::assertSame('\frac{\boxed{a}}{{\color{red} b}}', $tex);
        TreeAssert::assertSameTree($t, Math::tex($tex));
        self::assertSame($tex, TexWriter::write(Math::tex($tex)));
    }

    public function test_color_group_does_not_swallow_what_follows(): void
    {
        self::assertSame('{\color{blue} a} c', TexWriter::write(Math::tex('{\color{blue} a} c')));
    }

    public function test_content_is_written_the_way_the_writer_writes_content(): void
    {
        self::assertSame('\boxed{\text{\textbackslash{}foo} x}', TexWriter::write(Math::tex('\boxed{\foo x}')));
        self::assertSame('\boxed{\frac{a}{b}}', TexWriter::write(Math::tex('\boxed{a\over b}')));
    }

    public function test_an_equation_number_is_written_only_at_the_top(): void
    {
        self::assertSame('\frac{a}{b}', TexWriter::write(Math::tex('\frac{a \tag{1}}{b}')));
        self::assertSame('x \tag{1}', TexWriter::write(Math::tex('x \tag{1}')));
        self::assertSame('\tag{1}', TexWriter::write(Math::tex('\tag{1}')));
    }

    public function test_nested_constructs_round_trip(): void
    {
        $tex = '\boxed{\boxed{x}}';
        self::assertSame($tex, TexWriter::write(Math::tex($tex)));
        $t = Math::tex($tex);
        TreeAssert::assertSameTree($t, Math::tex(TexWriter::write($t)));
    }

    public function test_color_as_a_script_argument_is_braced_once(): void
    {
        self::assertSame('x^{\color{red}} y', TexWriter::write(Math::tex('x^\color{red} y')));
    }

    public function test_tree_comparison_can_ignore_source(): void
    {
        TreeAssert::assertSameTree(Math::tex('x'), Math::tex('\boxed{x}'), true);
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        TreeAssert::assertSameTree(Math::tex('x'), Math::tex('\boxed{x}'));
    }
}
