<?php

declare(strict_types=1);

namespace Papercut\Math\Mathml;

use Papercut\Math\MathNote;
use Papercut\Math\Node\DisplayStyle;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Grid;
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
 * Reads MathML (from editors, Word, hand-written) into formula nodes. Well-formed markup is read as XML first, then anything
 * else goes through the HTML5 parser (so entities such as &ApplyFunction; resolve); prefixed (`mml:`) or unprefixed
 * elements both work.
 */
final class MathmlReader
{
    /** Opening fence to the closers it pairs with. */
    private const PAIRS = [
        '(' => [')', ']'], '[' => [']', ')'], '{' => ['}'], '⟨' => ['⟩'], '⌈' => ['⌉'], '⌊' => ['⌋'], '⟪' => ['⟫'],
    ];

    private const NAMED_SPACES = [
        'veryverythin' => 1, 'verythin' => 2, 'thin' => 3, 'medium' => 4, 'thick' => 5, 'verythick' => 6, 'veryverythick' => 7,
    ];

    /** Points per unit, for widths turned into em at 12pt. */
    private const UNIT_PT = ['pt' => 1.0, 'px' => 0.75, 'mm' => 72 / 25.4, 'cm' => 72 / 2.54, 'in' => 72.0];

    /** Elements with no drawing: images, scripts of other languages, embedded objects. */
    private const FOREIGN = ['img', 'svg', 'picture', 'video', 'audio', 'iframe', 'object', 'embed', 'canvas', 'mglyph'];

    private const MAX_DEPTH = 256;

    /** The longest string read, in bytes; the rest is dropped (and noted). */
    public const int MAX_INPUT = 1_048_576;

    /** The most text a formula keeps, in characters across all its tokens, as much as 16 KB of TeX could draw. */
    public const int MAX_TEXT = 16_384;

    /** The most elements a formula reads; the rest is dropped (and noted). */
    public const int MAX_ELEMENTS = 32_768;

    private const string TOO_LARGE = 'formula too large; the rest is dropped';

    /** Table attributes that are noted, not drawn, per element (columnalign/rowalign on the mtable itself are honoured). */
    private const IGNORED = [
        'mtable' => ['columnlines', 'rowlines', 'frame', 'rowspacing', 'columnwidth', 'equalrows', 'equalcolumns', 'align', 'groupalign'],
        'mtr' => ['columnalign', 'rowalign'],
        'mtd' => ['rowspan', 'columnspan', 'columnalign', 'rowalign'],
    ];

    /** @var array<string, true> element names already noted in this formula */
    private array $noted = [];

    private int $depth = 0;

    private int $elements = 0;

    private int $text = 0;

    /** A limit was reached: nothing more is read. */
    private bool $full = false;

    private function __construct()
    {
    }

    public static function read(string|\Dom\Element $input): MathmlResult
    {
        $reader = new self();
        $lost = null;
        $cut = is_string($input) && strlen($input) > self::MAX_INPUT;
        if ($cut) {
            $input = mb_strcut($input, 0, self::MAX_INPUT);
        }
        $math = is_string($input) ? self::parse($input, $lost) : $input;
        if ($math === null) {
            return new MathmlResult(new Row([]), false);
        }
        $node = $reader->map($math, self::name($math)) ?? new Row([]);
        if ($cut) {
            $node = $reader->note('#size', $node, NoteLevel::Approximated, 'math', self::TOO_LARGE, 'math') ?? $node;
        }
        if ($lost !== null) {
            $node = new Fallback($node, $lost);
        }

        return new MathmlResult($node, strtolower(trim($math->getAttribute('display') ?? '')) === 'block');
    }

