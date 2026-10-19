<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Font\Ttf\TtfFile;
use Dskripchenko\PhpPdf\Image\PdfImage;
use Dskripchenko\PhpPdf\Pdf\Document as PdfDocument;
use Dskripchenko\PhpPdf\Pdf\Page as PdfPage;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Papercut\Backend\Backend;
use Papercut\Backend\FontHandle;
use Papercut\Backend\ImageHandle;
use Papercut\Exception\FontNotFound;
use Papercut\Exception\ImageUnreadable;
use Papercut\Paint\{Clip, DocumentMeta, DrawImage, Line, Link, Op, Opacity, PageDisplayList, Rect, TextRun};
use Papercut\Text\CachedFont;
use Papercut\Value\Color;

final class PhpPdfBackend implements Backend
{
    /** @var array<string, PhpPdfFont> */
    private array $fonts = [];

    public function loadFont(string $path): FontHandle
    {
        $real = realpath($path);
        if ($real === false || !is_readable($real) || !is_file($real)) {
            throw new FontNotFound('Font file not found: ' . $path);
        }
        if (isset($this->fonts[$real])) {
            return $this->fonts[$real];
        }
        try {
            $font = new PdfFont(TtfFile::fromFile($real));
            $font->ttf()->unitsPerEm();
        } catch (\Throwable $e) {
            throw new FontNotFound('Font file is not a valid TrueType/OpenType font: ' . $path, 0, $e);
        }
        return $this->fonts[$real] = new PhpPdfFont($font, sha1($real));
    }

    public function loadImageFile(string $path): ImageHandle
    {
        $bytes = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($bytes === false) {
            throw new ImageUnreadable(sprintf('Cannot read image "%s": file not found or not readable', $path));
        }
        try {
            return new PhpPdfImage(PdfImage::fromBytes($bytes), sha1($bytes));
        } catch (\Throwable $e) {
            throw new ImageUnreadable(sprintf('Cannot read image "%s": %s', $path, $e->getMessage()), 0, $e);
        }
    }

    public function loadImageBytes(string $bytes): ImageHandle
    {
        try {
            return new PhpPdfImage(PdfImage::fromBytes($bytes), sha1($bytes));
        } catch (\Throwable $e) {
            throw new ImageUnreadable('Cannot read image bytes: ' . $e->getMessage(), 0, $e);
        }
    }

    public function render(DocumentMeta $meta, array $pages): string
    {
        if ($pages === []) {
            throw new \InvalidArgumentException('Cannot render a document with no pages.');
        }
        foreach ($this->fonts as $font) {
            $font->pdfFont()->reset();
        }
        $doc = PdfDocument::new();
        $doc->metadata(
            title: $meta->title,
            author: $meta->author,
            subject: $meta->subject,
            keywords: $meta->keywords,
            creator: 'Papercut',
            producer: 'Papercut',
            creationDate: $meta->createdAt ?? new \DateTimeImmutable(),
        );
        if ($meta->lang !== null) {
            $doc->setLang($meta->lang);
        }
        foreach ($pages as $page) {
            $pdfPage = $doc->addPage(customDimensionsPt: [$page->width, $page->height]);
            $this->drawOps($pdfPage, $page->height, $page->ops);
        }
        return $doc->toBytes();
    }

    /** @param list<Op> $ops */
    private function drawOps(PdfPage $p, float $h, array $ops): void
    {
        foreach ($ops as $op) {
            match (true) {
                $op instanceof TextRun => $this->drawText($p, $h, $op),
                $op instanceof Rect => $this->drawRect($p, $h, $op),
                $op instanceof Line => $this->drawLine($p, $h, $op),
                $op instanceof DrawImage => $p->drawImage($this->pdfImage($op), $op->x, $h - $op->y - $op->height, $op->width, $op->height),
                $op instanceof Clip => $p->withClipRect($op->x, $h - $op->y - $op->height, $op->width, $op->height, fn () => $this->drawOps($p, $h, $op->ops)),
                $op instanceof Opacity => $op->alpha >= 1.0
                    ? $this->drawOps($p, $h, $op->ops)
                    : $p->withOpacity($op->alpha, $op->alpha, fn () => $this->drawOps($p, $h, $op->ops)),
                $op instanceof Link => $p->addExternalLink($op->x, $h - $op->y - $op->height, $op->width, $op->height, $op->uri),
                default => throw new \LogicException('Unsupported op ' . $op::class),
            };
        }
    }

    private function drawText(PdfPage $p, float $h, TextRun $t): void
    {
        if ($t->text === '' || $t->color->isTransparent()) {
            return;
        }
        $font = $this->pdfFont($t->font);
        [$r, $g, $b] = $t->color->toUnitRgb();
        $this->withAlpha($p, $t->color, fn () => $p->showEmbeddedText($t->text, $t->x, $h - $t->baseline, $font, $t->size, $r, $g, $b, $t->letterSpacing));
    }

    private function drawRect(PdfPage $p, float $h, Rect $o): void
    {
        $y = $h - $o->y - $o->height;
        if ($o->fill !== null && !$o->fill->isTransparent()) {
            [$r, $g, $b] = $o->fill->toUnitRgb();
            $this->withAlpha($p, $o->fill, fn () => $p->fillRoundedRect($o->x, $y, $o->width, $o->height, $o->radius, $r, $g, $b));
        }
        if ($o->stroke !== null && !$o->stroke->isTransparent() && $o->strokeWidth > 0) {
            [$r, $g, $b] = $o->stroke->toUnitRgb();
            $this->withAlpha($p, $o->stroke, fn () => $p->strokeRoundedRect($o->x, $y, $o->width, $o->height, $o->radius, $o->strokeWidth, $r, $g, $b));
        }
    }

    private function drawLine(PdfPage $p, float $h, Line $l): void
    {
        if ($l->color->isTransparent()) {
            return;
        }
        [$r, $g, $b] = $l->color->toUnitRgb();
        $this->withAlpha($p, $l->color, fn () => $l->dashed
            ? $p->strokeDashedLine($l->x1, $h - $l->y1, $l->x2, $h - $l->y2, $l->width, $r, $g, $b, [3 * $l->width, 2 * $l->width])
            : $p->strokeLine($l->x1, $h - $l->y1, $l->x2, $h - $l->y2, $l->width, $r, $g, $b));
    }

    private function withAlpha(PdfPage $p, Color $c, \Closure $draw): void
    {
        $c->isOpaque() ? $draw() : $p->withOpacity($c->alpha, $c->alpha, $draw);
    }

    private function pdfFont(FontHandle $font): \Dskripchenko\PhpPdf\Pdf\PdfFont
    {
        $inner = $font instanceof CachedFont ? $font->inner() : $font;
        if (!$inner instanceof PhpPdfFont) {
            throw new \LogicException('PhpPdfBackend can only draw fonts it loaded; got ' . $inner::class);
        }
        return $inner->pdfFont();
    }

    private function pdfImage(DrawImage $op): \Dskripchenko\PhpPdf\Image\PdfImage
    {
        if (!$op->image instanceof PhpPdfImage) {
            throw new \LogicException('PhpPdfBackend can only draw images it loaded; got ' . $op->image::class);
        }
        return $op->image->pdfImage();
    }
}
