<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex\Chem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Quoin\Math\Math;
use Quoin\Math\Tex\TexReader;

final class ChemHardeningTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> the source, and whether it is malformed enough to need a note */
    public static function hostile(): iterable
    {
        // Valid as mhchem reads them (it draws an open bracket, a lone arrow, nothing): no note owed, no throw allowed.
        yield 'open paren' => ['\ce{(}', false];
        yield 'open complex' => ['\ce{[Cu(NH3}', false];
        yield 'lone caret' => ['\ce{^}', false];
        yield 'empty ce' => ['\ce{}', false];
        yield 'empty pu' => ['\pu{}', false];
        // Malformed: something is lost or guessed, so the reader says so.
        yield 'dangling label' => ['\ce{A ->[}', true];
        yield 'second label open' => ['\ce{A ->[x][}', true];
        yield 'no close' => ['\ce{', true];
        yield 'no close with content' => ['\ce{H2O', true];
        yield 'stray dollar' => ['\ce{$x}', true];
        yield 'run of arrows' => ['\ce{' . str_repeat('->', 32_768) . '}', true];
        yield 'run of charges' => ['\ce{A' . str_repeat('^2-', 21_845) . '}', true];
        yield 'nested parens' => ['\ce{' . str_repeat('(', 300) . '}', false];
        yield 'nested math' => [str_repeat('$\ce{', 300) . 'x' . str_repeat('}$', 300), true];
        yield 'ce in a big array' => ['\begin{matrix}' . str_repeat(str_repeat('\ce{H2}&', 99) . '\\\\', 101) . '\end{matrix}', true];
    }

    #[DataProvider('hostile')]
    public function test_hostile_chemistry_is_bounded(string $tex, bool $noted): void
    {
        memory_reset_peak_usage();
        $mem = memory_get_usage();
        $t = microtime(true);
        $result = TexReader::read($tex);
        Math::layout($result->node);
        $wall = microtime(true) - $t;
        self::assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $mem, 'memory');
        if (! (function_exists('xdebug_info') && xdebug_info('mode') !== [])) {
            self::assertLessThan(1.0, $wall, 'read and layout');
        }
        if ($noted) {
            self::assertNotEmpty($result->notes(), 'a note');
        }
    }

    /** Thousands of random inputs: run on demand with `--group fuzz`, not in the default suite. */
    #[Group('fuzz')]
    public function test_fuzz(): void
    {
        mt_srand(1117);
        $atoms = [...str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789()[]{}^_+-=#*.$ '), '\ce', '\pu', '->', '<=>', 'v', ' ', ' '];
        for ($n = 0; $n < 2000; $n++) {
            $body = '';
            for ($i = mt_rand(1, 200); $i > 0; $i--) {
                $body .= $atoms[mt_rand(0, count($atoms) - 1)];
            }
            foreach (['\ce{' . $body . '}', '\pu{' . $body . '}', '\ce{' . $body] as $tex) {
                try {
                    $t = microtime(true);
                    $box = Math::layout(TexReader::read($tex)->node);
                    // Reaching this line is the real assertion: nothing threw. The width only proves a box came back.
                    self::assertGreaterThanOrEqual(0.0, $box->width, 'input #' . $n . ': ' . $tex);
                    if (! (function_exists('xdebug_info') && xdebug_info('mode') !== [])) {
                        self::assertLessThan(1.0, microtime(true) - $t, 'input #' . $n . ' took too long: ' . $tex);
                    }
                } catch (\Throwable $e) {
                    self::fail('input #' . $n . ' threw ' . $e::class . ': ' . $e->getMessage() . ' for ' . $tex);
                }
            }
        }
    }
}
