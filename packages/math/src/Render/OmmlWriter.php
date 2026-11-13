<?php

declare(strict_types=1);

namespace Papercut\Math\Render;

use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Number;
use Papercut\Math\Node\Operator;
use Papercut\Math\Node\Root;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\Node\Text;
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

        return '<m:oMathPara><m:oMathParaPr><m:jc m:val="' . self::esc($jc) . '"/></m:oMathParaPr>'
            . self::oMath($root, $format) . '</m:oMathPara>';
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function rPr(?RunFormat $f): string
    {
        $out = '<w:rPr><w:rFonts w:ascii="Cambria Math" w:hAnsi="Cambria Math" w:cs="Cambria Math"/>';
        if ($f?->colorHex !== null) {
            $out .= '<w:color w:val="' . self::esc($f->colorHex) . '"/>';
        }
        if ($f?->sizeHalfPoints !== null) {
            $out .= '<w:sz w:val="' . $f->sizeHalfPoints . '"/><w:szCs w:val="' . $f->sizeHalfPoints . '"/>';
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
            . '<m:t xml:space="preserve">' . self::esc($text) . '</m:t></m:r>';
    }

    private static function node(Node $n, string $rPr): string
    {
        return match (true) {
            $n instanceof Identifier => self::run(self::plain($n->name), $rPr, $n->upright ? 'p' : ''),
            $n instanceof Number => self::run($n->text, $rPr),
            $n instanceof Operator => self::run($n->char, $rPr),
            $n instanceof Text => self::run($n->text, $rPr, 'nor'),
            $n instanceof Fraction => '<m:f><m:num>' . self::node($n->numerator, $rPr) . '</m:num><m:den>' . self::node($n->denominator, $rPr) . '</m:den></m:f>',
            $n instanceof Root => self::root($n, $rPr),
            $n instanceof Scripts => self::scripts($n, $rPr),
            $n instanceof Fenced => '<m:d><m:dPr><m:begChr m:val="' . self::esc($n->open) . '"/><m:endChr m:val="' . self::esc($n->close) . '"/></m:dPr><m:e>'
                . self::node($n->content, $rPr) . '</m:e></m:d>',
            $n instanceof Row => implode('', array_map(fn (Node $c) => self::node($c, $rPr), $n->children)),
            default => throw new \InvalidArgumentException('Unknown node ' . $n::class),
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
