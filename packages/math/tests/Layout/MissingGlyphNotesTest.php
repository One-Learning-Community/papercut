<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Layout;

use Quoin\Math\Font\Font;
use Quoin\Math\Math;
use Quoin\Math\MathNote;
use Quoin\Math\NoteLevel;
use PHPUnit\Framework\TestCase;

final class MissingGlyphNotesTest extends TestCase
{
    private static function fira(): Font
    {
        return Font::fromFile(__DIR__ . '/../fixtures/FiraMath-Regular.otf');
    }

    public function test_a_formula_the_font_covers_has_no_notes(): void
    {
        self::assertSame([], Math::layout(Math::tex('\mathbb{R} = \frac{x^2}{\sqrt{y}}'), font: self::fira())->notes());
        self::assertSame([], Math::layout(Math::tex('\mathcal{L}\,\mathfrak{g}'))->notes()); // STIX has them
    }

    public function test_each_missing_character_is_noted_once_in_drawing_order(): void
    {
        $notes = Math::layout(Math::tex('\mathcal{L} + \mathfrak{g} + \mathcal{L} + \mathbb{R}'), font: self::fira())->notes();

        self::assertSame(["\u{2112}", "\u{1D524}"], array_map(static fn (MathNote $n): string => $n->element, $notes));
        self::assertSame(NoteLevel::Dropped, $notes[0]->level);
        self::assertSame('the font has no glyph for U+2112, so it is drawn as a missing-glyph box', $notes[0]->reason);
        self::assertStringContainsString('U+1D524', $notes[1]->reason);
    }

    public function test_missing_characters_inside_nested_boxes_are_found(): void
    {
        $notes = Math::layout(Math::tex('\frac{1}{\sqrt{\text{日}}}'), font: self::fira())->notes();

        self::assertSame(['日'], array_map(static fn (MathNote $n): string => $n->element, $notes));
    }
}
