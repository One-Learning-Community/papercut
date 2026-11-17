<?php

declare(strict_types=1);

namespace Papercut\Math\Tex;

use Papercut\Math\NoteLevel;

/**
 * Macro expansion on the token list, before the reader sees it: `\newcommand`, `\renewcommand`, `\providecommand`,
 * `\def` (undelimited parameters only) and `\DeclareMathOperator`. Definitions last to the end of their group. The
 * expansion count and the size of what expansion produces are capped; past a cap a macro is left as its own name, as
 * text, so hostile input (`\def\a#1{\a{#1#1}}\a{x}`) cannot grow without bound.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class TexMacros
{
    /** @var array<string, array{int, ?list<array{string, string, int}>, list<array{string, string, int}>}> name => [parameter count, default of the first (null: none), body] */
    private array $macros = [];
    /** @var list<array{NoteLevel, string, string, int}> */
    private array $notes = [];
    /** @var list<list<array{string, mixed}>> per open group, what to put back at its end: [name, the definition it replaced or null] */
    private array $scopes = [];
    private bool $deepNoted = false;
    private int $expansions = 0;
    private int $produced = 0;
    private bool $stopped = false;

    /**
     * @param list<array{string, string, int}> $tokens
     *
     * @return array{list<array{string, string, int}>, list<array{NoteLevel, string, string, int}>}
     */
    public static function expand(array $tokens): array
    {
        $m = new self();

        return [$m->run($tokens), $m->notes];
    }

    /**
     * @param list<array{string, string, int}> $tokens
     *
     * @return list<array{string, string, int}>
     */
    private function run(array $tokens): array
    {
        // Cheap exit: nothing here defines or uses a macro.
        $any = false;
        foreach ($tokens as $t) {
            if ($t[0] === 'cs' && in_array($t[1], ['newcommand', 'renewcommand', 'providecommand', 'def', 'DeclareMathOperator'], true)) {
                $any = true;
                break;
            }
        }
        if (! $any) {
            return $tokens;
        }
        // The budget is the stream's size in source bytes, input included: what the reader is handed is never bigger than
        // some raw input of MAX_INPUT bytes, so the raw worst case bounds what macros can build.
        foreach ($tokens as $t) {
            $this->produced += self::cost($t);
        }
        $pending = array_reverse($tokens);
        $out = [];
        // A definition lasts to the end of its group, as in TeX.
        $over = 0;
        while ($pending !== []) {
            $t = array_pop($pending);
            if ($t[0] === '{') {
                if (count($this->scopes) < TexReader::MAX_DEPTH) {
                    $this->scopes[] = [];
                } else {
                    // Groups past the depth limit share the last scope; the reader draws them flat.
                    if ($over++ === 0 && ! $this->deepNoted) {
                        $this->deepNoted = true;
                        $this->notes[] = [NoteLevel::Approximated, 'nesting', 'nested too deeply', $t[2]];
                    }
                }
            } elseif ($t[0] === '}') {
                if ($over > 0) {
                    $over--;
                } elseif ($this->scopes !== []) {
                    foreach (array_reverse(array_pop($this->scopes)) as [$n, $before]) {
                        if ($before === null) {
                            unset($this->macros[$n]);
                        } else {
                            $this->macros[$n] = $before;
                        }
                    }
                }
            }
            if ($t[0] !== 'cs') {
                $out[] = $t;
                continue;
            }
            $name = $t[1];
            if (in_array($name, ['newcommand', 'renewcommand', 'providecommand'], true)) {
                $this->newCommand($pending, $name, $t[2]);
            } elseif ($name === 'def') {
                $this->def($pending, $t[2]);
            } elseif ($name === 'DeclareMathOperator') {
                $this->declareOperator($pending);
            } elseif (isset($this->macros[$name])) {
                if (! $this->stopped && $this->expansions < TexReader::MAX_EXPANSIONS) {
                    $this->invoke($pending, $name, $t[2]);
                } else {
                    $this->stop($t[2]);
                    array_push($out, ...self::asText('\\' . $name, $t[2]));
                }
            } else {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** @param array{int, ?list<array{string, string, int}>, list<array{string, string, int}>} $macro */
    private function define(string $name, array $macro): void
    {
        if ($this->scopes !== []) {
            $this->scopes[array_key_last($this->scopes)][] = [$name, $this->macros[$name] ?? null];
        }
        $this->macros[$name] = $macro;
    }

    /**
     * A token's size as source text: `\left` is 5 bytes, `{` is 1.
     *
     * @param array{string, string, int} $t
     */
    private static function cost(array $t): int
    {
        return max(1, strlen($t[1]) + ($t[0] === 'cs' ? 1 : 0));
    }

    private function stop(int $at): void
    {
        if (! $this->stopped) {
            $this->stopped = true;
            $this->notes[] = [NoteLevel::Approximated, 'macro', 'expansion limit reached; the rest is drawn as its names', $at];
        }
    }

    /**
     * @return list<array{string, string, int}>
     */
    private static function asText(string $s, int $at): array
    {
        $out = [['cs', 'text', $at], ['{', '{', $at]];
        foreach (mb_str_split($s) as $ch) {
            $out[] = ['ch', $ch, $at];
        }
        $out[] = ['}', '}', $at];

        return $out;
    }

    /**
     * @param array<int, array{string, string, int}> $pending reversed: the next token is last
     *
     * @return ?array{string, string, int}
     */
    private static function next(array $pending): ?array
    {
        return $pending === [] ? null : $pending[array_key_last($pending)];
    }

    /** @param ?array{string, string, int} $t */
    private static function isChar(?array $t, string $c): bool
    {
        return $t !== null && $t[0] === 'ch' && $t[1] === $c;
    }

    /** @param array<int, array{string, string, int}> $pending reversed: the next token is last */
    private static function skipSp(array &$pending): void
    {
        while ((self::next($pending)[0] ?? '') === 'sp') {
            array_pop($pending);
        }
    }

    /**
     * One undelimited argument: the inside of a group, or the one token.
     *
     * @param array<int, array{string, string, int}> $pending
     *
     * @return list<array{string, string, int}>
     */
    private static function argument(array &$pending): array
    {
        self::skipSp($pending);
        $t = array_pop($pending);
        if ($t === null) {
            return [];
        }
        if ($t[0] !== '{') {
            return [$t];
        }
        $depth = 1;
        $arg = [];
        while (($t = array_pop($pending)) !== null) {
            if ($t[0] === '{') {
                $depth++;
            } elseif ($t[0] === '}' && --$depth === 0) {
                break;
            }
            $arg[] = $t;
        }

        return $arg;
    }

    /**
     * The name of a macro in a definition: `{\R}` or `\R`; null when there is none.
     *
     * @param array<int, array{string, string, int}> $pending
     */
    private static function macroName(array &$pending): ?string
    {
        $arg = self::argument($pending);

        return count($arg) === 1 && $arg[0][0] === 'cs' ? $arg[0][1] : null;
    }

    /**
     * A `[...]` argument of a definition as its characters, or null when none follows.
     *
     * @param array<int, array{string, string, int}> $pending
     */
    private static function bracket(array &$pending): ?string
    {
        self::skipSp($pending);
        $t = self::next($pending);
        if ($t === null || $t[0] !== 'ch' || $t[1] !== '[') {
            return null;
        }
        array_pop($pending);
        $s = '';
        while (($t = array_pop($pending)) !== null && ! ($t[0] === 'ch' && $t[1] === ']')) {
            $s .= $t[1];
        }

        return $s;
    }

    /** @param array<int, array{string, string, int}> $pending */
    private function newCommand(array &$pending, string $how, int $at): void
    {
        self::skipSp($pending);
        if (self::isChar(self::next($pending), '*')) {
            array_pop($pending);
        }
        $name = self::macroName($pending);
        $count = self::bracket($pending);
        $n = $count !== null && ctype_digit(trim($count)) ? min(9, (int) trim($count)) : 0;
        $default = null;
        if ($n > 0 && self::next($pending) !== null) {
            self::skipSp($pending);
            $t = self::next($pending);
            if ($t !== null && $t[0] === 'ch' && $t[1] === '[') {
                array_pop($pending);
                $default = [];
                $depth = 0;
                while (($t = array_pop($pending)) !== null) {
                    if ($t[0] === '{') {
                        $depth++;
                    } elseif ($t[0] === '}') {
                        $depth--;
                    } elseif ($depth === 0 && $t[0] === 'ch' && $t[1] === ']') {
                        break;
                    }
                    $default[] = $t;
                }
            }
        }
        $body = self::argument($pending);
        if ($name === null || ($how === 'providecommand' && isset($this->macros[$name]))) {
            return;
        }
        $this->define($name, [$n, $default, $body]);
    }

    /** @param array<int, array{string, string, int}> $pending */
    private function def(array &$pending, int $at): void
    {
        self::skipSp($pending);
        $t = array_pop($pending);
        if ($t === null || $t[0] !== 'cs') {
            return;
        }
        $name = $t[1];
        $expect = 1;
        $delimited = false;
        while (($t = self::next($pending)) !== null && $t[0] !== '{') {
            array_pop($pending);
            if ($t[0] === 'param' && (int) $t[1] === $expect) {
                $expect++;
            } else {
                $delimited = true;
            }
        }
        $body = self::argument($pending);
        if ($delimited) {
            $this->notes[] = [NoteLevel::Approximated, '\\def', 'delimited parameters', $at];

            return;
        }
        $this->define($name, [$expect - 1, null, $body]);
    }

    /** @param array<int, array{string, string, int}> $pending */
    private function declareOperator(array &$pending): void
    {
        self::skipSp($pending);
        $star = self::isChar(self::next($pending), '*');
        if ($star) {
            array_pop($pending);
        }
        $name = self::macroName($pending);
        $label = self::argument($pending);
        if ($name === null) {
            return;
        }
        $at = $label[0][2] ?? 0;
        $body = [['cs', 'operatorname', $at]];
        if ($star) {
            $body[] = ['ch', '*', $at];
        }
        $this->define($name, [0, null, [...$body, ['{', '{', $at], ...$label, ['}', '}', $at]]]);
    }

    /** @param array<int, array{string, string, int}> $pending */
    private function invoke(array &$pending, string $name, int $at): void
    {
        [$n, $default, $body] = $this->macros[$name];
        $args = [];
        $given = [];
        $short = false;
        for ($i = 1; $i <= $n; $i++) {
            if ($i === 1 && $default !== null) {
                self::skipSp($pending);
                $t = self::next($pending);
                if ($t !== null && $t[0] === 'ch' && $t[1] === '[') {
                    array_pop($pending);
                    $arg = [];
                    $depth = 0;
                    while (($t = array_pop($pending)) !== null) {
                        if ($t[0] === '{') {
                            $depth++;
                        } elseif ($t[0] === '}') {
                            $depth--;
                        } elseif ($depth === 0 && $t[0] === 'ch' && $t[1] === ']') {
                            break;
                        }
                        $arg[] = $t;
                    }
                    $args[1] = $arg;
                    $given[1] = true;
                } else {
                    $args[1] = $default;
                    $given[1] = false;
                }
                continue;
            }
            self::skipSp($pending);
            $next = self::next($pending);
            if ($next === null || in_array($next[0], ['}', '&', 'nl'], true) || ($next[0] === 'cs' && in_array($next[1], ['end', 'right', 'middle'], true))) {
                // Nothing left for this argument: the group, cell or row ends here (and is not swallowed).
                if (! $short) {
                    $short = true;
                    $this->notes[] = [NoteLevel::Approximated, '\\' . $name, 'missing argument', $at];
                }
                $args[$i] = [];
                $given[$i] = true;
                continue;
            }
            $args[$i] = self::argument($pending);
            $given[$i] = true;
        }
        // The size of the result, before it is built.
        $size = 0;
        foreach ($body as $t) {
            if ($t[0] === 'param') {
                foreach ($args[(int) $t[1]] ?? [] as $a) {
                    $size += self::cost($a);
                }
            } else {
                $size += self::cost($t);
            }
        }
        $this->expansions++;
        $this->produced += $size;
        if ($this->produced > TexReader::MAX_EXPANSION_BYTES) {
            // Back on the stream as the macro's own name (the main loop draws it as text now that expansion has stopped), then its arguments.
            $this->stop($at);
            for ($i = $n; $i >= 1; $i--) {
                if ($given[$i]) {
                    $pending[] = ['}', '}', $at];
                    for ($j = count($args[$i]) - 1; $j >= 0; $j--) {
                        $pending[] = $args[$i][$j];
                    }
                    $pending[] = ['{', '{', $at];
                }
            }
            $pending[] = ['cs', $name, $at];

            return;
        }
        $built = [];
        $count = count($body);
        for ($i = 0; $i < $count; $i++) {
            $t = $body[$i];
            if ($t[0] === 'ch' && $t[1] === '#' && ($body[$i + 1][0] ?? '') === 'param') {
                // `##1` in a body is a literal `#1` of a definition made by the expansion.
                $built[] = [$body[++$i][0], $body[$i][1], $at];
            } elseif ($t[0] === 'param') {
                array_push($built, ...($args[(int) $t[1]] ?? []));
            } else {
                // What the macro produces is at the macro's use, not at its definition: notes point there.
                $built[] = [$t[0], $t[1], $at];
            }
        }
        for ($i = count($built) - 1; $i >= 0; $i--) {
            $pending[] = $built[$i];
        }
    }
}
