<?php

declare(strict_types=1);

namespace Quoin\Math\Tex\Chem;

use Quoin\Math\NoteLevel;
use Quoin\Math\Node\Fraction;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Number;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Node\Space;
use Quoin\Math\Node\Text;
use Quoin\Math\Tex\TexSymbols;

/**
 * Reads the argument of mhchem's `\ce` into the semantic tree: one upright identifier per element symbol, counts as
 * subscripts, charges as superscripts, bonds and hydrate dots as operators, isotopes as prescripts. The rules follow
 * mhchemParser 4.x (Apache-2.0); the tree is not the TeX mhchem expands to. Math inside (`$…$`, `\alpha`) goes to the
 * TeX reader through $math. What is outside the subset is drawn as its source text and noted.
 *
 * @internal Engine detail, not public API. It may change in any release.
 */
final class ChemReader
{
    /** Nesting of `{…}` groups beyond which the rest is drawn as text. */
    private const int MAX_DEPTH = 32;

    /** Longest stretch of source quoted in a note. */
    private const int QUOTE = 40;

    /** @var list<string> */
    private array $c = [];
    private int $n = 0;
    private int $i = 0;

    /** @var list<Node> */
    private array $out = [];

    /** The atom being built: a base that counts and charges attach to. */
    private ?Node $base = null;
    private string $kind = '';
    private ?Node $sub = null;
    private ?Node $sup = null;
    private ?Node $preSub = null;
    private ?Node $preSup = null;

    /** At the start of a species, where a number is a coefficient. */
    private bool $fresh = true;
    private bool $space = true;

    /** @var array<int, int>|null the index of each `[` to the `]` that closes it, found once per reader */
    private ?array $pairs = null;

    /**
     * @param \Closure(string, ?array{int, int}): Node $math reads TeX with the calling reader's limits and depth; the span is where the
     *        TeX stands in the source given to read(), when it does, so the caller can read the tokens it came from
     * @param \Closure(NoteLevel, string, string): void $note records a note on the `\ce` command
     */
    private function __construct(private readonly \Closure $math, private readonly \Closure $note, private readonly int $depth, private readonly int $origin = 0, private readonly bool $label = false)
    {
    }

    /**
     * @param \Closure(string, ?array{int, int}): Node $math
     * @param \Closure(NoteLevel, string, string): void $note
     */
    public static function read(string $src, \Closure $math, \Closure $note): Node
    {
        return (new self($math, $note, 0))->run($src);
    }

    private function run(string $s): Node
    {
        $this->c = mb_str_split($s);
        $this->n = count($this->c);
        while ($this->i < $this->n) {
            $this->step();
        }
        $this->flush();

        return count($this->out) === 1 ? $this->out[0] : new Row($this->out);
    }

    private function at(int $k = 0): string
    {
        return $this->c[$this->i + $k] ?? '';
    }

    private function step(): void
    {
        $ch = $this->at();
        $spaceBefore = $this->space;
        if (ctype_space($ch)) {
            $this->flush();
            if ($this->label && $this->wordSpace()) {
                $this->emit(new Space(1 / 3));
            }
            $this->i++;
            $this->space = true;
            $this->fresh = true;

            return;
        }
        $this->space = false;
        if ($ch === '$') {
            $this->dollar();
        } elseif ($ch === '\\') {
            $this->command();
        } elseif ($ch === '{') {
            $this->group();
        } elseif ($ch === '(' || $ch === '[') {
            if ($ch === '(' && ($this->base !== null || $this->out !== []) && preg_match('/^\([a-z]{1,3}\)(?:[\s,;)\]}]|$)/', $this->slice($this->i, min($this->n, $this->i + 6))) === 1) {
                // A state of aggregation: mhchem sets `\mskip2mu` before it.
                $this->emit(new Space(1 / 9));
            }
            $this->atom(new Operator($ch), 'open');
            $this->i++;
            $this->fresh = true;
        } elseif ($ch === ')' || $ch === ']') {
            $this->atom(new Operator($ch), 'close');
            $this->i++;
        } elseif (ctype_digit($ch) || ($ch === '.' && $this->fresh && ctype_digit($this->at(1)))) {
            $this->digits();
        } elseif ($ch === '*' || $ch === '.') {
            $this->dot();
        } elseif ($ch === '+') {
            $this->plus($spaceBefore);
        } elseif ($ch === '-') {
            $this->minus($spaceBefore);
        } elseif ($ch === '=' || $ch === '#') {
            $this->equals($spaceBefore);
        } elseif ($ch === '^' || $ch === '_') {
            $this->script($ch, $spaceBefore);
        } elseif ($ch === '<' || $ch === '>') {
            $this->arrow();
        } elseif ($ch === ',' || $ch === ';' || $ch === '/' || $ch === "'" || $ch === '!' || $ch === '?' || $ch === ':' || $ch === '°') {
            $this->emit(($this->math)($ch, null));
            $this->i++;
            $this->fresh = true;
        } elseif (preg_match('/^\p{L}$/u', $ch) === 1) {
            $this->letters($spaceBefore);
        } else {
            $this->unsupported($ch);
            $this->i++;
        }
    }

