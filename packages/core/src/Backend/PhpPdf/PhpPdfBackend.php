<?php

declare(strict_types=1);

namespace Papercut\Backend\PhpPdf;

use Dskripchenko\PhpPdf\Image\PdfImage;
use Dskripchenko\PhpPdf\Pdf\Document as PdfDocument;
use Dskripchenko\PhpPdf\Pdf\Merge\{PageImporter, PdfSource};
use Dskripchenko\PhpPdf\Pdf\PdfAConfig;
use Dskripchenko\PhpPdf\Pdf\PdfFormXObject;
use Dskripchenko\PhpPdf\Pdf\{Encryption as PdfEncryption, EncryptionAlgorithm};
use Dskripchenko\PhpPdf\Pdf\Page as PdfPage;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Dskripchenko\PhpPdf\Svg\SvgRenderer;
use Papercut\Backend\Backend;
use Papercut\Backend\FontHandle;
use Papercut\Backend\ImageHandle;
use Papercut\Backend\ImageFormat;
use Papercut\Backend\{PdfPageHandle, PdfPageLoader, TaggingCapability};
use Papercut\Exception\FontNotFound;
use Papercut\Exception\ImageUnreadable;
use Papercut\Exception\PdfUnreadable;
use Papercut\Export\{Encryption, PdfA};
use Papercut\Paint\{Anchor, Bookmark, Clip, DocumentMeta, DrawImage, DrawPdfPage, DrawSvg, Line, Link, Op, Opacity, PageDisplayList, Rect, Tag, Tagged, TextRun};
use Papercut\Text\CachedFont;
use Papercut\Value\Color;

/**
 * Papercut's PDF writer, on php-pdf. Making one with its constructor options and passing it to
 * `PdfOptions(backend:)` is supported (for example to cache fonts as PHP files for opcache). Its other
 * methods are internal and may change in any release.
 */
final class PhpPdfBackend implements Backend, PdfPageLoader, TaggingCapability
{
    /** The document being rendered, for outline entries. */
    private ?PdfDocument $doc = null;
    private int $outlineLevel = 0;
    /** @var array<string, bool> anchors in the document being rendered; true once registered */
    private array $destinations = [];
    /** @var array<string, PdfFormXObject> pages of other PDFs imported into the document being rendered, by handle id */
    private array $forms = [];
    /** Roles that only group other elements: with no tagged content in them, they are not elements. */
    private const array GROUPING = ['Div', 'Sect', 'BlockQuote', 'TOC', 'TOCI', 'L', 'LI', 'LBody', 'Table', 'TR', 'Link'];

    /** The structure tree of the tagged PDF being rendered, built while drawing and written when it is done. */
    private ?StructNode $root = null;
    /** @var list<array{level: int, text: string, page: int, key: string}> the last tagged render's headings, in structure order */
    private array $headings = [];
    /** 1-based number of the page being drawn */
    private int $pageNumber = 0;
    /** Whether the last tagged render's structure tree held any content below Document. */
    private bool $structureHasContent = false;
    /** True while a leaf's own links are placed in the structure, so drawing leaves them out. */
    private bool $skipLinks = false;
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

    public function render(DocumentMeta $meta, array $pages, bool $tagged = false, ?PdfA $pdfA = null): string
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
        $this->root = null;
        $this->headings = [];
        $this->pageNumber = 0;
        if ($pdfA !== null) {
            if ($meta->encryption !== null) {
                throw new \InvalidArgumentException('PDF/A cannot be combined with encryption.');
            }
            $doc->enablePdfA(new PdfAConfig(
                iccProfilePath: dirname(__DIR__, 3) . '/resources/icc/sRGB2014.icc',
                iccProfileName: 'sRGB2014',
                lang: $meta->lang ?? 'en',
                title: $meta->title ?? '',
                author: $meta->author ?? '',
                part: $pdfA->part(),
                conformance: $pdfA->conformance(),
            ));
        }
        if ($tagged) {
            $doc->enablePdfUa();
            if ($pdfA?->part() !== 1) {
                $doc->useObjectStreams();
            }
        }
        if ($meta->encryption !== null) {
            $e = $meta->encryption;
            $doc->encrypt($e->userPassword, $e->ownerPassword, self::permissions($e), EncryptionAlgorithm::Aes_256_R6);
        }
        try {
            foreach ($pages as $page) {
                $this->pageNumber++;
                $pdfPage = $doc->addPage(customDimensionsPt: [$page->width, $page->height]);
                if ($tagged) {
                    $this->root ??= StructNode::root($pdfPage);
                    $pdfPage->setTabOrder('S');
                    $this->drawStructured($pdfPage, $page->height, $page->ops, 1.0);
                } else {
                    $this->drawOps($pdfPage, $page->height, $page->ops);
                }
            }
            if ($tagged) {
                \assert($this->root !== null);
                $this->headings = $this->root->headings();
                $this->structureHasContent = $this->root->holdsContent();
                $this->writeStructure($doc, $this->root);
            }
            return $doc->toBytes();
        } finally {
            $this->doc = null;
            $this->root = null;
            $this->forms = [];
            $this->destinations = [];
        }
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function structureHasContent(): bool
    {
        return $this->structureHasContent;
    }

