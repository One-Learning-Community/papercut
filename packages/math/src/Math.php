<?php

declare(strict_types=1);

namespace Quoin\Math;

use Quoin\Math\Font\Font;
use Quoin\Math\Layout\Box;
use Quoin\Math\Layout\Context;
use Quoin\Math\Mathml\MathmlReader;
use Quoin\Math\Node\DisplayStyle;
use Quoin\Math\Node\Fallback;
use Quoin\Math\Node\Fenced;
use Quoin\Math\Node\Fraction;
use Quoin\Math\Node\Grid;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Middle;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Phantom;
use Quoin\Math\Node\Root;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Node\Space;
use Quoin\Math\Node\Text;
use Quoin\Math\Node\UnderOver;
use Quoin\Math\Tex\TexReader;

/**
 * Builder API. Wherever a Node is expected a plain string works too and is tokenized
 * (a string that yields several tokens becomes a Row).
 */
final class Math
{
    /** The bundled STIX Two Math, opened once per process. */
    private static ?Font $stix = null;

    /** Path of the bundled STIX Two Math font (SIL OFL 1.1, see resources/fonts/OFL.txt). */
    public static function stixTwoMathPath(): string
    {
        return __DIR__ . '/../resources/fonts/STIXTwoMath-Regular.ttf';
    }

    public static function stixTwoMath(): Font
    {
        return self::$stix ??= Font::fromFile(self::stixTwoMathPath());
    }

    /** Total heights in em of the four fixed delimiter sizes (TeX's \big to \Bigg). */
    public const BIG_SIZES = [1.2, 1.8, 2.4, 3.0];

    /** A delimiter drawn at a fixed size, 1 (big) to 4 (Bigg), centered on the math axis. */
    public static function big(string $delimiter, int $size): Operator
    {
        if ($size < 1 || $size > 4) {
            throw new \InvalidArgumentException('Delimiter size must be 1 to 4, got ' . $size);
        }
        $em = self::BIG_SIZES[$size - 1];

        return new Operator($delimiter, minSize: $em, maxSize: $em);
    }

    public static function row(string|Node ...$items): Node
    {
        $nodes = [];
        $bad = false;
        foreach ($items as $it) {
            if (is_string($it)) {
                Tokenizer::tokenizeInto($nodes, self::scrub($it, $bad));
            } else {
                $nodes[] = $it;
            }
        }

        return self::flagged(count($nodes) === 1 ? $nodes[0] : new Row($nodes), $bad);
    }

    /** Twin of Quoin\Support\Utf8::scrub() in quoin/core (see {@see Utf8}). */
    private static function scrub(string $text, bool &$bad): string
    {
        return Utf8::scrub($text, $bad);
    }

    /** $node, carrying a note that text in it was not valid UTF-8 when $bad. */
    private static function flagged(Node $node, bool $bad): Node
    {
        return $bad ? new Fallback($node, new MathNote(NoteLevel::Approximated, 'text', Utf8::NOTE)) : $node;
    }

    public static function frac(string|Node $num, string|Node $den): Fraction
    {
        return new Fraction(self::row($num), self::row($den));
    }

    /** A fraction without a rule (MathML linethickness="0"). */
    public static function stack(string|Node $top, string|Node $bottom): Fraction
    {
        return new Fraction(self::row($top), self::row($bottom), false);
    }

    /** A binomial coefficient: a barless fraction in parentheses. */
    public static function binom(string|Node $top, string|Node $bottom): Fenced
    {
        return new Fenced('(', ')', self::stack($top, $bottom));
    }

    /** Horizontal space of $em times the font size (clamped to +-100em); negative pulls the next item back. */
    public static function space(float $em): Space
    {
        if (! is_finite($em)) {
            throw new \InvalidArgumentException('Math::space() needs a finite number of em, got ' . var_export($em, true));
        }

        return new Space(max(-Space::MAX_EM, min(Space::MAX_EM, $em)));
    }

    /** Takes the size of its content and draws nothing. */
    public static function phantom(string|Node ...$content): Phantom
    {
        return new Phantom(self::row(...$content));
    }

    /**
     * Text drawn in a MathML mathvariant (double-struck, bold, script, ...) as an upright identifier of the mapped code points.
     *
     * @throws \InvalidArgumentException for a variant outside Styles::VARIANTS
     */
    public static function styled(string $text, string $variant): Node
    {
        $bad = false;
        $text = self::scrub($text, $bad);
        if ($text === '') {
            throw new \InvalidArgumentException('Styled text is empty');
        }
        if (! in_array($variant, Styles::VARIANTS, true)) {
            throw new \InvalidArgumentException('Unknown math variant "' . $variant . '"');
        }

        return self::flagged(new Identifier(implode('', array_map(static fn (string $ch): string => mb_chr(Styles::variant(mb_ord($ch), $variant)), mb_str_split($text))), false), $bad);
    }

