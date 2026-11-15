<?php

declare(strict_types=1);

namespace Papercut\Math\Mathml;

use Papercut\Math\MathNote;
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
use Papercut\Math\NoteLevel;
use Papercut\Math\OperatorDictionary;
use Papercut\Math\Styles;

/**
 * Reads MathML (from editors, Word, hand-written) into formula nodes. Markup is parsed with the HTML5 parser, so
 * entities such as &ApplyFunction; resolve and prefixed (`mml:`) or unprefixed elements both work.
 */
final class MathmlReader
{
    /** Opening fence to the closers it pairs with. */
    private const PAIRS = [
        '(' => [')', ']'], '[' => [']', ')'], '{' => ['}'], '⟨' => ['⟩'], '⌈' => ['⌉'], '⌊' => ['⌋'], '⟪' => ['⟫'],
    ];

    private const NAMED_SPACES = [
        'veryverythin' => 1, 'verythin' => 2, 'thin' => 3, 'medium' => 4, 'thick' => 5, 'verythick' => 6, 'veryverythick' => 7,
        'veryveryhigh' => 7,
    ];

    /** Points per unit, for widths turned into em at 12pt. */
    private const UNIT_PT = ['pt' => 1.0, 'px' => 0.75, 'mm' => 72 / 25.4, 'cm' => 72 / 2.54, 'in' => 72.0];

    /** @var array<string, true> element names already noted in this formula */
    private array $noted = [];

    private function __construct()
    {
    }

    public static function read(string|\Dom\Element $input): MathmlResult
    {
        $reader = new self();
        $math = is_string($input) ? self::parse($input) : $input;
        if ($math === null) {
            return new MathmlResult(new Row([]), false);
        }

        return new MathmlResult($reader->map($math, self::name($math)) ?? new Row([]), strtolower(trim($math->getAttribute('display') ?? '')) === 'block');
    }

    /**
     * Well-formed XML is read as XML (prefixed self-closing tags work); anything else goes through the HTML5 parser,
     * which resolves entities such as &ApplyFunction; but needs prefixed self-closing tags written out.
     */
    private static function parse(string $s): ?\Dom\Element
    {
        if (str_starts_with($s, "\u{FEFF}")) {
            $s = substr($s, 3);
        }
        $s = (string) preg_replace('/^\s*<\?xml[^>]*\?>/', '', $s);
        $s = (string) preg_replace('~<((?:[\w.-]+:)?annotation(?:-xml)?)\b(?:"[^"]*"|\'[^\']*\'|[^>"\'])*/>~i', '', $s);
        $s = (string) preg_replace('~<((?:[\w.-]+:)?annotation(?:-xml)?)\b[^>]*>.*?</\1\s*>~is', '', $s);

        if (stripos($s, '<!DOCTYPE') === false && stripos($s, '<!ENTITY') === false) {
            try {
                $xml = \Dom\XMLDocument::createFromString($s, LIBXML_NOERROR | LIBXML_NONET);
                if ($xml->documentElement !== null && ($found = self::findMath($xml->documentElement, true)) !== null) {
                    return $found;
                }
            } catch (\Throwable) {
                // not well-formed XML: fall through to HTML5
            }
        }
        $html = (string) preg_replace('~<([A-Za-z][\w.-]*:[\w.-]+)((?:"[^"]*"|\'[^\']*\'|[^>"\'])*?)\s*/>~', '<$1$2></$1>', $s);
        $find = static function (string $src): ?\Dom\Element {
            $doc = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $src, LIBXML_NOERROR, 'UTF-8');

            return $doc->body === null ? null : self::findMath($doc->body);
        };

