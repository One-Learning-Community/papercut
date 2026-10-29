<?php

declare(strict_types=1);

namespace Papercut\Docx\Map;

use Dskripchenko\PhpDocx\Element\{BlockElement, Field, Hyperlink, Paragraph, Run, Table};
use Dskripchenko\PhpDocx\Style\{ParagraphStyle, RunStyle, TableStyle};
use Papercut\Export\Fidelity;
use Papercut\Docx\Package\Numbering;
use Papercut\ListNode;
use Papercut\Style\ListMarker;

/**
 * List items as real Word numbering: the first paragraph of an item carries a reserved style id that DocxPatcher turns
 * into `w:numPr`, and Word draws the label and the hanging indent. A list with no marker only indents.
 */
final class ListMapper
{
    public const int INDENT = 360;

    /** @var list<array{ordered: bool, numId: ?int, marker: ListMarker, typed: bool, numbers: list<int>, next: int}> open lists, outermost first */
    private array $open = [];

    /** @var list<list<array{ordered: bool, numId: ?int, marker: ListMarker, typed: bool, numbers: list<int>, next: int}>> lists open outside the table cells being walked */
    private array $saved = [];

    /** @var ?\WeakMap<BlockElement, true> blocks whose indent is already their total under the list level */
    private static ?\WeakMap $settled = null;

    public function __construct(private readonly Numbering $numbering, private readonly Fidelity $fidelity)
    {
    }

    /** Whether a list item already produced this block (its indent is its total under the list level). */
    private static function isSettled(BlockElement $b): bool
    {
        return self::$settled !== null && isset(self::$settled[$b]);
    }

    private static function settle(BlockElement $b): void
    {
        self::$settled ??= new \WeakMap();
        self::$settled[$b] = true;
    }

    /** A copy of a block (spacing, pagination) is as settled as the original. */
    public static function carry(BlockElement $from, BlockElement $to): void
    {
        if (self::isSettled($from)) {
            self::settle($to);
        }
    }

    public function enter(ListNode $list, string $path): void
    {
        // The marker follows the depth of its kind across every enclosing list, as the PDF counts it; the level only counts the lists since the last cell.
        $depth = 1;
        foreach ([...$this->saved, $this->open] as $stack) {
            $depth += count(array_filter($stack, static fn (array $l): bool => $l['ordered'] === $list->isOrdered()));
        }
        $marker = $list->markerStyle() ?? ListMarker::forDepth($list->isOrdered(), $depth);
        $numbers = $list->numbers();
        $typed = $marker !== ListMarker::None && !self::numberable($marker, $numbers);
        if ($typed) {
            $this->fidelity->approximated('list numbering', $path);
        }
        $this->open[] = [
            'ordered' => $list->isOrdered(),
            'numId' => $marker === ListMarker::None || $typed ? null : $this->numbering->instance($marker, $numbers[0] ?? $list->startNumber()),
            'marker' => $marker,
            'typed' => $typed,
            'numbers' => $numbers,
            'next' => 0,
        ];
    }

    public function leave(): void
    {
        array_pop($this->open);
    }

    /** A table cell (or a box, which Word draws as one) starts its lists from level 0, whatever list it sits in. */
    public function enterCell(): void
    {
        $this->saved[] = $this->open;
        $this->open = [];
    }

    public function leaveCell(): void
    {
        $this->open = array_pop($this->saved) ?? [];
    }

