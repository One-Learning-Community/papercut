<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

use Papercut\Math\Math;
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
use Papercut\Math\OperatorDictionary;
use Papercut\Math\Styles;
use Papercut\Math\Tex\TexClass;
use Papercut\Math\Tex\TexSymbols;

/**
 * Writes a node tree as standard TeX, the kind KaTeX accepts: no package-specific macros, one spelling per construct.
 * A tree with no standard spelling is written as the nearest TeX and the difference is dropped.
 */
final class TexWriter
{
    /** Functions TeX has a command for (\sin, \lim). */
    private const array FUNCTIONS_ALL = ['arccos', 'arcsin', 'arctan', 'arg', 'cos', 'cosh', 'cot', 'coth', 'csc', 'deg', 'dim', 'exp', 'hom', 'ker', 'lg', 'ln', 'log', 'sec', 'sin', 'sinh', 'tan', 'tanh',
        'det', 'gcd', 'inf', 'lim', 'liminf', 'limsup', 'max', 'min', 'Pr', 'sup']; // not sgn: KaTeX has no \sgn, so it is an \operatorname

    /** Text-mode escapes. */
    private const array TEXT_ESCAPES = [
        '\\' => '\textbackslash{}', '{' => '\{', '}' => '\}', '$' => '\$', '&' => '\&', '#' => '\#', '^' => '\textasciicircum{}',
        '_' => '\_', '%' => '\%', '~' => '\textasciitilde{}',
    ];

    /** Characters TeX reads as something else in math, written as a command. */
    private const array MATH_ESCAPES = [
        '\\' => '\backslash', '{' => '\{', '}' => '\}', '$' => '\$', '&' => '\&', '#' => '\#', '^' => '\char"5E',
        '_' => '\_', '%' => '\%', '~' => '\char"7E',
    ];

    public static function write(Node $root): string
    {
        return trim(self::w($root, false));
    }

    private static function w(Node $n, bool $inFence): string
    {
        return match (true) {
            $n instanceof Fallback => $n->content === null ? '' : self::w($n->content, $inFence),
            $n instanceof Row => self::row($n, $inFence),
            $n instanceof Identifier => self::identifier($n),
            // A MathML-only number such as '1,000' is written raw: the nearest TeX.
            $n instanceof Number => self::escapeAll($n->text),
            $n instanceof Text => '\text{' . self::textEscape($n->text) . '}',
            $n instanceof Operator => self::operator($n),
            $n instanceof Middle => $inFence && TexSymbols::delimiter($n->char) !== null ? '\middle' . TexSymbols::delimiter($n->char) : self::symbol($n->char),
            $n instanceof Space => self::space($n->em),
            $n instanceof Phantom => '\phantom{' . self::w($n->content, false) . '}',
            $n instanceof Fraction => $n->bar
                ? '\frac{' . self::w($n->numerator, false) . '}{' . self::w($n->denominator, false) . '}'
                : '{' . self::w($n->numerator, false) . ' \atop ' . self::w($n->denominator, false) . '}',
            $n instanceof Root => self::root($n),
            $n instanceof Scripts => self::scripts($n),
            $n instanceof UnderOver => self::underOver($n)[0],
            $n instanceof Fenced => self::fenced($n),
            $n instanceof Grid => self::gridBody($n, true),
            $n instanceof DisplayStyle => '{' . ($n->display ? '\displaystyle ' : '\textstyle ') . self::w($n->content, false) . '}',
            default => '',
        };
    }

    private static function row(Row $row, bool $inFence): string
    {
        $pieces = [];
        $afterNumber = false;
        foreach ($row->children as $kid) {
            $bare = self::strip($kid);
            if ($bare instanceof Row) {
                $piece = '{' . self::w($bare, false) . '}';
            } else {
                $piece = self::w($kid, $inFence);
            }
            if ($piece === '') {
                continue;
            }
            // Numbers side by side would read as one number, so each after the first is braced.
            $isNumber = $bare instanceof Number;
            $pieces[] = $isNumber && $afterNumber ? '{' . $piece . '}' : $piece;
            $afterNumber = $isNumber;
        }

        return implode(' ', $pieces);
    }