        return $find($html) ?? $find('<math>' . $html . '</math>');
    }

    private static function findMath(\Dom\Element $parent, bool $self = false): ?\Dom\Element
    {
        if ($self && self::name($parent) === 'math') {
            return $parent;
        }
        foreach ($parent->children as $child) {
            if (self::name($child) === 'math') {
                return $child;
            }
            if (($found = self::findMath($child)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /** The element name without a namespace prefix, lower-cased. */
    private static function name(\Dom\Element $e): string
    {
        $n = $e->localName;
        $p = strrpos($n, ':');

        return strtolower($p === false ? $n : substr($n, $p + 1));
    }

    private static function collapse(string $t): string
    {
        return trim((string) preg_replace('/[ \t\n\r\f]+/', ' ', $t), " \t\n\r\f");
    }

    private static function bool(\Dom\Element $e, string $attr): ?bool
    {
        return match (strtolower(trim($e->getAttribute($attr) ?? ''))) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function map(\Dom\Element $e, string $where): ?Node
    {
        $name = self::name($e);

        return match ($name) {
            'math', 'mrow', 'mpadded' => $this->children($e, $where),
            'mstyle' => $this->style($e, $where),
            'semantics' => $this->slots($e, $where, 1)[0] ?? new Row([]),
            'annotation', 'annotation-xml' => null,
            'mi' => $this->identifier($e),
            'mn' => ($t = self::collapse((string) $e->textContent)) === '' ? null : new Number($t),
            'mtext' => ($t = self::collapse((string) $e->textContent)) === '' ? null : new Text($t),
            'ms' => new Text('"' . self::collapse((string) $e->textContent) . '"'),
            'mo' => $this->operator($e),
            'mspace' => new Space(self::width($e->getAttribute('width'))),
            'mphantom' => new Phantom($this->children($e, $where)),
            'msqrt' => new Root($this->children($e, $where)),
            'mfrac' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Fraction($a[0], $a[1], ! self::zeroThickness($e->getAttribute('linethickness')))),
            'mroot' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Root($a[0], $a[1])),
            'msub' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Scripts($a[0], $a[1], null)),
            'msup' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Scripts($a[0], null, $a[1])),
            'msubsup' => $this->fixed($name, 3, $this->slots($e, $where), $where, fn (array $a): Node => new Scripts($a[0], $a[1], $a[2])),
            'munder' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new UnderOver($a[0], $a[1], null, false, self::bool($e, 'accentunder') ?? false)),
            'mover' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new UnderOver($a[0], null, $a[1], self::bool($e, 'accent') ?? self::isAccent($a[1]))),
            'munderover' => $this->fixed($name, 3, $this->slots($e, $where), $where, fn (array $a): Node => new UnderOver($a[0], $a[1], $a[2], self::bool($e, 'accent') ?? self::isAccent($a[2]), self::bool($e, 'accentunder') ?? false)),
            'mfenced' => $this->fenced($e, $this->slots($e, $where)),
            default => $this->children($e, $where),
        };
    }

    private function at(string $where, \Dom\Element $child, int $index): string
    {
        return $where . ' > ' . self::name($child) . '[' . $index . ']';
    }

    /**
     * Each child element mapped (null when it maps to nothing), and each run of stray text as a Text node, in order.
     *
     * @return list<?Node>
     */
    private function slots(\Dom\Element $e, string $where, ?int $limit = null): array
    {
        $slots = [];
        $i = 0;
        foreach ($e->childNodes as $c) {
            if ($limit !== null && count($slots) >= $limit) {
                break;
            }
            if ($c instanceof \Dom\Element) {
                $slots[] = $this->map($c, $this->at($where, $c, $i++));
            } elseif ($c instanceof \Dom\Text && ($t = self::collapse($c->data)) !== '') {
                $slots[] = new Text($t);
            }
        }

        return $slots;
    }

    /** The children as one node (a Row, or the child alone), with fences paired. */
    private function children(\Dom\Element $e, string $where): Node
    {
        return self::row(self::pair(array_values(array_filter($this->slots($e, $where), static fn (?Node $n): bool => $n !== null))));
    }

    /** @param list<Node> $nodes */
    private static function row(array $nodes): Node
    {
        return count($nodes) === 1 ? $nodes[0] : new Row($nodes);
    }

    private function style(\Dom\Element $e, string $where): Node
    {
        $content = $this->children($e, $where);
        $display = self::bool($e, 'displaystyle');

        return $display === null ? $content : new DisplayStyle($content, $display);
    }

    private function identifier(\Dom\Element $e): ?Node
    {
        $text = self::collapse((string) $e->textContent);
        if ($text === '') {
            return null;
        }
        $variant = strtolower(trim($e->getAttribute('mathvariant') ?? ''));
        if ($variant === 'normal') {
            return new Identifier($text, false);
        }
        if (in_array($variant, Styles::VARIANTS, true)) {
            $mapped = implode('', array_map(static fn (string $ch): string => mb_chr(Styles::variant(mb_ord($ch), $variant)), mb_str_split($text)));

            return new Identifier($mapped, false);
        }

        return new Identifier($text);
    }

    private function operator(\Dom\Element $e): ?Node
    {
        $text = self::collapse((string) $e->textContent);
        if (preg_match('/^[\s\x{00A0}\x{2061}-\x{2064}]*$/u', $text) === 1) {
            return null;
        }

        return new Operator($text, self::bool($e, 'stretchy'), self::bool($e, 'largeop'), self::bool($e, 'movablelimits'));
    }

    /** A width in em (12pt basis); 0 when missing or unparseable. */
    private static function width(?string $w): float
    {
        $w = strtolower(trim($w ?? ''));
        if (preg_match('/^(negative)?(veryverythin|verythin|thin|medium|thick|verythick|veryverythick|veryveryhigh)mathspace$/', $w, $m) === 1) {
            return (($m[1] === '' ? 1 : -1) * self::NAMED_SPACES[$m[2]]) / 18;
        }
        if (preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+))\s*(em|ex|pt|px|mm|cm|in)$/', $w, $m) !== 1) {
            return 0.0;
        }
        $v = (float) $m[1];

        return match ($m[2]) {
            'em' => $v,
            'ex' => $v / 2,
            default => $v * self::UNIT_PT[$m[2]] / 12,
        };
    }

    private static function zeroThickness(?string $t): bool
    {
        return preg_match('/^\s*[+-]?(?:0+\.?0*|\.0+)\s*(?:em|ex|pt|px|mm|cm|in|%)?\s*$/i', $t ?? '') === 1;
    }

    private static function isAccent(Node $over): bool
    {
        return $over instanceof Operator && Styles::accentMark($over->char) !== null;
    }

    /**
     * A construct taking $arity children. Missing children are empty rows, extra ones follow the construct in a row;
     * either way the construct is wrapped in a Fallback with an approximated note (once per element name).
     *
     * @param list<?Node> $slots
     * @param \Closure(list<Node>): Node $build
     */
    private function fixed(string $name, int $arity, array $slots, string $where, \Closure $build): Node
    {
        $nodes = array_map(static fn (?Node $n): Node => $n ?? new Row([]), $slots);
        $given = count($nodes);
        $args = array_slice($nodes, 0, $arity);
        while (count($args) < $arity) {
            $args[] = new Row([]);
        }
        $node = $build($args);
        if ($given > $arity) {
            $node = new Row([$node, ...array_slice($nodes, $arity)]);
        }
        if ($given === $arity || isset($this->noted[$name])) {
            return $node;
        }
        $this->noted[$name] = true;

        return new Fallback($node, new MathNote(NoteLevel::Approximated, $name, '<' . $name . '> with ' . $given . ($given === 1 ? ' child' : ' children'), $where));
    }

    /** @param list<?Node> $slots */
    private function fenced(\Dom\Element $e, array $slots): Node
    {
        $open = $e->hasAttribute('open') ? $e->getAttribute('open') : '(';
        $close = $e->hasAttribute('close') ? $e->getAttribute('close') : ')';
        $seps = mb_str_split((string) preg_replace('/\s+/u', '', $e->hasAttribute('separators') ? (string) $e->getAttribute('separators') : ','));
        $nodes = [];
        $placed = 0;
        foreach ($slots as $n) {
            if ($n === null) {
                continue;
            }
            if ($nodes !== [] && $seps !== []) {
                $nodes[] = new Operator($seps[min($placed++, count($seps) - 1)]);
            }
            $nodes[] = $n;
        }

        return new Fenced(trim((string) $open), trim((string) $close), self::row($nodes));
    }

    /**
     * Pairs fences. Brackets first, left to right with a stack: a matching closer replaces the run from its opener with
     * a Fenced node, and unmatched brackets stay plain operators in place. Then, level by level (the row itself and
     * each Fenced's content), `|` pairs with the next `|` and `‖` with the next `‖` among that level's siblings.
     *
     * @param list<Node> $nodes
     *
     * @return list<Node>
     */
    private static function pair(array $nodes): array
    {
        $out = [];
        /** @var list<array{int, Operator}> $stack */
        $stack = [];
        foreach ($nodes as $n) {
            if (! $n instanceof Operator || $n->stretchy === false) {
                $out[] = $n;
                continue;
            }
            $ch = $n->char;
            if (OperatorDictionary::isOpening($ch)) {
                $stack[] = [count($out), $n];
                $out[] = $n;
            } elseif (OperatorDictionary::isClosing($ch)) {
                $found = null;
                for ($k = count($stack) - 1; $k >= 0; $k--) {
                    if (in_array($ch, self::PAIRS[$stack[$k][1]->char] ?? [], true)) {
                        $found = $k;
                        break;
                    }
                }
                if ($found === null) {
                    $out[] = $n;
                } else {
                    [$idx, $opener] = $stack[$found];
                    $stack = array_slice($stack, 0, $found);
                    $between = array_splice($out, $idx + 1);
                    $out[$idx] = new Fenced($opener->char, $ch, self::row(self::bars($between)));
                }
            } else {
                $out[] = $n;
            }
        }

        return self::bars($out);
    }

    /**
     * Pairs `|` with the next `|` and `‖` with the next `‖` among these siblings; a lone bar stays plain.
     *
     * @param list<Node> $nodes
     *
     * @return list<Node>
     */
    private static function bars(array $nodes): array
    {
        $isBar = static fn (Node $n, string $ch): bool => $n instanceof Operator && $n->stretchy !== false && $n->char === $ch;
        $out = [];
        $count = count($nodes);
        for ($i = 0; $i < $count; $i++) {
            $n = $nodes[$i];
            $close = null;
            if ($n instanceof Operator && $n->stretchy !== false && ($n->char === '|' || $n->char === '‖')) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($isBar($nodes[$j], $n->char)) {
                        $close = $j;
                        break;
                    }
                }
            }
            if ($close === null) {
                $out[] = $n;
                continue;
            }
            $out[] = new Fenced($n->char, $n->char, self::row(self::bars(array_slice($nodes, $i + 1, $close - $i - 1))));
            $i = $close;
        }

        return $out;
    }
}