    // ---------------------------------------------------------------- the atom being built

    private function flush(): void
    {
        if ($this->base === null) {
            return;
        }
        $this->out[] = $this->sub === null && $this->sup === null && $this->preSub === null && $this->preSup === null
            ? $this->base
            : new Scripts($this->base, $this->sub, $this->sup, $this->preSub, $this->preSup);
        $this->base = $this->sub = $this->sup = $this->preSub = $this->preSup = null;
        $this->kind = '';
    }

    /** Start a new atom: counts and charges that follow attach to it, and scripts read before it become its prescripts. */
    private function atom(Node $base, string $kind): void
    {
        $pre = [$this->preSub, $this->preSup];
        $this->preSub = $this->preSup = null;
        $this->flush();
        [$this->preSub, $this->preSup] = $pre;
        $this->base = $base;
        $this->kind = $kind;
        $this->fresh = false;
    }

    private function emit(Node $n): void
    {
        $this->flush();
        $this->out[] = $n;
        $this->fresh = false;
    }

    /** Whether a charge or count may attach to what was just read. */
    private function chargeable(): bool
    {
        return $this->base !== null && ($this->kind === 'el' || $this->kind === 'close' || $this->kind === 'group');
    }

    private function unsupported(string $chunk): void
    {
        ($this->note)(NoteLevel::Approximated, '\\ce', 'not supported: ' . mb_substr($chunk, 0, self::QUOTE));
        $this->emit(new Text($chunk));
    }

    // ---------------------------------------------------------------- parts

    /** The index of the character that closes the group opened at $open, or null. */
    private function closing(int $open): ?int
    {
        $depth = 0;
        for ($k = $open; $k < $this->n; $k++) {
            if ($this->c[$k] === '{') {
                $depth++;
            } elseif ($this->c[$k] === '}' && --$depth === 0) {
                return $k;
            }
        }

        return null;
    }

    private function slice(int $from, int $to): string
    {
        return implode('', array_slice($this->c, $from, $to - $from));
    }

    private function rest(): void
    {
        $this->unsupported($this->slice($this->i, $this->n));
        $this->i = $this->n;
    }

    private function dollar(): void
    {
        for ($k = $this->i + 1; $k < $this->n; $k++) {
            if ($this->c[$k] === '$') {
                $inner = $this->slice($this->i + 1, $k);
                $span = [$this->origin + $this->i + 1, $this->origin + $k];
                $this->i = $k + 1;
                $fresh = $this->fresh;
                $this->atom(($this->math)($inner, $span), 'math');
                if ($fresh) {
                    $this->amountGap(true);
                }

                return;
            }
        }
        $this->rest();
    }

    private function command(): void
    {
        $k = $this->i + 1;
        $name = '';
        while ($k < $this->n && ctype_alpha($this->c[$k])) {
            $name .= $this->c[$k++];
        }
        if ($name === '') {
            $k = min($this->n, $k + 1);
        }
        if ($name === 'bond' || $name === 'hline' || $this->at(1) === '\\') {
            // A bond with a name, a table row break: not drawn here. Take the command and its argument as text.
            $k = max($k, $this->i + 2);
            while ($k < $this->n && $this->c[$k] === '{' && ($close = $this->closing($k)) !== null) {
                $k = $close + 1;
            }
            $this->unsupported($this->slice($this->i, $k));
            $this->i = $k;

            return;
        }
        if ($name !== '' && ! isset(TexSymbols::GREEK[$name]) && ! isset(TexSymbols::GREEK_UP[$name])) {
            for ($args = 0; $args < 2 && $k < $this->n && $this->c[$k] === '{' && ($close = $this->closing($k)) !== null; $args++) {
                $k = $close + 1;
            }
        }
        $tex = $this->slice($this->i, $k);
        $span = [$this->origin + $this->i, $this->origin + $k];
        $this->i = $k;
        $this->atom(($this->math)($tex, $span), 'math');
    }