    /** @return list<string> */
    public function taggingMissing(): array
    {
        return TaggingSupport::missing();
    }

    /**
     * Draws ops as structure. Tagged groups become containers (when they hold other tagged
     * groups) or leaves (one marked-content sequence); untagged drawing is marked as an artifact.
     * $chain is the enclosing groups' tags, outermost first.
     *
     * @param list<Op> $ops
     * @param list<Tag> $chain
     */
    private function drawStructured(PdfPage $p, float $h, array $ops, float $inherited, array $chain = []): void
    {
        foreach ($ops as $op) {
            if ($op instanceof Tagged) {
                $this->drawTagged($p, $h, $op, $inherited, $chain);
            } elseif ($op instanceof Clip) {
                $this->wrapped($p, fn () => $p->withClipRect($op->x, $h - $op->y - $op->height, $op->width, $op->height, fn () => $this->drawStructured($p, $h, $op->ops, $inherited, $chain)));
            } elseif ($op instanceof Opacity) {
                $this->drawOpacity($p, $h, $op, $inherited, fn (array $inner, float $alpha) => $this->drawStructured($p, $h, $inner, $alpha, $chain));
            } elseif ($op instanceof Link) {
                $this->node($p, $chain)->link($p, $this->linkFor($h, $op));
            } elseif ($op instanceof Bookmark || $op instanceof Anchor) {
                $this->drawOps($p, $h, [$op], $inherited);
            } else {
                $this->drawArtifact($p, $h, [$op], $inherited, Tag::LAYOUT);
            }
        }
    }

    /**
     * Draws one tagged group as a container (it holds other tagged groups, or nothing) or a leaf (one
     * marked-content sequence). A cell with a home row belongs in that row though it is drawn after the rows.
     *
     * @param list<Tag> $chain
     */
    private function drawTagged(PdfPage $p, float $h, Tagged $t, float $inherited, array $chain): void
    {
        $tag = $t->tag;
        if ($tag->role === Tag::ARTIFACT) {
            $this->drawArtifact($p, $h, $t->ops, $inherited, $tag->artifactType);
            return;
        }
        $column = null;
        if ($tag->home !== null) {
            $chain[] = new Tag('TR', $tag->home);
            $column = $tag->column;
        }
        if ($t->ops === [] || self::holdsTags($t->ops)) {
            $chain[] = $tag;
            $this->node($p, $chain, $column);
            $this->drawStructured($p, $h, $t->ops, $inherited, $chain);
            return;
        }
        if (in_array($tag->role, self::GROUPING, true)) {
            // A grouping element with nothing tagged inside is decoration (an empty card's border): no element, no content.
            $this->drawArtifact($p, $h, $t->ops, $inherited, Tag::LAYOUT);
            return;
        }
        $parent = $this->node($p, $chain, $column);
        $mcid = $p->nextMcid();
        $heading = preg_match('/^H[1-6]$/', $tag->role) === 1 ? ['key' => $tag->key, 'text' => self::plainText($t->ops), 'page' => $this->pageNumber] : null;
        $parent->leaf($tag->role, $p, $mcid, $tag->alt, $tag->attributes, $heading);
        foreach (self::linkOps($t->ops) as $link) {
            $parent->link($p, $this->linkFor($h, $link));
        }
        $p->beginMarkedContent($tag->role, $mcid);
        $this->skipLinks = true;
        try {
            $this->drawOps($p, $h, $t->ops, $inherited);
        } finally {
            $this->skipLinks = false;
        }
        $p->endMarkedContent();
    }

    /**
     * The element for the groups in $chain, made (with its ancestors) when it is not there yet.
     *
     * @param list<Tag> $chain
     */
    private function node(PdfPage $p, array $chain, ?int $column = null): StructNode
    {
        \assert($this->root !== null);
        $node = $this->root;
        $last = count($chain) - 1;
        foreach ($chain as $i => $tag) {
            $node = $node->container($tag, $p, $i === $last ? $column : null);
        }
        return $node;
    }

    /** @param list<Op> $ops */
    private function drawArtifact(PdfPage $p, float $h, array $ops, float $inherited, string $type): void
    {
        $p->beginArtifact($type);
        try {
            $this->drawOps($p, $h, $ops, $inherited);
        } finally {
            $p->endMarkedContent();
        }
    }

