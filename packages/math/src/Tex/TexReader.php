<?php

declare(strict_types=1);

namespace Papercut\Math\Tex;

use Papercut\Math\Math;
use Papercut\Math\MathNote;
use Papercut\Math\Node\DisplayStyle;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Grid;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Middle;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Phantom;
use Papercut\Math\Node\Root;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\Node\Space;
use Papercut\Math\Node\Text;
use Papercut\Math\Node\UnderOver;
use Papercut\Math\NoteLevel;
use Papercut\Math\OperatorDictionary;
use Papercut\Math\Styles;
use Papercut\Math\Tokenizer;
use Papercut\Math\Utf8;

/**
 * LaTeX math to the node tree, the inverse of {@see \Papercut\Math\Render\TexWriter}. Never throws: what it cannot draw
 * becomes a Fallback with a MathNote (an unknown command is drawn as its own name, as KaTeX shows its error text), one
 * note per element and reason. Single symbols are classified by the builder's Tokenizer, so TeX and the builder read
 * the same characters the same way.
 *
 * Atoms are passed around as [node, kind, meta]: kind says how scripts attach (op: limits above and below, int: beside,
 * func: a name with scripts beside, funclim: a name with limits, brace: \overbrace or \underbrace waiting for a label).
 */
final class TexReader
{
    /** Input beyond this many bytes is dropped. */
    public const int MAX_INPUT = 16_384;
    /** Nesting (groups, arguments, environments) beyond this is drawn flat. */
    public const int MAX_DEPTH = 256;
    /** Macro expansions per formula. */
    public const int MAX_EXPANSIONS = 10_000;
    /**
     * What macro expansion may produce, counted in source bytes (each token at its own length, input included), so the
     * expanded stream is never bigger than a raw input of MAX_INPUT bytes.
     */
    public const int MAX_EXPANSION_BYTES = self::MAX_INPUT;
    /** Cells of all environments in a formula; rows past it are dropped. */
    public const int MAX_CELLS = 10_000;

    private TexCursor $c;
    private int $depth = 0;
    /** Nesting beyond this is drawn flat: MAX_DEPTH, lower under Xdebug's nesting guard (see effectiveDepth()). */
    private int $maxDepth = self::MAX_DEPTH;
    /** The font command in force: a Styles variant, 'rm', 'it', or null for the default math font. */
    private ?string $font = null;
    /** @var array<string, true> */
    private array $noted = [];
    /** \overset results not yet wrapped in a group of their own: \underset{b}{\overset{a}{x}} folds them, {{\overset..}} does not. */
    /** @var \SplObjectStorage<Node, bool> */
    private \SplObjectStorage $bare;
    /** A node to put in front of the atom just read (the leading digits of 12^3). */
    private ?Node $pending = null;
    /**
     * The stops of the expression being read, so a \left inside it ends where that one would.
     *
     * @var list<string>
     */
    private array $outerStops = [];
    /** Inside an environment's cells `&`, `\\` and `\end` end a cell, so they end a \left too; a group or \text starts over at 0. */
    private int $envDepth = 0;
    /** An alignment environment was read: the formula is a display. */
    private bool $display = false;
    private int $cellCount = 0;
    /** `\begin` tokens drawn flat past the depth limit whose `\end` is still to come: that `\end` is a result of the overflow, not a note of its own. */
    private int $skippedBegins = 0;
    /** The same for `\left` and its `\right`. */
    private int $skippedLefts = 0;

    /**
     * Where each stretch of the scrubbed text came from in the input, when the input had invalid UTF-8: [scrubbed start, input
     * start, scrubbed length, input length]. Offsets in notes are in the caller's input, not the scrubbed copy.
     *
     * @var list<array{int, int, int, int}>
     */
    private array $offsets = [];

    /**
     * @return list<array{int, int, int, int}>
     */
    private static function offsetMap(string $input, string $scrubbed): array
    {
        // A valid UTF-8 sequence (no overlongs or surrogates); anything else is an invalid stretch that scrubbing replaced.
        $valid = '/(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})++/A';
        $map = [];
        $in = 0;
        $out = 0;
        $n = strlen($input);
        while ($in < $n) {
            if (preg_match($valid, $input, $m, 0, $in) === 1) {
                $len = $outLen = strlen($m[0]);
            } else {
                $len = 1;
                while ($in + $len < $n && preg_match($valid, $input, $m, 0, $in + $len) !== 1) {
                    $len++;
                }
                $outLen = strlen(Utf8::clean(substr($input, $in, $len)));
            }
            $map[] = [$out, $in, $outLen, $len];
            $out += $outLen;
            $in += $len;
        }
        $map[] = [strlen($scrubbed), $n, 0, 0];

        return $map;
    }

    /** A byte offset in the scrubbed text as an offset in the caller's input. */
    private function inputOffset(int $offset): int
    {
        if ($this->offsets === []) {
            return $offset;
        }
        foreach ($this->offsets as [$out, $in, $outLen, $inLen]) {
            if ($offset >= $out && $offset < $out + $outLen) {
                return $outLen === $inLen ? $in + ($offset - $out) : $in;
            }
        }
        $last = $this->offsets[count($this->offsets) - 1];

        return $last[1] + max(0, $offset - $last[0]);
    }

    private function __construct()
    {
        $this->bare = new \SplObjectStorage();
        $this->c = new TexCursor([]);
    }

    /** Call frames the reader uses per level of nesting at most (measured: 8.3 for `\mathbf{`, 6.2 for `a_{`). */
    private const int FRAMES_PER_LEVEL = 10;
    /** Frames kept free for the reader's own entry and the layout and writers that follow. */
    private const int GUARD_MARGIN = 16;

    public static function read(string $tex): TexResult
    {
        return self::readWithin($tex, self::effectiveDepth(self::nestingGuard(), self::stackDepth()));
    }

    /**
     * Read with a nesting limit of $maxDepth (at most MAX_DEPTH) instead of MAX_DEPTH.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function readWithin(string $tex, int $maxDepth): TexResult
    {
        $reader = new self();
        $reader->maxDepth = max(1, min($maxDepth, self::MAX_DEPTH));

        return $reader->run($tex);
    }

    /**
     * How deep to nest before drawing flat. MAX_DEPTH, unless Xdebug's nesting guard is on (`$nestingLimit`, its
     * xdebug.max_nesting_level, above 0): that guard throws once the call stack passes the limit, so the depth is lowered
     * until a formula at the limit, with the $stackDepth frames already on the stack, stays under it.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function effectiveDepth(?int $nestingLimit, int $stackDepth = 0): int
    {
        if ($nestingLimit === null || $nestingLimit <= 0) {
            return self::MAX_DEPTH;
        }

        return max(1, min(self::MAX_DEPTH, intdiv($nestingLimit - $stackDepth - self::GUARD_MARGIN, self::FRAMES_PER_LEVEL)));
    }

    /** Xdebug's max_nesting_level when its develop mode (the one that enforces it) is on, else null. */
    private static function nestingGuard(): ?int
    {
        if (! extension_loaded('xdebug')) {
            return null;
        }
        $mode = (string) ini_get('xdebug.mode');
        if (! in_array('develop', array_map('trim', explode(',', $mode)), true)) {
            return null;
        }
        $level = (int) ini_get('xdebug.max_nesting_level');

        return $level > 0 ? $level : null;
    }