    private function group(): void
    {
        $close = $this->closing($this->i);
        if ($close === null || $this->depth >= self::MAX_DEPTH) {
            $this->rest();

            return;
        }
        $inner = $this->slice($this->i + 1, $close);
        $origin = $this->origin + $this->i + 1;
        $this->i = $close + 1;
        if (trim($inner) !== '') {
            $this->atom((new self($this->math, $this->note, $this->depth + 1, $origin))->run($inner), 'group');
        }
    }

    private function digits(): void
    {
        $s = $this->slice($this->i, min($this->n, $this->i + 64));
        if ($this->fresh) {
            if (preg_match('~^([0-9]+)/([0-9]+)~', $s, $m) === 1) {
                $this->i += strlen($m[0]);
                $this->emit(new Fraction(new Number($m[1]), new Number($m[2])));
                $this->amountGap();

                return;
            }
            $num = self::first('/^(?:[0-9]+(?:[.,][0-9]+)?|\.[0-9]+)/', $s);
            $this->i += strlen($num);
            $this->emit(new Number($num));
            $this->amountGap();

            return;
        }
        $num = self::first('/^[0-9]+/', $s);
        $this->i += strlen($num);
        if ($this->chargeable() && $this->sub === null && $this->sup === null) {
            $this->sub = new Number($num);

            return;
        }
        $this->emit(new Number($num));
    }

    /** A thin space after an amount when a formula follows it, as mhchem writes `2\,\mathrm{O}`. */
    private function amountGap(bool $needSpace = false): void
    {
        $j = $this->i;
        while ($j < $this->n && ctype_space($this->c[$j])) {
            $j++;
        }
        if ($needSpace && $j === $this->i) {
            return;
        }
        $next = $this->c[$j] ?? '';
        if ($next !== '' && (str_contains('([$\\', $next) || preg_match('/^\p{L}$/u', $next) === 1)) {
            $this->emit(new Space(1 / 6));
        }
    }

    /** The text the pattern matches at the start of $s, or ''. */
    private static function first(string $pattern, string $s): string
    {
        return preg_match($pattern, $s, $m) === 1 ? $m[0] : '';
    }

    private function dot(): void
    {
        if ($this->at() === '.' && $this->at(1) === '.' && $this->at(2) === '.') {
            $this->i += 3;
            $this->emit(($this->math)('\\ldots', null));

            return;
        }
        $this->i++;
        $this->emit(new Operator('⋅'));
        $this->fresh = true;
    }

    private function plus(bool $spaceBefore): void
    {
        $this->i++;
        if (! $spaceBefore && $this->chargeable() && $this->sup === null) {
            $signs = [new Operator('+')];
            while ($this->at() === '+') {
                $signs[] = new Operator('+');
                $this->i++;
            }
            $this->sup = count($signs) === 1 ? $signs[0] : new Row($signs);

            return;
        }
        $this->emit(new Operator('+'));
        $this->fresh = true;
    }

    private function minus(bool $spaceBefore): void
    {
        $next = $this->at(1);
        if ($next === '>') {
            $this->arrow();

            return;
        }
        $this->i++;
        $end = $next === '' || ctype_space($next);
        if (! $spaceBefore && $this->chargeable() && $this->sup === null && ($end || str_contains(')]},;/_+', $next))) {
            $this->sup = new Operator('−');

            return;
        }
        if ($spaceBefore && $end && $this->base === null && $this->out !== []) {
            $this->emit(new Operator('−'));
            $this->fresh = true;

            return;
        }
        $this->emit(new Operator('−', lspace: 0.0, rspace: 0.0));
    }

    private function equals(bool $spaceBefore): void
    {
        $ch = $this->at();
        $next = $this->at(1);
        $this->i++;
        if ($ch === '=' && $spaceBefore && ($next === '' || ctype_space($next))) {
            $this->emit(new Operator('='));
            $this->fresh = true;

            return;
        }
        $this->emit(new Operator($ch === '=' ? '=' : '≡', lspace: 0.0, rspace: 0.0));
    }

