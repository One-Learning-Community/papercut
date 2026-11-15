<?php

declare(strict_types=1);

namespace Papercut\Math\Tests;

use PHPUnit\Framework\TestCase;
use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Scripts;
use Papercut\Math\NoteLevel;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\TextWriter;

final class NotesTest extends TestCase
{
    public function test_notes_found_at_any_depth(): void
    {
        $note = new MathNote(NoteLevel::Approximated, 'mfoo', 'not drawn', 'numerator');
        $tree = new Scripts(new Fraction(new Fallback(Math::row('a'), $note), Math::row('b')), null, Math::row('2'));

        self::assertSame([$note], Math::notes($tree));
    }

    public function test_no_notes_without_fallback(): void
    {
        foreach (Fixtures::all() as $name => $node) {
            self::assertSame([], Math::notes($node), $name);
        }
    }

    public function test_fallback_lays_out_as_its_content(): void
    {
        $note = new MathNote(NoteLevel::Approximated, 'mfoo', 'not drawn');
        $plain = Math::layout(Math::frac('a', 'b'));
        $wrapped = Math::layout(new Fallback(Math::frac('a', 'b'), $note));

        self::assertSame($plain->width, $wrapped->width);
        self::assertSame($plain->ascent, $wrapped->ascent);
        self::assertSame($plain->descent, $wrapped->descent);
    }

    public function test_dropped_fallback_is_empty(): void
    {
        $dropped = new Fallback(null, new MathNote(NoteLevel::Dropped, 'mfoo', 'lost'));

        self::assertSame(0.0, Math::layout($dropped)->width);
        self::assertSame('', TextWriter::write($dropped));
        self::assertSame('<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline"></math>', MathmlWriter::write($dropped, false));
        self::assertSame('<m:oMath></m:oMath>', OmmlWriter::oMath($dropped));
    }

    public function test_several_notes_come_in_reading_order(): void
    {
        $a = new MathNote(NoteLevel::Approximated, 'a', 'first');
        $b = new MathNote(NoteLevel::Dropped, 'b', 'second');
        $c = new MathNote(NoteLevel::Approximated, 'c', 'third');
        $tree = Math::row(new Fallback(Math::row('x'), $a), Math::frac(new Fallback(null, $b), 'y'), new Fallback(Math::row('z'), $c));

        self::assertSame([$a, $b, $c], Math::notes($tree));
    }

    public function test_nested_fallbacks_report_outer_then_inner(): void
    {
        $outer = new MathNote(NoteLevel::Approximated, 'outer', 'o');
        $inner = new MathNote(NoteLevel::Dropped, 'inner', 'i');

        self::assertSame([$outer, $inner], Math::notes(new Fallback(new Fallback(Math::row('x'), $inner), $outer)));
    }

    public function test_writers_draw_the_content_of_a_non_null_fallback(): void
    {
        $note = new MathNote(NoteLevel::Approximated, 'mfoo', 'drawn as its contents');
        $tree = new Fallback(Math::frac('a', 'b'), $note);

        self::assertSame(MathmlWriter::write(Math::frac('a', 'b'), false), MathmlWriter::write($tree, false));
        self::assertSame(OmmlWriter::oMath(Math::frac('a', 'b')), OmmlWriter::oMath($tree));
        self::assertSame('a/b', TextWriter::write($tree));
    }
}