    /** A formula read from MathML (a string, or a `math` element of a parsed document). */
    public static function mathml(string|\Dom\Element $input): Node
    {
        return MathmlReader::read($input)->node;
    }

    /** A formula read from TeX (math mode, as KaTeX reads it). Never throws: what it cannot draw carries a note, see {@see TexReader::read()}. */
    public static function tex(string $tex): Node
    {
        return TexReader::read($tex)->node;
    }

    public static function sqrt(string|Node ...$content): Root
    {
        return new Root(self::row(...$content));
    }

    public static function root(string|Node $radicand, string|Node $index): Root
    {
        return new Root(self::row($radicand), self::row($index));
    }

    public static function sup(string|Node $base, string|Node $sup): Scripts
    {
        return new Scripts(self::row($base), null, self::row($sup));
    }

    public static function sub(string|Node $base, string|Node $sub): Scripts
    {
        return new Scripts(self::row($base), self::row($sub), null);
    }

    public static function subSup(string|Node $base, string|Node $sub, string|Node $sup): Scripts
    {
        return new Scripts(self::row($base), self::row($sub), self::row($sup));
    }

    /** A part below the base (munder). */
    public static function under(string|Node $base, string|Node $under): UnderOver
    {
        return new UnderOver(self::row($base), self::row($under), null);
    }

    /** A part above the base (mover). */
    public static function over(string|Node $base, string|Node $over): UnderOver
    {
        return new UnderOver(self::row($base), null, self::row($over));
    }

    public static function underOver(string|Node $base, string|Node $under, string|Node $over): UnderOver
    {
        return new UnderOver(self::row($base), self::row($under), self::row($over));
    }

    /**
     * A large operator with limits above and below it in display style (beside it as scripts in inline style when its
     * limits are movable). Without limits it is the operator alone.
     */
    public static function bigOp(string $op, string|Node|null $under = null, string|Node|null $over = null): Node
    {
        $bad = false;
        $o = new Operator(self::scrub($op, $bad));
        if ($under === null && $over === null) {
            return self::flagged($o, $bad);
        }

        return self::flagged(new UnderOver($o, $under === null ? null : self::row($under), $over === null ? null : self::row($over)), $bad);
    }

    public static function sum(string|Node|null $under = null, string|Node|null $over = null): Node
    {
        return self::bigOp('∑', $under, $over);
    }

    public static function product(string|Node|null $under = null, string|Node|null $over = null): Node
    {
        return self::bigOp('∏', $under, $over);
    }

    /** An integral: its limits stay beside it as scripts, in display style too. */
    public static function integral(string|Node|null $under = null, string|Node|null $over = null): Node
    {
        $o = new Operator('∫');
        if ($under === null && $over === null) {
            return $o;
        }

        return new Scripts($o, $under === null ? null : self::row($under), $over === null ? null : self::row($over));
    }

    /** An accent mark (a combining character such as U+0302) above the base. */
    public static function accent(string|Node $base, string $mark): UnderOver
    {
        $bad = false;
        $mark = self::scrub($mark, $bad);

        return new UnderOver(self::flagged(self::row($base), $bad), null, new Operator($mark), true);
    }

    public static function hat(string|Node $base): UnderOver
    {
        return self::accent($base, "\u{0302}");
    }

    public static function tilde(string|Node $base): UnderOver
    {
        return self::accent($base, "\u{0303}");
    }

    public static function vec(string|Node $base): UnderOver
    {
        return self::accent($base, "\u{20D7}");
    }

    public static function dot(string|Node $base): UnderOver
    {
        return self::accent($base, "\u{0307}");
    }

    /** A bar over the base, drawn as a rule as wide as the base. */
    public static function overline(string|Node $base): UnderOver
    {
        return new UnderOver(self::row($base), null, new Operator("\u{00AF}"), true);
    }

    /** A bar under the base, drawn as a rule as wide as the base. */
    public static function underline(string|Node $base): UnderOver
    {
        return new UnderOver(self::row($base), new Operator('_'), null, false, true);
    }

    /** A brace over the base, stretched to its width, with an optional label above the brace. */
    public static function overbrace(string|Node $base, string|Node|null $label = null): UnderOver
    {
        $braced = new UnderOver(self::row($base), null, new Operator("\u{23DE}"));

        return $label === null ? $braced : new UnderOver($braced, null, self::row($label));
    }