    /**
     * One item's blocks: the first paragraph is numbered, later paragraphs and tables sit under the level.
     * A paragraph that already has an indent (a container's) keeps it, added to the level's.
     *
     * @param list<BlockElement> $blocks
     * @return list<BlockElement>
     */
    public function item(array $blocks, string $path): array
    {
        $i = count($this->open) - 1;
        if ($i < 0) {
            return $blocks;
        }
        $ilvl = $i;
        $list = $this->open[$i];
        $n = $list['numbers'][$list['next']] ?? null;
        $numId = $list['numId'];
        if ($numId !== null && self::isNumeric($list['marker']) && $n !== null && $list['next'] > 0 && $n !== $list['numbers'][$list['next'] - 1] + 1) {
            $numId = $this->numbering->instance($list['marker'], $n);
            $this->fidelity->approximated('list item number', $path);
        }
        $this->open[$i] = [...$list, 'numId' => $numId, 'next' => $list['next'] + 1];
        $label = $list['typed'] && $n !== null ? $list['marker']->label($n) : '';
        $marked = $numId !== null || $label !== '';
        $text = self::INDENT * ($ilvl + 1);

        $first = $blocks[0] ?? null;
        if ($marked && $first instanceof Paragraph && Flow::isHairline($first)) {
            // A spacing hairline can't carry a label (Word clips it): its spacing and page break move to a paragraph of normal height.
            // The hairline's space after was its margin less its own 2-twip line; a normal line gives the full margin back.
            $after = $first->style->spaceAfterTwips;
            $blocks[0] = $first = new Paragraph([], new ParagraphStyle(...[...get_object_vars($first->style), 'spaceAfterTwips' => $after > 0 ? $after + 2 : 0, 'lineSpacingTwips' => null, 'lineSpacingRule' => null]));
        }
        if ($marked && !($first instanceof Paragraph && !self::isSettled($first))) {
            array_unshift($blocks, new Paragraph([]));
        }
        foreach ($blocks as $k => $b) {
            if (self::isSettled($b)) {
                continue;
            }
            if ($b instanceof Table) {
                $b = new Table($b->rows, new TableStyle(...[...get_object_vars($b->style), 'indentTwips' => ($b->style->indentTwips ?? 0) + $text]), $b->caption, $b->gridColumnsTwips);
            } elseif (!$b instanceof Paragraph) {
                continue;
            } elseif ($k === 0 && $numId !== null) {
                $offset = $b->style->indentLeftTwips;
                $b = new Paragraph($b->children, $b->style, $b->headingLevel, Numbering::marker($numId, $ilvl, $b->headingLevel));
                if ($offset > 0) {
                    $b = Flow::withParagraph($b, ['indentLeftTwips' => $offset + $text, 'indentFirstLineTwips' => -self::INDENT]);
                }
            } elseif ($k === 0 && $label !== '') {
                $b = self::typed($b, $label, $text);
            } else {
                $b = Flow::withParagraph($b, ['indentLeftTwips' => $b->style->indentLeftTwips + $text]);
            }
            self::settle($b);
            $blocks[$k] = $b;
        }

        return $blocks;
    }

    private static function isNumeric(ListMarker $marker): bool
    {
        return in_array($marker, [ListMarker::Decimal, ListMarker::LowerAlpha, ListMarker::UpperAlpha, ListMarker::LowerRoman, ListMarker::UpperRoman], true);
    }

    /**
     * Whether Word's numbering draws every one of these numbers as this marker does.
     *
     * @param list<int> $numbers */
    private static function numberable(ListMarker $marker, array $numbers): bool
    {
        if (!self::isNumeric($marker)) {
            return true;
        }
        $roman = $marker === ListMarker::LowerRoman || $marker === ListMarker::UpperRoman;
        foreach ($numbers as $n) {
            if ($n < 1 || ($roman && $n > 3999)) {
                return false;
            }
        }

        return true;
    }

    /** The label as text with a hanging indent: the way lists Word can't number are drawn. */
    private static function typed(Paragraph $p, string $label, int $text): Paragraph
    {
        $children = $p->children;
        $first = $children[0] ?? null;
        if ($first instanceof Hyperlink) {
            $first = $first->children[0] ?? null;
        }
        $style = $first instanceof Run || $first instanceof Field ? $first->style : new RunStyle();
        array_unshift($children, new Run($label . "\t", $style));

        return Flow::withParagraph(new Paragraph($children, $p->style, $p->headingLevel, $p->styleId), [
            'indentLeftTwips' => $p->style->indentLeftTwips + $text,
            'indentFirstLineTwips' => -self::INDENT,
        ]);
    }
}
