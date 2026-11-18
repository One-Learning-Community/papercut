<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Row;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\Chem\ChemArrows;
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
        $tex = self::wrap($ce);
        TreeAssert::assertSameTree(self::expectedTree($expected), Math::tex($tex), ignoreSource: true);
        $found = array_map(static fn ($n): array => [$n->element, $n->level->name], TexReader::read($tex)->notes());
        self::assertSame($notes, $found);
    }

    /** An entry written as `\pu{…}` is read as it stands; any other is the argument of `\ce`. */
    private static function wrap(string $source): string
    {
        return str_starts_with($source, '\pu{') ? $source : '\ce{' . $source . '}';
    }

    /**
     * The expected TeX read to a tree. A `⟦→⟧` stands for the stretchy arrow with no label, which no TeX spelling
     * reads to: the text around it is read apart and joined with the arrow node.
     */
    private static function expectedTree(string $expected): Node
    {
        $parts = preg_split('/⟦(.)⟧/u', $expected, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false || count($parts) === 1) {
            return Math::tex($expected);
        }
        $children = [];
        foreach ($parts as $k => $part) {
            if ($k % 2 === 1) {
                $children[] = ChemArrows::node($part, null, null);
            } elseif (trim($part) !== '') {
                $tree = Math::tex($part);
                array_push($children, ...($tree instanceof Row ? $tree->children : [$tree]));
            }
        }

        return new Row($children);
    }

    #[DataProvider('formulas')]
    public function test_writes_back_exactly_and_round_trips(string $ce): void
    {
        $tex = self::wrap($ce);
        $tree = Math::tex($tex);
        self::assertSame($tex, TexWriter::write($tree));
        TreeAssert::assertSameTree($tree, Math::tex(TexWriter::write($tree)));
    }
}
