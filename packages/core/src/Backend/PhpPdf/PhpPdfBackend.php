<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Image\PdfImage;
use Dskripchenko\PhpPdf\Pdf\Document as PdfDocument;
use Dskripchenko\PhpPdf\Pdf\Merge\{PageImporter, PdfSource};
use Dskripchenko\PhpPdf\Pdf\PdfFormXObject;
use Dskripchenko\PhpPdf\Pdf\{Encryption as PdfEncryption, EncryptionAlgorithm};
use Dskripchenko\PhpPdf\Pdf\Page as PdfPage;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Dskripchenko\PhpPdf\Svg\SvgRenderer;
use Papercut\Backend\Backend;
use Papercut\Backend\FontHandle;
use Papercut\Backend\ImageHandle;
use Papercut\Backend\ImageFormat;
use Papercut\Backend\{PdfPageHandle, PdfPageLoader};
use Papercut\Exception\FontNotFound;
use Papercut\Exception\ImageUnreadable;
use Papercut\Exception\PdfUnreadable;
use Papercut\Paint\{Anchor, Bookmark, Clip, DocumentMeta, DrawImage, DrawPdfPage, DrawSvg, Line, Link, Op, Opacity, PageDisplayList, Rect, Tag, Tagged, TextRun};
use Papercut\Text\CachedFont;
use Papercut\Value\Color;

/**
 * Papercut's PDF writer, on php-pdf. Making one with its constructor options and passing it to
 * `PdfOptions(backend:)` is supported (for example to cache fonts as PHP files for opcache). Its other
 * methods are internal and may change in any release.
 */
final class PhpPdfBackend implements Backend, PdfPageLoader
{
    /** The document being rendered, for outline entries. */
    private ?PdfDocument $doc = null;
    private int $outlineLevel = 0;
    /** @var array<string, bool> anchors in the document being rendered; true once registered */
    private array $destinations = [];
    /** @var array<string, PdfFormXObject> pages of other PDFs imported into the document being rendered, by handle id */
    private array $forms = [];
    private const array TEXT_STATE_PROPERTIES = ['lastFillR', 'lastFillG', 'lastFillB', 'lastCharSpacing'];

    /** @var array<string, PhpPdfFont> */
    private array $fonts = [];
    private readonly FontStateCache $fontCache;
    private readonly GdImageConverter $gd;

