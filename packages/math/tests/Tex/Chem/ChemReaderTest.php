<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Layout\DisplayItem;
use Quoin\Math\Layout\Glyph;
use Quoin\Math\Math;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Text;
use Quoin\Math\Node\UnderOver;
use Quoin\Math\NoteLevel;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Render\TextWriter;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\Chem\ChemArrows;
use Quoin\Math\Tex\TexReader;

final class ChemReaderTest extends TestCase
{
    /** @return iterable<string, array{string, string}> source, then what the TeX writer writes back */
    public static function nested(): iterable
    {
        yield 'in a fraction' => ['\frac{\ce{H2O}}{2}', '\frac{\ce{H2O}}{2}'];
        yield 'in a box' => ['\boxed{\ce{O2}}', '\boxed{\ce{O2}}'];
        yield 'in text' => ['\text{gas $\ce{O2}$}', '\text{gas } \ce{O2}'];
        yield 'in an aligned cell' => ['\begin{aligned}\ce{H2} &\ce{-> H}\end{aligned}', '\begin{aligned}\ce{H2} &\ce{-> H}\end{aligned}'];
        yield 'two in a row' => ['\ce{H2} + \ce{O2}', '\ce{H2} + \ce{O2}'];
        yield 'from a macro' => ['\newcommand\g{\ce{CO2}}\g + \g', '\ce{CO2} + \ce{CO2}'];
    }

    #[DataProvider('nested')]
    public function test_nested_source_writes_back_and_round_trips(string $tex, string $expected): void
    {
        $tree = Math::tex($tex);
        $written = TexWriter::write($tree);
        self::assertSame($expected, $written);
        TreeAssert::assertSameTree($tree, Math::tex($written));
        self::assertSame($written, TexWriter::write(Math::tex($written)));
    }

    /** @return iterable<string, array{string, int}> source, then how many notes it should give */
    public static function shared(): iterable
    {
        yield 'repeated in one formula' => ['\ce{$\foo$ $\foo$}', 1];
        yield 'outside and inside' => ['\foo\ce{\foo}', 1];
        yield 'box outside and inside' => ['\boxed{a}\ce{$\boxed{b}$}', 1];
        yield 'macro that grows' => ['\ce{$\newcommand\a{\a\a}\a$}', 1];
    }

    #[DataProvider('shared')]
    public function test_math_inside_shares_the_readers_notes(string $tex, int $count): void
    {
        $notes = TexReader::read($tex)->notes();
        self::assertCount($count, $notes);
        foreach ($notes as $note) {
            self::assertStringNotContainsString('input too long', $note->reason);
        }
    }

    public function test_notes_inside_point_into_the_input(): void
    {
        $tex = 'xxxxxxxxxx \ce{H2 $\foo$}';
        $notes = TexReader::read($tex)->notes();
        self::assertCount(1, $notes);
        self::assertSame('offset ' . strpos($tex, '\foo'), $notes[0]->where);
    }

    public function test_script_content_outside_the_subset_is_noted(): void
    {
        TreeAssert::assertSameTree(Math::tex('\mathrm{X}^{2+}'), Math::tex('\ce{X^{{2}+}}'), ignoreSource: true);
        $notes = TexReader::read('\ce{X^{a_b}}')->notes();
        self::assertCount(1, $notes);
        self::assertSame('not supported in a script: _', $notes[0]->reason);
    }

    public function test_script_mark_without_content_is_noted(): void
    {
        $notes = TexReader::read('\ce{H2O^}')->notes();
        self::assertCount(1, $notes);
        self::assertSame(NoteLevel::Dropped, $notes[0]->level);
        TreeAssert::assertSameTree(Math::tex('\mathrm{H}_{2}\mathrm{O}'), Math::tex('\ce{H2O^}'), ignoreSource: true);
    }

