<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Mathml;

use Quoin\Math\Math;
use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Node\Fallback;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Number;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Text;
use Quoin\Math\Node\UnderOver;
use Quoin\Math\NoteLevel;
use Quoin\Math\Render\MathmlWriter;
use PHPUnit\Framework\TestCase;

final class MathmlTokenTest extends TestCase
{
    private static function unwrapped(string $mathml): Node
    {
        return Fallback::unwrap(MathmlReader::read($mathml)->node);
    }

    public function test_foreign_child_of_a_token_is_noted_and_text_kept(): void
    {
        foreach ([
            '<math><mi>x<mglyph alt="A"/></mi></math>' => 'mglyph',
            '<math><mtext>see <img src="a.png"/> here</mtext></math>' => 'img',
            '<math><mn>1<img src="a.png"/></mn></math>' => 'img',
            '<math><mo>+<img src="a.png"/></mo></math>' => 'img',
        ] as $mathml => $element) {
            $notes = MathmlReader::read($mathml)->notes();
            self::assertCount(1, $notes, $mathml);
            self::assertSame(NoteLevel::Dropped, $notes[0]->level);
            self::assertSame($element, $notes[0]->element);
            self::assertSame('not drawn', $notes[0]->reason);
        }
        $text = self::unwrapped('<math><mtext>see <img src="a.png"/> here</mtext></math>');
        self::assertInstanceOf(Text::class, $text);
        self::assertStringContainsString('see', $text->text);
    }

    public function test_token_that_is_only_a_foreign_child_is_still_noted(): void
    {
        $r = MathmlReader::read('<math><mi><mglyph alt="A"/></mi></math>');
        self::assertCount(1, $r->notes());
    }

    public function test_mathvariant_applies_to_mn_mo_and_mtext(): void
    {
        $n = self::unwrapped('<math><mn mathvariant="bold">1</mn></math>');
        self::assertInstanceOf(Number::class, $n);
        self::assertSame("\u{1D7CF}", $n->text);
        $t = self::unwrapped('<math><mtext mathvariant="bold">A</mtext></math>');
        self::assertInstanceOf(Text::class, $t);
        self::assertSame("\u{1D400}", $t->text);
        $o = self::unwrapped('<math><mo mathvariant="bold">A</mo></math>');
        self::assertInstanceOf(Operator::class, $o);
        self::assertSame("\u{1D400}", $o->char);
        $normal = self::unwrapped('<math><mn mathvariant="normal">1</mn></math>');
        self::assertInstanceOf(Number::class, $normal);
        self::assertSame('1', $normal->text);
    }

    public function test_mo_form_is_read_and_changes_spacing(): void
    {
        $mathml = '<math><mrow><mi>a</mi><mo form="prefix">+</mo><mi>b</mi></mrow></math>';
        $row = self::unwrapped($mathml);
        self::assertInstanceOf(Row::class, $row);
        self::assertSame('prefix', $row->forms()[1]);
        $plain = Math::layout(MathmlReader::read('<math><mrow><mi>a</mi><mo>+</mo><mi>b</mi></mrow></math>')->node, 12.0, true);
        $forced = Math::layout(MathmlReader::read($mathml)->node, 12.0, true);
        self::assertLessThan($plain->width, $forced->width);
        self::assertStringContainsString('form="prefix"', MathmlWriter::write(MathmlReader::read($mathml)->node));
        $post = self::unwrapped('<math><mrow><mi>a</mi><mo form="postfix">!</mo><mi>b</mi></mrow></math>');
        self::assertInstanceOf(Row::class, $post);
        self::assertSame('postfix', $post->forms()[1]);
    }

    public function test_mo_accent_attribute_marks_an_accent_part(): void
    {
        $over = self::unwrapped('<math><mover><mi>x</mi><mo accent="true">~~</mo></mover></math>');
        self::assertInstanceOf(UnderOver::class, $over);
        self::assertTrue($over->accent);
        $under = self::unwrapped('<math><munder><mi>x</mi><mo accent="true">~</mo></munder></math>');
        self::assertInstanceOf(UnderOver::class, $under);
        self::assertTrue($under->accentUnder);
        $off = self::unwrapped('<math><mover><mi>x</mi><mo accent="false">^</mo></mover></math>');
        self::assertInstanceOf(UnderOver::class, $off);
        self::assertFalse($off->accent);
        $plain = self::unwrapped('<math><munder><mi>x</mi><mo>~</mo></munder></math>');
        self::assertInstanceOf(UnderOver::class, $plain);
        self::assertFalse($plain->accentUnder);
    }

    public function test_long_runs_of_spaces_in_a_prefixed_tag_neither_hang_nor_empty_the_formula(): void
    {
        $src = '<math><mi>x</mi><br><mml:mn' . str_repeat(' ', 200000) . 'a="1">2</mml:mn></math>';
        $start = microtime(true);
        $r = MathmlReader::read($src);
        self::assertLessThan(2.0, microtime(true) - $start);
        self::assertStringContainsString('x', \Quoin\Math\Render\TextWriter::write($r->node));
    }

    public function test_minsize_reads_from_mathml(): void
    {
        $o = self::unwrapped('<math><mo minsize="1.8em" maxsize="1.8em">(</mo></math>');
        self::assertInstanceOf(Operator::class, $o);
        self::assertSame(1.8, $o->minSize);
        self::assertSame(1.8, $o->maxSize);
        self::assertSame([], MathmlReader::read('<math><mo minsize="1.8em">(</mo></math>')->notes());
        $pt = self::unwrapped('<math><mo minsize="12pt">(</mo></math>');
        self::assertInstanceOf(Operator::class, $pt);
        self::assertSame(1.0, $pt->minSize);
    }

    public function test_relative_minsize_is_noted(): void
    {
        $r = MathmlReader::read('<math><mo minsize="2">(</mo></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('minsize is not drawn', $notes[0]->reason);
        $o = Fallback::unwrap($r->node);
        self::assertInstanceOf(Operator::class, $o);
        self::assertNull($o->minSize);
    }

    public function test_bad_size_names_its_attribute(): void
    {
        foreach (['minsize="2"' => 'minsize', 'maxsize="150%"' => 'maxsize', 'maxsize="-1em"' => 'maxsize', 'minsize="0em"' => 'minsize'] as $attr => $name) {
            $notes = MathmlReader::read('<math><mo ' . $attr . '>(</mo></math>')->notes();
            self::assertCount(1, $notes, $attr);
            self::assertSame($name . ' is not drawn', $notes[0]->reason, $attr);
        }
    }

    public function test_maxsize_infinity_is_absent(): void
    {
        $r = MathmlReader::read('<math><mo maxsize="infinity">(</mo></math>');
        self::assertSame([], $r->notes());
        $o = Fallback::unwrap($r->node);
        self::assertInstanceOf(Operator::class, $o);
        self::assertNull($o->maxSize);
    }
}