    /** `^` and `_`: a gas or precipitate arrow as a word, a charge or count on the atom before, or a prescript on the one after. */
    private function script(string $mark, bool $spaceBefore): void
    {
        $next = $this->at(1);
        if ($mark === '^' && $spaceBefore && ($next === '' || str_contains(" \t,;)]}", $next))) {
            $this->i++;
            $this->emit(new Operator('↑'));
            $this->fresh = true;

            return;
        }
        $this->i++;
        if ($this->at() === '{') {
            $close = $this->closing($this->i);
            if ($close === null || $this->depth >= self::MAX_DEPTH) {
                $this->rest();

                return;
            }
            $arg = $this->slice($this->i + 1, $close);
            $start = $this->i + 1;
            $this->i = $close + 1;
        } else {
            $arg = self::first('/^(?:[0-9]+[+\-]*|[+\-]+|.)/u', $this->slice($this->i, min($this->n, $this->i + 64)));
            $start = $this->i;
            $this->i += mb_strlen($arg);
        }
        if (trim($arg) === '') {
            ($this->note)(NoteLevel::Dropped, '\\ce', 'script without content: ' . $mark);

            return;
        }
        $node = $this->scriptContent($arg, $this->origin + $start);
        if ($this->base === null) {
            if ($mark === '^') {
                $this->preSup = $node;
            } else {
                $this->preSub = $node;
            }
        } elseif ($mark === '^' && $this->sup === null) {
            $this->sup = $node;
        } elseif ($mark === '_' && $this->sub === null) {
            $this->sub = $node;
        } else {
            $this->emit(new Scripts(new Row([]), $mark === '_' ? $node : null, $mark === '^' ? $node : null));
        }
    }

    /** What stands in a script: numbers, signs, letters, groups and math. $origin is where $s starts in the source. */
    private function scriptContent(string $s, int $origin): Node
    {
        $parts = [];
        $chars = mb_str_split($s);
        $n = count($chars);
        for ($k = 0; $k < $n;) {
            $ch = $chars[$k];
            if (ctype_digit($ch)) {
                for ($j = $k; $j < $n && ctype_digit($chars[$j]); $j++) {
                }
                $parts[] = new Number(implode('', array_slice($chars, $k, $j - $k)));
                $k = $j;
            } elseif ($ch === '+' || $ch === '-') {
                $parts[] = new Operator($ch === '-' ? '−' : '+');
                $k++;
            } elseif ($ch === '(' || $ch === ')' || $ch === '[' || $ch === ']') {
                $parts[] = new Operator($ch);
                $k++;
            } elseif ($ch === '$' || $ch === '\\' || $ch === '{') {
                // Math, a command or a group: read by a reader of its own over the rest, which takes what it needs.
                $sub = new self($this->math, $this->note, $this->depth + 1, $origin + $k);
                $sub->c = array_slice($chars, $k);
                $sub->n = $n - $k;
                if ($ch === '{' && $this->depth >= self::MAX_DEPTH) {
                    $sub->rest();
                } else {
                    $sub->step();
                }
                $k += max(1, $sub->i);
                $sub->flush();
                $parts = [...$parts, ...$sub->out];
            } elseif (ctype_space($ch)) {
                $k++;
            } elseif (preg_match('/^\p{L}$/u', $ch) === 1) {
                for ($j = $k; $j < $n && preg_match('/^\p{L}$/u', $chars[$j]) === 1; $j++) {
                }
                $parts[] = new Identifier(implode('', array_slice($chars, $k, $j - $k)), false);
                $k = $j;
            } else {
                ($this->note)(NoteLevel::Approximated, '\\ce', 'not supported in a script: ' . $ch);
                $parts[] = new Text($ch);
                $k++;
            }
        }

        return count($parts) === 1 ? $parts[0] : new Row($parts);
    }

    /** A reaction arrow with up to two labels in `[…]` straight after it: the first goes above, the second below. */
    private function arrow(): void
    {
        $found = ChemArrows::match($this->slice($this->i, min($this->n, $this->i + 4)), 0);
        if ($found === null) {
            // A lone `<` or `>`: not an arrow.
            $this->unsupported($this->at());
            $this->i++;

            return;
        }
        $this->i += $found['length'];
        $labels = [];
        while (count($labels) < 2 && $this->at() === '[' && ($close = $this->closingBracket($this->i)) !== null) {
            $inner = $this->slice($this->i + 1, $close);
            $origin = $this->origin + $this->i + 1;
            $this->i = $close + 1;
            $labels[] = trim($inner) === '' ? null : $this->label($inner, $origin);
        }
        if (count($labels) < 2 && $this->at() === '[') {
            ($this->note)(NoteLevel::Approximated, '\\ce', 'arrow label without a closing bracket drawn as text');
        }
        if (ChemArrows::isUnequal($found['arrow'])) {
            ($this->note)(NoteLevel::Approximated, '\\ce', 'unequal equilibrium arrow drawn as ⇌');
        }
        $this->emit(ChemArrows::node($found['arrow'], $labels[0] ?? null, $labels[1] ?? null));
        $this->fresh = true;
    }