    public function test_state_at_the_start_has_no_leading_gap(): void
    {
        TreeAssert::assertSameTree(Math::tex('(\mathrm{aq})'), Math::tex('\ce{(aq)}'), ignoreSource: true);
        self::assertSame('\ce{(aq)}', TexWriter::write(Math::tex('\ce{(aq)}')));
    }

    public function test_math_inside_reaches_the_tex_reader(): void
    {
        $tree = Math::tex('\ce{\alpha H2O}');
        TreeAssert::assertSameTree(Math::tex('\alpha\mathrm{H}_{2}\mathrm{O}'), $tree, ignoreSource: true);
        self::assertSame('\ce{\alpha H2O}', TexWriter::write($tree));
    }

    public function test_out_of_subset_construct_is_text_with_one_note(): void
    {
        $result = TexReader::read('\ce{H2O \bond{~} CO2 \bond{~}}');
        $notes = $result->notes();
        self::assertCount(1, $notes);
        self::assertSame('\ce', $notes[0]->element);
        self::assertSame(NoteLevel::Approximated, $notes[0]->level);
        self::assertSame('not supported: \bond{~}', $notes[0]->reason);
        $bond = new Text('\bond{~}');
        TreeAssert::assertSameTree(
            new Row([Math::tex('\mathrm{H}_{2}'), Math::tex('\mathrm{O}'), $bond, Math::tex('\mathrm{C}'), Math::tex('\mathrm{O}_{2}'), $bond]),
            $result->node,
            ignoreSource: true,
        );
    }

    public function test_unsupported_text_is_a_text_node(): void
    {
        $found = false;
        $walk = static function ($n) use (&$walk, &$found): void {
            $found = $found || ($n instanceof Text && $n->text === '\bond{~}');
            foreach ($n->children() as $c) {
                $walk($c);
            }
        };
        $walk(Math::tex('\ce{C\bond{~}C}'));
        self::assertTrue($found);
    }

    public function test_ce_without_a_group_is_not_chemistry(): void
    {
        self::assertSame(['\ce'], array_map(static fn ($n): string => $n->element, TexReader::read('\ce H2O')->notes()));
    }

    public function test_never_throws_on_malformed_input(): void
    {
        foreach (['', '(', '[Cu', '{', 'H2O^', 'H2O_{', '$x', '\ce', '^', '-', '->[', '{{{{', 'Fe^{3+'] as $ce) {
            $tree = Math::tex('\ce{' . $ce . '}');
            self::assertNotSame('', TexWriter::write($tree));
        }
    }

    /** @return iterable<string, array{string, ?array{string, int}}> text, then the arrow and how many characters spell it */
    public static function spellings(): iterable
    {
        yield 'arrow' => ['-> B', ['→', 2]];
        yield 'left' => ['<- B', ['←', 2]];
        yield 'both' => ['<-> B', ['↔', 3]];
        yield 'to and fro' => ['<--> B', ['⇄', 4]];
        yield 'equilibrium' => ['<=> B', ['⇌', 3]];
        yield 'forward' => ['<=>> B', ['forward', 4]];
        yield 'backward' => ['<<=> B', ['backward', 4]];
        yield 'equals is not an arrow' => ['= B', null];
        yield 'lone angle' => ['< B', null];
    }

    /** @param ?array{string, int} $want */
    #[DataProvider('spellings')]
    public function test_arrow_spellings_match_longest_first(string $src, ?array $want): void
    {
        $got = ChemArrows::match($src, 0);
        self::assertSame($want, $got === null ? null : [$got['arrow'], $got['length']]);
        self::assertNull(ChemArrows::match('->', 5));
    }

    /** @return list<UnderOver> */
    private static function arrows(string $ce): array
    {
        $found = [];
        $walk = static function (Node $n) use (&$walk, &$found): void {
            if ($n instanceof UnderOver && $n->base instanceof Operator && UnderOver::isArrow($n->base->char)) {
                $found[] = $n;
            }
            foreach ($n->children() as $c) {
                $walk($c);
            }
        };
        $walk(Math::tex('\\ce{' . $ce . '}'));

        return $found;
    }

