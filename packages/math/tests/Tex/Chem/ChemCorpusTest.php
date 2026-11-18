<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\TexReader;

/** Every `\ce` formula in the chemistry corpus reads to the tree its plain-TeX spelling reads to. */
final class ChemCorpusTest extends TestCase
{
    /** @return iterable<string, array{string, string, list<array{string, string}>}> */
    public static function corpus(): iterable
    {
        /** @var array<string, array{string, string, list<array{string, string}>}> $all */
        $all = require __DIR__ . '/../../fixtures/tex/chem-corpus.php';
        foreach ($all as $name => $entry) {
            yield $name => $entry;
        }
    }

    /** @return iterable<string, array{string}> */
    public static function formulas(): iterable
    {
        foreach (self::corpus() as $name => [$ce]) {
            yield $name => [$ce];
        }
    }

    /** @param list<array{string, string}> $notes */
    #[DataProvider('corpus')]
    public function test_reads_to_the_expected_tree(string $ce, string $expected, array $notes): void
    {
        $tex = '\ce{' . $ce . '}';
        TreeAssert::assertSameTree(Math::tex($expected), Math::tex($tex), ignoreSource: true);
        $found = array_map(static fn ($n): array => [$n->element, $n->level->name], TexReader::read($tex)->notes());
        self::assertSame($notes, $found);
    }

    #[DataProvider('formulas')]
    public function test_writes_back_exactly_and_round_trips(string $ce): void
    {
        $tex = '\ce{' . $ce . '}';
        $tree = Math::tex($tex);
        self::assertSame($tex, TexWriter::write($tree));
        TreeAssert::assertSameTree($tree, Math::tex(TexWriter::write($tree)));
    }
}
