<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

use Papercut\Math\Node\DisplayStyle;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Identifier;
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

/**
 * Serializes a formula tree as Word OMML. The output declares no namespaces: the caller
 * declares xmlns:m and xmlns:w. Word always uses Cambria Math for equations.
 */
final class OmmlWriter
{
    public static function oMath(Node $root, ?RunFormat $format = null): string
    {
        return '<m:oMath>' . self::node($root, self::rPr($format)) . '</m:oMath>';
    }

    public static function oMathPara(Node $root, string $jc = 'left', ?RunFormat $format = null): string
    {
        if (! in_array($jc, ['left', 'center', 'right'], true)) {
            throw new \InvalidArgumentException('Unsupported equation alignment "' . $jc . '"');
        }

        return '<m:oMathPara><m:oMathParaPr><m:jc m:val="' . Xml::text($jc) . '"/></m:oMathParaPr>'
            . self::oMath($root, $format) . '</m:oMathPara>';
    }

    private static function rPr(?RunFormat $f): string
    {
        $out = '<w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/>';
        if ($f?->colorHex !== null) {
            $out .= '<w:color w:val="' . Xml::text($f->colorHex) . '"/>';
        }
        if ($f?->sizeHalfPoints !== null) {
            $out .= '<w:sz w:val="' . $f->sizeHalfPoints . '"/><w:szCs w:val="' . $f->sizeHalfPoints . '"/>';
        }

        if ($f?->shadingHex !== null) {
            $out .= '<w:shd w:val="clear" w:color="auto" w:fill="' . Xml::text($f->shadingHex) . '"/>';
        }

        return $out . '</w:rPr>';
    }

    /** @param ''|'p'|'nor' $mode 'p' for upright math, 'nor' for normal text; the two are alternatives in m:rPr */
    private static function run(string $text, string $rPr, string $mode = ''): string
    {
        $mPr = match ($mode) {
            'p' => '<m:rPr><m:sty m:val="p"/></m:rPr>',
            'nor' => '<m:rPr><m:nor/></m:rPr>',
            default => '',
        };

        return '<m:r>' . $mPr . $rPr
            . '<m:t xml:space="preserve">' . Xml::text($text) . '</m:t></m:r>';
    }

    private static function node(Node $n, string $rPr): string
    {
        return match (true) {
            $n instanceof Identifier => self::run($n->upright ? $n->name : self::plain($n->name), $rPr, $n->upright ? 'p' : ''),
            $n instanceof Number => self::run($n->text, $rPr),
            $n instanceof Operator => self::run($n->char, $rPr, preg_match('/^\\p{L}+$/u', $n->char) === 1 ? 'p' : ''),
            $n instanceof Text => self::run($n->text, $rPr, 'nor'),
            $n instanceof Space => self::space($n->em, $rPr),
            $n instanceof Phantom => '<m:phant><m:phantPr><m:show m:val="0"/></m:phantPr><m:e>' . self::node($n->content, $rPr) . '</m:e></m:phant>',
            $n instanceof Fraction => '<m:f>' . ($n->bar ? '' : '<m:fPr><m:type m:val="noBar"/></m:fPr>') . '<m:num>' . self::node($n->numerator, $rPr) . '</m:num><m:den>' . self::node($n->denominator, $rPr) . '</m:den></m:f>',
            $n instanceof Root => self::root($n, $rPr),
            $n instanceof Scripts => self::scripts($n, $rPr),
            $n instanceof UnderOver => self::underOver($n, $rPr),
            $n instanceof Fenced => '<m:d><m:dPr><m:begChr m:val="' . Xml::text($n->open) . '"/><m:endChr m:val="' . Xml::text($n->close) . '"/></m:dPr><m:e>'
                . self::node($n->content, $rPr) . '</m:e></m:d>',
            $n instanceof Row => self::sequence($n->children, $rPr),
            $n instanceof DisplayStyle => self::node($n->content, $rPr),
            $n instanceof Fallback => $n->content === null ? '' : self::node($n->content, $rPr),
            default => throw new \InvalidArgumentException('Unknown node ' . $n::class),
        };
    }

