<?php

declare(strict_types=1);

namespace Quoin\Math\Tests\Tex;

use Quoin\Math\Math;
use Quoin\Math\Node\DisplayStyle;
use Quoin\Math\NoteLevel;
use Quoin\Math\Tex\TexReader;
use Quoin\Math\Tex\TexSymbols;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class TexHardeningTest extends TestCase
{
    /**
     * Items in the laid-out box per byte of (expanded) input. The worst measured is 0.66 (MAX_INPUT of nested `\sqrt{x+`
     * at MAX_DEPTH; a flat run of `x+` is 1.0): a stretched delimiter is a few scaled pieces, not a hundred repeated
     * ones. 2 leaves about 2x headroom.
     * Layout places each item once per enclosing level and levels stop at MAX_DEPTH, so this bounds the work as well as
     * the output.
     */
    private const int ITEMS_PER_BYTE = 2;

    /** Two levels per `\left(x^{` style unit: the deepest nest of them that reads without a nesting note. */
    private const int UNIT_DEPTH = (TexReader::MAX_DEPTH >> 1) - 1;

    /** @return iterable<string, array{string, string|list<string>}> */
    public static function hostile(): iterable
    {
        yield 'self-recursive macro' => ['\def\a{\a}\a', 'macro'];
        yield 'doubling macro' => ['\def\a#1{\a{#1#1}}\a{x}', 'macro'];
        yield 'nested braces' => [str_repeat('{', 600) . 'x' . str_repeat('}', 600), 'nesting'];
        yield 'long input' => [str_repeat('x+', 500_000), 'input'];
        yield 'huge matrix' => ['\begin{matrix}' . str_repeat(str_repeat('&', 149) . '\\\\', 100) . '\end{matrix}', 'cells'];
        yield 'huge matrix with content' => ['\begin{matrix}' . str_repeat(implode('&', array_fill(0, 200, 'x')) . '\\\\', 200) . '\end{matrix}', ''];
        yield 'script run' => ['x' . str_repeat('^', 50_000), ''];
        yield 'alternating scripts' => ['x' . str_repeat('^_', 20_000), ''];
        yield 'nested fractions' => [str_repeat('\frac', 2000) . 'x', ['\frac', 'nesting']];
        yield 'nested fences with scripts' => [str_repeat('\left(x^{', 80) . 'x' . str_repeat('}\right)', 80), ''];
        yield 'fences with scripts past the limit' => [str_repeat('\left(x^{', 200) . 'x' . str_repeat('}\right)', 200), 'nesting'];
        yield 'nested fences with fractions' => [str_repeat('\left(\frac{1}{', 120) . 'x' . str_repeat('}\right)', 120), ''];
        yield 'macro of repeated text' => ['\def\e{' . str_repeat('x', 2000) . '}' . str_repeat('\e', 99), 'macro'];
        yield 'macro of long arrow labels' => ['\def\e{\xrightarrow{' . str_repeat('y', 6000) . '}}' . str_repeat('\e', 100), 'macro'];
        // Small inputs whose macros build what a raw MAX_INPUT of siblings would: expansion stops at the same size.
        foreach (['fences with scripted middles' => ['\left(x\middle|x^{', '}\right)', self::UNIT_DEPTH + 1], 'fences with scripts' => ['\left(x^{', '}\right)', self::UNIT_DEPTH + 1], 'arrows' => ['\xrightarrow{', '}', self::UNIT_DEPTH + 1], 'fences with continued fractions' => ['\left(\cfrac{1}{x\middle|', '}\right)', intdiv(TexReader::MAX_DEPTH, 3)]] as $what => [$open, $close, $d]) {
            $e = '\def\e{' . str_repeat($open, $d) . 'x' . str_repeat($close, $d) . '}\def\f{' . str_repeat('\e', 10) . '}';
            $notes = $d > self::UNIT_DEPTH ? ['macro', 'nesting'] : ['\\middle', 'macro'];
            yield 'macro of nested ' . $what => [$e . '\def\g{' . str_repeat('\f', 10) . '}' . str_repeat('\g', 10), $notes];
        }
        foreach ([100, 300] as $units) {
            yield 'macro repeating nested fences ' . $units . ' times' => ['\def\e{' . str_repeat('\left(x\middle|x^{', self::UNIT_DEPTH) . 'x' . str_repeat('}\right)', self::UNIT_DEPTH) . '}\def\f{' . str_repeat('\e', 10) . '}' . str_repeat('\f', intdiv($units, 10)), ['macro']];
        }
        yield 'one huge row' => ['\begin{matrix}' . str_repeat('&', 12_000) . 'x\end{matrix}', 'cells'];
        yield 'macro-built row' => ['\def\r{' . str_repeat('&', 1000) . '}\begin{matrix}' . str_repeat('\r', 11) . 'x\end{matrix}', 'cells'];
        yield 'nested groups each defining a name' => [implode('', array_map(static fn (int $i): string => '{\def\\' . self::macroName($i) . '{x}', range(0, 999))) . 'x' . str_repeat('}', 1000), 'nesting'];
        yield 'nested groups after a definition' => [implode('', array_map(static fn (int $i): string => '\def\\' . self::macroName($i) . '{x}{', range(0, 799))) . 'x' . str_repeat('}', 800), 'nesting'];
        yield 'nested fences with middles' => [str_repeat('\left( a \middle| ', 100) . 'x' . str_repeat('\right)', 100), ''];
        yield 'nested fences with scripted middles' => [str_repeat('\left(x\middle|x^{', 80) . 'x' . str_repeat('}\right)', 80), ''];
        yield 'deep fences with scripts' => [str_repeat('\left(x^{', 250) . 'x' . str_repeat('}\right)', 250), 'nesting'];
        yield 'deep fences with sums' => [str_repeat('\left(\sum_{', 200) . 'x' . str_repeat('}\right)', 200), 'nesting'];
        // The worst a MAX_INPUT of siblings can do: each unit nested as deep as MAX_DEPTH reaches, and half that.
        foreach (['\\left(x\\middle|x^{' => '}\\right)', '\\left(x^{' => '}\\right)', '\\left(\\sum_{' => '}\\right)', '\\sqrt{x+' => '}', '\\overline{x+' => '}', '\\frac{1}{' => '}'] as $open => $close) {
            foreach ([intdiv(self::UNIT_DEPTH, 2), self::UNIT_DEPTH] as $d) {
                $one = str_repeat($open, $d) . 'x' . str_repeat($close, $d);
                yield 'siblings of ' . $open . ' at depth ' . $d => [str_repeat($one, intdiv(TexReader::MAX_INPUT, strlen($one))), []];
            }
        }
        yield 'nested sqrt' => [str_repeat('\sqrt[', 2000), ['\sqrt', 'nesting']];
    }

    private static function macroName(int $i): string
    {
        $s = '';
        do {
            $s = chr(97 + $i % 26) . $s;
            $i = intdiv($i, 26);
        } while ($i > 0);

        return $s;
    }

    /** @param string|list<string> $elements the Approximated notes expected, one each ('' for none checked) */
    #[DataProvider('hostile')]
    public function test_hostile_input_is_bounded(string $tex, string|array $elements): void
    {
        memory_reset_peak_usage();
        $mem = memory_get_usage();
        $t = microtime(true);
        $result = TexReader::read($tex);
        $box = Math::layout($result->node);
        $wall = microtime(true) - $t;
        self::assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $mem);
        // Deterministic: macros can expand to at most MAX_EXPANSION_BYTES bytes of source, anything else to the (capped) input.
        $bytes = preg_match('/\\\\(def|newcommand|renewcommand|providecommand|DeclareMathOperator)/', $tex) === 1 ? TexReader::MAX_EXPANSION_BYTES : min(strlen($tex), TexReader::MAX_INPUT);
        self::assertLessThanOrEqual(self::ITEMS_PER_BYTE * $bytes, count($box->items), 'items');
        // Wall time only as a backstop: generous, and not under a debugger or profiler.
        if (! self::instrumented()) {
            self::assertLessThan(5.0, $wall, 'read and layout');
        }
        if ($elements !== '') {
            $approx = array_map(static fn ($n): string => $n->element, array_values(array_filter($result->notes(), static fn ($n): bool => $n->level === NoteLevel::Approximated)));
            sort($approx);
            self::assertSame((array) $elements, $approx);
        }
    }

    /** Xdebug in any mode but off slows layout several times over. */
    private static function instrumented(): bool
    {
        return function_exists('xdebug_info') && xdebug_info('mode') !== [];
    }

    public function test_input_is_cut_at_a_whole_character(): void
    {
        $tex = str_repeat('x', TexReader::MAX_INPUT - 1) . 'é';
        $notes = TexReader::read($tex)->notes();
        self::assertSame('input', $notes[0]->element);
        self::assertSame('input too long; the rest is dropped', $notes[0]->reason);
    }

    public function test_nesting_past_the_limit_is_drawn_as_text(): void
    {
        $tex = str_repeat('{', 600) . 'x+y' . str_repeat('}', 600);
        $r = TexReader::read($tex);
        self::assertStringContainsString('x+y', print_r($r->node, true));
        self::assertCount(1, $r->notes());
        $env = TexReader::read(str_repeat('\\begin{matrix}', 600) . 'x' . str_repeat('\\end{matrix}', 600));
        self::assertCount(1, array_filter($env->notes(), static fn ($n): bool => $n->element === 'nesting'));
        self::assertSame([], array_filter($env->notes(), static fn ($n): bool => $n->element === '\\end'));
    }

    public function test_the_depth_limit_is_lowered_under_xdebugs_nesting_guard(): void
    {
        self::assertSame(TexReader::MAX_DEPTH, TexReader::effectiveDepth(null));
        self::assertSame(TexReader::MAX_DEPTH, TexReader::effectiveDepth(0));
        self::assertSame(TexReader::MAX_DEPTH, TexReader::effectiveDepth(-1));
        // Xdebug's default of 512 frames leaves room for a few dozen levels (measured: 62 of the worst, `\mathbf{`).
        $at512 = TexReader::effectiveDepth(512, 10);
        self::assertGreaterThan(30, $at512);
        self::assertLessThan(62, $at512);
        self::assertLessThan($at512, TexReader::effectiveDepth(512, 100));
        self::assertGreaterThan($at512, TexReader::effectiveDepth(1024, 10));
        self::assertSame(TexReader::MAX_DEPTH, TexReader::effectiveDepth(100_000));
        self::assertSame(1, TexReader::effectiveDepth(10));
    }

    public function test_content_past_the_effective_depth_gets_the_nesting_note(): void
    {
        $r = TexReader::readWithin(str_repeat('a_{', 12) . str_repeat('}', 12), 5);
        self::assertSame(['nesting'], array_map(static fn ($n): string => $n->element, $r->notes()));
        self::assertSame([], TexReader::readWithin(str_repeat('a_{', 4) . 'b' . str_repeat('}', 4), 5)->notes());
        // Never above MAX_DEPTH.
        $deep = TexReader::readWithin(str_repeat('{', 300) . 'x' . str_repeat('}', 300), 10_000);
        self::assertCount(1, $deep->notes());
    }

    public function test_nesting_at_the_effective_limit_does_not_trip_the_guard(): void
    {
        if (! extension_loaded('xdebug') || ! str_contains((string) ini_get('xdebug.mode'), 'develop')) {
            self::markTestSkipped('Needs Xdebug with its develop-mode nesting guard.');
        }
        // The test bootstrap raises Xdebug's limit; put the default back for this test.
        $was = (string) ini_get('xdebug.max_nesting_level');
        ini_set('xdebug.max_nesting_level', '512');
        try {
            $this->deepFormulasStayUnderTheGuard();
        } finally {
            ini_set('xdebug.max_nesting_level', $was);
        }
    }

    private function deepFormulasStayUnderTheGuard(): void
    {
        foreach (['a_{', '\\frac{1}{', '\\mathbf{', '\\sqrt{', '\\left(', '\\begin{matrix}'] as $open) {
            $close = match ($open) {
                '\\left(' => '\\right)',
                '\\begin{matrix}' => '\\end{matrix}',
                default => '}',
            };
            $tex = str_repeat($open, 300) . 'x' . str_repeat($close, 300);
            $r = TexReader::read($tex);
            Math::layout($r->node);
            self::assertNotSame([], $r->notes());
        }
    }

    public function test_a_stray_end_after_an_overflow_is_still_noted(): void
    {
        $notes = TexReader::read(str_repeat('{', 600) . 'x' . str_repeat('}', 600) . '\\end{matrix}')->notes();
        self::assertCount(1, array_filter($notes, static fn ($n): bool => $n->element === '\\end'));
    }

    public function test_flattened_left_swallows_its_right(): void
    {
        $r = TexReader::read(str_repeat('\\left(', 600) . 'x' . str_repeat('\\right)', 600));
        self::assertCount(1, $r->notes());
        self::assertSame('nesting', $r->notes()[0]->element);
    }

    public function test_builder_fences_with_display_style_stay_linear(): void
    {
        memory_reset_peak_usage();
        $mem = memory_get_usage();
        $t = microtime(true);
        $n = Math::row('x');
        for ($i = 0; $i < 100; $i++) {
            $n = Math::fenced('(', ')', new DisplayStyle(Math::row('a', Math::middle('|'), $n), true));
        }
        Math::layout($n);
        self::assertLessThan(1.0, microtime(true) - $t);
        self::assertLessThan(64 * 1024 * 1024, memory_get_peak_usage() - $mem);
    }

    public function test_cells_note_says_where(): void
    {
        $n = TexReader::read('\begin{matrix}' . str_repeat('&', 10_500) . 'x\end{matrix}')->notes();
        self::assertSame('cells', $n[0]->element);
        self::assertStringStartsWith('offset ', $n[0]->where);
    }

    public function test_unexpanded_macros_are_drawn_as_their_names(): void
    {
        self::assertStringContainsString('\\a', print_r(Math::tex('\def\a{\a}\a'), true));
        self::assertCount(1, TexReader::read('\def\a{\a}\a')->notes());
    }

    /** Thousands of random inputs: run on demand with `--group fuzz`, not in the default suite. */
    #[Group('fuzz')]
    public function test_fuzz(): void
    {
        mt_srand(1612);
        $words = ['frac', 'sqrt', 'left', 'right', 'begin', 'end', 'text', 'sum', 'int', 'alpha', 'mathbb', 'hat', 'over', 'color', 'not', 'newcommand', 'def', 'DeclareMathOperator', 'operatorname', 'substack', 'overset', 'big', 'tag', 'displaystyle', 'x', 'matrix', 'cases', 'aligned', 'pmod', 'char'];
        $words = [...$words, ...array_slice(array_keys(TexSymbols::GREEK), 0, 5)];
        $atoms = ['{', '}', '^', '_', '&', '\\\\', '$', '%', '[', ']', '(', ')', '#1', '#', '*', "\n", ' ', '0', '1.5', 'x', '\\'];
        for ($n = 0; $n < 2000; $n++) {
            $tex = '';
            for ($i = mt_rand(0, 40); $i > 0; $i--) {
                $r = mt_rand(0, 9);
                $tex .= match (true) {
                    $r < 4 => '\\' . $words[mt_rand(0, count($words) - 1)] . (mt_rand(0, 1) === 1 ? ' ' : ''),
                    $r < 8 => $atoms[mt_rand(0, count($atoms) - 1)],
                    default => implode('', array_map(static fn (): string => chr(mt_rand(0, 255)), range(1, mt_rand(1, 3)))),
                };
            }
            $node = TexReader::read($tex)->node;
            self::assertGreaterThanOrEqual(0.0, Math::layout($node)->width, 'input #' . $n . ': ' . bin2hex($tex));
        }
    }
}