    /** An extensible arrow with optional labels above and below (TeX's \xrightarrow): it reaches past its labels and has a minimum length.
     * An arrow the font has no horizontal construction for (such as ⟶ or ⥂ in STIX Two Math) is drawn at its natural width, with no pad or minimum.
     * A stretchy = (\xlongequal) has none either, so it is drawn as two rules. */
    public static function xarrow(string $arrow = '→', string|Node|null $over = null, string|Node|null $under = null): UnderOver
    {
        return new UnderOver(
            new Operator($arrow, stretchy: UnderOver::isStretchy(new Operator($arrow)) ? null : true), // set only where the dictionary does not already stretch it (=)
            $under === null ? null : self::row($under),
            $over === null ? null : self::row($over),
        );
    }

    /** A brace under the base, stretched to its width, with an optional label below the brace. */
    public static function underbrace(string|Node $base, string|Node|null $label = null): UnderOver
    {
        $braced = new UnderOver(self::row($base), new Operator("\u{23DF}"), null);

        return $label === null ? $braced : new UnderOver($braced, self::row($label), null);
    }

    /** A delimiter inside Math::fenced() content that stretches with the fence (TeX's \middle). */
    public static function middle(string $delimiter): Node
    {
        $bad = false;
        $delimiter = self::scrub($delimiter, $bad);

        return self::flagged(new Middle($delimiter), $bad);
    }

    public static function fenced(string $open, string $close, string|Node ...$content): Fenced
    {
        $bad = false;
        $open = self::scrub($open, $bad);
        $close = self::scrub($close, $bad);

        return new Fenced($open, $close, self::flagged(self::row(...$content), $bad));
    }

    /**
     * A matrix: rows of cells in aligned columns, inside a fence.
     *
     * @param list<list<string|Node>> $rows
     * @param string $fence paren, bracket, brace, bar, doublebar or none (a bare grid)
     * @param string|array<string> $align left, center or right: one value for every column, or a list with one per column (the last repeats)
     *
     * @throws \InvalidArgumentException for no cells, an unknown fence or an unknown alignment
     */
    public static function matrix(array $rows, string $fence = 'paren', string|array $align = 'center'): Node
    {
        $fences = ['paren' => ['(', ')'], 'bracket' => ['[', ']'], 'brace' => ['{', '}'], 'bar' => ['|', '|'], 'doublebar' => ["\u{2016}", "\u{2016}"], 'none' => ['', '']];
        if (! isset($fences[$fence])) {
            throw new \InvalidArgumentException(sprintf("Math::matrix() fence must be one of %s, got '%s'.", implode(', ', array_keys($fences)), $fence));
        }
        $align = is_array($align) ? array_values($align) : [$align];
        if ($align === []) {
            throw new \InvalidArgumentException(sprintf('Math::matrix() align must be one or a list of %s.', implode(', ', Grid::COLUMN_ALIGN)));
        }
        foreach ($align as $a) {
            if (! in_array($a, Grid::COLUMN_ALIGN, true)) {
                // The docblock says string; callers without static analysis can still pass anything.
                // @phpstan-ignore function.alreadyNarrowedType
                throw new \InvalidArgumentException(sprintf('Math::matrix() align must be one or a list of %s, got %s.', implode(', ', Grid::COLUMN_ALIGN), is_string($a) ? "'" . $a . "'" : get_debug_type($a)));
            }
        }
        $grid = new Grid(self::gridCells('matrix', $rows), $align);

        return $fence === 'none' ? $grid : new Fenced($fences[$fence][0], $fences[$fence][1], $grid);
    }

    /**
     * Equations lined up on their alignment points, as TeX's aligned does: cells alternate right- and left-aligned,
     * with no gap inside a pair (so `x` `= 1` meet at the relation) and two em between pairs.
     * The leading operator of each left-hand cell keeps infix spacing, so `= 3` is spaced as a relation.
     *
     * @param list<list<string|Node>> $rows
     * @param float $pairGap em between pairs of columns; 0 for TeX's alignat, which leaves the spacing to the author.
     *                       TeX output keeps only 0 (written as alignedat) and the default 2 em; other gaps read back as 2.
     *
     * @throws \InvalidArgumentException for no cells
     */
    public static function aligned(array $rows, float $pairGap = 2.0): Grid
    {
        $cells = self::gridCells('aligned', $rows);
        $columns = 0;
        foreach ($cells as $row) {
            $columns = max($columns, count($row));
        }
        $align = [];
        $gaps = [];
        for ($j = 0; $j < $columns; $j++) {
            $align[] = $j % 2 === 0 ? 'right' : 'left';
            if ($j > 0) {
                $gaps[] = $j % 2 === 1 ? 0.0 : $pairGap;
            }
        }
        foreach ($cells as &$row) {
            foreach ($row as $j => &$cell) {
                if ($j % 2 === 1) {
                    $cell = self::infixLead($cell);
                }
            }
            unset($cell);
        }
        unset($row);

        return new Grid($cells, $align, 'baseline', true, $gaps, true);
    }

