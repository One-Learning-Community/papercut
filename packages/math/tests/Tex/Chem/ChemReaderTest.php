<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Text;
use Quoin\Math\NoteLevel;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tests\TreeAssert;
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
}
