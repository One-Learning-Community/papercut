<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Render\MathmlWriter;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Render\SvgRenderer;
use Quoin\Math\Tex\TexReader;

/** MathML, OMML and SVG snapshots of chemistry and unit formulas. Regenerate with UPDATE_SNAPSHOTS=1. */
final class ChemSnapshotTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function markup(): iterable
    {
        yield 'chem-iron-hydroxide' => ['chem-iron-hydroxide', '\ce{Fe(OH)3}'];
        yield 'chem-sulfate' => ['chem-sulfate', '\ce{SO4^2-}'];
        yield 'chem-haber' => ['chem-haber', '\ce{N2 + 3H2 <=> 2NH3}'];
        yield 'chem-decomposition' => ['chem-decomposition', '\ce{CaCO3 ->[\Delta] CaO + CO2 ^}'];
        yield 'chem-carbon-14' => ['chem-carbon-14', '\ce{^{14}_{6}C}'];
        yield 'chem-unit' => ['chem-unit', '\pu{25 kJ/mol}'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function drawings(): iterable
    {
        yield 'chem-combustion' => ['chem-combustion', '\ce{CH4 + 2O2 -> CO2 + 2H2O}'];
        yield 'chem-acid-on-limestone' => ['chem-acid-on-limestone', '\ce{CaCO3 + 2HCl -> CaCl2 + CO2 + H2O}'];
        yield 'chem-rusting' => ['chem-rusting', '\ce{4Fe + 3O2 -> 2Fe2O3}'];
        yield 'chem-sulfate' => ['chem-sulfate', '\ce{SO4^2-}'];
        yield 'chem-ammonium' => ['chem-ammonium', '\ce{NH4+}'];
        yield 'chem-calcium-ion' => ['chem-calcium-ion', '\ce{Ca^2+}'];
        yield 'chem-iron-hydroxide' => ['chem-iron-hydroxide', '\ce{Fe(OH)3}'];
        yield 'chem-haber' => ['chem-haber', '\ce{N2 + 3H2 <=> 2NH3}'];
        yield 'chem-salt-aqueous' => ['chem-salt-aqueous', '\ce{NaCl(aq)}'];
        yield 'chem-carbon-14' => ['chem-carbon-14', '\ce{^{14}_{6}C}'];
        yield 'chem-labelled-arrow' => ['chem-labelled-arrow', '\ce{N2 + 3H2 ->[Fe][450 °C] 2NH3}'];
        yield 'chem-unequal-forward' => ['chem-unequal-forward', '\ce{A <=>> B}'];
        yield 'chem-unequal-backward' => ['chem-unequal-backward', '\ce{A <<=> B}'];
    }

    #[DataProvider('markup')]
    public function test_markup(string $name, string $tex): void
    {
        $r = TexReader::read($tex);
        $outputs = [
            'mathml' => MathmlWriter::write($r->node, $r->display),
            'omml' => $r->display ? OmmlWriter::oMathPara($r->node, 'center') : OmmlWriter::oMath($r->node),
        ];
        foreach ($outputs as $kind => $actual) {
            self::assertSnapshot(__DIR__ . "/../../snapshots/tex/$kind/$name.xml", $actual);
        }
    }

    #[DataProvider('drawings')]
    public function test_drawing(string $name, string $tex): void
    {
        $svg = (new SvgRenderer(Math::stixTwoMath()))->render(Math::layout(TexReader::read($tex)->node, 12.0, true));
        self::assertSnapshot(__DIR__ . "/../../snapshots/svg/$name.svg", $svg);
    }

    private static function assertSnapshot(string $path, string $actual): void
    {
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $actual . "\n");
        }
        self::assertFileExists($path);
        self::assertSame(rtrim((string) file_get_contents($path)), $actual, basename($path));
    }
}
