<?php

declare(strict_types=1);

namespace Quoin\Math\Render;

use Quoin\Math\Node\DisplayStyle;
use Quoin\Math\Node\Fallback;
use Quoin\Math\Node\Fenced;
use Quoin\Math\Node\Fraction;
use Quoin\Math\Node\Grid;
use Quoin\Math\Node\Identifier;
use Quoin\Math\Node\Node;
use Quoin\Math\Node\Number;
use Quoin\Math\Node\Middle;
use Quoin\Math\Node\Operator;
use Quoin\Math\Node\Phantom;
use Quoin\Math\Node\Root;
use Quoin\Math\Node\Row;
use Quoin\Math\Node\Scripts;
use Quoin\Math\Node\Space;
use Quoin\Math\Node\TexSource;
use Quoin\Math\Node\Text;
use Quoin\Math\Node\UnderOver;
use Quoin\Math\OperatorDictionary;
use Quoin\Math\Styles;

/** Serializes a formula tree as MathML Core, for the HTML export and for standalone use. */
final class MathmlWriter
{
    public static function write(Node $root, bool $display = true): string
    {
        return '<math xmlns="http://www.w3.org/1998/Math/MathML" display="' . ($display ? 'block' : 'inline') . '">'
            . self::node($root) . '</math>';
    }

    /** Set for the one node about to be written as the base of a script or limit: ApplyFunction goes after the whole of it. */
    private static bool $bare = false;

    /** A function name, alone or as the base of scripts or limits: what MathML's invisible function application follows. */
    private static function isFunction(Node $n): bool
    {
        while (($n instanceof Fallback && $n->content !== null) || $n instanceof TexSource) {
            $n = $n->content;
        }
        if ($n instanceof Scripts || $n instanceof UnderOver) {
            return self::isFunction($n->base);
        }

        return $n instanceof Identifier && $n->function;
    }

    private static function bare(Node $n): string
    {
        self::$bare = true;
        $out = self::slot($n);
        self::$bare = false;

        return $out;
    }

    private static function node(Node $n): string
    {
        $bare = self::$bare;
        self::$bare = false;

        $xml = self::plain0($n);
        // A name with no sibling after it, outside the standard table, keeps its flag through an mrow that holds the mark; a
        // standard name is flagged by the reader anyway, and nothing is added (a slot takes exactly one element).
        if (! $bare && self::isFunction($n) && ! self::isStandardName($n)) {
            return '<mrow>' . $xml . '<mo>&#x2061;</mo></mrow>';
        }

        return $xml;
    }

    private static function isStandardName(Node $n): bool
    {
        while (($n instanceof Fallback && $n->content !== null) || $n instanceof TexSource) {
            $n = $n->content;
        }
        if ($n instanceof Scripts || $n instanceof UnderOver) {
            return self::isStandardName($n->base);
        }

        return $n instanceof Identifier && in_array($n->name, OperatorDictionary::FUNCTION_NAMES, true);
    }

    private static function plain0(Node $n): string
    {
        return match (true) {
            $n instanceof Identifier => '<mi' . ($n->upright ? ' mathvariant="normal"' : '') . '>' . Xml::text($n->upright ? $n->name : self::plain($n->name)) . '</mi>',
            $n instanceof Number => '<mn>' . Xml::text($n->text) . '</mn>',
            // Alone, the layout treats an operator as prefix while a browser infers infix.
            $n instanceof Operator => self::operator($n, OperatorDictionary::FORM_PREFIX, OperatorDictionary::FORM_INFIX),
            $n instanceof Text => '<mtext>' . Xml::text(self::keepSpaces($n->text)) . '</mtext>',
            $n instanceof Fraction => '<mfrac' . ($n->bar ? '' : ' linethickness="0"') . '>' . self::slot($n->numerator) . self::slot($n->denominator) . '</mfrac>',
            $n instanceof Space => '<mspace width="' . self::em($n->em) . 'em"/>',
            $n instanceof Phantom => '<mphantom>' . self::node($n->content) . '</mphantom>',
            $n instanceof Root => $n->index === null
                ? '<msqrt>' . self::slot($n->radicand) . '</msqrt>'
                : '<mroot>' . self::slot($n->radicand) . self::slot($n->index) . '</mroot>',
            $n instanceof Scripts => self::scripts($n),
            $n instanceof UnderOver => self::underOver($n),
            // Only a Middle that Fenced stretches does so in the PDF; a browser must not stretch the others.
            $n instanceof Middle => self::operator(new Operator($n->char, $n->stretches(), lspace: 3, rspace: 3), OperatorDictionary::FORM_INFIX, OperatorDictionary::FORM_INFIX),
            $n instanceof Fenced => self::fenced($n),
            $n instanceof Grid => self::grid($n),
            $n instanceof Row => self::row($n),
            $n instanceof DisplayStyle => '<mstyle displaystyle="' . ($n->display ? 'true' : 'false') . '">' . self::node($n->content) . '</mstyle>',
            $n instanceof Fallback => $n->content === null ? '' : self::plain0($n->content),
            $n instanceof TexSource => self::plain0($n->content),
            default => throw new \InvalidArgumentException('Unknown node ' . $n::class),
        };
    }