    /** The frames on the call stack now, only worth counting when a guard is on. */
    private static function stackDepth(): int
    {
        return extension_loaded('xdebug') ? count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) : 0;
    }

    private function run(string $tex): TexResult
    {
        $bad = false;
        $input = $tex;
        $tex = Utf8::scrub($tex, $bad);
        if ($bad) {
            $this->offsets = self::offsetMap($input, $tex);
        }
        $notes = [];
        if (strlen($tex) > self::MAX_INPUT) {
            $tex = mb_strcut($tex, 0, self::MAX_INPUT);
            $notes[] = [NoteLevel::Approximated, 'input', 'input too long; the rest is dropped', self::MAX_INPUT];
        }
        [$tokens, $macroNotes] = TexMacros::expand(TexLexer::lex($tex));
        array_push($notes, ...$macroNotes);
        $this->c = new TexCursor($this->unwrap($tokens), strlen($tex));
        $node = $this->topLevel();
        foreach (array_reverse($notes) as [$level, $element, $reason, $off]) {
            $node = $this->note($level, $element, $reason, $off, $node);
        }
        if ($bad) {
            // Named like the other TeX notes: the input, at the byte where the first bad sequence starts.
            $valid = [];
            preg_match('/^(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})*/', $input, $valid);
            $node = new Fallback($node, new MathNote(NoteLevel::Approximated, 'input', Utf8::NOTE, 'offset ' . (isset($valid[0]) ? strlen($valid[0]) : 0)));
        }

        return new TexResult($node, $this->display);
    }

    /**
     * The tokens without a wrapper around the whole input: `$…$`, `$$…$$`, `\(…\)` or `\[…\]`. `$$` and `\[` ask for display style.
     *
     * @param list<array{string, string, int}> $t
     *
     * @return list<array{string, string, int}>
     */
    private function unwrap(array $t): array
    {
        $a = 0;
        $z = count($t) - 1;
        while ($a <= $z && $t[$a][0] === 'sp') {
            $a++;
        }
        while ($z >= $a && $t[$z][0] === 'sp') {
            $z--;
        }
        if ($z <= $a) {
            return $t;
        }
        $dollar = static fn (int $i): bool => $t[$i][0] === 'ch' && $t[$i][1] === '$';
        $open = null;
        if ($dollar($a) && $dollar($z)) {
            $open = $dollar($a + 1) && $z - $a >= 3 && $dollar($z - 1) ? 2 : 1;
            for ($i = $a + $open; $i <= $z - $open; $i++) {
                if ($dollar($i)) {
                    // `$a$ $b$` is two formulas, not one wrapped.
                    return $t;
                }
            }
            $this->display = $open === 2;
        } elseif ($t[$a][0] === 'cs' && $t[$z][0] === 'cs' && ($t[$a][1] . $t[$z][1] === '()' || $t[$a][1] . $t[$z][1] === '[]')) {
            for ($i = $a + 1; $i < $z; $i++) {
                if ($t[$i][0] === 'cs' && ($t[$i][1] === $t[$a][1] || $t[$i][1] === $t[$z][1])) {
                    // `\[a\] + \[b\]` is two formulas, not one wrapped.
                    return $t;
                }
            }
            $open = 1;
            $this->display = $t[$a][1] === '[';
        }

        return $open === null ? $t : array_slice($t, $a + $open, $z - $a + 1 - 2 * $open);
    }

    /** The whole formula; a `\\` at its top level stacks the rows as `gathered` does. */
    private function topLevel(): Node
    {
        $rows = [];
        $breakAt = null;
        $spacing = null;
        while (true) {
            $rows[] = [self::row($this->parseUntil('nl'))];
            $t = $this->c->peek();
            if ($t === null) {
                break;
            }
            $breakAt ??= $t[2];
            $this->c->p++;
            if ($this->skipOptionalDimension()) {
                $spacing ??= $this->spacingNote($t[2]);
            }
        }
        if ($breakAt === null) {
            return $rows[0][0];
        }
        $lost = $spacing === null ? [] : [$spacing];
        while (count($rows) > 1 && self::blank($rows[array_key_last($rows)][0])) {
            array_push($lost, ...self::lostNotes(array_pop($rows)[0]));
        }
        $node = self::withNotes(count($rows) === 1 ? $rows[0][0] : new Grid($rows, ['center'], 'baseline', true), $lost);

        return $this->note(NoteLevel::Approximated, '\\\\', 'line break outside an environment; rows stacked', $breakAt, $node);
    }

    // ---------------------------------------------------------------- stream

    /** @param list<Node> $nodes */
    private static function row(array $nodes): Node
    {
        return count($nodes) === 1 ? $nodes[0] : new Row($nodes);
    }

    /** A note in the tree: a Fallback around the content (or nothing). A repeat of the same element and reason only gives the content back. */
    /**
     * Wrap $content in a note; the first note of an element and reason is kept. A closure builds the content after the note is
     * claimed, so a note for a command that wraps more source (`\color{red} …`) is the first use, not the last one read.
     *
     * @param Node|\Closure(): Node|null $content
     */
    private function note(NoteLevel $level, string $element, string $reason, int $offset, Node|\Closure|null $content = null): Node
    {
        $key = $element . '|' . $reason;
        $seen = isset($this->noted[$key]);
        $this->noted[$key] = true;
        if ($content instanceof \Closure) {
            $content = $content();
        }
        if ($seen) {
            return $content ?? new Row([]);
        }

        return new Fallback($content, new MathNote($level, $element, $reason, 'offset ' . $this->inputOffset($offset)));
    }

    // ---------------------------------------------------------------- expressions

    /**
     * Atoms with their scripts up to a stop token (not consumed) or the end.
     *
     * @return list<Node>
     */
    private function parseUntil(string ...$stops): array
    {
        $saved = $this->outerStops;
        $this->outerStops = array_values($stops);
        $nodes = $this->parseUntilStops();
        $this->outerStops = $saved;

        return $nodes;
    }

    /** @return list<Node> */
    private function parseUntilStops(): array
    {
        $nodes = [];
        $stops = $this->outerStops;
        while (($t = $this->c->peek()) !== null) {
            if (TexCursor::isStop($t, $stops)) {
                break;
            }
            $ty = $t[0];
            if ($ty === 'sp') {
                $this->c->p++;
            } elseif ($ty === '}') {
                $this->c->p++;
                $nodes[] = $this->note(NoteLevel::Dropped, '}', 'unmatched closing brace', $t[2]);
            } elseif ($ty === '&') {
                $this->c->p++;
                $nodes[] = $this->note(NoteLevel::Dropped, '&', 'alignment tab outside an alignment environment', $t[2]);
            } elseif ($ty === 'nl') {
                $this->c->p++;
                $spaced = $this->skipOptionalDimension();
                $nodes[] = $this->note(NoteLevel::Dropped, '\\\\', 'line break outside an environment', $t[2]);
                if ($spaced && ($m = $this->spacingNote($t[2])) !== null) {
                    $nodes[] = new Fallback(null, $m);
                }
            } elseif ($ty === 'cs' && in_array($t[1], ['over', 'choose', 'atop', 'brace', 'brack'], true)) {
                $this->c->p++;
                $frac = new Fraction(self::row($nodes), self::row($this->parseUntil(...$stops)), $t[1] === 'over');

                return [match ($t[1]) {
                    'choose' => new Fenced('(', ')', $frac),
                    'brace' => new Fenced('{', '}', $frac),
                    'brack' => new Fenced('[', ']', $frac),
                    default => $frac,
                }];
            } elseif ($ty === 'cs' && in_array($t[1], ['displaystyle', 'textstyle', 'scriptstyle', 'scriptscriptstyle'], true)) {
                $this->c->p++;
                $this->display = $this->display || ($t[1] === 'displaystyle' && $this->depth === 0 && $this->envDepth === 0);
                $node = new DisplayStyle(self::row($this->parseUntil(...$stops)), $t[1] === 'displaystyle');
                if ($t[1] !== 'displaystyle' && $t[1] !== 'textstyle') {
                    $node = $this->note(NoteLevel::Approximated, '\\' . $t[1], 'script sizes are not switchable; set in text style', $t[2], $node);
                }
                $nodes[] = $node;

                return $nodes;
            } elseif ($ty === 'cs' && $t[1] === 'color') {
                $this->c->p++;
                $this->c->rawGroup();
                $nodes[] = $this->note(NoteLevel::Approximated, '\\color', 'colour is not drawn', $t[2], fn (): Node => self::row($this->parseUntil(...$stops)));

                return $nodes;
            } elseif ($ty === 'cs' && isset(TexSymbols::FONT_SWITCHES[$t[1]])) {
                $this->c->p++;
                $this->font = TexSymbols::FONT_SWITCHES[$t[1]];
            } else {
                $atom = $this->parseAtom(false);
                if ($this->pending !== null) {
                    $nodes[] = $this->pending;
                    $this->pending = null;
                }
                if ($atom !== null) {
                    $nodes[] = $this->withScripts($atom);
                }
            }
        }

        return $nodes;
    }

    /** `\\[2pt]`: the optional dimension after a row break. */
    private function skipOptionalDimension(): bool
    {
        if (! $this->c->peekIs('ch', '[')) {
            return false;
        }
        $s = '';
        for ($i = $this->c->p + 1; isset($this->c->t[$i]); $i++) {
            if ($this->c->t[$i][0] === 'ch' && $this->c->t[$i][1] === ']') {
                if (preg_match('/^\s*-?[\d.]+\s*[a-z]{2}\s*$/', $s) === 1) {
                    $this->c->p = $i + 1;

                    return true;
                }

                return false;
            }
            if ($this->c->t[$i][0] !== 'ch' && $this->c->t[$i][0] !== 'sp') {
                return false;
            }
            $s .= $this->c->t[$i][1];
        }

        return false;
    }

    /** `\\[4pt]` is read and its spacing ignored: say so, once. */
    private function spacingNote(int $at): ?MathNote
    {
        if (isset($this->noted['\\\\|row spacing is not drawn'])) {
            return null;
        }
        $this->noted['\\\\|row spacing is not drawn'] = true;

        return new MathNote(NoteLevel::Approximated, '\\\\', 'row spacing is not drawn', 'offset ' . $this->inputOffset($at));
    }

    /**
     * The nodes of a `{...}` group; the current token must be `{`.
     *
     * @return list<Node>
     */
    private function groupNodes(): array
    {
        $at = $this->c->offset();
        $from = $this->c->p;
        $this->c->p++;
        if ($this->depth >= $this->maxDepth) {
            $this->c->skipBalanced();

            return [$this->tooDeep('nesting', $from, $at)];
        }
        $this->depth++;
        $font = $this->font;
        $env = $this->envDepth;
        $this->envDepth = 0;
        $inOptional = $this->inOptional;
        $this->inOptional = false;
        $nodes = $this->parseUntil('}');
        $this->inOptional = $inOptional;
        $this->envDepth = $env;
        $this->font = $font;
        $this->depth--;
        if ($this->c->peekIs('}')) {
            $this->c->p++;
        } else {
            $nodes[] = $this->note(NoteLevel::Approximated, '{', 'unclosed brace', $at);
        }

        return $nodes;
    }

    /** The tokens from `$from` to the cursor as one Text of their source, with the one nesting note. */
    private function tooDeep(string $element, int $from, int $at): Node
    {
        $src = '';
        for ($i = $from; $i < $this->c->p; $i++) {
            $t = $this->c->t[$i];
            if ($t[0] === 'cs' && $t[1] === 'begin') {
                $this->skippedBegins++;
            } elseif ($t[0] === 'cs' && $t[1] === 'end' && $this->skippedBegins > 0) {
                $this->skippedBegins--;
            } elseif ($t[0] === 'cs' && $t[1] === 'left') {
                $this->skippedLefts++;
            } elseif ($t[0] === 'cs' && $t[1] === 'right' && $this->skippedLefts > 0) {
                $this->skippedLefts--;
            }
            $src .= match ($t[0]) {
                'cs' => '\\' . $t[1] . (ctype_alpha($t[1]) ? ' ' : ''),
                'nl' => '\\\\',
                'param' => '#' . $t[1],
                default => $t[1],
            };
        }

        return $this->note(NoteLevel::Approximated, $element, 'nested too deeply', $at, new Text(trim($src)));
    }

    /** One argument: a group's content or a single atom (a digit is one digit, as in `\frac12`). */
    private function parseArgument(): Node
    {
        $this->c->skipSp();
        $t = $this->c->peek();
        if ($t === null || $this->endsArguments($t)) {
            return $this->missingArgument();
        }
        if ($t[0] === '{') {
            return self::row($this->groupNodes());
        }
        $this->takeNullDelimiter();
        $a = $this->parseAtom(true);
        if ($a === null && $this->takeNullDelimiter()) {
            // `x^\big.`: a null delimiter is a valid argument, and draws nothing.
            return new Row([]);
        }

        return $a === null ? $this->missingArgument() : $a[0];
    }

    /** Set when the atom just read was a null delimiter (`\big.`): no node, but not a missing argument. */
    private bool $nullDelimiter = false;

    private function takeNullDelimiter(): bool
    {
        $was = $this->nullDelimiter;
        $this->nullDelimiter = false;

        return $was;
    }

    /**
     * Whether nothing is left to be an argument: the end of the input, or a token that closes the group, cell, row or
     * environment the command is in.
     *
     * @param ?array{string, string, int} $t
     */
    private function endsArguments(?array $t): bool
    {
        return $t === null || ($this->inOptional && $t[0] === 'ch' && $t[1] === ']') || in_array($t[0], ['}', '&', 'nl'], true) || ($t[0] === 'cs' && in_array($t[1], ['end', 'right', 'middle'], true));
    }

    /** An empty slot where the command being read wanted an argument: the empty row, with a note naming the command. */
    private function missingArgument(?string $element = null, int $at = 0): Node
    {
        [$element, $at] = $element === null ? ($this->current ?? ['', 0]) : [$element, $at];
        if ($element === '') {
            return new Row([]);
        }

        return $this->note(NoteLevel::Approximated, $element, 'missing argument', $at, new Row([]));
    }

    /** Reading the `[...]` argument of a command: a `]` ends the arguments of what is inside it. */
    private bool $inOptional = false;

    /** @var ?array{string, int} the command whose arguments are being read: its name and offset */
    private ?array $current = null;

    /** The `[...]` argument if one follows, else null. */
    private function parseOptional(): ?Node
    {
        $this->c->skipSp();
        if (! $this->c->peekIs('ch', '[')) {
            return null;
        }
        $this->c->p++;
        $saved = $this->inOptional;
        $this->inOptional = true;
        $nodes = $this->parseUntil(']');
        $this->inOptional = $saved;
        if ($this->c->peekIs('ch', ']')) {
            $this->c->p++;
        }

        return self::row($nodes);
    }

    /** A script argument: a group, or one token. */
    private function scriptArg(string $element, int $at): Node
    {
        $this->c->skipSp();
        $t = $this->c->peek();
        if ($t === null || $t[0] === '^' || $t[0] === '_' || $this->endsArguments($t)) {
            return $this->missingArgument($element, $at);
        }
        $saved = $this->current;
        $this->current = [$element, $at];
        $arg = $this->parseArgument();
        $this->current = $saved;

        return $arg;
    }

    /**
     * @param array{Node, string, mixed} $a
     */
    private function withScripts(array $a): Node
    {
        [$node, $kind, $meta] = $a;
        $limits = null;
        $subs = [];
        $sups = [];
        $primes = 0;
        $doubled = null;
        while (true) {
            $this->c->skipSp();
            $t = $this->c->peek();
            if ($t === null) {
                break;
            }
            if ($t[0] === '^' || $t[0] === '_') {
                $this->c->p++;
                $arg = $this->scriptArg($t[1], $t[2]);
                if ($t[0] === '^') {
                    $doubled = $sups !== [] ? ['^', 'double superscript', $t[2]] : $doubled;
                    $sups[] = $arg;
                } else {
                    $doubled = $subs !== [] ? ['_', 'double subscript', $t[2]] : $doubled;
                    $subs[] = $arg;
                }
            } elseif ($t[0] === 'ch' && $t[1] === "'") {
                $this->c->p++;
                // x'^2 is fine; a prime after a superscript is a second one.
                $doubled = $sups !== [] ? ['^', 'double superscript', $t[2]] : $doubled;
                $primes++;
            } elseif ($t[0] === 'cs' && in_array($t[1], ['limits', 'nolimits', 'displaylimits'], true)) {
                $this->c->p++;
                $limits = $t[1];
            } else {
                break;
            }
        }
        if ($primes > 0) {
            $chars = [1 => '′', 2 => '″', 3 => '‴', 4 => '⁗'];
            array_unshift($sups, new Identifier($chars[$primes] ?? str_repeat('′', $primes), false));
        }
        // Repeated scripts are one flat Row, so a long run of them stays shallow.
        $sub = $subs === [] ? null : self::row($subs);
        $sup = $sups === [] ? null : self::row($sups);
        if ($kind === 'brace') {
            [$inner, $over] = $meta;
            if ($over && $sup !== null) {
                $node = Math::overbrace($inner, $sup);
                $sup = null;
            } elseif (! $over && $sub !== null) {
                $node = Math::underbrace($inner, $sub);
                $sub = null;
            }
        }
        if ($sub !== null || $sup !== null) {
            $stacked = match ($kind) {
                'op', 'funclim' => $limits !== 'nolimits',
                default => $limits === 'limits',
            };
            $node = $stacked ? new UnderOver($node, $sub, $sup) : new Scripts($node, $sub, $sup);
        }
        if ($doubled !== null) {
            $node = $this->note(NoteLevel::Approximated, $doubled[0], $doubled[1], $doubled[2], $node);
        }

        return $node;
    }

    // ---------------------------------------------------------------- atoms

    /**
     * @return ?array{Node, string, mixed}
     */
    private function parseAtom(bool $single): ?array
    {
        $t = $this->c->peek();
        if ($t === null) {
            return [new Row([]), 'ord', null];
        }
        if ($this->depth >= $this->maxDepth) {
            $from = $this->c->p++;
            if ($t[0] === '{') {
                $this->c->skipBalanced();
            }

            return [$this->tooDeep('nesting', $from, $t[2]), 'ord', null];
        }
        switch ($t[0]) {
            case 'sp':
                $this->c->p++;

                return null;
            case '{':
                $node = self::row($this->groupNodes());
                $this->bare->offsetUnset($node);
                // A group holding just an operator is an ordinary atom: {+} has no operator spacing.
                if ($node instanceof Operator && $node->lspace === null && $node->rspace === null) {
                    $node = $this->withOp($node, lspace: 0.0, rspace: 0.0);
                }

                return [$node, 'ord', null];
            case 'ch':
                $this->c->p++;

                return $this->charAtom($t[1], $single);
            case 'param':
                $this->c->p++;

                return [$this->note(NoteLevel::Dropped, '#' . $t[1], 'macro parameter outside a macro', $t[2]), 'ord', null];
            case 'cs':
                $this->c->p++;
                $this->depth++;
                $atom = $this->command($t[1], $t[2], $single);
                $this->depth--;

                return $atom;
            default: // ^ _ } & nl: nothing to attach to, or nothing here
                return [new Row([]), 'ord', null];
        }
    }

    /**
     * @return ?array{Node, string, mixed}
     */
    private function charAtom(string $c, bool $single): ?array
    {
        if ($this->font !== null && preg_match('/^[\p{L}\p{Nd}]$/u', $c) === 1) {
            return [$this->glyphs($c, $single), 'ord', null];
        }
        if (ctype_digit($c)) {
            return [$single ? new Number($c) : $this->number($c), 'ord', null];
        }
        if ($c === '.' && ($this->c->peek()[0] ?? '') === 'ch' && ctype_digit($this->c->peek()[1])) {
            return [$this->number('.'), 'ord', null];
        }
        switch ($c) {
            case '~':
                return [new Space(1 / 3), 'ord', null];
            case "'":
                return [new Identifier('′', false), 'ord', null];
            case '$':
                return null;
        }

        $node = Tokenizer::tokenize($c)[0] ?? null;

        return $node === null ? null : [$node, 'ord', null];
    }

    /** A run of digits (with one decimal point before a digit). TeX scripts the last digit only, so 12^3 leaves 1 behind. */
    private function number(string $first): Node
    {
        $run = $first;
        $dot = $first === '.';
        while (($n = $this->c->peek()) !== null && $n[0] === 'ch') {
            if (ctype_digit($n[1])) {
                $run .= $n[1];
            } elseif ($n[1] === '.' && ! $dot) {
                $run .= '.'; // 1. is a number too, as KaTeX reads it
                $dot = true;
            } else {
                break;
            }
            $this->c->p++;
        }
        if (! str_contains($run, '.') && strlen($run) > 1) {
            $i = $this->c->p;
            while (($this->c->t[$i][0] ?? '') === 'sp') {
                $i++;
            }
            $next = $this->c->t[$i] ?? null;
            if ($next !== null && ($next[0] === '^' || $next[0] === '_' || $next[0] === 'ch' && $next[1] === "'")) {
                $this->pending = new Number(substr($run, 0, -1));

                return new Number(substr($run, -1));
            }
        }

        return new Number($run);
    }

    /** Letters and digits under a font command: one identifier for a run side by side, in the font's variant. */
    private function glyphs(string $first, bool $single): Node
    {
        $chars = $first;
        while (! $single && ($t = $this->c->peek()) !== null) {
            if ($t[0] === 'ch' && preg_match('/^[\p{L}\p{Nd}]$/u', $t[1]) === 1) {
                $chars .= $t[1];
            } elseif ($t[0] === 'cs' && (TexSymbols::GREEK[$t[1]] ?? TexSymbols::GREEK_UP[$t[1]] ?? null) !== null) {
                $chars .= TexSymbols::GREEK[$t[1]] ?? TexSymbols::GREEK_UP[$t[1]];
            } else {
                break;
            }
            $this->c->p++;
        }

        return $this->inFont($chars);
    }

    private function inFont(string $chars): Node
    {
        $font = $this->font;
        if ($font === null) {
            return new Identifier($chars);
        }
        if ($font === 'rm') {
            return new Identifier($chars, false);
        }
        if ($font === 'it' && mb_strlen($chars) > 1) {
            return new Identifier($chars, true);
        }
        $variant = $font === 'it' ? 'italic' : $font;

        return new Identifier(implode('', array_map(static fn (string $ch): string => mb_chr(Styles::variant(mb_ord($ch), $variant)), mb_str_split($chars))), false);
    }

    private function withOp(Operator $o, ?bool $stretchy = null, ?float $lspace = null, ?float $rspace = null): Operator
    {
        return new Operator(
            $o->char,
            stretchy: $stretchy ?? $o->stretchy,
            largeop: $o->largeop,
            movablelimits: $o->movablelimits,
            form: $o->form,
            lspace: $lspace ?? $o->lspace,
            rspace: $rspace ?? $o->rspace,
            minSize: $o->minSize,
            maxSize: $o->maxSize,
        );
    }

    // ---------------------------------------------------------------- commands

    /**
     * @return ?array{Node, string, mixed}
     */
    private function command(string $name, int $at, bool $single = false): ?array
    {
        $saved = $this->current;
        $this->current = ['\\' . $name, $at];
        try {
            return $this->commandBody($name, $at, $single);
        } finally {
            $this->current = $saved;
        }
    }

    /**
     * @return ?array{Node, string, mixed}
     */
    private function commandBody(string $name, int $at, bool $single): ?array
    {
        if ($name === 'prime' && ! $single) {
            // \prime\prime and \prime' are one double prime, as '' is (but not as an argument: x^\prime\prime is two).
            $primes = 1;
            while (true) {
                $p = $this->c->p;
                $this->c->skipSp();
                $next = $this->c->peek();
                if ($next !== null && (($next[0] === 'cs' && $next[1] === 'prime') || ($next[0] === 'ch' && $next[1] === "'"))) {
                    $this->c->p++;
                    $primes++;
                } else {
                    $this->c->p = $p;
                    break;
                }
            }
            $chars = [1 => '′', 2 => '″', 3 => '‴', 4 => '⁗'];

            return [new Identifier($chars[$primes] ?? str_repeat('′', $primes), false), 'ord', null];
        }
        if (in_array($name, ['text', 'mbox', 'hbox', 'textnormal', 'textrm', 'textup', 'textmd', 'textbf', 'textit', 'textsf', 'texttt', 'emph'], true)) {
            $node = $this->textInGroup();
            if (in_array($name, ['textbf', 'textit', 'textsf', 'texttt', 'emph'], true)) {
                $node = $this->note(NoteLevel::Approximated, '\\' . $name, 'text style is not drawn', $at, $node);
            }

            return [$node, 'ord', null];
        }
        if (isset(TexSymbols::GREEK[$name])) {
            return [$this->font === null ? new Identifier(TexSymbols::GREEK[$name]) : $this->glyphs(TexSymbols::GREEK[$name], $single), 'ord', null];
        }
        if (isset(TexSymbols::GREEK_UP[$name])) {
            $char = TexSymbols::GREEK_UP[$name];
            if ($this->font !== null) {
                return [$this->glyphs($char, $single), 'ord', null];
            }

            return [str_starts_with($name, 'var') ? new Identifier($char) : new Identifier($char, false), 'ord', null];
        }
        if (isset(TexSymbols::ORD[$name])) {
            return [Tokenizer::tokenize(TexSymbols::ORD[$name])[0] ?? new Row([]), 'ord', null];
        }
        if (isset(TexSymbols::LARGE[$name])) {
            return [new Operator(TexSymbols::LARGE[$name]), 'op', null];
        }
        if (isset(TexSymbols::INTS[$name])) {
            return [new Operator(TexSymbols::INTS[$name]), 'int', null];
        }
        if (in_array($name, TexSymbols::FUNCS, true)) {
            return [new Identifier($name, false, true), 'func', null];
        }
        if (in_array($name, TexSymbols::FUNCS_LIM, true)) {
            return [new Identifier($name, false, true), 'funclim', null];
        }
        if (isset(TexSymbols::FUNCS_TEXT[$name])) {
            return [new Identifier(TexSymbols::FUNCS_TEXT[$name], false, true), 'funclim', null];
        }
        if (isset(TexSymbols::OPS[$name])) {
            return [new Operator(TexSymbols::OPS[$name]), 'ord', null];
        }
        if (isset(TexSymbols::SPACES[$name])) {
            return [new Space((float) TexSymbols::SPACES[$name]), 'ord', null];
        }
        if (isset(TexSymbols::ACCENTS[$name])) {
            $mark = TexSymbols::ACCENTS[$name];
            $names = TexSymbols::ACCENT_WRITE[$mark];
            // \hat is the short form (not stretchy), \widehat the wide one; a mark with a single spelling is the builder's.
            $stretchy = $names[1] !== null && $names[0] === $name ? false : null;

            return [new UnderOver($this->parseArgument(), null, new Operator($mark, stretchy: $stretchy), true), 'ord', null];
        }
        if (isset(TexSymbols::FONTS[$name])) {
            return [$this->fontArgument($name === 'mathnormal' ? null : TexSymbols::FONTS[$name]), 'ord', null];
        }
        if (preg_match('/^math(rel|bin|op|open|close|punct|ord)$/', $name, $m) === 1) {
            return $this->classed($m[1], $name, $at);
        }

        if (preg_match('/^(big|Big|bigg|Bigg)[lrm]?$/', $name) === 1) {
            $sized = $this->sized($name, $at);

            return $sized === null ? null : [$sized, 'ord', null];
        }
        if (($arrow = TexSymbols::xarrowChar($name)) !== null) {
            $under = $this->parseOptional();
            $this->c->skipSp();
            $missing = $this->c->peek() === null || $this->c->peekIs('}');
            $over = $this->parseArgument();
            $over = self::emptyToNull($over);
            $under = self::emptyToNull($under);
            $node = $over === null && $under === null ? new Operator($arrow) : Math::xarrow($arrow, $over, $under);

            return [$missing ? $this->note(NoteLevel::Approximated, '\\' . $name, 'missing argument', $at, $node) : $node, 'ord', null];
        }

        switch ($name) {
            case 'left':
                return [$this->fence($at), 'ord', null];
            case 'middle':
            case 'right':
                $d = $this->delimiter();
                if ($this->skippedLefts > 0) {
                    // The `\left` was drawn flat past the depth limit: this one belongs to it.
                    $this->skippedLefts -= $name === 'right' ? 1 : 0;

                    return null;
                }

                return [$this->note(NoteLevel::Approximated, '\\' . $name, $name === 'middle' ? '\\middle outside \\left…\\right' : '\\right without \\left', $at, $d === null || $d === '' ? null : new Operator($d)), 'ord', null];
            case 'not':
                return $this->negated($at);
            case 'mod':
                return [new Row([new Space(1.0), new Identifier('mod', false, true), new Space(6 / 18), $this->parseArgument()]), 'ord', null];
            case 'bmod':
                return [new Row([new Space(4 / 18), new Identifier('mod', false, true), new Space(4 / 18)]), 'ord', null];
            case 'pmod':
            case 'pod':
                $arg = $this->parseArgument();
                $mod = $name === 'pmod' ? [new Identifier('mod', false, true), new Space(6 / 18)] : [];

                return [new Row([new Space(1.0), ...Tokenizer::tokenize('('), ...$mod, $arg, ...Tokenizer::tokenize(')')]), 'ord', null];
            case 'frac':
            case 'dfrac':
            case 'tfrac':
            case 'cfrac':
                if ($name === 'cfrac') {
                    $this->parseOptional();
                }
                $f = new Fraction($this->parseArgument(), $this->parseArgument());

                return [match ($name) {
                    'dfrac', 'cfrac' => new DisplayStyle($f, true),
                    'tfrac' => new DisplayStyle($f, false),
                    default => $f,
                }, 'ord', null];
            case 'binom':
            case 'dbinom':
            case 'tbinom':
                $f = new Fenced('(', ')', new Fraction($this->parseArgument(), $this->parseArgument(), false));

                return [$name === 'binom' ? $f : new DisplayStyle($f, $name === 'dbinom'), 'ord', null];
            case 'genfrac':
                return [$this->genfrac($at), 'ord', null];
            case 'sqrt':
                $index = $this->parseOptional();

                return [new Root($this->parseArgument(), $index), 'ord', null];
            case 'overline':
                return [Math::overline($this->parseArgument()), 'ord', null];
            case 'underline':
                return [Math::underline($this->parseArgument()), 'ord', null];
            case 'overbrace':
            case 'underbrace':
                $inner = $this->parseArgument();

                return [$name === 'overbrace' ? Math::overbrace($inner) : Math::underbrace($inner), 'brace', [$inner, $name === 'overbrace']];
            case 'overset':
            case 'stackrel':
            case 'underset':
                return [$this->overUnder($name === 'underset'), 'ord', null];
            case 'operatorname':
            case 'operatorfont':
                $star = $this->c->peekIs('ch', '*');
                if ($star) {
                    $this->c->p++;
                }

                $dropped = [];
                $text = trim($this->operatorName($dropped));
                $node = new Identifier($text, false, true);
                if ($dropped !== []) {
                    $node = $this->note(NoteLevel::Approximated, '\\operatorname', 'only letters and symbols are kept in the name (not ' . implode(', ', array_unique($dropped)) . ')', $at, $node);
                }

                return [$node, $star ? 'funclim' : 'func', null];
            case 'hspace':
            case 'mspace':
            case 'hskip':
            case 'mskip':
            case 'kern':
            case 'mkern':
                if ($this->c->peekIs('ch', '*')) {
                    $this->c->p++;
                }

                return [new Space($this->dimension()), 'ord', null];
            case 'phantom':
                return [new Phantom($this->parseArgument()), 'ord', null];
            case 'hphantom':
            case 'vphantom':
                return [$this->note(NoteLevel::Approximated, '\\' . $name, 'takes both width and height', $at, fn (): Node => new Phantom($this->parseArgument())), 'ord', null];
            case 'char':
                return $this->charCode($at);
            case 'begin':
                return [$this->environment($at), 'ord', null];
            case 'end':
                $env = trim($this->c->rawGroup());
                if ($this->skippedBegins > 0) {
                    $this->skippedBegins--;

                    return null;
                }

                return [$this->note(NoteLevel::Dropped, '\\end', 'without a matching \\begin' . ($env === '' ? '' : ' (' . $env . ')'), $at), 'ord', null];
            case 'substack':
                return [$this->substack($at), 'ord', null];
            case 'hline':
            case 'hdashline':
                return [$this->note(NoteLevel::Dropped, '\\hline', 'row rules are not drawn', $at), 'ord', null];
            case 'tag':
                if ($this->c->peekIs('ch', '*')) {
                    $this->c->p++;
                }
                $this->c->rawGroup();

                return [$this->note(NoteLevel::Dropped, '\\tag', 'equation numbers are not drawn', $at), 'ord', null];
            case 'label':
                $this->c->rawGroup();

                return null;
            case 'nonumber':
            case 'notag':
                return null; // equation numbers are not drawn, so there is nothing to switch off
            case 'color':
                $this->c->rawGroup();

                return [$this->note(NoteLevel::Approximated, '\\color', 'colour is not drawn', $at), 'ord', null];
            case 'textcolor':
                $this->c->rawGroup();

                return [$this->note(NoteLevel::Approximated, '\\textcolor', 'colour is not drawn', $at, fn (): Node => $this->parseArgument()), 'ord', null];
            case 'boxed':
                return [$this->note(NoteLevel::Approximated, '\\boxed', 'the box is not drawn', $at, fn (): Node => $this->parseArgument()), 'ord', null];
            case 'fbox':
                return [$this->note(NoteLevel::Approximated, '\\fbox', 'the box is not drawn', $at, fn (): Node => $this->textInGroup()), 'ord', null];
            case 'cancel':
            case 'bcancel':
            case 'xcancel':
            case 'sout':
                return [$this->note(NoteLevel::Approximated, '\\' . $name, 'the strike-through is not drawn', $at, fn (): Node => $this->parseArgument()), 'ord', null];
            case 'sideset':
            case 'prescript':
            case 'tensor':
            case 'mathchoice':
            case 'newenvironment':
            case 'renewenvironment':
                $count = ['sideset' => 3, 'prescript' => 3, 'tensor' => 2, 'mathchoice' => 4, 'newenvironment' => 3, 'renewenvironment' => 3][$name];
                $args = [];
                for ($i = 0; $i < $count; $i++) {
                    $args[] = $this->parseArgument();
                }

                return [$this->note(NoteLevel::Approximated, '\\' . $name, 'not supported', $at, new Row($args)), 'ord', null];
            case 'limits':
            case 'nolimits':
            case 'displaylimits':
            case 'relax':
            case 'protect':
            case 'allowbreak':
            case '/':
            case '-':
            case '@':
                return null;
        }

        // Control symbols and the rest of the delimiters read as the symbol itself.
        if (in_array($name, ['{', '}', '|', '_', '%', '$', '#', '&'], true)) {
            return [Tokenizer::tokenize($name === '|' ? '‖' : $name)[0] ?? new Row([]), 'ord', null];
        }
        if (isset(TexSymbols::DELIMS[$name]) && $name !== '.') {
            return [new Operator(TexSymbols::DELIMS[$name]), 'ord', null];
        }

        return [$this->note(NoteLevel::Dropped, '\\' . $name, 'unknown command', $at, new Text('\\' . $name)), 'ord', null];
    }

    // ---------------------------------------------------------------- environments

    /**
     * Rows of cells up to `\end` (or the closing brace for `\substack`), neither consumed. The row after a final `\\` is
     * dropped when it is empty, as TeX does.
     *
     * @return array{list<list<Node>>, list<MathNote>} the rows, and the notes of the empty row that was dropped
     */
    private function cells(string $closer): array
    {
        $env = $this->envDepth;
        $this->envDepth = $env + 1;
        $rows = [];
        $row = [];
        $capped = false;
        $capNote = null;
        $spacing = null;
        while (true) {
            $at = $this->c->offset();
            $cell = self::row($this->parseUntil('&', 'nl', $closer));
            if (++$this->cellCount > self::MAX_CELLS) {
                // Past the limit the cells are read and dropped, so a long row is cut as well as the rows after it.
                if (! $capped) {
                    $capped = true;
                    if (! isset($this->noted['cells|too many'])) {
                        $this->noted['cells|too many'] = true;
                        $capNote = new MathNote(NoteLevel::Approximated, 'cells', 'too many cells; the rest are dropped', 'offset ' . $this->inputOffset($at));
                    }
                }
            } else {
                $row[] = $cell;
            }
            $t = $this->c->peek();
            if ($t !== null && $t[0] === '&') {
                $this->c->p++;
                continue;
            }
            if ($t === null || $t[0] !== 'nl') {
                break;
            }
            $this->c->p++;
            if ($this->skipOptionalDimension()) {
                $spacing ??= $this->spacingNote($t[2]);
            }
            if ($row !== []) {
                $rows[] = $row;
            }
            $row = [];
        }
        $this->envDepth = $env;
        $lost = [];
        if ($capNote !== null) {
            $lost[] = $capNote;
        }
        if ($capped) {
            if ($row !== []) {
                $rows[] = $row;
            }
            $rows = $rows === [] ? [[new Row([])]] : $rows;
        } elseif ($rows === [] || count($row) > 1 || ! self::blank($row[0])) {
            $rows[] = $row;
        } else {
            $lost = array_merge($lost, self::lostNotes($row[0]));
        }
        if ($spacing !== null) {
            $lost[] = $spacing;
        }
        // Source order: the spacing of an earlier `\\[len]` and the cap note can come before what the dropped row held.
        usort($lost, static fn (MathNote $a, MathNote $b): int => (int) substr($a->where, 7) <=> (int) substr($b->where, 7));

        return [$rows, $lost];
    }

    /** @return list<MathNote> the notes in a cell that has nothing else in it */
    private static function lostNotes(Node $n): array
    {
        if ($n instanceof Fallback) {
            return [$n->note];
        }
        $out = [];
        foreach ($n instanceof Row ? $n->children : [] as $c) {
            array_push($out, ...self::lostNotes($c));
        }

        return $out;
    }

    /** @param list<MathNote> $notes */
    private static function withNotes(Node $node, array $notes): Node
    {
        foreach ($notes as $note) {
            $node = new Fallback($node, $note);
        }

        return $node;
    }

    /** A cell with nothing in it (rule commands that draw nothing do not count). */
    private static function blank(Node $n): bool
    {
        if ($n instanceof Row) {
            foreach ($n->children as $c) {
                if (! self::blank($c)) {
                    return false;
                }
            }

            return true;
        }

        return $n instanceof Fallback && $n->content === null;
    }

    /** `\begin{name}` … `\end{name}`; the `\begin` is already read. */
    private function environment(int $at): Node
    {
        $this->c->skipSp();
        if (! $this->c->peekIs('{')) {
            return $this->note(NoteLevel::Dropped, '\\begin', 'environment name expected', $at);
        }
        $name = trim($this->c->rawGroup());
        if ($name === '') {
            return $this->note(NoteLevel::Dropped, '\\begin', 'environment name expected', $at);
        }
        $notes = [];
        $align = TexEnvironments::arguments($name, $this->c, $notes);
        [$rows, $lost] = $this->cells('end');
        if ($this->c->peekIs('cs', 'end')) {
            $this->c->p++;
            $endAt = $this->c->offset();
            $end = trim($this->c->rawGroup());
            if (! $this->c->closed) {
                $notes[] = [NoteLevel::Approximated, $name, 'missing } after \\end{' . $end, $endAt];
            } elseif ($end !== $name) {
                $notes[] = [NoteLevel::Approximated, $name, 'closed by \\end{' . $end . '}', $at];
            }
        } else {
            $notes[] = [NoteLevel::Approximated, $name, 'missing \\end{' . $name . '}', $at];
        }
        if (TexEnvironments::isDisplay($name)) {
            $this->display = true;
        }
        $node = TexEnvironments::node($name, $rows, $align, $notes, $at);
        foreach (array_reverse($notes) as [$level, $element, $reason, $off]) {
            $node = $this->note($level, $element, $reason, $off, $node);
        }

        return self::withNotes($node, $lost);
    }

    /** `\substack{a \\ b}`: stacked lines, centered, for under- and overscripts. */
    private function substack(int $at): Node
    {
        $this->c->skipSp();
        if (! $this->c->peekIs('{')) {
            return $this->note(NoteLevel::Approximated, '\\substack', 'missing argument', $at);
        }
        $this->c->p++;
        [$rows, $lost] = $this->cells('}');
        $closed = $this->c->peekIs('}');
        if ($closed) {
            $this->c->p++;
        }
        $node = self::withNotes(new Grid($rows, ['center']), $lost);

        return $closed ? $node : $this->note(NoteLevel::Approximated, '\\substack', 'unclosed brace', $at, $node);
    }

    /** A delimiter after \left, \right, \middle or \big, as its character ('' for `.`); null (nothing consumed) when none follows. */
    private function delimiter(): ?string
    {
        $this->c->skipSp();
        $t = $this->c->peek();
        if ($t === null) {
            return null;
        }
        $d = match ($t[0]) {
            'ch' => TexSymbols::delimiterChar($t[1]),
            'cs' => $t[1] === '|' ? '‖' : TexSymbols::delimiterChar($t[1]),
            default => null,
        };
        if ($d !== null) {
            $this->c->p++;
        }

        return $d;
    }

    /** \left … \middle … \right; the `\left` is already read. */
    private function fence(int $at): Node
    {
        $open = $this->delimiter();
        $stops = ['right', 'middle', '}'];
        if ($this->envDepth > 0) {
            array_push($stops, '&', 'nl', 'end');
        }
        foreach ($this->outerStops as $outer) {
            if (! ctype_alpha($outer) && ! in_array($outer, ['}', '&', 'nl'], true)) {
                $stops[] = $outer;
            }
        }
        $rightAt = $at;
        $parts = [];
        $close = null;
        $closed = false;
        while (true) {
            array_push($parts, ...$this->parseUntil(...$stops));
            $t = $this->c->peek();
            if ($t === null || $t[0] !== 'cs' || $t[1] === 'end') {
                break;
            }
            $this->c->p++;
            if ($t[1] === 'right') {
                $rightAt = $t[2];
                $close = $this->delimiter();
                $closed = true;
                break;
            }
            $mid = $this->delimiter();
            if ($mid !== null && $mid !== '') {
                $parts[] = new Middle($mid);
            }
        }
        $fence = new Fenced($open ?? '', $close ?? '', self::row($parts));
        $notes = [];
        if ($open === null) {
            $notes[] = ['\left', 'delimiter expected after \left', $at];
        }
        if ($closed && $close === null) {
            $notes[] = ['\right', 'delimiter expected after \right', $rightAt];
        }
        if (! $closed) {
            $notes[] = ['\left', 'missing \right', $at];
        }
        foreach ($notes as [$element, $reason, $off]) {
            $fence = $this->note(NoteLevel::Approximated, $element, $reason, $off, $fence);
        }

        return $fence;
    }

    /** \big and its family: a delimiter drawn at a fixed size. */
    private function sized(string $name, int $at): ?Node
    {
        $size = array_search(rtrim($name, 'lrm'), TexSymbols::BIG_COMMANDS, true);
        $d = $this->delimiter();
        if ($d === '') {
            $this->nullDelimiter = true;

            return null; // `\big.`: a null delimiter, draws nothing
        }
        if ($d === null || $size === false) {
            return $this->note(NoteLevel::Dropped, '\\' . $name, 'delimiter expected', $at);
        }

        return Math::big($d, (int) $size + 1);
    }

    /**
     * \not: the negated symbol where one exists, else the next symbol with a combining slash.
     *
     * @return array{Node, string, mixed}
     */
    private function negated(int $at): array
    {
        $this->c->skipSp();
        $t = $this->c->peek();
        if ($t !== null && ($t[0] === 'ch' || $t[0] === 'cs') && isset(TexSymbols::NEGATED[$t[1]])) {
            $this->c->p++;

            return [new Operator(TexSymbols::NEGATED[$t[1]]), 'ord', null];
        }
        $atom = $this->parseAtom(true);
        if ($atom === null) {
            return [new Row([]), 'ord', null];
        }
        $lone = self::lone($atom[0]);
        if ($lone instanceof Operator) {
            return [new Operator($lone->char . "\u{0338}", form: $lone->form, lspace: $lone->lspace, rspace: $lone->rspace), 'ord', null];
        }

        return [$this->note(NoteLevel::Approximated, '\\not', 'only a symbol can be negated', $at, $atom[0]), $atom[1], $atom[2]];
    }

    private static function emptyToNull(?Node $n): ?Node
    {
        return $n === null || ($n instanceof Row && $n->children === []) ? null : $n;
    }

    /**
     * \mathrel{=} and friends: the symbol with TeX's spacing for that atom class.
     *
     * @return array{Node, string, mixed}
     */
    private function classed(string $class, string $name, int $at): array
    {
        $arg = $this->parseArgument();
        $lone = self::lone($arg);
        $char = match (true) {
            $lone instanceof Operator => $lone->char,
            $lone instanceof Identifier => $lone->name,
            $lone instanceof Number => $lone->text,
            default => null,
        };
        if ($char === null) {
            return [$this->note(NoteLevel::Approximated, '\\' . $name, 'the class applies to a single symbol only', $at, $arg), $class === 'op' ? 'op' : 'ord', null];
        }
        [$l, $r] = TexClass::SPACING[$class];
        $spaced = $lone instanceof Operator ? $this->withOp($lone, lspace: (float) $l, rspace: (float) $r) : new Operator($char, lspace: (float) $l, rspace: (float) $r);

        return [$spaced, $class === 'op' && $spaced->isLargeop(OperatorDictionary::FORM_PREFIX) ? 'op' : 'ord', null];
    }

    /** The one node a Row or Fallback wraps, or the node itself. */
    private static function lone(Node $n): Node
    {
        while (true) {
            if ($n instanceof Fallback && $n->content !== null) {
                $n = $n->content;
            } elseif ($n instanceof Row && count($n->children) === 1) {
                $n = $n->children[0];
            } else {
                return $n;
            }
        }
    }

    /** A font command and its argument. */
    private function fontArgument(?string $style): Node
    {
        $this->c->skipSp();
        $save = $this->font;
        $this->font = $style;
        $node = $this->parseArgument();
        $this->font = $save;

        return $node;
    }

    /** \overset{a}{x}, \stackrel{a}{x}, \underset{b}{x}; \underset{b}{\overset{a}{x}} is one node with both parts. */
    private function overUnder(bool $under): Node
    {
        $label = $this->parseArgument();
        $this->c->skipSp();
        $folded = null;
        if ($this->c->peekIs('{')) {
            $inner = $this->groupNodes();
            $only = count($inner) === 1 ? $inner[0] : null;
            if ($under && $only instanceof UnderOver && $only->under === null && $only->over !== null && $this->bare->offsetExists($only)) {
                $folded = $only;
            }
            $base = self::row($inner);
        } else {
            $base = $this->parseArgument();
        }
        if ($folded !== null) {
            return new UnderOver($folded->base, $label, $folded->over);
        }
        // Over an arrow, \overset means a plain arrow: the writer spells a non-stretchy one that way.
        $arrow = UnderOver::operatorOf($base);
        if ($arrow !== null && $arrow->stretchy === null && UnderOver::isArrow($arrow->char) && UnderOver::isStretchy($arrow)) {
            $base = $this->withOp($arrow, stretchy: false);
        }
        $node = $under ? new UnderOver($base, $label, null) : new UnderOver($base, null, $label);
        if (! $under) {
            $this->bare->offsetSet($node, true);
        }

        return $node;
    }

    private function genfrac(int $at): Node
    {
        $unknown = false;
        $l = $this->delimiterArgument($unknown);
        $r = $this->delimiterArgument($unknown);
        $thick = trim($this->c->rawGroup());
        $style = trim($this->c->rawGroup());
        $node = new Fraction($this->parseArgument(), $this->parseArgument(), preg_match('/^0*\.?0*\s*[a-z]*$/', $thick) !== 1 || $thick === '');
        if ($l !== '' || $r !== '') {
            $node = new Fenced($l, $r, $node);
        }
        if ($style !== '' && ctype_digit($style)) {
            $node = new DisplayStyle($node, $style === '0');
        }

        return $unknown ? $this->note(NoteLevel::Approximated, '\\genfrac', 'unrecognised delimiter, drawn without it', $at, $node) : $node;
    }

    /**
     * A delimiter in a group, as its character ('' for `.` or nothing). $unknown is set when something else stood there (a
     * command that is no delimiter), which is left out.
     */
    private function delimiterArgument(bool &$unknown): string
    {
        $this->c->skipSp();
        if (! $this->c->peekIs('{')) {
            $t = $this->c->peek();
            if ($t === null || $t[0] !== 'ch' && $t[0] !== 'cs') {
                return '';
            }
            $this->c->p++;

            return $this->delimiterOf($t, $unknown);
        }
        $this->c->p++;
        $first = null;
        $depth = 1;
        while (($t = $this->c->peek()) !== null) {
            $this->c->p++;
            if ($t[0] === '{') {
                $depth++;
            } elseif ($t[0] === '}' && --$depth === 0) {
                break;
            } elseif ($first === null && $t[0] !== 'sp') {
                $first = $t;
            }
        }
        if ($first === null) {
            return '';
        }

        return $this->delimiterOf($first, $unknown);
    }

    /** @param array{string, string, int} $t */
    private function delimiterOf(array $t, bool &$unknown): string
    {
        if ($t[1] === '.') {
            return '';
        }
        $d = TexSymbols::delimiterChar($t[1]);
        if ($d === null && $t[0] === 'cs') {
            $unknown = true;
        }

        return $d ?? ($t[0] === 'ch' ? $t[1] : '');
    }

    /**
     * The name in \operatorname{...}: its characters, with commands for symbols read as the symbol.
     *
     * @param list<string> $dropped the commands left out of the name (spacing and the like), as written
     */
    private function operatorName(array &$dropped): string
    {
        $this->c->skipSp();
        if (! $this->c->peekIs('{')) {
            $t = $this->c->peek();
            if ($t === null) {
                return '';
            }
            $this->c->p++;

            return $this->nameChar($t, $dropped);
        }
        $this->c->p++;
        $s = '';
        $depth = 1;
        while (($t = $this->c->peek()) !== null) {
            $this->c->p++;
            if ($t[0] === '{') {
                $depth++;
            } elseif ($t[0] === '}' && --$depth === 0) {
                break;
            } else {
                $s .= $this->nameChar($t, $dropped);
            }
        }

        return $s;
    }

    /**
     * @param array{string, string, int} $t
     * @param list<string> $dropped
     */
    private function nameChar(array $t, array &$dropped): string
    {
        if ($t[0] === 'cs') {
            $char = TexSymbols::char($t[1]) ?? (in_array($t[1], ['_', '%', '$', '#', '&', '{', '}'], true) ? $t[1] : '');
            if ($char === '') {
                $dropped[] = '\\' . $t[1];
            }

            return $char;
        }

        return match ($t[0]) {
            'ch' => $t[1],
            'sp' => ' ',
            default => '',
        };
    }

    /**
     * `\char"5E`: the character with that code.
     *
     * @return array{Node, string, mixed}
     */
    private function charCode(int $at): array
    {
        $code = null;
        if ($this->c->peekIs('ch', '"')) {
            $this->c->p++;
            $hex = '';
            while (($t = $this->c->peek()) !== null && $t[0] === 'ch' && ctype_xdigit($t[1])) {
                $hex .= $t[1];
                $this->c->p++;
            }
            $code = $hex === '' ? null : hexdec(substr($hex, 0, 6));
        } else {
            $dec = '';
            while (($t = $this->c->peek()) !== null && $t[0] === 'ch' && ctype_digit($t[1])) {
                $dec .= $t[1];
                $this->c->p++;
            }
            $code = $dec === '' ? null : (int) substr($dec, 0, 7);
        }
        if ($code === null || $code < 33 || $code > 0x10FFFF || ($code >= 0xD800 && $code < 0xE000)) {
            return [$this->note(NoteLevel::Dropped, '\\char', 'character code not drawn', $at), 'ord', null];
        }

        return [Tokenizer::tokenize(mb_chr((int) $code))[0] ?? new Row([]), 'ord', null];
    }

    // ---------------------------------------------------------------- text and dimensions

    /** @param array{string, string, int} $t */
    private static function textToken(array $t): string
    {
        return match ($t[0]) {
            'ch' => $t[1] === '~' ? "\u{00A0}" : $t[1],
            'sp', 'nl' => ' ',
            'cs' => TexSymbols::TEXT_ESC[$t[1]] ?? '',
            '^', '_', '&' => $t[1],
            default => '',
        };
    }

    private function textInGroup(): Node
    {
        $env = $this->envDepth;
        $this->envDepth = 0;
        $node = $this->text();
        $this->envDepth = $env;

        return $node;
    }

    /** The argument of \text: its words as Text, with `$...$` read as math. */
    private function text(): Node
    {
        $this->c->skipSp();
        $t = $this->c->peek();
        if ($t === null) {
            return new Text('');
        }
        /** @var array<string, array{NoteLevel, string, int}> $found what the text could not keep, by command: level, reason, offset */
        $found = [];
        if ($t[0] !== '{') {
            $this->c->p++;
            $row = new Text($this->textPiece($t, $found));

            return $this->noteText($row, $found);
        }
        $this->c->p++;
        $depth = 1;
        $buf = '';
        $nodes = [];
        while (($t = $this->c->peek()) !== null) {
            if ($t[0] === '{') {
                $depth++;
                $this->c->p++;
            } elseif ($t[0] === '}') {
                $this->c->p++;
                if (--$depth === 0) {
                    break;
                }
            } elseif ($t[0] === 'ch' && $t[1] === '$') {
                $this->c->p++;
                if ($buf !== '') {
                    $nodes[] = new Text($buf);
                    $buf = '';
                }
                $inOptional = $this->inOptional;
                $this->inOptional = false;
                $nodes[] = self::row($this->parseUntil('$', '}'));
                $this->inOptional = $inOptional;
                if ($this->c->peekIs('ch', '$')) {
                    $this->c->p++;
                }
            } else {
                $this->c->p++;
                $buf .= $this->textPiece($t, $found);
            }
        }
        if ($buf !== '' || $nodes === []) {
            $nodes[] = new Text($buf);
        }

        return $this->noteText(self::row($nodes), $found);
    }

    /**
     * One token of text as the characters it adds. A command text mode cannot honor adds nothing (an accent, a style: the
     * letters it covers stay) or its own name (unknown), and is recorded in $found.
     *
     * @param array{string, string, int} $t
     * @param array<string, array{NoteLevel, string, int}> $found
     */
    private function textPiece(array $t, array &$found): string
    {
        if ($t[0] !== 'cs' || isset(TexSymbols::TEXT_ESC[$t[1]])) {
            return self::textToken($t);
        }
        $name = $t[1];
        if (in_array($name, ["'", '`', '^', '"', '~', '=', '.', 'u', 'v', 'H', 'r', 'c', 'k', 'd', 'b', 't'], true)) {
            $found['\\' . $name] ??= [NoteLevel::Approximated, 'accents in text are not drawn', $t[2]];

            return '';
        }
        if (in_array($name, ['textbf', 'textit', 'textsf', 'texttt', 'emph'], true)) {
            $found['\\' . $name] ??= [NoteLevel::Approximated, 'text style is not drawn', $t[2]];

            return '';
        }
        if (in_array($name, ['text', 'mbox', 'hbox', 'textnormal', 'textrm', 'textup', 'textmd'], true)) {
            return ''; // the braces that follow are plain text already
        }
        $found['\\' . $name] ??= [NoteLevel::Dropped, 'unknown command', $t[2]];

        return '\\' . $name;
    }

    /** @param array<string, array{NoteLevel, string, int}> $found */
    private function noteText(Node $node, array $found): Node
    {
        foreach (array_reverse($found, true) as $element => [$level, $reason, $at]) {
            $node = $this->note($level, $element, $reason, $at, $node);
        }

        return $node;
    }

    /** A TeX dimension as em: `1em`, `-3mu`, `2pt`, in a group (\hspace) or bare (\kern). */
    private function dimension(): float
    {
        $this->c->skipSp();
        $s = '';
        if ($this->c->peekIs('{')) {
            $this->c->p++;
            while (($t = $this->c->peek()) !== null) {
                $this->c->p++;
                if ($t[0] === '}') {
                    break;
                }
                $s .= $t[1];
            }
        } else {
            while (($t = $this->c->peek()) !== null && ($t[0] === 'ch' || $t[0] === 'sp') && preg_match('/^[-+.\d a-z]$/', $t[1]) === 1) {
                $s .= $t[1];
                $this->c->p++;
                if (preg_match('/[a-z]{2}\s*$/', $s) === 1) {
                    break;
                }
            }
        }
        if (preg_match('/^\s*([-+]?\d*\.?\d+)\s*([a-z]{2})?/', $s, $m) !== 1) {
            return 0.0;
        }
        $v = (float) $m[1];
        $em = match ($m[2] ?? 'em') {
            'ex' => $v * 0.43,
            'mu' => $v / 18,
            'pt' => $v / 10,
            'bp' => $v / 9.6,
            'pc' => $v * 1.2,
            'cm' => $v * 2.845,
            'mm' => $v * 0.2845,
            'in' => $v * 7.227,
            default => $v,
        };

        return max(-Space::MAX_EM, min(Space::MAX_EM, $em));
    }
}