    /** Unicode spaces approximating the width; a negative space cannot be written and gives nothing. */
    private static function space(float $em, string $rPr): string
    {
        $text = match (true) {
            $em <= 0 => '',
            $em <= 0.2 => "\u{2009}",
            $em <= 0.3 => "\u{2005}",
            $em <= 0.4 => "\u{2004}",
            $em <= 0.6 => "\u{2002}",
            $em < 1 => "\u{2003}",
            default => str_repeat("\u{2003}", (int) floor($em)) . self::fraction($em - floor($em)),
        };

        return $text === '' ? '' : self::run($text, $rPr);
    }

    /** The Unicode space nearest a fraction of an em; nothing below a tenth. */
    private static function fraction(float $f): string
    {
        return match (true) {
            $f < 0.1 => '',
            $f <= 0.2 => "\u{2009}",
            $f <= 0.3 => "\u{2005}",
            $f <= 0.4 => "\u{2004}",
            $f <= 0.6 => "\u{2002}",
            default => "\u{2003}",
        };
    }

    private static function root(Root $r, string $rPr): string
    {
        $e = '<m:e>' . self::node($r->radicand, $rPr) . '</m:e>';
        if ($r->index === null) {
            return '<m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:deg/>' . $e . '</m:rad>';
        }

        return '<m:rad><m:radPr><m:degHide m:val="0"/></m:radPr><m:deg>' . self::node($r->index, $rPr) . '</m:deg>' . $e . '</m:rad>';
    }

    private static function scripts(Scripts $s, string $rPr): string
    {
        $nary = self::nary($s);
        if ($nary !== null) {
            return self::naryXml($nary, null, $rPr);
        }
        $e = '<m:e>' . self::node($s->base, $rPr) . '</m:e>';
        $sub = $s->sub === null ? '' : '<m:sub>' . self::node($s->sub, $rPr) . '</m:sub>';
        $sup = $s->sup === null ? '' : '<m:sup>' . self::node($s->sup, $rPr) . '</m:sup>';
        $tag = match (true) {
            $s->sub !== null && $s->sup !== null => 'm:sSubSup',
            $s->sup !== null => 'm:sSup',
            $s->sub !== null => 'm:sSub',
            default => throw new \LogicException('Scripts without a script'),
        };

        return '<' . $tag . '>' . $e . $sub . $sup . '</' . $tag . '>';
    }

    /**
     * Children side by side. A large operator with limits takes the next child as its body, as Word's n-ary does
     * (skipping spaces, which stay in the body ahead of it), unless that child is an operator (then the body is empty).
     * A body that is itself an n-ary takes its own body in turn.
     *
     * @param list<Node> $children
     */
    private static function sequence(array $children, string $rPr): string
    {
        $out = '';
        $i = 0;
        $count = count($children);
        while ($i < $count) {
            $out .= self::item($children, $i, $rPr);
        }

        return $out;
    }

    /** @param list<Node> $children */
    private static function item(array $children, int &$i, string $rPr): string
    {
        $child = $children[$i++];
        $nary = self::nary($child);
        if ($nary === null) {
            return self::node($child, $rPr);
        }
        $spaces = '';
        $j = $i;
        while (isset($children[$j]) && Fallback::unwrap($children[$j]) instanceof Space) {
            $spaces .= self::node($children[$j], $rPr);
            $j++;
        }
        $next = $children[$j] ?? null;
        if ($next === null || Fallback::unwrap($next) instanceof Operator) {
            return self::naryXml($nary, null, $rPr);
        }
        $i = $j;

        return self::naryXml($nary, $spaces . self::item($children, $i, $rPr), $rPr);
    }

    /**
     * The parts of a large operator with limits: an UnderOver (limits above and below) or Scripts (beside it).
     *
     * @return ?array{Operator, ?Node, ?Node, bool} operator, lower limit, upper limit, whether they sit above and below
     */
    private static function nary(Node $n): ?array
    {
        $n = Fallback::unwrap($n);
        if (! ($n instanceof UnderOver || $n instanceof Scripts)) {
            return null;
        }
        $op = UnderOver::operatorOf($n->base);
        if ($op === null || ! $op->isLargeop(OperatorDictionary::FORM_PREFIX)) {
            return null;
        }

        return $n instanceof UnderOver ? [$op, $n->under, $n->over, true] : [$op, $n->sub, $n->sup, false];
    }

