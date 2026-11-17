<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use Quoin\Math\Math;
use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\TexWriter;
use Quoin\Math\Tests\Mathml\EditorFixturesTest;
use Quoin\Math\Tests\TreeAssert;
use Quoin\Math\Tex\TexReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The realistic-formula corpus (tests/fixtures/tex/corpus.php) through the reader and every writer. */
final class TexCorpusTest extends TestCase
{
    /** Entries whose MathML and OMML are snapshotted: the ones that exercise a different writer path each. */
    private const SNAPSHOTS = [
        'quadratic', 'euler', 'maxwell-aligned', 'taylor', 'gaussian', 'double-integral', 'limit-n', 'binomial-thm',
        'cfrac', 'piecewise', 'set-builder', 'set-builder-middle', 'forall-exists', 'matrix-dots', 'linear-system',
        'aligned-derivation', 'aligned-two-rows', 'two-pair-align', 'macro-args', 'operatorname-star', 'text-math',
        'fonts', 'accents', 'braces', 'xarrow', 'xarrow-limit', 'sizes', 'middle', 'sum-substack', 'displaystyle',
        'matrix-small', 'alignat', 'split-env', 'prescripts', 'delim-dot', 'display-wrapper', 'sqrt-root',
        'schrodinger', 'boxed', 'cd-env',
    ];

    /** @return array<string, array{string, list<array{string, string}>}> */
    private static function corpus(): array
    {
        return require __DIR__ . '/../fixtures/tex/corpus.php';
    }

    /** @return iterable<string, array{string}> */
    public static function names(): iterable
    {
        foreach (array_keys(self::corpus()) as $name) {
            yield $name => [$name];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function snapshotNames(): iterable
    {
        foreach (self::SNAPSHOTS as $name) {
            yield $name => [$name];
        }
    }

    public function test_corpus_size(): void
    {
        self::assertGreaterThanOrEqual(150, count(self::corpus()));
        foreach (self::SNAPSHOTS as $name) {
            self::assertArrayHasKey($name, self::corpus());
        }
    }

    #[DataProvider('names')]
    public function test_corpus_round_trip(string $name): void
    {
        $src = self::corpus()[$name][0];
        $a = Math::tex($src);
        $tex = TexWriter::write($a);
        TreeAssert::assertSameTree($a, Math::tex($tex));
        // Writing what was written changes nothing more.
        self::assertSame($tex, TexWriter::write(Math::tex($tex)));
    }

    #[DataProvider('names')]
    public function test_corpus_notes(string $name): void
    {
        [$src, $expected] = self::corpus()[$name];
        $actual = array_map(static fn ($n): array => [$n->element, $n->level->name], TexReader::read($src)->notes());
        self::assertSame($expected, $actual, $name);
    }

    #[DataProvider('snapshotNames')]
    public function test_corpus_writers(string $name): void
    {
        $r = TexReader::read(self::corpus()[$name][0]);
        $outputs = [
            'mathml' => MathmlWriter::write($r->node, $r->display),
            'omml' => $r->display ? OmmlWriter::oMathPara($r->node, 'center') : OmmlWriter::oMath($r->node),
        ];
        foreach ($outputs as $kind => $actual) {
            $path = __DIR__ . "/../snapshots/tex/$kind/$name.xml";
            if (getenv('UPDATE_SNAPSHOTS') === '1') {
                if (! is_dir(dirname($path))) {
                    mkdir(dirname($path), 0777, true);
                }
                file_put_contents($path, $actual . "\n");
            }
            self::assertFileExists($path);
            self::assertSame(rtrim((string) file_get_contents($path)), $actual, "$name $kind");
        }
    }

    /** @return iterable<string, array{string}> */
    public static function editorFormulas(): iterable
    {
        foreach (EditorFixturesTest::fixtureFiles() as [$path]) {
            foreach (EditorFixturesTest::formulas((string) file_get_contents($path)) as $i => $src) {
                yield basename($path, '.html') . " #$i" => [$src];
            }
        }
    }

    /** Editor MathML to TeX and back reads clean; the trees may differ (the spec says where). */
    #[DataProvider('editorFormulas')]
    public function test_mathml_to_tex(string $mathml): void
    {
        $tex = TexWriter::write(MathmlReader::read($mathml)->node);
        $back = TexReader::read($tex);
        self::assertSame([], array_map(static fn ($n): string => $n->element . ': ' . $n->reason, $back->notes()), $tex);
    }
}
