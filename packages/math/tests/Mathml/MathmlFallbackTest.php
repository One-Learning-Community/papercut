<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Mathml;

use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Mathml\MathmlReader;
use Papercut\Math\Node\DisplayStyle;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Forms;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Space;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\UnderOver;
use Papercut\Math\NoteLevel;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\TextWriter;
use PHPUnit\Framework\TestCase;

final class MathmlFallbackTest extends TestCase
{
    private const MATRIX = '<mtable><mtr><mtd><mi>a</mi></mtd><mtd><mi>b</mi></mtd></mtr><mtr><mtd><mi>c</mi></mtd><mtd><mi>d</mi></mtd></mtr></mtable>';

    private static function note(): MathNote
    {
        return new MathNote(NoteLevel::Approximated, 'x', 'test');
    }

    public function test_mtable_rows_of_cells(): void
    {
        $r = MathmlReader::read('<math><mo>(</mo>' . self::MATRIX . '<mo>)</mo></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('mtable', $notes[0]->element);
        self::assertSame('tables are drawn as rows of cells', $notes[0]->reason);
        $text = TextWriter::write($r->node);
        self::assertMatchesRegularExpression('/a.*b.*c.*d/s', $text);
        $fenced = $r->node;
        self::assertInstanceOf(Fenced::class, $fenced);
        self::assertInstanceOf(Fallback::class, $fenced->content);
        $table = $fenced->content->content;
        self::assertInstanceOf(Row::class, $table);
        $kinds = array_map(static fn (Node $n): string => $n instanceof Space ? 'S' . $n->em : 'cell', $table->children);
        self::assertSame(['cell', 'S1', 'cell', 'S2', 'cell', 'S1', 'cell'], $kinds);
    }

    public function test_mlabeledtr_drops_label(): void
    {
        $r = MathmlReader::read('<math><mtable><mlabeledtr><mtd><mtext>LABEL</mtext></mtd><mtd><mi>a</mi></mtd></mlabeledtr></mtable></math>');
        self::assertStringNotContainsString('LABEL', TextWriter::write($r->node));
        self::assertStringContainsString('a', TextWriter::write($r->node));
    }

    public function test_two_tables_one_note(): void
    {
        $r = MathmlReader::read('<math>' . self::MATRIX . '<mo>+</mo>' . self::MATRIX . '</math>');
        self::assertCount(1, $r->notes());
        self::assertSame(2, preg_match_all('/a.*?b.*?c.*?d/s', TextWriter::write($r->node)));
    }

    public function test_menclose_draws_contents(): void
    {
        $r = MathmlReader::read('<math><menclose notation="box"><mi>x</mi></menclose></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame('menclose', $notes[0]->element);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('drawn as its contents', $notes[0]->reason);
        self::assertSame('x', TextWriter::write($r->node));
    }

    public function test_mmultiscripts_drops_markers(): void
    {
        $r = MathmlReader::read('<math><mmultiscripts><mi>x</mi><mi>a</mi><none/><mprescripts/><mi>b</mi><none/></mmultiscripts></math>');
        self::assertCount(1, $r->notes());
        self::assertSame('mmultiscripts', $r->notes()[0]->element);
        self::assertSame('xab', preg_replace('/\s+/', '', TextWriter::write($r->node)));
    }

    public function test_unknown_element(): void
    {
        $r = MathmlReader::read('<math><foo><mi>x</mi><mn>2</mn></foo></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame('foo', $notes[0]->element);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('drawn as its contents', $notes[0]->reason);
        self::assertSame('math > foo[0]', $notes[0]->where);
        self::assertStringContainsString('x', TextWriter::write($r->node));
    }

    public function test_maction_first_child(): void
    {
        $r = MathmlReader::read('<math><maction actiontype="toggle"><mi>a</mi><mi>b</mi></maction></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame('maction', $notes[0]->element);
        self::assertSame('drawn as its first choice', $notes[0]->reason);
        self::assertSame('a', TextWriter::write($r->node));
        $second = MathmlReader::read('<math><maction selection="2"><mi>a</mi><mi>b</mi></maction></math>');
        self::assertSame('b', TextWriter::write($second->node));
    }

    public function test_img_inside_math_dropped(): void
    {
        $r = MathmlReader::read('<math><mi>x</mi><mglyph src="a.png"/><mi>y</mi></math>');
        self::assertCount(1, $r->notes());
        self::assertSame('mglyph', $r->notes()[0]->element);
        self::assertSame(NoteLevel::Dropped, $r->notes()[0]->level);
        self::assertSame('not drawn', $r->notes()[0]->reason);

        $r = MathmlReader::read('<mml:math xmlns:mml="http://www.w3.org/1998/Math/MathML"><mml:mi>x</mml:mi><img src="a.png"/></mml:math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame('img', $notes[0]->element);
        self::assertSame(NoteLevel::Dropped, $notes[0]->level);
        self::assertSame('x', TextWriter::write($r->node));
    }

    public function test_span_in_mtext_kept_silently(): void
    {
        $r = MathmlReader::read('<math><mtext>if <span>and only if</span> x</mtext></math>');
        self::assertSame([], $r->notes());
        self::assertSame('if and only if x', TextWriter::write($r->node));
    }

    public function test_depth_limit(): void
    {
        $r = MathmlReader::read('<math>' . str_repeat('<mrow>', 300) . '<mi>x</mi>' . str_repeat('</mrow>', 300) . '</math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('math', $notes[0]->element);
        self::assertSame('nesting deeper than 256 levels (flattened)', $notes[0]->reason);
        self::assertStringContainsString('x', TextWriter::write($r->node));
    }

    public function test_where_path(): void
    {
        $r = MathmlReader::read('<math><mrow><mi>a</mi>' . self::MATRIX . '</mrow></math>');
        self::assertSame('math > mrow[0] > mtable[1]', $r->notes()[0]->where);
    }

    public function test_breakout_tag_in_unprefixed_math_notes_lost_content(): void
    {
        // On the HTML path <b> closes <math>, so what follows is never drawn; the loss is noted.
        $r = MathmlReader::read('<math><mi>x</mi><b>bold</b><mi>y</mi><br></math>');
        self::assertStringNotContainsString('y', TextWriter::write($r->node));
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Dropped, $notes[0]->level);
        self::assertSame('b', $notes[0]->element);
        self::assertSame('content after the math element (not drawn)', $notes[0]->reason);
        self::assertSame('math', $notes[0]->where);
    }

    public function test_unprefixed_img_on_html_path_noted(): void
    {
        $r = MathmlReader::read('<math><mi>x</mi><img src="a.png"><mi>y</mi></math>');
        $notes = $r->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Dropped, $notes[0]->level);
        self::assertSame('img', $notes[0]->element);
        self::assertSame('x', TextWriter::write($r->node));
    }

    public function test_trailing_whitespace_after_math_is_not_loss(): void
    {
        self::assertSame([], MathmlReader::read("<p><math><mi>x</mi><mo>&ApplyFunction;</mo></math></p>\n")->notes());
    }

    public function test_element_named_depth_does_not_hide_depth_note(): void
    {
        $r = MathmlReader::read('<math><depth><mi>q</mi></depth>' . str_repeat('<mrow>', 300) . '<mi>x</mi>' . str_repeat('</mrow>', 300) . '</math>');
        self::assertSame(['depth', 'math'], array_map(static fn (MathNote $n): string => $n->element, $r->notes()));
    }

    public function test_table_levels_count_toward_depth(): void
    {
        $r = MathmlReader::read('<math>' . str_repeat('<mrow>', 252) . '<mtable><mtr><mtd><mi>x</mi></mtd></mtr></mtable>' . str_repeat('</mrow>', 252) . '</math>');
        self::assertContains('math', array_map(static fn (MathNote $n): string => $n->element, $r->notes()));
    }

    public function test_menclose_operator_spacing_in_text(): void
    {
        $r = MathmlReader::read('<math><mi>a</mi><menclose><mo>+</mo></menclose><mi>b</mi></math>');
        self::assertSame('a + b', TextWriter::write($r->node));
    }

    public function test_unwrap(): void
    {
        $op = new Operator('+');
        self::assertSame($op, Fallback::unwrap(new Fallback(new DisplayStyle(new Fallback($op, self::note()), true), self::note())));
        $empty = new Fallback(null, self::note());
        self::assertSame($empty, Fallback::unwrap($empty));
    }

    public function test_fallback_keeps_operator_form_and_spacing(): void
    {
        $bare = new Row([new Identifier('a'), new Operator('+'), new Identifier('b')]);
        foreach ([new Fallback(new Operator('+'), self::note()), new DisplayStyle(new Operator('+'), true)] as $wrapped) {
            $row = new Row([new Identifier('a'), $wrapped, new Identifier('b')]);
            self::assertSame([null, 'infix', null], Forms::of($row->children));
            self::assertEqualsWithDelta(Math::layout($bare, 12.0)->width, Math::layout($row, 12.0)->width, 1e-9);
        }
        self::assertSame(['prefix', null], Forms::of([new Fallback(new Operator('−'), self::note()), new Identifier('b')]));
    }

    public function test_fallback_sum_base_keeps_limits(): void
    {
        $plain = new UnderOver(new Operator('∑'), new Row([new Identifier('i')]), new Identifier('n'));
        $wrapped = new UnderOver(new Fallback(new Operator('∑'), self::note()), new Row([new Identifier('i')]), new Identifier('n'));
        self::assertEqualsWithDelta(Math::layout($plain, 12.0, true)->width, Math::layout($wrapped, 12.0, true)->width, 1e-9);
        self::assertEqualsWithDelta(Math::layout($plain, 12.0, true)->ascent, Math::layout($wrapped, 12.0, true)->ascent, 1e-9);
        self::assertSame(OmmlWriter::oMath($plain), OmmlWriter::oMath($wrapped));
    }

    public function test_display_style_operator_keeps_display_size_in_row(): void
    {
        $bare = new Row([new Identifier('a'), new Operator('∑'), new Identifier('b')]);
        $wrapped = new Row([new Identifier('a'), new DisplayStyle(new Operator('∑'), true), new Identifier('b')]);
        self::assertGreaterThan(Math::layout($bare, 12.0, false)->ascent + 1.0, Math::layout($wrapped, 12.0, false)->ascent);
    }

    public function test_mathml_writer_keeps_mstyle_around_operator(): void
    {
        $row = new Row([new Identifier('a'), new DisplayStyle(new Operator('∑'), true), new Identifier('b')]);
        self::assertStringContainsString('<mstyle displaystyle="true"><mo', MathmlWriter::write($row));
        $base = new UnderOver(new DisplayStyle(new Operator('∑'), true), new Identifier('i'), new Identifier('n'));
        self::assertMatchesRegularExpression('~<munderover><mstyle displaystyle="true"><mo[^>]*>∑</mo></mstyle>~', MathmlWriter::write($base));
        $part = new UnderOver(new Identifier('x'), null, new DisplayStyle(new Operator('→'), false));
        self::assertMatchesRegularExpression('~<mover>.*<mstyle displaystyle="false"><mo[^>]*>→</mo></mstyle></mover>~', MathmlWriter::write($part));
    }

    public function test_fallback_space_is_space_like(): void
    {
        self::assertSame([null, 'postfix', null], Forms::of([new Identifier('a'), new Operator('!'), new Fallback(new Space(1), self::note())]));
        $sum = new UnderOver(new Operator('∑'), new Identifier('i'), new Identifier('n'));
        $plain = new Row([$sum, new Space(1), new Identifier('x')]);
        $wrapped = new Row([$sum, new Fallback(new Space(1), self::note()), new Identifier('x')]);
        self::assertSame(OmmlWriter::oMath($plain), OmmlWriter::oMath($wrapped));
    }
}