    public function test_a_labelless_arrow_is_stretchy_with_the_minimum_width(): void
    {
        [$arrow] = self::arrows('A -> B');
        self::assertInstanceOf(Operator::class, $arrow->base);
        self::assertTrue(UnderOver::isStretchy($arrow->base));
        $width = Math::layout(Math::tex('\\ce{A -> B}'), 22.0, false)->width - Math::layout(Math::tex('\\ce{A B}'), 22.0, false)->width;
        self::assertGreaterThan(UnderOver::ARROW_MIN_WIDTH * 22.0, $width);
    }

    public function test_labels_go_above_then_below_and_are_read_as_chemistry(): void
    {
        [$arrow] = self::arrows('N2 <=>[Fe][450 °C] 2NH3');
        self::assertInstanceOf(Operator::class, $arrow->base);
        self::assertSame('⇌', $arrow->base->char);
        self::assertNotNull($arrow->over);
        self::assertNotNull($arrow->under);
        self::assertSame('\\mathrm{Fe}', TexWriter::write($arrow->over));
        self::assertSame('450 \\  \\degree \\mathrm{C}', TexWriter::write($arrow->under));
    }

    public function test_labels_take_nested_brackets_math_and_commands(): void
    {
        foreach (['A ->[[Cu(NH3)4]^2+] B', 'A ->[$x_1$] B', 'A ->[\\Delta][{H2O}] B'] as $ce) {
            self::assertCount(1, self::arrows($ce), $ce);
            self::assertSame([], TexReader::read('\\ce{' . $ce . '}')->notes(), $ce);
        }
        [$arrow] = self::arrows('A ->[\\Delta] B');
        self::assertSame('\\Delta', TexWriter::write($arrow->over ?? new Row([])));
    }

    public function test_a_third_bracket_group_is_not_a_label(): void
    {
        [$arrow] = self::arrows('A ->[x][y][z]');
        self::assertNotNull($arrow->under);
        self::assertSame('\\ce{A ->[x][y][z]}', TexWriter::write(Math::tex('\\ce{A ->[x][y][z]}')));
    }

    public function test_an_unclosed_label_leaves_the_bracket_to_the_species(): void
    {
        self::assertCount(1, self::arrows('A ->[x B'));
        self::assertCount(1, self::arrows('A ->['));
    }

    public function test_an_empty_label_is_no_label(): void
    {
        TreeAssert::assertSameTree(Math::tex('\\ce{A -> B}'), Math::tex('\\ce{A ->[] B}'), ignoreSource: true);
        TreeAssert::assertSameTree(Math::tex('\\ce{A ->[ ][] B}'), Math::tex('\\ce{A -> B}'), ignoreSource: true);
        $this->addToAssertionCount(2);
    }

    public function test_a_bracket_inside_math_does_not_close_a_label(): void
    {
        [$arrow] = self::arrows('A ->[$[a,b]$] B');
        self::assertNotNull($arrow->over);
        self::assertSame([], TexReader::read('\\ce{A ->[$[a,b]$] B}')->notes());
        self::assertNull($arrow->under);
    }

    public function test_many_unclosed_labels_take_linear_time(): void
    {
        $ce = str_repeat('->[', intdiv(TexReader::MAX_INPUT - 5, 3));
        $start = microtime(true);
        Math::tex('\\ce{' . $ce . '}');
        self::assertLessThan(0.5, microtime(true) - $start);
    }

    /** @return iterable<string, array{string}> */
    public static function stretching(): iterable
    {
        yield 'equilibrium' => ['<=>'];
        yield 'to and fro' => ['<-->'];
        yield 'forward' => ['->'];
    }