    /** The node with Fallback wrappers peeled. */
    private static function strip(Node $n): Node
    {
        while ($n instanceof Fallback && $n->content !== null) {
            $n = $n->content;
        }

        return $n;
    }

    /** The node with Fallback wrappers and one-child Rows peeled. */
    private static function peel(Node $n): Node
    {
        while (true) {
            $n = self::strip($n);
            if ($n instanceof Row && count($n->children) === 1) {
                $n = $n->children[0];
            } else {
                return $n;
            }
        }
    }

    // ---- leaves ----

    /** Digits and punctuation as TeX reads them; anything TeX would read as markup is escaped. */
    private static function escapeAll(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text) as $ch) {
            $out .= self::MATH_ESCAPES[$ch] ?? $ch;
            if (isset(self::MATH_ESCAPES[$ch]) && ctype_alpha(substr(self::MATH_ESCAPES[$ch], -1))) {
                $out .= ' ';
            }
        }

        return rtrim($out);
    }

    private static function textEscape(string $text): string
    {
        $out = '';
        foreach (mb_str_split($text) as $ch) {
            $out .= self::TEXT_ESCAPES[$ch] ?? $ch;
        }

        return $out;
    }

    /** One symbol: its command, or the character (escaped where TeX needs it). */
    private static function symbol(string $char): string
    {
        if ($char === '−') {
            return '-';
        }
        // What \not builds for a symbol with no negated form: the symbol and a combining slash.
        if (mb_strlen($char) === 2 && str_ends_with($char, "\u{0338}")) {
            return '\not' . self::symbol(mb_substr($char, 0, 1));
        }
        $cmd = TexSymbols::command($char);
        if ($cmd !== null) {
            return $cmd;
        }
        if (isset(self::MULTI_PRIMES[$char])) {
            return str_repeat('\prime', self::MULTI_PRIMES[$char]);
        }
        if (($negated = TexSymbols::negation($char)) !== null) {
            return '\not' . $negated;
        }

        return mb_strlen($char) > 1 ? self::functionName($char) : (self::MATH_ESCAPES[$char] ?? $char);
    }

    /** A multi-letter name: a standard function's command, otherwise \operatorname. */
    private static function functionName(string $name): string
    {
        if (in_array($name, self::FUNCTIONS_ALL, true)) {
            return '\\' . $name;
        }
        $inner = [];
        foreach (mb_str_split($name) as $ch) {
            $inner[] = TexSymbols::command($ch) ?? (self::MATH_ESCAPES[$ch] ?? $ch);
        }

        return '\operatorname{' . self::cat($inner) . '}';
    }

    /**
     * Pieces run together, with a space where a command would swallow the letter after it.
     *
     * @param list<string> $pieces
     */
    private static function cat(array $pieces): string
    {
        $out = '';
        foreach ($pieces as $p) {
            if ($out !== '' && (preg_match('/\\\\[a-zA-Z]+$/', $out) === 1 && preg_match('/^[a-zA-Z]/', $p) === 1 || preg_match('/\\\\char"[0-9A-F]+$/', $out) === 1 && preg_match('/^[0-9A-Fa-f]/', $p) === 1)) {
                $out .= ' ';
            }
            $out .= $p;
        }

        return $out;
    }

    private static function identifier(Identifier $i): string
    {
        $chars = mb_str_split($i->name);
        if ($chars === []) {
            return '';
        }
        if (! $i->upright) {
            if (count($chars) > 1) {
                return '\mathit{' . self::cat(array_map(self::letter(...), $chars)) . '}';
            }

            return self::italicSingle($chars[0]);
        }
        if (count($chars) === 1) {
            return self::uprightSingle($chars[0]);
        }
        if (in_array($i->name, self::FUNCTIONS_ALL, true)) {
            return '\\' . $i->name;
        }
        // Runs of one math variant (or none) side by side.
        $runs = [];
        foreach ($chars as $ch) {
            $u = TexSymbols::unstyle($ch);
            $variant = $u[0] ?? null;
            $plain = $u[1] ?? $ch;
            $last = array_key_last($runs);
            if ($last !== null && $runs[$last][0] === $variant) {
                $runs[$last][1][] = $plain;
            } else {
                $runs[] = [$variant, [$plain]];
            }
        }
        $out = [];
        foreach ($runs as [$variant, $run]) {
            if ($variant !== null) {
                $out[] = '\\' . TexSymbols::VARIANT_WRITE[$variant] . '{' . self::cat(array_map(self::letter(...), $run)) . '}';
            } elseif (count($run) === 1) {
                $out[] = self::uprightSingle($run[0]);
            } else {
                $out[] = self::functionName(implode('', $run));
            }
        }

        return implode(' ', $out);
    }

    /** A character inside a font group: its command if it has one, else itself. */
    private static function letter(string $ch): string
    {
        return TexSymbols::command($ch) ?? (self::MATH_ESCAPES[$ch] ?? $ch);
    }

    private static function italicSingle(string $ch): string
    {
        $u = TexSymbols::unstyle($ch);
        $cmd = TexSymbols::command($ch);
        // \Re and \Im are the fraktur capitals of Unicode's gaps: they have commands of their own, which read back as the same symbol.
        if ($u !== null && $cmd === null) {
            return '\\' . TexSymbols::VARIANT_WRITE[$u[0]] . '{' . self::letter($u[1]) . '}';
        }
        if ($cmd !== null && preg_match('/^[\x{391}-\x{3A9}]$/u', $ch) === 1) {
            return '\var' . substr($cmd, 1); // an italic capital Greek letter: \varGamma
        }

        return $cmd ?? (self::MATH_ESCAPES[$ch] ?? $ch);
    }

    private static function uprightSingle(string $ch): string
    {
        $u = TexSymbols::unstyle($ch);
        if ($u !== null) {
            return '\\' . TexSymbols::VARIANT_WRITE[$u[0]] . '{' . self::letter($u[1]) . '}';
        }
        $cmd = TexSymbols::command($ch);
        if ($cmd === null && isset(self::MULTI_PRIMES[$ch])) {
            return str_repeat('\prime', self::MULTI_PRIMES[$ch]);
        }
        if ($cmd === null && ($negated = TexSymbols::negation($ch)) !== null) {
            return '\not' . $negated;
        }
        $letterLike = preg_match('/^[\p{L}\p{N}]$/u', $ch) === 1;
        if ($cmd !== null && (! $letterLike || self::isOrdinaryCommand($ch) || preg_match('/^[\x{391}-\x{3A9}]$/u', $ch) === 1)) {
            return $cmd; // \infty, \Gamma: upright by themselves
        }
        if ($letterLike) {
            // Nearest TeX for upright lowercase Greek: \mathrm{\alpha} (there is no \upalpha).
            return '\mathrm{' . ($cmd ?? $ch) . '}';
        }

        return self::MATH_ESCAPES[$ch] ?? $ch;
    }

    /** A letter-like character TeX sets upright already (\ell, \hbar, \imath ...). */
    private static function isOrdinaryCommand(string $ch): bool
    {
        return in_array($ch, TexSymbols::ORD, true);
    }

    private static function space(float $em): string
    {
        foreach ([[3 / 18, '\,'], [4 / 18, '\:'], [5 / 18, '\;'], [-3 / 18, '\!'], [1.0, '\quad'], [2.0, '\qquad']] as [$v, $cmd]) {
            if (abs($em - $v) < 1e-9) {
                return $cmd;
            }
        }
        $num = rtrim(rtrim(number_format($em, 6, '.', ''), '0'), '.');

        return '\hspace{' . ($num === '' || $num === '-0' ? '0' : $num) . 'em}';
    }

    // ---- operators ----

    private static function operator(Operator $o): string
    {
        // A sized glyph with no \big spelling (a corner) is written plain: the size is dropped, as the nearest TeX.
        $size = $o->minSize ?? $o->maxSize;
        if ($size !== null) {
            $delim = TexSymbols::delimiter($o->char);
            if ($delim !== null && $delim !== '.') {
                $best = 0;
                foreach (Math::BIG_SIZES as $k => $s) {
                    if (abs($s - $size) < abs(Math::BIG_SIZES[$best] - $size)) {
                        $best = $k;
                    }
                }
                $side = OperatorDictionary::isOpening($o->char) ? 'l' : (OperatorDictionary::isClosing($o->char) ? 'r' : '');

                return '\\' . TexSymbols::BIG_COMMANDS[$best] . $side . $delim;
            }
        }
        $sym = self::symbol($o->char);
        // Only explicit spacing becomes an atom class. A form alone is MathML-only and is dropped.
        $class = $o->lspace !== null && $o->rspace !== null ? self::classOf($o->lspace, $o->rspace, $o->char) : null;

        return $class === null ? $sym : '\\math' . $class . '{' . $sym . '}';
    }

    /** The TeX atom class that sets this much space before and after, or null when none does. */
    private static function classOf(float|int $l, float|int $r, string $char): ?string
    {
        foreach (TexClass::SPACING as $name => [$cl, $cr]) {
            if (abs($cl - $l) > 1e-6 || abs($cr - $r) > 1e-6) {
                continue;
            }
            if ($name === 'open' || $name === 'close' || $name === 'ord') {
                // 0/0 is shared: fences are open or close, anything else ordinary.
                return OperatorDictionary::isOpening($char) ? 'open' : (OperatorDictionary::isClosing($char) ? 'close' : 'ord');
            }

            return $name;
        }

        return null;
    }

    // ---- structures ----

    private static function root(Root $r): string
    {
        $body = '{' . self::w($r->radicand, false) . '}';

        return $r->index === null ? '\sqrt' . $body : '\sqrt[' . self::braceIf(self::w($r->index, false), ']') . ']' . $body;
    }

    private static function braceIf(string $s, string $needle): string
    {
        return str_contains($s, $needle) ? '{' . $s . '}' : $s;
    }

    /** A script argument: bare when it is one token, braced otherwise. */
    private static function arg(Node $n): string
    {
        $s = self::w($n, false);

        return preg_match('/^(\\\\[a-zA-Z]+|\\\\[^a-zA-Z]|[^\\\\\s{}])$/u', $s) === 1 ? $s : '{' . $s . '}';
    }

    private static function scriptParts(?Node $sub, ?Node $sup): string
    {
        return ($sub === null ? '' : '_' . self::arg($sub)) . ($sup === null ? '' : '^' . self::arg($sup));
    }

    /** A script base: braced unless it is a single atom. */
    private static function base(Node $n): string
    {
        $s = self::w($n, false);
        $p = self::peel($n);
        $atom = match (true) {
            $p instanceof Identifier, $p instanceof Operator, $p instanceof Fraction, $p instanceof Root, $p instanceof Text => true,
            // A fence is one atom only when written \left..\right (or as a matrix); a plain one is several.
            $p instanceof Fenced => TexSymbols::delimiter($p->open) !== null && TexSymbols::delimiter($p->close) !== null,
            $p instanceof Number => mb_strlen($p->text) === 1,
            $p instanceof UnderOver => self::underOver($p)[1],
            default => false,
        };
        // A mixed-variant identifier is several groups side by side: not one atom.
        if ($atom && ! $p instanceof Fenced && self::hasTopLevelSpace($s)) {
            $atom = false;
        }

        return $atom && $s !== '' ? $s : '{' . $s . '}';
    }

    private static function hasTopLevelSpace(string $s): bool
    {
        $depth = 0;
        $clean = preg_replace('/\\\\[{}]/', '', $s) ?? $s;
        foreach (str_split($clean) as $c) {
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
            } elseif ($c === ' ' && $depth === 0) {
                return true;
            }
        }

        return false;
    }

    private static function scripts(Scripts $s): string
    {
        $op = UnderOver::operatorOf($s->base);
        $nolimits = UnderOver::isLimitName($s->base) || ($op !== null && $op->isLargeop(OperatorDictionary::FORM_PREFIX) && $op->hasMovableLimits(OperatorDictionary::FORM_PREFIX));

        // Primes are written as apostrophes: KaTeX and LaTeX have no raw ″ ‴ ⁗.
        $sup = $s->sup;
        $primes = '';
        if ($sup !== null && ! $nolimits) {
            $parts = $sup instanceof Row ? $sup->children : [$sup];
            $first = $parts[0] ?? null;
            if ($first instanceof Identifier && isset(self::MULTI_PRIMES[$first->name])) {
                $primes = str_repeat("'", self::MULTI_PRIMES[$first->name]);
                $rest = array_slice($parts, 1);
                $sup = $rest === [] ? null : (count($rest) === 1 ? $rest[0] : new Row($rest));
            }
        }

        if ($primes !== '' && $sup !== null) {
            // KaTeX takes a superscript after primes only straight after them: f'^2_a, not f'_a^2.
            return self::base($s->base) . $primes . '^' . self::arg($sup) . self::scriptParts($s->sub, null);
        }

        return self::base($s->base) . $primes . ($nolimits ? '\nolimits' : '') . self::scriptParts($s->sub, $sup);
    }

    /** Prime characters TeX has no symbol for, and how many apostrophes make them. */
    private const array MULTI_PRIMES = ['″' => 2, '‴' => 3, '⁗' => 4];

    /**
     * @return array{string, bool} the TeX, and whether it is a single atom (no braces needed before a script)
     */
    private static function underOver(UnderOver $u): array
    {
        $base = $u->base;
        $under = $u->under;
        $over = $u->over;
        $op = UnderOver::operatorOf($base);
        $plainFlags = ! $u->accent && ! $u->accentUnder;

        if ($plainFlags && (UnderOver::isLimitName($base) || ($op !== null && ($op->isLargeop(OperatorDictionary::FORM_PREFIX) || $op->hasMovableLimits(OperatorDictionary::FORM_PREFIX))))) {
            $limits = $op !== null && ! UnderOver::isLimitName($base) && ! $op->hasMovableLimits(OperatorDictionary::FORM_PREFIX) ? '\limits' : '';

            return [self::w($base, false) . $limits . self::scriptParts($under, $over), false];
        }

        $overOp = UnderOver::operatorOf($over);
        $underOp = UnderOver::operatorOf($under);

        // \overbrace{x}^{label}, \underbrace{x}_{label}
        if ($under === null && $over !== null && ($inner = self::peel($base)) instanceof UnderOver && $inner->under === null && UnderOver::isBrace(UnderOver::operatorOf($inner->over), true)) {
            return ['\overbrace{' . self::w($inner->base, false) . '}^' . self::arg($over), false];
        }
        if ($over === null && $under !== null && ($inner = self::peel($base)) instanceof UnderOver && $inner->over === null && UnderOver::isBrace(UnderOver::operatorOf($inner->under), false)) {
            return ['\underbrace{' . self::w($inner->base, false) . '}_' . self::arg($under), false];
        }
        if ($under === null && $overOp !== null && UnderOver::isBrace($overOp, true)) {
            return ['\overbrace{' . self::w($base, false) . '}', false];
        }
        if ($over === null && $underOp !== null && UnderOver::isBrace($underOp, false)) {
            return ['\underbrace{' . self::w($base, false) . '}', false];
        }
        // Rules.
        if ($under === null && $overOp !== null && in_array($overOp->char, ["\u{00AF}", "\u{203E}"], true)) {
            return ['\overline{' . self::w($base, false) . '}', true];
        }
        if ($over === null && $underOp !== null && in_array($underOp->char, ['_', "\u{00AF}", "\u{203E}"], true)) {
            return ['\underline{' . self::w($base, false) . '}', true];
        }
        // Accents.
        if ($u->accent && $under === null && $overOp !== null) {
            $mark = Styles::accentMark($overOp->char) ?? $overOp->char;
            $names = TexSymbols::ACCENT_WRITE[$mark] ?? null;
            if ($names !== null) {
                $wide = $overOp->stretchy !== false;
                $name = $wide ? ($names[1] ?? $names[0]) : ($names[0] ?? $names[1]);

                return ['\\' . $name . '{' . self::w($base, false) . '}', true];
            }
        }
        // Extensible arrows.
        if ($plainFlags && $op !== null && $op->stretchy !== false && mb_strlen($op->char) === 1 && isset(TexSymbols::XARROWS[$op->char]) && UnderOver::isArrow($op->char)) {
            $label = '';
            if ($under !== null) {
                $t = self::w($under, false);
                $label = '[' . (str_contains($t, ']') ? '{' . $t . '}' : $t) . ']';
            }

            return ['\\' . TexSymbols::XARROWS[$op->char] . $label . '{' . ($over === null ? '' : self::w($over, false)) . '}', true];
        }
        // Anything else.
        $s = self::w($base, false);
        if (self::peel($base) instanceof UnderOver) {
            $s = '{' . $s . '}'; // a nested pair, not one node with both parts
        }
        if ($over !== null) {
            $s = '\overset{' . self::w($over, false) . '}{' . $s . '}';
        }
        if ($under !== null) {
            $s = '\underset{' . self::w($under, false) . '}{' . $s . '}';
        }

        return [$s, true];
    }

    private static function fenced(Fenced $f): string
    {
        $inner = self::peel($f->content);
        if ($f->open === '(' && $f->close === ')' && $inner instanceof Fraction && ! $inner->bar) {
            return '\binom{' . self::w($inner->numerator, false) . '}{' . self::w($inner->denominator, false) . '}';
        }
        if ($inner instanceof Grid && ! $inner->alignment) {
            $env = self::matrixEnvironment($f, $inner);
            if ($env !== null) {
                return self::environment($inner, $env);
            }
        }
        $open = TexSymbols::delimiter($f->open);
        $close = TexSymbols::delimiter($f->close);
        // \middle only works between \left and \right: in a plain fence the middles are plain symbols too.
        $body = $inner instanceof Grid ? self::gridBody($inner, false) : self::w($f->content, $open !== null && $close !== null);
        if ($open === null || $close === null) {
            // A fence glyph with no \left spelling (a corner): plain symbols around the content, so the glyphs stay.
            return implode(' ', array_filter([self::symbol($f->open), $body, self::symbol($f->close)], static fn (string $p): bool => $p !== ''));
        }

        return '\left' . $open . ($body === '' ? ' ' : ' ' . $body . ' ') . '\right' . $close;
    }

    private static function matrixEnvironment(Fenced $f, Grid $g): ?string
    {
        $all = static fn (string $a): bool => count(array_unique($g->columnAlign)) <= 1 && ($g->columnAlign[0] ?? $a) === $a;
        if ($all('center')) {
            return match ([$f->open, $f->close]) {
                ['(', ')'] => 'pmatrix',
                ['[', ']'] => 'bmatrix',
                ['{', '}'] => 'Bmatrix',
                ['|', '|'] => 'vmatrix',
                ["\u{2016}", "\u{2016}"] => 'Vmatrix',
                default => null,
            };
        }
        if ($f->open === '{' && $f->close === '' && $all('left')) {
            return $g->display ? 'dcases' : 'cases';
        }

        return null;
    }

    /** A grid on its own (or inside a \left \right pair): a matrix when centred, aligned when an alignment, otherwise an array. */
    private static function gridBody(Grid $g, bool $bare): string
    {
        if ($g->alignment) {
            return self::environment($g, 'aligned');
        }
        // gathered has one column (amsmath rejects an & in it), so a wider display grid is a darray.
        if ($bare && ($g->columns === 0 || count(array_unique($g->columnAlign)) === 1 && $g->columnAlign[0] === 'center') && (! $g->display || $g->columns <= 1)) {
            return self::environment($g, $g->display ? 'gathered' : 'matrix');
        }
        $spec = implode('', array_map(static fn (string $a): string => $a[0], $g->columnAlign));

        return self::environment($g, $g->display ? 'darray' : 'array', '{' . $spec . '}');
    }

    private static function environment(Grid $g, string $env, string $args = ''): string
    {
        $rows = [];
        foreach ($g->rows as $row) {
            $cells = [];
            foreach ($row as $j => $cell) {
                $cells[] = trim(self::w($cell, false));
            }
            while (count($cells) > 1 && end($cells) === '') {
                array_pop($cells);
            }
            $line = '';
            foreach ($cells as $j => $c) {
                if ($j > 0) {
                    $line .= ($line === '' ? '' : ' ') . '&';
                }
                if ($c !== '') {
                    $line .= ($j > 0 && ! ($g->alignment && $j % 2 === 1) ? ' ' : '') . $c;
                }
            }
            $rows[] = trim($line);
        }

        return '\begin{' . $env . '}' . $args . implode(' \\\\ ', $rows) . '\end{' . $env . '}';
    }
}
