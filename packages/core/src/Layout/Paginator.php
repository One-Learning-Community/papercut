<?php

declare(strict_types=1);

namespace Papercut\Layout;

use Papercut\{Column, Document, OverflowMode, Page};
use Papercut\Exception\LayoutOverflow;
use Papercut\Style\ComputedStyle;

/**
 * Flows each Page template's body across physical pages between its header
 * and footer, with its background behind. Header, footer and background
 * fragments are laid out once per template and reused on every page.
 */
final class Paginator
{
    private const int MAX_PAGES = 10_000;

    public function __construct(private readonly LayoutEngine $engine, private readonly LayoutContext $ctx)
    {
    }

    /** @return list<PlacedPage> */
    public function paginate(Document $doc): array
    {
        $out = [];
        foreach ($doc->pages() as $page) {
            $this->paginatePage($page, $out);
        }
        return $out;
    }

    /** @param list<PlacedPage> $out */
    private function paginatePage(Page $page, array &$out): void
    {
        $root = ComputedStyle::root($this->ctx->theme);
        $w = $page->width();
        $h = $page->height();
        $m = $page->margins();
        $left = $m->left->points;
        $top = $m->top->points;
        $contentWidth = $w - $left - $m->right->points;
        $contentHeight = $h - $top - $m->bottom->points;

        $header = $this->slot($page->headerNodes(), $root, $contentWidth);
        $footer = $this->slot($page->footerNodes(), $root, $contentWidth);
        $headerSpace = $header === null ? 0.0 : $header->height + $page->headerGapLength()->points;
        $footerSpace = $footer === null ? 0.0 : $footer->height + $page->footerGapLength()->points;

        $bodyY = $top + $headerSpace;
        $bodyHeight = $contentHeight - $headerSpace - $footerSpace;
        if ($bodyHeight <= 0.0) {
            throw new LayoutOverflow('page', 'header and footer leave no room for content');
        }

        $background = null;
        if ($page->backgroundNodes() !== []) {
            $background = $this->engine->layout(Column::make(...$page->backgroundNodes()), $root, new Constraints($w, null, true, $h))->fragment;
        }

        /** @var list<array{x: float, y: float, fragment: Fragment}> $before */
        $before = [];
        if ($background !== null) {
            $before[] = ['x' => 0.0, 'y' => 0.0, 'fragment' => $background];
        }
        if ($header !== null) {
            $before[] = ['x' => $left, 'y' => $top, 'fragment' => $header];
        }
        $after = $footer === null ? [] : [['x' => $left, 'y' => $h - $m->bottom->points - $footer->height, 'fragment' => $footer]];
        $emit = function (Fragment $body) use (&$out, $w, $h, $before, $after, $left, $bodyY): void {
            if (count($out) >= self::MAX_PAGES) {
                throw new LayoutOverflow('document', sprintf('more than %d pages', self::MAX_PAGES));
            }
            $out[] = new PlacedPage($w, $h, [...$before, ['x' => $left, 'y' => $bodyY, 'fragment' => $body], ...$after]);
        };

        $body = Column::make(...$page->bodyNodes());
        $token = null;
        while (true) {
            $this->ctx->setLastFailurePath(null);
            $res = $this->engine->layout($body, $root, new Constraints($contentWidth, $bodyHeight, true), $token);
            $progressed = $res->status !== Status::Partial || $res->token === null || $res->token != $token;
            if ($res->fragment !== null && $progressed) {
                $emit($res->fragment);
                if ($res->status === Status::Complete || $res->token === null) {
                    return;
                }
                $token = $res->token;
                continue;
            }

            // DoesNotFit on a fresh page, or a Partial that made no progress.
            $path = $this->ctx->lastFailurePath ?? 'body';
            if ($this->ctx->overflow === OverflowMode::Throw) {
                throw new LayoutOverflow($path, 'content is taller than the page body');
            }
            $res = $this->engine->layout($body, $root, new Constraints($contentWidth, null, true), $token);
            if ($res->fragment !== null) {
                $clip = new Fragment($contentWidth, $bodyHeight);
                $clip->clip = [0.0, 0.0, $contentWidth, $bodyHeight];
                $clip->addChild(0.0, 0.0, $res->fragment);
                $emit($clip);
            }
            $this->ctx->warn(sprintf('Content clipped: "%s" is taller than the page body.', $path));
            return;
        }
    }

    /** @param list<\Papercut\Node|\Papercut\Component> $nodes */
    private function slot(array $nodes, ComputedStyle $root, float $width): ?Fragment
    {
        if ($nodes === []) {
            return null;
        }
        return $this->engine->layout(Column::make(...$nodes), $root, new Constraints($width, null, true))->fragment;
    }
}