    /** A label is read as `\ce` text of its own. */
    private function label(string $inner, int $origin): Node
    {
        if ($this->depth >= self::MAX_DEPTH) {
            $this->unsupported($inner);

            return new Text($inner);
        }

        $trimmed = trim($inner);
        if (str_starts_with($trimmed, '{') && $this->bracesWrap($trimmed)) {
            // Braces in a label are literal text, as in mhchem.
            return ($this->math)('\\text{' . substr($trimmed, 1, -1) . '}', null);
        }

        return (new self($this->math, $this->note, $this->depth + 1, $origin, true))->run($inner);
    }

    /** Whether the first brace of $inner closes at its last one. */
    private function bracesWrap(string $inner): bool
    {
        $depth = 0;
        $s = $inner;
        $last = strlen($s) - 1;
        for ($k = 0; $k <= $last; $k++) {
            $depth += $s[$k] === '{' ? 1 : ($s[$k] === '}' ? -1 : 0);
            if ($depth === 0 && $k < $last) {
                return false;
            }
        }

        return $depth === 0;
    }

    /** Whether the space at the cursor stands between two words or a number and its unit, which a label keeps. */
    private function wordSpace(): bool
    {
        $last = $this->out[count($this->out) - 1] ?? null;
        if ($last === null || $last instanceof Operator || $last instanceof Space) {
            return false;
        }
        $k = $this->i;
        while ($k < $this->n && ctype_space($this->c[$k])) {
            $k++;
        }
        $next = $this->c[$k] ?? '';

        return $next !== '' && ($next === '°' || str_contains('([{$\\', $next) || preg_match('/^[\p{L}\p{N}]$/u', $next) === 1);
    }

    /** The index of the `]` that closes the `[` at $open, skipping nested brackets, braces and `$…$`, or null. */
    private function closingBracket(int $open): ?int
    {
        if ($this->pairs === null) {
            $this->pairs = $this->bracketPairs();
        }

        return $this->pairs[$open] ?? null;
    }

    /**
     * Every `[` that has a `]`, found in one pass so that many unclosed ones do not each rescan the rest.
     *
     * @return array<int, int>
     */
    private function bracketPairs(): array
    {
        $pairs = [];
        $stack = [];
        $braces = 0;
        for ($k = 0; $k < $this->n; $k++) {
            $ch = $this->c[$k];
            if ($ch === '{') {
                $braces++;
            } elseif ($ch === '}') {
                $braces = max(0, $braces - 1);
                while ($stack !== [] && $stack[count($stack) - 1][1] > $braces) {
                    array_pop($stack);
                }
            } elseif ($ch === '$') {
                for ($j = $k + 1; $j < $this->n && $this->c[$j] !== '$'; $j++) {
                }
                if ($j < $this->n) {
                    $k = $j;
                }
            } elseif ($ch === '[') {
                $stack[] = [$k, $braces];
            } elseif ($ch === ']' && $stack !== [] && $stack[count($stack) - 1][1] === $braces) {
                [$open] = array_pop($stack);
                $pairs[$open] = $k;
            }
        }

        return $pairs;
    }

    private function letters(bool $spaceBefore): void
    {
        $ch = $this->at();
        if (ctype_upper($ch)) {
            $k = $this->i + 1;
            while ($k < $this->n && ctype_lower($this->c[$k])) {
                $k++;
            }
            $sym = $this->slice($this->i, $k);
            $this->i = $k;
            $this->atom(new Identifier($sym, false), 'el');

            return;
        }
        if (! ctype_lower($ch)) {
            $this->i++;
            $this->atom(($this->math)($ch, null), 'math');

            return;
        }
        $k = $this->i + 1;
        while ($k < $this->n && ctype_lower($this->c[$k])) {
            $k++;
        }
        $run = $this->slice($this->i, $k);
        $after = $this->c[$k] ?? '';
        if ($run === 'v' && $spaceBefore && ($after === '' || str_contains(" \t,;)]}", $after))) {
            $this->i = $k;
            $this->emit(new Operator('↓'));
            $this->fresh = true;

            return;
        }
        if ($this->fresh && $k - $this->i === 1) {
            // A variable as the amount: `n H2O`.
            $j = $k;
            while ($j < $this->n && ctype_space($this->c[$j])) {
                $j++;
            }
            if ($j > $k && $j < $this->n && ctype_upper($this->c[$j])) {
                $this->i = $k;
                $this->emit(($this->math)($run, null));
                $this->amountGap();

                return;
            }
        }
        $this->i = $k;
        $this->atom(new Identifier($run, false), 'el');
    }
}
