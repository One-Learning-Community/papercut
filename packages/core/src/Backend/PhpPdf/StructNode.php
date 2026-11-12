<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Pdf\Page as PdfPage;
use Papercut\Paint\Tag;

/**
 * An element of a tagged PDF's structure tree, kept until the whole document is drawn. php-pdf builds the tree
 * with a stack, where an element cannot be reopened once a sibling has started; a group that goes on over a page
 * or beside a sibling needs to, so the tree is made here first and written to php-pdf afterwards.
 *
 * @internal Part of Papercut's PDF writer. It may change in any release.
 */
final class StructNode
{
    public const string CONTAINER = 'container';
    public const string LEAF = 'leaf';
    public const string LINK = 'link';

    /** @var list<StructNode> in reading order */
    public array $children = [];
    /** @var list<StructNode> links that belong to no content, written after the children */
    public array $tail = [];
    /** @var array<string, StructNode> containers among the children, by id */
    private array $containers = [];

    /**
     * @param array<string, string|int|float> $attributes
     * @param array{x: float, y: float, width: float, height: float, uri: string, internal: bool, contents: string}|null $link
     */
    private function __construct(
        public readonly string $kind,
        public readonly string $role,
        public readonly PdfPage $page,
        public readonly array $attributes = [],
        public readonly ?string $alt = null,
        public readonly ?int $mcid = null,
        public readonly ?array $link = null,
        public readonly ?int $column = null,
    ) {
    }

    public static function root(PdfPage $page): self
    {
        return new self(self::CONTAINER, 'Document', $page);
    }

    /**
     * The container for $tag among the children: the one already there, or a new one. A cell that is drawn after
     * its row's other cells ($column set) goes in column order; anything else goes last.
     */
    public function container(Tag $tag, PdfPage $page, ?int $column = null): self
    {
        $id = $tag->key . "\0" . $tag->role;
        if (isset($this->containers[$id])) {
            return $this->containers[$id];
        }
        $node = new self(self::CONTAINER, $tag->role, $page, $tag->attributes, column: $tag->column);
        $this->containers[$id] = $node;
        $this->insert($node, $column);
        return $node;
    }

    /** @param array<string, string|int|float> $attributes */
    public function leaf(string $role, PdfPage $page, int $mcid, ?string $alt, array $attributes): void
    {
        $this->children[] = new self(self::LEAF, $role, $page, $attributes, $alt, $mcid);
    }

    /** @param array{x: float, y: float, width: float, height: float, uri: string, internal: bool, contents: string} $link */
    public function link(PdfPage $page, array $link): void
    {
        $this->children[] = new self(self::LINK, 'Link', $page, link: $link);
    }

    /** @param array{x: float, y: float, width: float, height: float, uri: string, internal: bool, contents: string} $link */
    public function tailLink(PdfPage $page, array $link): void
    {
        $this->tail[] = new self(self::LINK, 'Link', $page, link: $link);
    }

    private function insert(self $node, ?int $column): void
    {
        if ($column !== null) {
            foreach ($this->children as $i => $child) {
                if ($child->column !== null && $child->column > $column) {
                    array_splice($this->children, $i, 0, [$node]);
                    return;
                }
            }
        }
        $this->children[] = $node;
    }
}
