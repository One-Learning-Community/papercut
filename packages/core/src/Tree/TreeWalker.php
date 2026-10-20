<?php

declare(strict_types=1);

namespace Papercut\Tree;

use Papercut\{Component, Document, Link, Node, Page, Table, TableCell, TableRow, View};
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Style\{ComputedStyle, StyleResolver};

/**
 * Walks a Document's expanded node tree without laying it out: pages, then
 * regions (background, header, body, footer), then nodes, with computed styles
 * and paths. Body paths match layout's; header, footer and background use
 * their region names where layout has an anonymous column.
 */
final class TreeWalker
{
    public function __construct(private readonly StyleResolver $styles)
    {
    }

    public function walk(Document $doc, TreeVisitor $visitor): void
    {
        $pages = $doc->pages();
        $root = $this->styles->root();
        foreach ($pages as $i => $page) {
            $name = count($pages) > 1 ? sprintf('page[%d]', $i) : 'page';
            $visitor->enterPage($page, $i);
            foreach ($this->regions($page) as [$region, $nodes]) {
                if ($nodes === []) {
                    continue;
                }
                $visitor->enterRegion($region, $root);
                foreach ($nodes as $j => $node) {
                    $this->visit(Expander::expand($node), $root, $name . ' > ' . $region->value, $j, $visitor);
                }
                $visitor->leaveRegion($region);
            }
            $visitor->leavePage($page, $i);
        }
    }

    /** @return list<array{Region, list<Node|Component>}> */
    private function regions(Page $page): array
    {
        return [
            [Region::Background, $page->backgroundNodes()],
            [Region::Header, $page->headerNodes()],
            [Region::Body, $page->bodyNodes()],
            [Region::Footer, $page->footerNodes()],
        ];
    }

    private function visit(Node $node, ComputedStyle $parent, string $parentPath, int $index, TreeVisitor $visitor, ?ComputedStyle $rowStyle = null): void
    {
        $provisional = $parentPath . ' > ' . NodePath::segment($node, null, $index);
        try {
            $style = $node instanceof TableCell && $rowStyle !== null
                ? $this->styles->resolveInTable($node, $rowStyle)
                : $this->styles->resolve($node, $parent);
        } catch (UnknownAttribute | InvalidValue $e) {
            throw $e->within('at ' . $provisional);
        }
        $path = $parentPath . ' > ' . NodePath::segment($node, $style, $index);

        if ($visitor->enter($node, $style, $path)) {
            $this->children($node, $style, $path, $visitor);
        }
        $visitor->leave($node, $style, $path);
    }

    private function children(Node $node, ComputedStyle $style, string $path, TreeVisitor $visitor): void
    {
        if ($node instanceof View || $node instanceof Link || $node instanceof TableCell) {
            foreach ($node->children() as $i => $child) {
                $this->visit(Expander::expand($child), $style, $path, $i, $visitor);
            }
        } elseif ($node instanceof Table) {
            foreach ([...$node->headerRows(), ...$node->bodyRows()] as $r => $row) {
                $this->visit($row, $style, $path, $r, $visitor);
            }
        } elseif ($node instanceof TableRow) {
            foreach ($node->cells() as $j => $cell) {
                $this->visit($cell, $style, $path, $j, $visitor, $style);
            }
        }
    }
}