    /** @param array{Operator, ?Node, ?Node, bool} $nary */
    private static function naryXml(array $nary, ?string $body, string $rPr): string
    {
        [$op, $sub, $sup, $stacked] = $nary;

        return '<m:nary><m:naryPr><m:chr m:val="' . Xml::text($op->char) . '"/><m:limLoc m:val="' . ($stacked ? 'undOvr' : 'subSup') . '"/>'
            . ($sub === null ? '<m:subHide m:val="1"/>' : '') . ($sup === null ? '<m:supHide m:val="1"/>' : '') . '</m:naryPr>'
            . ($sub === null ? '<m:sub/>' : '<m:sub>' . self::node($sub, $rPr) . '</m:sub>')
            . ($sup === null ? '<m:sup/>' : '<m:sup>' . self::node($sup, $rPr) . '</m:sup>')
            . ($body === null ? '<m:e/>' : '<m:e>' . $body . '</m:e>')
            . '</m:nary>';
    }

    /** Limits, accents, bars and braces: Word's own structures where it has one, a plain limit stack otherwise. */
    private static function underOver(UnderOver $u, string $rPr): string
    {
        $nary = self::nary($u);
        if ($nary !== null) {
            return self::naryXml($nary, null, $rPr);
        }
        $out = self::node($u->base, $rPr);
        if ($u->under !== null) {
            $op = UnderOver::operatorOf($u->under);
            $out = match (true) {
                $op !== null && in_array($op->char, UnderOver::BARS, true) => self::bar($out, 'bot'),
                $op !== null && UnderOver::isBrace($op, false) => self::groupChr($out, $op->char, 'bot', 'top'),
                $op !== null && $u->accentUnder => self::groupChr($out, Styles::accentMark($op->char) ?? $op->char, 'bot', 'top'),
                default => '<m:limLow><m:e>' . $out . '</m:e><m:lim>' . self::node($u->under, $rPr) . '</m:lim></m:limLow>',
            };
        }
        if ($u->over !== null) {
            $op = UnderOver::operatorOf($u->over);
            $out = match (true) {
                $op !== null && in_array($op->char, UnderOver::BARS, true) => self::bar($out, 'top'),
                $op !== null && $u->accent => '<m:acc><m:accPr><m:chr m:val="' . Xml::text(Styles::accentMark($op->char) ?? $op->char) . '"/></m:accPr><m:e>' . $out . '</m:e></m:acc>',
                $op !== null && UnderOver::isBrace($op, true) => self::groupChr($out, $op->char, 'top', 'bot'),
                default => '<m:limUpp><m:e>' . $out . '</m:e><m:lim>' . self::node($u->over, $rPr) . '</m:lim></m:limUpp>',
            };
        }

        return $out;
    }

    private static function bar(string $e, string $pos): string
    {
        return '<m:bar><m:barPr><m:pos m:val="' . $pos . '"/></m:barPr><m:e>' . $e . '</m:e></m:bar>';
    }

    private static function groupChr(string $e, string $char, string $pos, string $vertJc): string
    {
        return '<m:groupChr><m:groupChrPr><m:chr m:val="' . Xml::text($char) . '"/><m:pos m:val="' . $pos . '"/><m:vertJc m:val="' . $vertJc . '"/></m:groupChrPr><m:e>' . $e . '</m:e></m:groupChr>';
    }

    /** Math italic code points back to the plain letter: Word italicises letters itself. */
    private static function plain(string $name): string
    {
        $out = '';
        foreach (mb_str_split($name) as $ch) {
            $cp = mb_ord($ch);
            $out .= $cp === 0x210E ? 'h' : mb_chr(Styles::plain($cp));
        }

        return $out;
    }
}