    /**
     * $cacheDir: where parsed fonts are kept across processes
     * (null = FontStateCache::defaultDir(), false = no disk cache).
     * $gd: converts WebP, GIF, BMP and AVIF (null = use GD when it is loaded).
     * $fontCacheFormat: FontStateCache::SERIALIZE, or FontStateCache::PHP to let opcache hold the fonts.
     */
    public function __construct(
        string|false|null $cacheDir = null,
        ?GdImageConverter $gd = null,
        string $fontCacheFormat = FontStateCache::SERIALIZE,
        private readonly bool $tagged = false,
    ) {
        $this->fontCache = new FontStateCache($cacheDir === false ? null : ($cacheDir ?? FontStateCache::defaultDir()), $fontCacheFormat);
        $this->gd = $gd ?? GdImageConverter::detect();
    }

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
            $font = new PdfFont($this->fontCache->load($real));
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
            return $this->image($bytes);
        } catch (\Throwable $e) {
            throw new ImageUnreadable(sprintf('Cannot read image "%s": %s', $path, $e->getMessage()), 0, $e);
        }
    }

    public function loadImageBytes(string $bytes): ImageHandle
    {
        try {
            return $this->image($bytes);
        } catch (\Throwable $e) {
            throw new ImageUnreadable('Cannot read image bytes: ' . $e->getMessage(), 0, $e);
        }
    }

    /** PNG and JPEG go to php-pdf as they are; formats it cannot read are converted to PNG first. */
    private function image(string $bytes): PhpPdfImage
    {
        $mime = ImageFormat::sniff($bytes);
        if ($mime === null) {
            throw new \RuntimeException(ImageFormat::unsupported());
        }
        $embed = $this->gd->handles($mime) ? $this->gd->toPng($bytes, $mime) : $bytes;
        return new PhpPdfImage(PdfImage::fromBytes($embed), sha1($bytes));
    }

    public function loadPdfPage(string $bytes, int $page, #[\SensitiveParameter] string $password = ''): PdfPageHandle
    {
        try {
            $document = PdfSource::fromBytes($bytes, $password)->document();
            $count = count($document->pages());
        } catch (\Throwable $e) {
            throw new PdfUnreadable('Cannot read PDF: ' . $e->getMessage(), 0, $e);
        }
        if ($page < 1 || $page > $count) {
            throw new PdfUnreadable(sprintf('Cannot draw page %d of the PDF: it has %d page%s', $page, $count, $count === 1 ? '' : 's'));
        }
        [$w, $h] = PageImporter::pageSize($document, $page - 1);

        return new PhpPdfPage($document, $page - 1, $w, $h, sha1($bytes) . ':' . $page);
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
        $this->doc = $doc;
        $this->outlineLevel = 0;
        $this->forms = [];
        $this->destinations = [];
        foreach ($pages as $page) {
            self::collectDestinations($page->ops, $this->destinations);
        }
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
        $this->open = [];
        $this->marking = 0;
        if ($this->tagged) {
            method_exists($doc, 'enablePdfUa') ? $doc->enablePdfUa() : $doc->enableTagged();
            $doc->setViewerPreferences(['displayDocTitle' => true]);
            $doc->useObjectStreams();
        }
        if ($meta->encryption !== null) {
            $e = $meta->encryption;
            $doc->encrypt($e->userPassword, $e->ownerPassword, self::permissions($e), EncryptionAlgorithm::Aes_256_R6);
        }
        try {
            $first = true;
            foreach ($pages as $page) {
                $pdfPage = $doc->addPage(customDimensionsPt: [$page->width, $page->height]);
                if ($this->tagged) {
                    if ($first) {
                        $doc->beginStructContainer('Document', $pdfPage);
                        $first = false;
                    }
                    $pdfPage->setTabOrder('S');
                    $this->drawStructured($pdfPage, $page->height, $page->ops);
                } else {
                    $this->drawOps($pdfPage, $page->height, $page->ops);
                }
            }
            if ($this->tagged) {
                foreach ($this->open as $_) {
                    $doc->endStructContainer();
                }
                $doc->endStructContainer();
                $this->open = [];
            }
            return $doc->toBytes();
        } finally {
            $this->doc = null;
            $this->forms = [];
            $this->destinations = [];
        }
    }

    /** SPIKE: keys of the structure containers open below Document, outermost first */
    private array $open = [];
    /** SPIKE: >0 while inside a marked-content sequence (a leaf or an artifact) */
    private int $marking = 0;

    /**
     * SPIKE: draws ops as structure. Tagged groups become containers (when they hold other tagged
     * groups) or leaves (one marked-content sequence); untagged drawing is marked as an artifact.
     *
     * @param list<Op> $ops
     */
    private function drawStructured(PdfPage $p, float $h, array $ops, float $inherited = 1.0): void
    {
        foreach ($ops as $op) {
            if ($op instanceof Tagged) {
                $this->drawTagged($p, $h, $op, $inherited);
            } elseif ($op instanceof Clip) {
                $this->wrapped($p, fn () => $p->withClipRect($op->x, $h - $op->y - $op->height, $op->width, $op->height, fn () => $this->drawStructured($p, $h, $op->ops, $inherited)));
            } elseif ($op instanceof Opacity) {
                if ($op->alpha <= 0.0) {
                    continue;
                }
                if ($op->alpha >= 1.0) {
                    $this->drawStructured($p, $h, $op->ops, $inherited);
                    continue;
                }
                $effective = $inherited * $op->alpha;
                $this->wrapped($p, fn () => $p->withOpacity($effective, $effective, fn () => $this->drawStructured($p, $h, $op->ops, $effective)));
            } elseif ($op instanceof Link) {
                $this->drawLink($p, $h, $op);
            } elseif ($op instanceof Bookmark || $op instanceof Anchor) {
                $this->drawOps($p, $h, [$op], $inherited);
            } else {
                $p->beginArtifact('Layout');
                $this->drawOps($p, $h, [$op], $inherited);
                $p->endMarkedContent();
            }
        }
    }

    private function drawTagged(PdfPage $p, float $h, Tagged $t, float $inherited): void
    {
        $doc = $this->doc;
        \assert($doc !== null);
        $tag = $t->tag;
        if ($tag->role === Tag::ARTIFACT) {
            $p->beginArtifact('Pagination');
            $this->marking++;
            $this->drawOps($p, $h, $t->ops, $inherited);
            $this->marking--;
            $p->endMarkedContent();
            return;
        }
        $role = $tag->role;
        $attrs = $tag->attributes === null ? [] : ['O' => 'Table', 'Scope' => 'Column'];
        if (self::holdsTags($t->ops)) {
            $this->closeTo($tag->key);
            if (end($this->open) !== $tag->key . "\0" . $tag->role) {
                $doc->beginStructContainer($role, $p, $attrs);
                $this->open[] = $tag->key . "\0" . $tag->role;
            }
            $this->drawStructured($p, $h, $t->ops, $inherited);
            return;
        }
        $this->closeTo($tag->key);
        $mcid = $p->nextMcid();
        $doc->addStructElement($role, $mcid, $p, $tag->alt, $attrs);
        $p->beginMarkedContent($tag->role, $mcid);
        $this->marking++;
        $this->drawOps($p, $h, $t->ops, $inherited);
        $this->marking--;
        $p->endMarkedContent();
    }

    /** SPIKE: a link with a description, placed in the structure where it is drawn. */
    private function drawLink(PdfPage $p, float $h, Link $op): void
    {
        $y = $h - $op->y - $op->height;
        $internal = $op->uri[0] === '#' && isset($this->destinations[substr($op->uri, 1)]);
        $internal
            ? $p->addInternalLink($op->x, $y, $op->width, $op->height, substr($op->uri, 1), 'Go to ' . substr($op->uri, 1))
            : $p->addExternalLink($op->x, $y, $op->width, $op->height, $op->uri, $op->uri);
        $this->doc?->placeLinkInStructure($p);
    }

    /** Closes open containers that are not ancestors of (or the same element as) $key. */
    private function closeTo(string $key): void
    {
        while ($this->open !== []) {
            $top = strstr(end($this->open), "\0", true);
            if ($top === $key || str_starts_with($key, $top . ' > ')) {
                return;
            }
            array_pop($this->open);
            $this->doc?->endStructContainer();
        }
    }

    /** @param list<Op> $ops */
    private static function holdsTags(array $ops): bool
    {
        foreach ($ops as $op) {
            if ($op instanceof Tagged) {
                if ($op->tag->role !== Tag::ARTIFACT) {
                    return true;
                }
            } elseif (($op instanceof Clip || $op instanceof Opacity) && self::holdsTags($op->ops)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<Op> $ops
     * @param array<string, bool> $names
     */
    private static function collectDestinations(array $ops, array &$names): void
    {
        foreach ($ops as $op) {
            if ($op instanceof Anchor) {
                $names[$op->name] = false;
            } elseif ($op instanceof Clip || $op instanceof Opacity || $op instanceof Tagged) {
                self::collectDestinations($op->ops, $names);
            }
        }
    }

    /** @param list<Op> $ops */
    private function drawOps(PdfPage $p, float $h, array $ops, float $inherited = 1.0): void
    {
        foreach ($ops as $op) {
            match (true) {
                $op instanceof TextRun => $this->drawText($p, $h, $op, $inherited),
                $op instanceof Rect => $this->drawRect($p, $h, $op, $inherited),
                $op instanceof Line => $this->drawLine($p, $h, $op, $inherited),
                $op instanceof DrawImage => $p->drawImage($this->pdfImage($op), $op->x, $h - $op->y - $op->height, $op->width, $op->height),
                $op instanceof DrawSvg => $this->wrapped($p, fn () => $this->drawSvg($p, $h, $op)),
                $op instanceof DrawPdfPage => $this->drawPdfPage($p, $h, $op),
                $op instanceof Clip => $this->wrapped($p, fn () => $p->withClipRect($op->x, $h - $op->y - $op->height, $op->width, $op->height, fn () => $this->drawOps($p, $h, $op->ops, $inherited))),
                $op instanceof Opacity => $this->drawOpacity($p, $h, $op, $inherited),
                $op instanceof Tagged => $this->drawOps($p, $h, $op->ops, $inherited),
                $op instanceof Bookmark => $this->bookmark($p, $h, $op),
                $op instanceof Anchor => $this->registerDestination($p, $h, $op),
                $op instanceof Link && $this->tagged => $this->drawLink($p, $h, $op),
                // A #name link whose name matches a registered anchor becomes an internal link.
                $op instanceof Link && $op->uri[0] === '#' && isset($this->destinations[substr($op->uri, 1)]) => $p->addInternalLink($op->x, $h - $op->y - $op->height, $op->width, $op->height, substr($op->uri, 1)),
                $op instanceof Link => $p->addExternalLink($op->x, $h - $op->y - $op->height, $op->width, $op->height, $op->uri),
                default => throw new \LogicException('Unsupported op ' . $op::class),
            };
        }
    }

    /** Screen-reader access is always granted; printing allows high resolution too. */
    private static function permissions(\Papercut\Export\Encryption $e): int
    {
        return PdfEncryption::PERM_ACCESSIBILITY
            | ($e->print ? PdfEncryption::PERM_PRINT | PdfEncryption::PERM_PRINT_HIGH : 0)
            | ($e->copy ? PdfEncryption::PERM_COPY : 0)
            | ($e->modify ? PdfEncryption::PERM_MODIFY : 0)
            | ($e->annotate ? PdfEncryption::PERM_ANNOTATE | PdfEncryption::PERM_FILL_FORMS : 0)
            | ($e->assemble ? PdfEncryption::PERM_ASSEMBLE : 0);
    }

    /** php-pdf keeps the last registration of a name, so only the first occurrence registers (a repeated header row anchors on every page). */
    private function registerDestination(PdfPage $p, float $h, Anchor $a): void
    {
        if (($this->destinations[$a->name] ?? true) === true) {
            return;
        }
        $this->destinations[$a->name] = true;
        $this->doc?->registerDestination($a->name, $p, $a->x, $h - $a->y);
    }

    /** Nests under the previous entry at most one level deeper, so an h3 straight after an h1 sits under it. */
    private function bookmark(PdfPage $p, float $h, Bookmark $b): void
    {
        $level = min($b->level, $this->outlineLevel + 1);
        $this->outlineLevel = $level;
        $this->doc?->registerOutlineEntry($level, $b->title, $p, $b->x, $h - $b->y);
    }

    private function drawText(PdfPage $p, float $h, TextRun $t, float $inherited): void
    {
        if ($t->text === '' || $t->color->isTransparent()) {
            return;
        }
        $font = $this->pdfFont($t->font);
        [$r, $g, $b] = $t->color->toUnitRgb();
        $this->withAlpha($p, $t->color, $inherited, fn () => $p->showEmbeddedText($t->text, $t->x, $h - $t->baseline, $font, $t->size, $r, $g, $b, $t->letterSpacing));
    }

    /**
     * php-pdf draws the paths. Text goes through our font choice, which always answers, so
     * php-pdf never falls back to an unembedded base-14 font.
     */
    private function drawSvg(PdfPage $p, float $h, DrawSvg $op): void
    {
        SvgRenderer::render(
            $op->svg->xml,
            $p,
            $op->x,
            $h - $op->y - $op->height,
            $op->width,
            $op->height,
            fn (?string $family, bool $bold, bool $italic, string $text): PdfFont => $this->pdfFont($op->fonts->resolve($family, $bold, $italic, $text)),
        );
    }

    private function drawRect(PdfPage $p, float $h, Rect $o, float $inherited): void
    {
        $y = $h - $o->y - $o->height;
        if ($o->fill !== null && !$o->fill->isTransparent()) {
            [$r, $g, $b] = $o->fill->toUnitRgb();
            $this->withAlpha($p, $o->fill, $inherited, fn () => $p->fillRoundedRect($o->x, $y, $o->width, $o->height, $o->radius, $r, $g, $b));
        }
        if ($o->stroke !== null && !$o->stroke->isTransparent() && $o->strokeWidth > 0) {
            [$r, $g, $b] = $o->stroke->toUnitRgb();
            $this->withAlpha($p, $o->stroke, $inherited, fn () => $p->strokeRoundedRect($o->x, $y, $o->width, $o->height, $o->radius, $o->strokeWidth, $r, $g, $b));
        }
    }

    private function drawLine(PdfPage $p, float $h, Line $l, float $inherited): void
    {
        if ($l->color->isTransparent()) {
            return;
        }
        [$r, $g, $b] = $l->color->toUnitRgb();
        $this->withAlpha($p, $l->color, $inherited, fn () => $l->dashed
            ? $p->strokeDashedLine($l->x1, $h - $l->y1, $l->x2, $h - $l->y2, $l->width, $r, $g, $b, [3 * $l->width, 2 * $l->width])
            : $p->strokeLine($l->x1, $h - $l->y1, $l->x2, $h - $l->y2, $l->width, $r, $g, $b));
    }

    private function drawOpacity(PdfPage $p, float $h, Opacity $op, float $inherited): void
    {
        if ($op->alpha <= 0.0) {
            return;
        }
        if ($op->alpha >= 1.0) {
            $this->drawOps($p, $h, $op->ops, $inherited);
            return;
        }
        // PDF /ca replaces rather than multiplies, so compose nested alphas ourselves.
        $effective = $inherited * $op->alpha;
        $this->wrapped($p, fn () => $p->withOpacity($effective, $effective, fn () => $this->drawOps($p, $h, $op->ops, $effective)));
    }

    private function withAlpha(PdfPage $p, Color $c, float $inherited, \Closure $draw): void
    {
        $effective = $c->alpha * $inherited;
        if ($effective === $inherited) {
            $draw();
            return;
        }
        $this->wrapped($p, fn () => $p->withOpacity($effective, $effective, $draw));
    }

    private function wrapped(PdfPage $p, \Closure $wrapper): void
    {
        $wrapper();
        $this->invalidateTextState($p);
    }

    /**
     * php-pdf's ContentStream caches the last text fill colour and char spacing and skips
     * `rg`/`Tc` when unchanged, but our q...Q wrappers discard that graphics state. Poison
     * the cache (NAN never === anything) so the next text run re-emits both.
     */
    private function invalidateTextState(PdfPage $p): void
    {
        $stream = $p->rawContentStream();
        self::guardTextStateProperties($stream, self::TEXT_STATE_PROPERTIES);
        \Closure::bind(function (): void {
            $this->lastFillR = $this->lastFillG = $this->lastFillB = NAN;
            $this->lastCharSpacing = NAN;
        }, $stream, $stream::class)();
    }

    /**
     * @internal public only so tests can exercise it against a fake object.
     * @param list<string> $properties
     */
    public static function guardTextStateProperties(object $stream, array $properties): void
    {
        foreach ($properties as $property) {
            if (!property_exists($stream, $property)) {
                throw new \LogicException('papercut/core: dskripchenko/php-pdf internals changed (ContentStream text-state cache); pin dskripchenko/php-pdf to a compatible 1.x version or upgrade papercut/core.');
            }
        }
    }

    private function pdfFont(FontHandle $font): PdfFont
    {
        $inner = $font instanceof CachedFont ? $font->inner() : $font;
        if (!$inner instanceof PhpPdfFont) {
            throw new \LogicException('PhpPdfBackend can only draw fonts it loaded; got ' . $inner::class);
        }
        return $inner->pdfFont();
    }

    /** Each page is imported once per document, however many times it is drawn. */
    private function drawPdfPage(PdfPage $p, float $h, DrawPdfPage $op): void
    {
        $page = $op->page;
        if (!$page instanceof PhpPdfPage) {
            throw new \LogicException('PhpPdfBackend can only draw PDF pages it loaded; got ' . $page::class);
        }
        if ($this->doc === null || $op->width <= 0.0 || $op->height <= 0.0) {
            return;
        }
        $form = $this->forms[$page->id()] ??= PageImporter::intoDocument($this->doc, $page->document(), $page->index());
        $p->useFormXObject($form, $op->x, $h - $op->y - $op->height, $op->width, $op->height);
    }

    private function pdfImage(DrawImage $op): PdfImage
    {
        if (!$op->image instanceof PhpPdfImage) {
            throw new \LogicException('PhpPdfBackend can only draw images it loaded; got ' . $op->image::class);
        }
        return $op->image->pdfImage();
    }
}
