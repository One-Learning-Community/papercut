<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Font\Ttf\TtfFile;
use Dskripchenko\PhpPdf\Image\PdfImage;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Papercut\Backend\Backend;
use Papercut\Backend\FontHandle;
use Papercut\Backend\ImageHandle;
use Papercut\Exception\FontNotFound;
use Papercut\Exception\ImageUnreadable;
use Papercut\Paint\DocumentMeta;

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
        throw new \LogicException('Not implemented');
    }
}