    /**
     * Well-formed XML is read as XML (prefixed self-closing tags work); anything else goes through the HTML5 parser,
     * which resolves entities such as &ApplyFunction; but needs prefixed self-closing tags written out.
     */
    private static function parse(string $s, ?MathNote &$lost = null): ?\Dom\Element
    {
        // UTF-16 hides "<!DOCTYPE" from the byte checks below: decode it so they see the text.
        if (str_starts_with($s, "\xFF\xFE") || str_starts_with($s, "\xFE\xFF")) {
            $s = mb_convert_encoding(substr($s, 2), 'UTF-8', str_starts_with($s, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        }
        if (str_starts_with($s, "\u{FEFF}")) {
            $s = substr($s, 3);
        }
        if (preg_match('/^\s*«/u', $s) === 1) {
            $s = SafeXml::decode($s);
        }
        $s = preg_replace('/^\s*<\?xml[^>]*\?>/', '', $s) ?? $s;
        $s = preg_replace('~<((?:[\w.-]+:)?annotation(?:-xml)?)\b(?:"[^"]*"|\'[^\']*\'|[^>"\'/]|/(?!>))*+/>~i', '', $s) ?? $s;
        $s = preg_replace('~<((?:[\w.-]+:)?annotation(?:-xml)?)\b[^>]*>.*?</\1\s*>~is', '', $s) ?? $s;

        if (stripos($s, '<!DOCTYPE') === false && stripos($s, '<!ENTITY') === false) {
            try {
                $xml = \Dom\XMLDocument::createFromString($s, LIBXML_NOERROR | LIBXML_NONET);
                // libxml sniffs UTF-16/32 itself, so a DTD can arrive past the byte checks above: any doctype means HTML path.
                if ($xml->doctype === null && $xml->documentElement !== null && ($found = self::findMath($xml->documentElement, true)) !== null) {
                    return $found;
                }
            } catch (\Throwable) {
                // not well-formed XML: fall through to HTML5
            }
        }
        // Possessive, so a long run of spaces in a tag costs one pass, not one per position.
        $html = preg_replace('~<([A-Za-z][\w.-]*:[\w.-]+)((?:"[^"]*"|\'[^\']*\'|[^>"\'/]|/(?!>))*+)/>~', '<$1$2></$1>', $s) ?? $s;
        $find = static function (string $src) use (&$lost): ?\Dom\Element {
            $doc = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $src, LIBXML_NOERROR, 'UTF-8');
            $body = $doc->body;
            $math = $body === null ? null : self::findMath($body);
            if ($math !== null) {
                $lost = self::contentAfter($math, $body);
            }

            return $math;
        };

        return $find($html) ?? $find('<math>' . $html . '</math>');
    }

    /** A note for content following the math element in the body: an HTML tag inside math ends it, losing the rest. */
    private static function contentAfter(\Dom\Element $math, \Dom\Element $body): ?MathNote
    {
        for ($n = $math; $n !== null && ! $n->isSameNode($body); $n = $n->parentNode) {
            for ($next = $n->nextSibling; $next !== null; $next = $next->nextSibling) {
                if ($next instanceof \Dom\Element) {
                    return new MathNote(NoteLevel::Dropped, self::name($next), 'content after the math element (not drawn)', 'math');
                }
                if ($next instanceof \Dom\Text && trim($next->data) !== '') {
                    return new MathNote(NoteLevel::Dropped, 'text', 'content after the math element (not drawn)', 'math');
                }
            }
        }

        return null;
    }

    private static function findMath(\Dom\Element $parent, bool $self = false): ?\Dom\Element
    {
        if ($self && self::name($parent) === 'math') {
            return $parent;
        }
        foreach (self::elements($parent) as $child) {
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
        return trim(preg_replace('/[ \t\n\r\f]+/', ' ', $t) ?? $t, " \t\n\r\f");
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
        if ($this->full) {
            return null;
        }
        if (! $this->count()) {
            return $this->note('#size', null, NoteLevel::Approximated, 'math', self::TOO_LARGE, $where);
        }
        $node = $this->mapWithin($e, $where);
        if ($this->full) {
            $node = $this->note('#size', $node, NoteLevel::Approximated, 'math', self::TOO_LARGE, $where);
        }

        return $node;
    }

    /**
     * Counts one more element read; false, and stops the reading, past MAX_ELEMENTS (or once a limit was reached).
     *
     * @phpstan-impure
     */
    private function count(): bool
    {
        if (! $this->full && ++$this->elements > self::MAX_ELEMENTS) {
            $this->full = true;
        }

        return ! $this->full;
    }

    /**
     * $text cut to what is left of MAX_TEXT, counting it; reaching the limit stops the reading.
     *
     * @phpstan-impure
     */
    private function budget(string $text): string
    {
        $len = mb_strlen($text);
        $left = self::MAX_TEXT - $this->text;
        if ($len > $left) {
            $this->full = true;
            $text = mb_substr($text, 0, $left);
            $len = $left;
        }
        $this->text += $len;

        return $text;
    }

    private function mapWithin(\Dom\Element $e, string $where): ?Node
    {
        if ($this->depth >= self::MAX_DEPTH) {
            $text = $this->budget(self::collapse((string) $e->textContent));

            return $text === '' ? null : $this->note('#depth', new Text($text), NoteLevel::Approximated, 'math', 'nesting deeper than 256 levels (flattened)', $where);
        }
        $this->depth++;
        try {
            return $this->dispatch($e, $where);
        } finally {
            $this->depth--;
        }
    }

    /** $content wrapped in a Fallback carrying the note, the first time $key is seen; bare afterwards. */
    private function note(string $key, ?Node $content, NoteLevel $level, string $element, string $reason, string $where): ?Node
    {
        if (isset($this->noted[$key])) {
            return $content;
        }
        $this->noted[$key] = true;

        return new Fallback($content, new MathNote($level, $element, $reason, $where));
    }

    private function dispatch(\Dom\Element $e, string $where): ?Node
    {
        $name = self::name($e);
        if (in_array($name, self::FOREIGN, true)) {
            return $this->note($name, null, NoteLevel::Dropped, $name, 'not drawn', $where);
        }

        return match ($name) {
            'math', 'mrow', 'mpadded' => $this->children($e, $where),
            'mstyle' => $this->style($e, $where),
            'semantics' => $this->slots($e, $where, 1)[0] ?? new Row([]),
            'annotation', 'annotation-xml', 'mprescripts', 'none' => null,
            'mi', 'mn', 'mtext', 'ms', 'mo' => $this->token($e, $name, $where),
            'mspace' => $this->space(self::width($e->getAttribute('width')) ?? 0.0, $where),
            'mphantom' => new Phantom($this->children($e, $where)),
            'msqrt' => new Root($this->children($e, $where)),
            'mfrac' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Fraction($a[0], $a[1], ! self::zeroThickness($e->getAttribute('linethickness')))),
            'mroot' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Root($a[0], $a[1])),
            'msub' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Scripts($a[0], $a[1], null)),
            'msup' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new Scripts($a[0], null, $a[1])),
            'msubsup' => $this->fixed($name, 3, $this->slots($e, $where), $where, fn (array $a): Node => new Scripts($a[0], $a[1], $a[2])),
            'munder' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new UnderOver($a[0], $a[1], null, false, self::bool($e, 'accentunder') ?? self::marked($e, 1))),
            'mover' => $this->fixed($name, 2, $this->slots($e, $where), $where, fn (array $a): Node => new UnderOver($a[0], null, $a[1], self::bool($e, 'accent') ?? self::isAccent($a[1], $e, 1))),
            'munderover' => $this->fixed($name, 3, $this->slots($e, $where), $where, fn (array $a): Node => new UnderOver($a[0], $a[1], $a[2], self::bool($e, 'accent') ?? self::isAccent($a[2], $e, 2), self::bool($e, 'accentunder') ?? self::marked($e, 1))),
            'mfenced' => $this->fenced($e, $this->slots($e, $where)),
            'mtable' => $this->table($e, $where),
            'maction' => $this->note($name, $this->choice($e, $where), NoteLevel::Approximated, $name, 'drawn as its first choice', $where),
            default => $this->note($name, $this->children($e, $where), NoteLevel::Approximated, $name, 'drawn as its contents', $where),
        };
    }

    /** The table as a Grid, wrapped in a Fallback for each distinct attribute that is not drawn. */
    private function table(\Dom\Element $e, string $where): Node
    {
        $this->depth += 2;
        try {
            return $this->tableRows($e, $where);
        } finally {
            $this->depth -= 2;
        }
    }

    private function tableRows(\Dom\Element $e, string $where): Node
    {
        $ignored = self::ignoredOn($e, 'mtable', $where);
        $rows = [];
        $rowNotes = [];
        $cellNotes = [];
        $labelled = false;
        $i = 0;
        foreach (self::elements($e) as $tr) {
            if (! $this->count()) {
                break;
            }
            $rowWhere = $this->at($where, $tr, $i++);
            $trName = self::name($tr);
            array_push($rowNotes, ...self::ignoredOn($tr, 'mtr', $rowWhere, $trName));
            $cells = [];
            $j = 0;
            foreach (self::elements($tr) as $td) {
                if (! $this->count()) {
                    break;
                }
                $cellWhere = $this->at($rowWhere, $td, $j++);
                if ($trName === 'mlabeledtr' && $j === 1) {
                    $labelled = true;
                    continue;
                }
                array_push($cellNotes, ...self::ignoredOn($td, 'mtd', $cellWhere));
                $cells[] = $this->children($td, $cellWhere);
            }
            $rows[] = $cells;
        }

        $columns = 0;
        foreach ($rows as $cells) {
            $columns = max($columns, count($cells));
        }
        $tokens = self::columnAlign($e->getAttribute('columnalign'));
        $align = [];
        for ($k = 0; $tokens !== [] && $k < $columns; $k++) {
            $align[] = $tokens[$k % count($tokens)];
        }

        $spacing = [];
        foreach (preg_split('/\s+/', trim($e->getAttribute('columnspacing') ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $em = self::width($token);
            if ($em !== null) {
                $spacing[] = max(0.0, $em);
            } else {
                $spacing[] = 1.0;
                $ignored[] = ['mtable', 'columnspacing', $where];
            }
        }
        $ignored = array_values(array_unique($ignored, SORT_REGULAR));

        $grid = new Grid(
            $rows,
            $align,
            self::rowAlign($e->getAttribute('rowalign')),
            self::bool($e, 'displaystyle') ?? false,
            $spacing,
        );
        $node = $grid;
        if ($labelled) {
            $node = $this->note('mlabeledtr', $node, NoteLevel::Approximated, 'mlabeledtr', 'row label not drawn', $where) ?? $node;
        }
        foreach ([...$ignored, ...$rowNotes, ...$cellNotes] as [$element, $attr, $path]) {
            $node = $this->note($element . '/' . $attr, $node, NoteLevel::Approximated, $element, $attr . ' is not drawn', $path) ?? $node;
        }

        return $node;
    }

    /**
     * The attributes of $kind (an IGNORED key) that $el sets to something the grid does not draw.
     *
     * @param string|null $element the element name for the note, when it differs from $kind (mlabeledtr rows)
     *
     * @return list<array{string, string, string}> element, attribute, path
     */
    private static function ignoredOn(\Dom\Element $el, string $kind, string $path, ?string $element = null): array
    {
        $out = [];
        foreach (self::IGNORED[$kind] as $attr) {
            if (self::notDrawn($el, $attr)) {
                $out[] = [$element ?? $kind, $attr, $path];
            }
        }

        return $out;
    }

    /** Whether the attribute is present with a value that asks for something the grid does not draw. */
    private static function notDrawn(\Dom\Element $e, string $attr): bool
    {
        $raw = $e->getAttribute($attr);
        $v = strtolower(trim($raw ?? ''));
        if ($v === '') {
            return false;
        }

        return match ($attr) {
            'columnlines', 'rowlines', 'frame' => preg_match('/[^\s]+/', str_replace('none', '', $v)) === 1,
            'rowspan', 'columnspan' => ctype_digit($v) && (int) $v > 1,
            'equalrows', 'equalcolumns' => $v !== 'false',
            default => true,
        };
    }

    /**
     * @return list<string> the table's columnalign tokens (anything but left or right is center)
     */
    private static function columnAlign(?string $raw): array
    {
        $tokens = preg_split('/\s+/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(static fn (string $t): string => in_array(strtolower($t), ['left', 'right'], true) ? strtolower($t) : 'center', $tokens);
    }

    private static function rowAlign(?string $raw): string
    {
        $first = strtolower(preg_split('/\s+/', trim((string) $raw), -1, PREG_SPLIT_NO_EMPTY)[0] ?? '');

        return in_array($first, Grid::ROW_ALIGN, true) ? $first : 'baseline';
    }

    /** The child `selection` names (1-based), else the first. */
    private function choice(\Dom\Element $e, string $where): Node
    {
        $kids = self::elements($e);
        if ($kids === []) {
            return new Row([]);
        }
        $pick = (int) $e->getAttribute('selection');
        $index = $pick >= 1 && $pick <= count($kids) ? $pick - 1 : 0;

        return $this->map($kids[$index], $this->at($where, $kids[$index], $index)) ?? new Row([]);
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

    /** A token element (mi, mn, mo, mtext, ms); an image or glyph inside it is not drawn and is noted, its text kept. */
    private function token(\Dom\Element $e, string $name, string $where): ?Node
    {
        $node = match ($name) {
            'mi' => $this->identifier($e),
            'mn' => ($t = $this->budget(self::collapse((string) $e->textContent))) === '' ? null : new Number(self::variantText($e, $t)),
            'mtext' => ($t = $this->budget(self::collapse((string) $e->textContent))) === '' ? null : new Text(self::variantText($e, $t)),
            'ms' => new Text('"' . self::variantText($e, $this->budget(self::collapse((string) $e->textContent))) . '"'),
            default => $this->operator($e, $where),
        };
        $i = 0;
        foreach (self::elements($e) as $child) {
            $childName = self::name($child);
            if (in_array($childName, self::FOREIGN, true)) {
                $node = $this->note($childName, $node, NoteLevel::Dropped, $childName, 'not drawn', $this->at($where, $child, $i));
            }
            $i++;
        }

        return $node;
    }

    /** $text with the element's mathvariant applied (none for normal or an unknown variant). */
    private static function variantText(\Dom\Element $e, string $text): string
    {
        $variant = strtolower(trim($e->getAttribute('mathvariant') ?? ''));
        if (! in_array($variant, Styles::VARIANTS, true)) {
            return $text;
        }

        return implode('', array_map(static fn (string $ch): string => mb_chr(Styles::variant(mb_ord($ch), $variant)), mb_str_split($text)));
    }

    private function identifier(\Dom\Element $e): ?Node
    {
        $text = $this->budget(self::collapse((string) $e->textContent));
        if ($text === '') {
            return null;
        }
        $variant = strtolower(trim($e->getAttribute('mathvariant') ?? ''));
        // A multi-letter name is a function when it is a standard one (any variant), or the invisible function application follows it
        // (or the script or limit element it is the base of).
        $function = mb_strlen($text) > 1 && (in_array($text, OperatorDictionary::FUNCTION_NAMES, true) || self::applied($e));
        if ($variant === 'normal') {
            return new Identifier($text, false, $function);
        }

        return in_array($variant, Styles::VARIANTS, true) ? new Identifier(self::variantText($e, $text), false, $function) : new Identifier($text, null, $function);
    }

    /** Whether U+2061 (ApplyFunction) follows this element, or the msub/msup/msubsup/munder/mover/munderover it is the base of. */
    private static function applied(\Dom\Element $e): bool
    {
        $parent = $e->parentElement;
        if ($parent !== null && in_array($parent->localName, ['msub', 'msup', 'msubsup', 'munder', 'mover', 'munderover'], true) && $parent->firstElementChild === $e) {
            $e = $parent;
        }
        $next = $e->nextElementSibling;

        return $next !== null && $next->localName === 'mo' && trim((string) $next->textContent) === "\u{2061}";
    }

    private function operator(\Dom\Element $e, string $where): ?Node
    {
        $raw = (string) $e->textContent;
        $nbsp = preg_match_all('/\x{00A0}/u', $raw);
        if ($nbsp > 0 && preg_match('/^[ \t\n\r\f\x{00A0}]*$/u', $raw) === 1) {
            return new Space(0.25 * $nbsp);
        }
        $text = $this->budget(self::collapse($raw));
        if (preg_match('/^[\s\x{00A0}\x{2061}-\x{2064}]*$/u', $text) === 1) {
            return null;
        }

        $form = strtolower(trim($e->getAttribute('form') ?? ''));

        $bad = [];
        $min = self::size($e->getAttribute('minsize'), 'minsize', $bad);
        $max = self::size($e->getAttribute('maxsize'), 'maxsize', $bad);
        $op = new Operator(
            self::variantText($e, $text),
            self::bool($e, 'stretchy'),
            self::bool($e, 'largeop'),
            self::bool($e, 'movablelimits'),
            in_array($form, [OperatorDictionary::FORM_PREFIX, OperatorDictionary::FORM_INFIX, OperatorDictionary::FORM_POSTFIX], true) ? $form : null,
            lspace: self::spacing($e->getAttribute('lspace')),
            rspace: self::spacing($e->getAttribute('rspace')),
            minSize: $min,
            maxSize: $max,
        );

        $node = $op;
        foreach ($bad as $attr) {
            $node = $this->note('#opsize-' . $attr, $node, NoteLevel::Approximated, 'mo', $attr . ' is not drawn', $where) ?? $node;
        }

        return $node;
    }

    /** An lspace or rspace in mu (1/18 em); null when absent or unusable, so the operator dictionary decides. */
    private static function spacing(?string $v): ?float
    {
        $em = self::width($v);

        return $em === null ? null : max(0.0, min($em, 100.0)) * 18;
    }

    /**
     * A minsize/maxsize in em; null when absent, infinity, or unusable (relative to the natural size, zero or negative),
     * which adds the attribute name to $bad.
     *
     * @param list<string> $bad
     */
    private static function size(?string $v, string $attr, array &$bad): ?float
    {
        $v = strtolower(trim($v ?? ''));
        if ($v === '' || $v === 'infinity') {
            return null;
        }
        $em = self::width($v);
        if ($em === null || $em <= 0) {
            $bad[] = $attr;

            return null;
        }

        return min($em, 100.0);
    }

    private function space(float $em, string $where): Node
    {
        if (abs($em) <= Space::MAX_EM) {
            return new Space($em);
        }
        $space = new Space(max(-Space::MAX_EM, min(Space::MAX_EM, $em)));

        return $this->note('#space', $space, NoteLevel::Approximated, 'mspace', 'width limited to 100em', $where) ?? $space;
    }

    /** A length in em (12pt basis): a number with a unit, a named mathspace or a bare 0; null when missing or unparseable. */
    private static function width(?string $w): ?float
    {
        $w = strtolower(trim($w ?? ''));
        if ($w === '0') {
            return 0.0;
        }
        if (preg_match('/^(negative)?(veryverythin|verythin|thin|medium|thick|verythick|veryverythick)mathspace$/', $w, $m) === 1) {
            return (($m[1] === '' ? 1 : -1) * self::NAMED_SPACES[$m[2]]) / 18;
        }
        if (preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+))\s*(em|ex|pt|px|mm|cm|in)$/', $w, $m) !== 1) {
            return null;
        }
        $v = (float) $m[1];
        if (! is_finite($v)) {
            return null;
        }

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

    /**
     * The element children of $e, in order. `Dom\Element::$children` is PHP 8.5 only, and the floor is 8.4.
     *
     * @return list<\Dom\Element>
     */
    private static function elements(\Dom\Element $e): array
    {
        $out = [];
        for ($c = $e->firstElementChild; $c !== null; $c = $c->nextElementSibling) {
            $out[] = $c;
        }

        return $out;
    }

    /** Whether the $index-th element child of $e is an mo that says accent="true". */
    private static function marked(\Dom\Element $e, int $index): bool
    {
        $part = self::elements($e)[$index] ?? null;

        return $part !== null && self::name($part) === 'mo' && self::bool($part, 'accent') === true;
    }

    /** An over part is an accent when its mo says so, else (unless it says not) when it is an accent mark. */
    private static function isAccent(Node $over, \Dom\Element $e, int $index): bool
    {
        $part = self::elements($e)[$index] ?? null;
        if ($part !== null && self::name($part) === 'mo' && ($flag = self::bool($part, 'accent')) !== null) {
            return $flag;
        }

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

        // MathType writes angle brackets as open="&lt;" close="&gt;".
        $angle = static fn (string $c): string => ['<' => '⟨', '>' => '⟩'][$c] ?? $c;

        return new Fenced($angle(trim((string) $open)), $angle(trim((string) $close)), self::row($nodes));
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
                    // Openers still on the stack above this one are unmatched, inside the pair.
                    $inner = [];
                    foreach (array_slice($stack, $found + 1) as [$innerIdx, $innerOpener]) {
                        $inner[] = [$innerIdx - $idx - 1, $innerOpener];
                    }
                    $stack = array_slice($stack, 0, $found);
                    $between = self::foldLoneOpeners(array_splice($out, $idx + 1), $inner);
                    $out[$idx] = new Fenced($opener->char, $ch, self::row(self::bars($between)));
                }
            } else {
                $out[] = $n;
            }
        }

        // An opener with no closer, directly before a table, fences it alone: `{` then a table is cases.
        return self::bars(self::foldLoneOpeners(array_values($out), $stack));
    }

    /**
     * An opener with no closer, directly before a table, fences it alone: `{` then a table is cases. One forward pass.
     *
     * @param list<Node> $nodes
     * @param list<array{int, Operator}> $openers index in $nodes and node of each unmatched opener
     *
     * @return list<Node>
     */
    private static function foldLoneOpeners(array $nodes, array $openers): array
    {
        $fold = [];
        foreach ($openers as [$idx, $opener]) {
            $next = $nodes[$idx + 1] ?? null;
            if ($next !== null && Fallback::unwrap($next) instanceof Grid) {
                $fold[$idx] = $opener;
            }
        }
        if ($fold === []) {
            return $nodes;
        }
        $out = [];
        $count = count($nodes);
        for ($i = 0; $i < $count; $i++) {
            if (isset($fold[$i])) {
                $out[] = new Fenced($fold[$i]->char, '', $nodes[++$i]);
            } else {
                $out[] = $nodes[$i];
            }
        }

        return $out;
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