    /** A child in a fixed-arity element: a dropped one is written as an empty mrow so the others keep their places. */
    private static function slot(Node $n): string
    {
        return $n instanceof Fallback && $n->content === null ? '<mrow></mrow>' : self::node($n);
    }

    /** An em width with up to $places decimals (3 by default) and no trailing zeros. */
    private static function em(float $em, int $places = 3): string
    {
        $s = rtrim(rtrim(number_format($em, $places, '.', ''), '0'), '.');

        return $s === '-0' || $s === '' ? '0' : $s;
    }

    /** Browsers collapse and trim spaces in mtext; no-break spaces survive. A single inner space stays a plain space. */
    private static function keepSpaces(string $t): string
    {
        return (string) preg_replace_callback('/^ +| +$| {2,}/', fn (array $m) => str_repeat("\u{A0}", strlen($m[0])), $t);
    }

    /** Math italic code points back to the plain letter: the browser italicizes a single-letter mi itself. */
    private static function plain(string $name): string
    {
        $out = '';
        foreach (mb_str_split($name) as $ch) {
            $cp = mb_ord($ch);
            $out .= $cp === 0x210E ? 'h' : mb_chr(Styles::plain($cp));
        }

        return $out;
    }

    /**
     * @param string $form the form the layout used
     * @param string $inferred the form a browser infers from the position
     * @param bool $stretched the layout stretches this operator (part of a stack), so it is not told to stay put
     */
    private static function operator(Operator $o, string $form, string $inferred, bool $fence = false, bool $stretched = false): string
    {
        $form = $o->form ?? $form;
        $attrs = $form !== $inferred ? ' form="' . $form . '"' : '';
        if ($fence) {
            $attrs .= ' stretchy="true"';
        } elseif ($o->minSize !== null || $o->maxSize !== null) {
            $attrs .= ' stretchy="true"'; // a browser applies minsize and maxsize to stretchy operators only; a size wins over stretchy=false
        } elseif ($o->stretchy !== null) {
            $attrs .= ' stretchy="' . ($o->stretchy ? 'true' : 'false') . '"';
        } elseif (! $stretched && OperatorDictionary::lookup($o->char, $form)['stretchy']) {
            // Only Fenced delimiters stretch in the PDF, so keep the browser from stretching the others.
            $attrs .= ' stretchy="false"';
        }
        if ($o->largeop !== null) {
            $attrs .= ' largeop="' . ($o->largeop ? 'true' : 'false') . '"';
        }
        if ($o->movablelimits !== null) {
            $attrs .= ' movablelimits="' . ($o->movablelimits ? 'true' : 'false') . '"';
        }

        if ($o->lspace !== null) {
            $attrs .= ' lspace="' . self::em($o->lspace / 18, 4) . 'em"';
        }
        if ($o->rspace !== null) {
            $attrs .= ' rspace="' . self::em($o->rspace / 18, 4) . 'em"';
        }

        if ($o->minSize !== null) {
            $attrs .= ' minsize="' . self::em($o->minSize, 4) . 'em"';
        }
        if ($o->maxSize !== null) {
            $attrs .= ' maxsize="' . self::em($o->maxSize, 4) . 'em"';
        }

        return '<mo' . $attrs . '>' . Xml::text($o->char) . '</mo>';
    }

    private static function row(Row $r): string
    {
        $count = count($r->children);
        if ($count === 1) {
            return self::node($r->children[0]);
        }
        $forms = $r->forms();
        $out = '';
        foreach ($r->children as $i => $child) {
            $resolved = $forms[$i];
            // What a browser infers from the position in the mrow: first prefix, last postfix, else infix.
            $inferred = $i === 0 ? OperatorDictionary::FORM_PREFIX : ($i === $count - 1 ? OperatorDictionary::FORM_POSTFIX : OperatorDictionary::FORM_INFIX);
            $op = Fallback::unwrap($child);
            if ($op instanceof Operator) {
                $out .= self::styled($child, self::operator($op, $resolved ?? $inferred, $inferred));
            } elseif ($i < $count - 1 && self::isFunction($child)) {
                // ApplyFunction goes between the name (with its scripts or limits) and what follows it.
                $out .= self::plain0($child) . '<mo>&#x2061;</mo>';
            } else {
                $out .= self::node($child);
            }
        }

        return '<mrow>' . $out . '</mrow>';
    }

    /** An operator's markup inside the mstyle displaystyle wrappers its node was peeled out of. */
    private static function styled(Node $node, string $xml): string
    {
        $displays = Fallback::displays($node);
        $inner = Fallback::unwrap($node);
        if ($inner instanceof Row && count($inner->children) === 1) {
            $displays = [...$displays, ...Fallback::displays($inner->children[0])];
        }
        foreach (array_reverse($displays) as $display) {
            $xml = '<mstyle displaystyle="' . ($display ? 'true' : 'false') . '">' . $xml . '</mstyle>';
        }

        return $xml;
    }