    /**
     * A link as the structure keeps it, described by what it says (or its URI, or just "Link" for an internal one).
     * php-pdf joins a link inside a Link container to that element, so the element holds the text and the annotation.
     *
     * @return array{x: float, y: float, width: float, height: float, uri: string, internal: bool, contents: string}
     */
    private function linkFor(float $h, Link $op): array
    {
        $internal = $op->uri[0] === '#' && isset($this->destinations[substr($op->uri, 1)]);
        return [
            'x' => $op->x,
            'y' => $h - $op->y - $op->height,
            'width' => $op->width,
            'height' => $op->height,
            'uri' => $internal ? substr($op->uri, 1) : $op->uri,
            'internal' => $internal,
            'contents' => $op->description ?? ($internal ? 'Link' : $op->uri),
        ];
    }

    /**
     * Writes the structure tree to php-pdf, adding the link annotations where the links belong.
     */
    private function writeStructure(PdfDocument $doc, StructNode $node): void
    {
        match ($node->kind) {
            StructNode::LEAF => $doc->addStructElement($node->role, (int) $node->mcid, $node->page, $node->alt, $node->attributes),
            StructNode::LINK => $this->writeLink($doc, $node),
            default => $this->writeContainer($doc, $node),
        };
    }

    private function writeContainer(PdfDocument $doc, StructNode $node): void
    {
        if (!$node->hasContent()) {
            return;
        }
        $doc->beginStructContainer($node->role, $node->page, $node->attributes);
        foreach ([...$node->children, ...$node->tail] as $child) {
            $this->writeStructure($doc, $child);
        }
        $doc->endStructContainer();
    }

    private function writeLink(PdfDocument $doc, StructNode $node): void
    {
        $l = $node->link;
        \assert($l !== null);
        $l['internal']
            ? $node->page->addInternalLink($l['x'], $l['y'], $l['width'], $l['height'], $l['uri'], $l['contents'])
            : $node->page->addExternalLink($l['x'], $l['y'], $l['width'], $l['height'], $l['uri'], $l['contents']);
        $doc->placeLinkInStructure($node->page);
    }

    /**
     * @param list<Op> $ops
     * @return list<Link>
     */
    private static function linkOps(array $ops): array
    {
        $found = [];
        foreach ($ops as $op) {
            if ($op instanceof Link) {
                $found[] = $op;
            } elseif ($op instanceof Clip || $op instanceof Opacity || $op instanceof Tagged) {
                array_push($found, ...self::linkOps($op->ops));
            }
        }
        return $found;
    }

    /**
     * The text of the runs in $ops, with whitespace collapsed.
     *
     * @param list<Op> $ops
     */
    private static function plainText(array $ops): string
    {
        $text = '';
        foreach ($ops as $op) {
            if ($op instanceof TextRun) {
                $text .= $op->text . ' ';
            } elseif ($op instanceof Clip || $op instanceof Opacity || $op instanceof Tagged) {
                $text .= self::plainText($op->ops) . ' ';
            }
        }
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @param list<Op> $ops */
    private static function holdsTags(array $ops): bool
    {
        foreach ($ops as $op) {
            if ($op instanceof Tagged || (($op instanceof Clip || $op instanceof Opacity) && self::holdsTags($op->ops))) {
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
                $op instanceof Link && $this->skipLinks => null,
                // Links in running headers and footers are artifacts' own: the tagged PDF keeps them at the end of Document.
                $op instanceof Link && $this->root !== null => $this->root->tailLink($p, $this->linkFor($h, $op)),
                // A #name link whose name matches a registered anchor becomes an internal link.
                $op instanceof Link && $op->uri[0] === '#' && isset($this->destinations[substr($op->uri, 1)]) => $p->addInternalLink($op->x, $h - $op->y - $op->height, $op->width, $op->height, substr($op->uri, 1)),
                $op instanceof Link => $p->addExternalLink($op->x, $h - $op->y - $op->height, $op->width, $op->height, $op->uri),
                default => throw new \LogicException('Unsupported op ' . $op::class),
            };
        }
    }

    /** Screen-reader access is always granted; printing allows high resolution too. */
    private static function permissions(Encryption $e): int
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

    /** @param (\Closure(list<Op>, float): void)|null $draw draws the group's ops at the given inherited opacity; plain ops by default */
    private function drawOpacity(PdfPage $p, float $h, Opacity $op, float $inherited, ?\Closure $draw = null): void
    {
        $draw ??= fn (array $ops, float $alpha) => $this->drawOps($p, $h, array_values($ops), $alpha);
        if ($op->alpha <= 0.0) {
            return;
        }
        if ($op->alpha >= 1.0) {
            $draw($op->ops, $inherited);
            return;
        }
        // PDF /ca replaces rather than multiplies, so compose nested alphas ourselves.
        $effective = $inherited * $op->alpha;
        $this->wrapped($p, fn () => $p->withOpacity($effective, $effective, fn () => $draw($op->ops, $effective)));
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
