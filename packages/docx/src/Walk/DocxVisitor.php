<?php

declare(strict_types=1);

namespace Papercut\Docx\Walk;

use Dskripchenko\PhpDocx\Element\{BlockElement, PageBreak as DocxPageBreak};
use Papercut\{Link, Node, Page, PageBreak, PageNumber, Spacer, TableCell, TableRow, Text, View};
use Papercut\Docx\Map\{Flow, TextMapper};
use Papercut\Export\Fidelity;
use Papercut\Layout\WidthResolver;
use Papercut\Style\{ComputedStyle, Direction};
use Papercut\Tree\{Region, TreeVisitor, Visit};
use Papercut\Value\{Length, Percent};

/**
 * Turns a tree walk into php-docx blocks (approach B2 of the multi-format spec): columns flow as paragraphs,
 * rows become borderless fixed-layout tables sized by WidthResolver, and a decorated box becomes a single-cell table.
 */
final class DocxVisitor implements TreeVisitor
{
    /** @var list<Frame> */
    private array $stack = [];
    /** @var array<string, list<BlockElement>> blocks per region of the current page */
    private array $regions = [];
    /** @var list<array{page: Page, regions: array<string, list<BlockElement>>}> */
    private array $pages = [];
    private ?Page $page = null;

    public function __construct(
        private readonly Fidelity $fidelity,
        private readonly WidthResolver $widths,
        private readonly TextMapper $text,
    ) {
    }

    /** @return list<array{page: Page, regions: array<string, list<BlockElement>>}> */
    public function pages(): array
    {
        return $this->pages;
    }

    public function enterPage(Page $page, int $index): void
    {
        $this->page = $page;
        $this->regions = [];
    }

    public function leavePage(Page $page, int $index): void
    {
        $this->pages[] = ['page' => $page, 'regions' => $this->regions];
    }

    public function enterRegion(Region $region, ComputedStyle $root): void
    {
        $page = $this->page;
        $width = $page === null ? 0.0 : $page->width() - $page->margins()->left->points - $page->margins()->right->points;
        $this->stack = [new Frame(null, $root, $width, $width)];
    }

    public function leaveRegion(Region $region): void
    {
        $root = array_pop($this->stack);
        \assert($root instanceof Frame);
        $this->regions[$region->value] = Flow::blocks($root);
    }

    public function enter(Node $node, ComputedStyle $style, Visit $at): bool
    {
        $parent = $this->top();
        $width = $this->outerWidth($node, $style, $parent, $at);
        $content = max(0.0, $width - $style->padding->horizontal() - $style->borderWidth->horizontal());
        $isRow = ($node instanceof View || $node instanceof Link) && $style->direction === Direction::Row;
        $frame = new Frame($node, $style, $width, $content, $isRow);
        if ($isRow && $node instanceof View) {
            $frame->lines = $this->widths->flexLines($node, $style, $width);
            foreach ($frame->lines as $line) {
                foreach ($line as $slot) {
                    $frame->slots[$slot->index] = $slot;
                }
            }
        }
        $this->stack[] = $frame;

        // Leaves produce their blocks in leave(); containers wait for their children.
        return !($node instanceof Text || $node instanceof Spacer || $node instanceof PageBreak || $node instanceof PageNumber);
    }

    public function leave(Node $node, ComputedStyle $style, Visit $at): void
    {
        $frame = array_pop($this->stack);
        \assert($frame instanceof Frame);
        $parent = $this->top();

        $blocks = match (true) {
            $node instanceof Text => [$this->text->paragraph($node, $style, $at)],
            $node instanceof PageNumber => [$this->text->pageNumber($node, $style)],
            $node instanceof Spacer => $this->spacer($node, $parent, $at),
            $node instanceof PageBreak => [new DocxPageBreak()],
            default => Flow::blocks($frame),
        };
        $parent->items[] = ['blocks' => $blocks, 'style' => $style, 'index' => $at->index(), 'boxed' => false];
    }

    private function top(): Frame
    {
        $top = end($this->stack);
        \assert($top instanceof Frame);

        return $top;
    }

    /** Border-box width: the row slot, the table cell's columns, an explicit width, or the parent's content box. */
    private function outerWidth(Node $node, ComputedStyle $style, Frame $parent, Visit $at): float
    {
        if ($parent->isRow) {
            return $parent->slots[$at->index()]->width ?? 0.0;
        }
        if ($node instanceof TableCell && $parent->node instanceof TableRow) {
            $table = $this->stack[count($this->stack) - 2] ?? null;
            $cols = $table instanceof Frame ? $table->columns : [];

            return array_sum(array_slice($cols, $parent->column, $node->span()));
        }
        if ($node instanceof TableRow) {
            return $parent->content;
        }
        $avail = max(0.0, $parent->content - $style->margin->horizontal());
        $w = match (true) {
            $style->width instanceof Length => $style->width->points,
            $style->width instanceof Percent => $parent->content * $style->width->value / 100,
            default => $avail,
        };
        if ($style->maxWidth instanceof Length) {
            $w = min($w, $style->maxWidth->points);
        }

        return min($w, $avail);
    }

    /** @return list<BlockElement> */
    private function spacer(Spacer $sp, Frame $parent, Visit $at): array
    {
        if ($parent->isRow) {
            return [];
        }
        $fixed = $sp->fixedSize();
        if ($fixed === null) {
            $this->fidelity->approximated('flexible spacer', $at->path());

            return [];
        }

        return [Flow::hairline($fixed->points)];
    }
}