    private static function scripts(Scripts $s): string
    {
        $base = self::bare($s->base);
        if ($s->preSub !== null || $s->preSup !== null) {
            $slot = static fn (?Node $n): string => $n === null ? '<none/>' : self::slot($n);

            return '<mmultiscripts>' . $base
                . ($s->sub === null && $s->sup === null ? '' : $slot($s->sub) . $slot($s->sup))
                . '<mprescripts/>' . $slot($s->preSub) . $slot($s->preSup) . '</mmultiscripts>';
        }
        if ($s->sub !== null && $s->sup !== null) {
            return '<msubsup>' . $base . self::slot($s->sub) . self::slot($s->sup) . '</msubsup>';
        }
        if ($s->sup !== null) {
            return '<msup>' . $base . self::slot($s->sup) . '</msup>';
        }

        return '<msub>' . $base . self::slot($s->sub ?? throw new \LogicException('Scripts without a script')) . '</msub>';
    }

    private static function underOver(UnderOver $u): string
    {
        $part = static function (Node $n): string {
            $op = UnderOver::operatorOf($n);

            return $op !== null ? self::styled($n, self::operator($op, OperatorDictionary::FORM_INFIX, OperatorDictionary::FORM_INFIX, false, true)) : self::slot($n);
        };
        $baseOp = UnderOver::operatorOf($u->base);
        if (UnderOver::isLimitName($u->base)) {
            // Marked as an operator so a browser sets its limit beside it in inline style, as the PDF does.
            $name = UnderOver::nameNode($u->base);
            $base = '<mo movablelimits="true">' . Xml::text($name instanceof Identifier ? $name->name : ($name instanceof Operator ? $name->char : '')) . '</mo>';
        } elseif ($baseOp !== null) {
            $stretches = $baseOp->stretchy ?? OperatorDictionary::lookup($baseOp->char, OperatorDictionary::FORM_POSTFIX)['stretchy'];
            $form = $baseOp->isLargeop(OperatorDictionary::FORM_PREFIX) ? OperatorDictionary::FORM_PREFIX : OperatorDictionary::FORM_INFIX;
            $base = self::styled($u->base, self::operator($baseOp, $form, OperatorDictionary::FORM_INFIX, false, $stretches));
        } else {
            $base = self::bare($u->base);
        }
        if ($u->under !== null && $u->over !== null) {
            return '<munderover' . ($u->accent ? ' accent="true"' : '') . ($u->accentUnder ? ' accentunder="true"' : '') . '>' . $base . $part($u->under) . $part($u->over) . '</munderover>';
        }
        if ($u->over !== null) {
            return '<mover' . ($u->accent ? ' accent="true"' : '') . '>' . $base . $part($u->over) . '</mover>';
        }

        return '<munder' . ($u->accentUnder ? ' accentunder="true"' : '') . '>' . $base . $part($u->under ?? throw new \LogicException('UnderOver without a part')) . '</munder>';
    }

    private static function grid(Grid $g): string
    {
        if ($g->columns === 0) {
            return '';
        }
        $attrs = '';
        if (array_any($g->columnAlign, static fn (string $a): bool => $a !== 'center')) {
            $attrs .= ' columnalign="' . implode(' ', $g->columnAlign) . '"';
        }
        if (array_any($g->columnSpacing, static fn (float $gap): bool => $gap !== 1.0)) {
            $attrs .= ' columnspacing="' . implode(' ', array_map(static fn (float $gap): string => rtrim(rtrim(number_format($gap, 4, '.', ''), '0'), '.') . 'em', $g->columnSpacing)) . '"';
        }
        if ($g->rowAlign !== 'baseline') {
            $attrs .= ' rowalign="' . $g->rowAlign . '"';
        }
        if ($g->display) {
            $attrs .= ' displaystyle="true"';
        }
        $rows = '';
        foreach ($g->rows as $row) {
            $rows .= '<mtr>' . implode('', array_map(static fn (Node $cell): string => '<mtd>' . self::node($cell) . '</mtd>', $row)) . '</mtr>';
        }

        return '<mtable' . $attrs . '>' . $rows . '</mtable>';
    }

    private static function fenced(Fenced $f): string
    {
        $out = '<mrow>';
        if ($f->open !== '') {
            $out .= self::operator(new Operator($f->open), OperatorDictionary::FORM_PREFIX, OperatorDictionary::FORM_PREFIX, true);
        }
        $out .= self::node(Fenced::stretchMiddles($f->content, 0.0));
        if ($f->close !== '') {
            $out .= self::operator(new Operator($f->close), OperatorDictionary::FORM_POSTFIX, OperatorDictionary::FORM_POSTFIX, true);
        }

        return $out . '</mrow>';
    }
}
