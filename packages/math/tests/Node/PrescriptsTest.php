<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Node;

use PHPUnit\Framework\TestCase;
use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;
use Quoin\Math\Layout\Glyph;
use Quoin\Math\Math;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Number;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Render\TextWriter;

final class PrescriptsTest extends TestCase
{
    /** @return array{float, float} absolute x and y of the first glyph with this text */
    private static function at(Box $b, string $text): array
    {
        $found = null;
        $b->each(function ($item, float $dx, float $dy) use ($text, &$found): void {
            if ($found === null && $item instanceof Glyph && $item->text === $text) {
                $found = [$item->x + $dx, $item->y + $dy];
            }
        });

        return $found ?? self::fail('No glyph with text ' . $text);
    }

    public function test_prescripts_sit_left_and_right_aligned(): void
    {
        $c = new Identifier('C', false);
        $full = Math::layout(Math::prescripts($c, '6', '14'));
        // Each prescript alone starts the box; the base follows at its width plus the same gap.
        $subOnly = Math::layout(Math::prescripts($c, '6', null));
        $supOnly = Math::layout(Math::prescripts($c, null, '14'));
        $toBase6 = self::at($subOnly, 'C')[0] - self::at($subOnly, '6')[0];
        $toBase14 = self::at($supOnly, 'C')[0] - self::at($supOnly, '1')[0];

        // Right-aligned: each script's distance to the base is what it has alone.
        self::assertEqualsWithDelta($toBase6, self::at($full, 'C')[0] - self::at($full, '6')[0], 1e-6);
        self::assertEqualsWithDelta($toBase14, self::at($full, 'C')[0] - self::at($full, '1')[0], 1e-6);
        // The wider script starts the block, so the base sits at the block width plus the gap, nothing else.
        self::assertEqualsWithDelta(max($toBase6, $toBase14), self::at($full, 'C')[0], 1e-6);
    }

    public function test_the_gap_before_the_base_is_the_fonts_script_space(): void
    {
        $size = 22.0;
        $c = new Context(Math::stixTwoMath(), $size, 0, true, false, false, true, null);
        $six = (new Number('6'))->layout($c->script(1, true));
        $box = Math::layout(Math::prescripts(new Identifier('C', false), '6', null), $size);

        // The prescript block ends one spaceAfterScript before the base, and nothing else sits between them.
        self::assertEqualsWithDelta($six->width + $c->k('spaceAfterScript'), self::at($box, 'C')[0], 1e-6);
    }

    public function test_shifts_match_postscripts(): void
    {
        $pre = Math::layout(Math::prescripts(new Identifier('C', false), '6', '14'));
        $post = Math::layout(Math::subSup(new Identifier('C', false), '6', '14'));

        self::assertEqualsWithDelta(self::at($post, '4')[1] - self::at($post, 'C')[1], self::at($pre, '4')[1] - self::at($pre, 'C')[1], 1e-6);
        self::assertEqualsWithDelta(self::at($post, '6')[1] - self::at($post, 'C')[1], self::at($pre, '6')[1] - self::at($pre, 'C')[1], 1e-6);
    }

    public function test_writers(): void
    {
        $n = Math::prescripts(new Identifier('C', false), '6', '14');

        self::assertSame('<math xmlns="http://www.w3.org/1998/Math/MathML" display="block"><mmultiscripts><mi mathvariant="normal">C</mi><mprescripts/><mn>6</mn><mn>14</mn></mmultiscripts></math>', MathmlWriter::write($n));
        $omml = OmmlWriter::oMath($n);
        self::assertStringContainsString('<m:sPre><m:sub>', $omml);
        $run = static fn (string $text): string => '<m:r><w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/></w:rPr><m:t xml:space="preserve">' . $text . '</m:t></m:r>';
        $base = '<m:r><m:rPr><m:sty m:val="p"/></m:rPr><w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/></w:rPr><m:t xml:space="preserve">C</m:t></m:r>';
        self::assertStringContainsString('<m:sPre><m:sub>' . $run('6') . '</m:sub><m:sup>' . $run('14') . '</m:sup><m:e>' . $base . '</m:e></m:sPre>', $omml);
        self::assertSame('{}_6^{14}\mathrm{C}', TexWriter::write($n));
        self::assertSame('¹⁴₆C', TextWriter::write($n));
    }

    public function test_pre_and_post(): void
    {
        $n = Math::prescripts(new Identifier('C', false), '6', '14', null, '4+');

        $omml = OmmlWriter::oMath($n);
        $run = static fn (string $text): string => '<m:r><w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/></w:rPr><m:t xml:space="preserve">' . $text . '</m:t></m:r>';
        $base = '<m:r><m:rPr><m:sty m:val="p"/></m:rPr><w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/></w:rPr><m:t xml:space="preserve">C</m:t></m:r>';
        self::assertStringContainsString('<m:e><m:sSup><m:e>' . $base . '</m:e><m:sup>' . $run('4') . $run('+') . '</m:sup></m:sSup></m:e></m:sPre>', $omml);
        self::assertStringContainsString('<mmultiscripts><mi mathvariant="normal">C</mi><none/><mrow><mn>4</mn><mo>+</mo></mrow><mprescripts/><mn>6</mn><mn>14</mn></mmultiscripts>', MathmlWriter::write($n));
        self::assertSame('{}_6^{14}\mathrm{C}^{4 +}', TexWriter::write($n));
    }

    public function test_children_in_reading_order_and_empty_is_rejected(): void
    {
        $n = Math::prescripts('C', '6', '14', null, '4');
        self::assertInstanceOf(Scripts::class, $n);
        self::assertSame([$n->preSub, $n->preSup, $n->base, $n->sup], $n->children());
        $this->expectException(\InvalidArgumentException::class);
        new Scripts(Math::row('C'), null, null);
    }
}