    /** $cell with its leading operator, if the cell starts with one (seen through Fallback and DisplayStyle), given the infix form. */
    private static function infixLead(Node $cell): Node
    {
        if ($cell instanceof Fallback && $cell->content !== null) {
            return new Fallback(self::infixLead($cell->content), $cell->note);
        }
        if ($cell instanceof DisplayStyle) {
            return new DisplayStyle(self::infixLead($cell->content), $cell->display);
        }
        if ($cell instanceof Operator) {
            return new Operator(
                $cell->char,
                stretchy: $cell->stretchy,
                largeop: $cell->largeop,
                movablelimits: $cell->movablelimits,
                form: 'infix',
                lspace: $cell->lspace,
                rspace: $cell->rspace,
                minSize: $cell->minSize,
                maxSize: $cell->maxSize,
            );
        }
        if ($cell instanceof Row && $cell->children !== [] && Fallback::unwrap($cell->children[0]) instanceof Operator) {
            $children = $cell->children;
            $children[0] = self::infixLead($children[0]);

            return new Row($children);
        }

        return $cell;
    }

    /**
     * A piecewise definition: a left brace around rows of cells, every column left-aligned.
     *
     * @param list<list<string|Node>> $rows
     *
     * @throws \InvalidArgumentException for no cells
     */
    public static function cases(array $rows): Fenced
    {
        return new Fenced('{', '', new Grid(self::gridCells('cases', $rows), ['left']));
    }

    /**
     * @param array<mixed> $rows
     * @return list<list<Node>>
     */
    private static function gridCells(string $method, array $rows): array
    {
        if ($rows === []) {
            throw new \InvalidArgumentException(sprintf('Math::%s() needs at least one row with at least one cell.', $method));
        }
        $out = [];
        $n = 0;
        foreach ($rows as $row) {
            $n++;
            if (! is_array($row) || $row === []) {
                throw new \InvalidArgumentException($n === 1
                    ? sprintf('Math::%s() needs at least one row with at least one cell.', $method)
                    : sprintf('Math::%s() row %d must be a non-empty list of cells.', $method, $n));
            }
            $cells = [];
            $m = 0;
            foreach ($row as $cell) {
                $m++;
                if (! is_string($cell) && ! $cell instanceof Node) {
                    throw new \InvalidArgumentException(sprintf('Math::%s() row %d cell %d must be a string or a Node, got %s.', $method, $n, $m, get_debug_type($cell)));
                }
                $cells[] = self::row($cell);
            }
            $out[] = $cells;
        }

        return $out;
    }

    public static function text(string $text): Node
    {
        $bad = false;

        return self::flagged(new Text(self::scrub($text, $bad)), $bad);
    }

    /**
     * The notes of every Fallback in the tree, depth first in reading order.
     *
     * @return list<MathNote>
     */
    public static function notes(Node $root): array
    {
        $notes = [];
        if ($root instanceof Fallback) {
            $notes[] = $root->note;
        }
        foreach ($root->children() as $child) {
            array_push($notes, ...self::notes($child));
        }

        return $notes;
    }

    /**
     * Lay a formula out. $display true = display style (block equation), false = inline/text style.
     *
     * @param float $size font size in points
     * @param bool $scriptAlternates false keeps scripts on the ordinary glyphs instead of the font's `ssty`
     *                               script-size forms (for backends that can only draw glyphs reachable from a code point)
     * @param (\Closure(string, float): ?float)|null $fallbackAdvance asked for the advance, in points, of a character the font has no glyph
     *                               for (called with the character and the size it is set at); a float replaces the .notdef
     *                               advance, null keeps it. For backends that draw such characters in another font.
     */
    public static function layout(
        Node $root,
        float $size = 12.0,
        bool $display = true,
        ?Font $font = null,
        bool $scriptAlternates = true,
        ?\Closure $fallbackAdvance = null,
    ): Box {
        return $root->layout(new Context($font ?? self::stixTwoMath(), $size, 0, $display, false, false, $scriptAlternates, $fallbackAdvance));
    }
}
