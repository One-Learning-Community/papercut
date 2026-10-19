<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Layout\{LayoutContext, LayoutEngine, Paginator};
use Papercut\Paint\{DocumentMeta, Painter};

final class Document
{
    /** @var list<Page> */
    private array $pages;
    private ?string $title = null;
    private ?string $author = null;
    private ?string $subject = null;
    private ?string $keywords = null;
    private ?string $lang = null;
    private ?\DateTimeImmutable $createdAt = null;

    private function __construct()
    {
        $this->pages = [];
    }

    public static function make(Page ...$pages): self
    {
        $doc = new self();
        $doc->pages = array_values($pages);
        return $doc;
    }

    public function title(string $v): self
    {
        $clone = clone $this;
        $clone->title = $v;
        return $clone;
    }

    public function author(string $v): self
    {
        $clone = clone $this;
        $clone->author = $v;
        return $clone;
    }

    public function subject(string $v): self
    {
        $clone = clone $this;
        $clone->subject = $v;
        return $clone;
    }

    public function keywords(string $v): self
    {
        $clone = clone $this;
        $clone->keywords = $v;
        return $clone;
    }

    public function lang(string $v): self
    {
        $clone = clone $this;
        $clone->lang = $v;
        return $clone;
    }

    public function createdAt(\DateTimeImmutable $v): self
    {
        $clone = clone $this;
        $clone->createdAt = $v;
        return $clone;
    }

    /**
     * @return list<Page>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    public function meta(): DocumentMeta
    {
        return new DocumentMeta($this->title, $this->author, $this->subject, $this->keywords, $this->lang, $this->createdAt);
    }

    /**
     * Lays out, paints and renders the document to PDF bytes.
     *
     * The font registry is built on the same backend instance that renders. A custom
     * registry created on a different backend makes the PhpPdf backend throw LogicException.
     */
    public function render(?RenderOptions $options = null): string
    {
        $options ??= new RenderOptions();
        $backend = $options->backend();
        $ctx = new LayoutContext($options->fonts($backend), $options->theme(), $backend, $options->overflow, $options->onWarning);
        $placed = (new Paginator(new LayoutEngine($ctx), $ctx))->paginate($this);

        return $backend->render($this->meta(), Painter::paint($placed));
    }

    public function toString(?RenderOptions $options = null): string
    {
        return $this->render($options);
    }

    public function save(string $path, ?RenderOptions $options = null): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            throw new \RuntimeException("Directory does not exist: $dir");
        }
        $bytes = $this->render($options);
        if (file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException("Could not write file: $path");
        }
    }

    /** Writes the PDF to php://output and returns the number of bytes written. */
    public function stream(?RenderOptions $options = null): int
    {
        $bytes = $this->render($options);
        $out = fopen('php://output', 'wb');
        if ($out === false) {
            throw new \RuntimeException('Could not open php://output');
        }
        $written = fwrite($out, $bytes);
        fclose($out);

        return $written === false ? 0 : $written;
    }
}
