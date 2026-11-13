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
use Papercut\Math\OperatorDictionary;
use Papercut\Math\Styles;

/** Serializes a formula tree as MathML Core, for the HTML export and for standalone use. */
final class MathmlWriter
{
    public static function write(Node $root, bool $display = true): string
    {
        return '<math xmlns="http://www.w3.org/1998/Math/MathML" display="' . ($display ? 'block' : 'inline') . '">'
            . self::node($root) . '</math>';
    }

    private static function node(Node $n): string
    {
        return match (true) {
            $n instanceof Identifier => '<mi' . ($n->upright ? ' mathvariant="normal"' : '') . '>' . Xml::text(self::plain($n->name)) . '</mi>',
            $n instanceof Number => '<mn>' . Xml::text($n->text) . '</mn>',
            // Alone, the layout treats an operator as prefix while a browser infers infix.
            $n instanceof Operator => self::operator($n, OperatorDictionary::FORM_PREFIX, OperatorDictionary::FORM_INFIX),
            $n instanceof Text => '<mtext>' . Xml::text(self::keepSpaces($n->text)) . '</mtext>',
            $n instanceof Fraction => '<mfrac>' . self::node($n->numerator) . self::node($n->denominator) . '</mfrac>',
            $n instanceof Root => $n->index === null
                ? '<msqrt>' . self::node($n->radicand) . '</msqrt>'
                : '<mroot>' . self::node($n->radicand) . self::node($n->index) . '</mroot>',
            $n instanceof Scripts => self::scripts($n),
            $n instanceof Fenced => self::fenced($n),
            $n instanceof Row => self::row($n),
            default => throw new \InvalidArgumentException('Unknown node ' . $n::class),
        };
    }

    /** Browsers collapse and trim spaces in mtext; no-break spaces survive. A single inner space stays a plain space. */
    private static function keepSpaces(string $t): string
    {
        return (string) preg_replace_callback('/^ +| +$| {2,}/', fn (array $m) => str_repeat("\u{A0}", strlen($m[0])), $t);
    }

    /** Math italic code points back to the plain letter: the browser italicises a single-letter mi itself. */
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
     */
    private static function operator(Operator $o, string $form, string $inferred, bool $fence = false): string
    {
        $attrs = $form !== $inferred ? ' form="' . $form . '"' : '';
        if ($fence) {
            $attrs .= ' stretchy="true"';
        } elseif (OperatorDictionary::lookup($o->char, $form)['stretchy']) {
            // Only Fenced delimiters stretch in the PDF, so keep the browser from stretching the others.
            $attrs .= ' stretchy="false"';
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
            $out .= $child instanceof Operator ? self::operator($child, $resolved ?? $inferred, $inferred) : self::node($child);
        }

        return '<mrow>' . $out . '</mrow>';
    }

    private static function scripts(Scripts $s): string
    {
        $base = self::node($s->base);
        if ($s->sub !== null && $s->sup !== null) {
            return '<msubsup>' . $base . self::node($s->sub) . self::node($s->sup) . '</msubsup>';
        }
        if ($s->sup !== null) {
            return '<msup>' . $base . self::node($s->sup) . '</msup>';
        }

        return '<msub>' . $base . self::node($s->sub ?? throw new \LogicException('Scripts without a script')) . '</msub>';
    }

    private static function fenced(Fenced $f): string
    {
        $out = '<mrow>';
        if ($f->open !== '') {
            $out .= self::operator(new Operator($f->open), OperatorDictionary::FORM_PREFIX, OperatorDictionary::FORM_PREFIX, true);
        }
        $out .= self::node($f->content);
        if ($f->close !== '') {
            $out .= self::operator(new Operator($f->close), OperatorDictionary::FORM_POSTFIX, OperatorDictionary::FORM_POSTFIX, true);
        }

        return $out . '</mrow>';
    }
}