    #[DataProvider('stretching')]
    public function test_every_arrow_is_at_least_as_wide_as_its_label_and_the_minimum(string $arrow): void
    {
        $size = 22.0;
        foreach (['A %s[Fe][450 °C] B', 'A %s[x] B', 'A %s B'] as $pattern) {
            $ce = sprintf($pattern, $arrow);
            [$node] = self::arrows($ce);
            $stack = Math::layout($node, $size, false);
            $label = static fn (?Node $n): float => $n === null ? 0.0 : Math::layout($n, $size * 0.7, false)->width;
            $widest = max($label($node->over), $label($node->under));
            self::assertGreaterThanOrEqual(UnderOver::ARROW_MIN_WIDTH * $size - 0.5, $stack->width, $ce);
            if ($widest > 0.0) {
                self::assertGreaterThanOrEqual($widest + 2 * UnderOver::ARROW_LABEL_PAD * $size - 0.5, $stack->width, $ce);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function pairs(): iterable
    {
        yield 'equilibrium' => ['<=>'];
        yield 'to and fro' => ['<-->'];
    }

    #[DataProvider('pairs')]
    public function test_a_drawn_pair_has_its_halves_one_above_the_other(string $arrow): void
    {
        $font = Math::stixTwoMath();
        [$node] = self::arrows('A ' . $arrow . ' B');
        // Lowest and highest ink of each half, as heights above the baseline.
        $ink = ['up' => [INF, -INF], 'down' => [INF, -INF]];
        Math::layout($node, 40.0, false)->each(static function (DisplayItem $i, float $dx, float $dy) use ($font, &$ink): void {
            if (! $i instanceof Glyph) {
                return;
            }
            $bb = $font->bbox($i->gid);
            $scale = $i->sy * $i->size / $font->unitsPerEm;
            $y = $i->y + $dy;
            $half = $y < 0 ? 'up' : ($y > 0 ? 'down' : null);
            if ($half !== null) {
                $ink[$half] = [min($ink[$half][0], -$y + $bb[1] * $scale), max($ink[$half][1], -$y + $bb[3] * $scale)];
            }
        });
        self::assertTrue(is_finite($ink['up'][0]) && is_finite($ink['down'][0]), 'both halves are drawn');
        // The halves are clearly apart; the harpoons' ink does not overlap (the font's plain arrows overlap in height, as its own do).
        self::assertGreaterThan(0.1 * 40.0, array_sum($ink['up']) / 2 - array_sum($ink['down']) / 2);
        if ($arrow === '<=>') {
            self::assertGreaterThan($ink['down'][1], $ink['up'][0]);
        }
    }

    public function test_an_arrow_has_relation_spacing_on_both_sides(): void
    {
        $size = 22.0;
        $gap = Math::layout(Math::tex('\\ce{A -> B}'), $size, false)->width - Math::layout(Math::tex('\\ce{A B}'), $size, false)->width;
        // The arrow at its minimum length plus the dictionary's spacing (5mu, 0.2778em) on each side.
        self::assertGreaterThan((UnderOver::ARROW_MIN_WIDTH + 2 * 5 / 18) * $size, $gap);
    }

    public function test_unequal_arrows_are_drawn_as_equilibrium_with_one_note(): void
    {
        foreach (['A <=>> B', 'A <<=> B', 'A <=>>[x][y] B <<=> C'] as $ce) {
            $res = TexReader::read('\\ce{' . $ce . '}');
            $notes = $res->notes();
            self::assertCount(1, $notes, $ce);
            self::assertSame(NoteLevel::Approximated, $notes[0]->level);
            self::assertSame('unequal equilibrium arrow drawn as ⇌', $notes[0]->reason);
            foreach (self::arrows($ce) as $arrow) {
                self::assertInstanceOf(Operator::class, $arrow->base);
                self::assertSame('⇌', $arrow->base->char);
            }
        }
        self::assertCount(2, self::arrows('A <=>>[x][y] B <<=> C'));
    }

    #[DataProvider('stretching')]
    public function test_arrows_stretch_with_a_long_label(string $arrow): void
    {
        $short = Math::layout(Math::tex('\\ce{A ' . $arrow . '[x] B}'), 22.0, false)->width;
        $long = Math::layout(Math::tex('\\ce{A ' . $arrow . '[a long label here] B}'), 22.0, false)->width;
        self::assertGreaterThan($short, $long);
    }

    /** @return iterable<string, array{string, list<array{?string, ?string}>}> source, then the empty-base scripts as [preSub, preSup] texts in reading order */
    public static function danglingPrescripts(): iterable
    {
        yield 'after a space' => ['x ^2', [[null, '2']]];
        yield 'subscript after a space' => ['A _2', [['2', null]]];
        yield 'after a formula' => ['H2O ^{18}', [[null, '18']]];
        yield 'two superscripts' => ['^2^3', [[null, '2'], [null, '3']]];
        yield 'before a plus' => ['^{14} + C', [[null, '14']]];
        yield 'before an arrow' => ['^{14} -> C', [[null, '14']]];
    }

    /** @param list<array{?string, ?string}> $expected */
    #[DataProvider('danglingPrescripts')]
    public function test_a_prescript_without_a_base_is_drawn_on_an_empty_base(string $ce, array $expected): void
    {
        $found = [];
        $walk = static function (Node $n) use (&$walk, &$found): void {
            if ($n instanceof \Quoin\Math\Node\Scripts && $n->base instanceof Row && $n->base->children === []) {
                $found[] = [$n->preSub === null ? null : TextWriter::write($n->preSub), $n->preSup === null ? null : TextWriter::write($n->preSup)];
            }
            foreach ($n->children() as $child) {
                $walk($child);
            }
        };
        $walk(Math::tex('\\ce{' . $ce . '}'));
        self::assertSame($expected, $found);
    }

    public function test_a_prescript_before_an_atom_still_attaches_to_it(): void
    {
        $tree = \Quoin\Math\Node\Fallback::unwrap(Math::tex('\\ce{^{14}C}'));
        self::assertInstanceOf(\Quoin\Math\Node\Scripts::class, $tree);
        self::assertInstanceOf(\Quoin\Math\Node\Identifier::class, $tree->base);
    }

    /** @return iterable<string, array{string}> */
    public static function pairsWithLabels(): iterable
    {
        yield 'equilibrium' => ['<=>'];
        yield 'to and fro' => ['<-->'];
    }

    #[DataProvider('pairsWithLabels')]
    public function test_the_under_label_of_a_drawn_pair_clears_its_lower_half_as_far_as_a_single_arrows(string $arrow): void
    {
        $font = Math::stixTwoMath();
        // The space between the lowest ink of the arrow glyphs (no text) and the highest ink of the label glyphs, below the baseline.
        $gap = static function (string $ce) use ($font): float {
            [$node] = self::arrows($ce);
            $arrowBottom = -INF;
            $labelTop = INF;
            Math::layout($node, 40.0, false)->each(static function (DisplayItem $i, float $dx, float $dy) use ($font, &$arrowBottom, &$labelTop): void {
                if (! $i instanceof Glyph) {
                    return;
                }
                $bb = $font->bbox($i->gid);
                $scale = $i->sy * $i->size / $font->unitsPerEm;
                $y = $i->y + $dy;
                if ($i->text === null) {
                    // Arrow pieces below the middle line only.
                    if ($y > -0.1 * 40.0) {
                        $arrowBottom = max($arrowBottom, $y - $bb[1] * $scale);
                    }
                } elseif ($y > 0.0) {
                    $labelTop = min($labelTop, $y - $bb[3] * $scale);
                }
            });

            return $labelTop - $arrowBottom;
        };
        $single = $gap('A ->[x][450 °C] B');
        self::assertGreaterThan(0.0, $single);
        self::assertEqualsWithDelta($single, $gap('A ' . $arrow . '[x][450 °C] B'), 0.05 * 40.0);
    }
}
