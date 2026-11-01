<?php

declare(strict_types=1);

// Renders the docs gallery images. Run from the repository root:
//   php site/gallery/render.php            every image
//   php site/gallery/render.php card.flat  one image
// Options, before the id: --manifest=<gallery.json> and --out=<directory> (default: the site's public gallery folder).
// Needs pdftoppm (poppler) and the GD extension, and `composer install` in packages/core.

require __DIR__ . '/../../packages/core/vendor/autoload.php';
require __DIR__ . '/Gallery.php';
require __DIR__ . '/Raster.php';

use Papercut\Document;
use Papercut\Site\{Gallery, Raster};

const DPI = 144; // twice the size it's shown at, for high-density screens and the lightbox

$options = getopt('', ['manifest:', 'out:'], $rest);
$manifest = is_string($options['manifest'] ?? null) ? $options['manifest'] : __DIR__ . '/gallery.json';
$out = is_string($options['out'] ?? null) ? $options['out'] : null;
$only = $argv[$rest] ?? null;
foreach (array_slice($argv, $rest + 1) as $extra) {
    // getopt() stops at the first plain argument, so an option after the id would be dropped and the image
    // written to the site instead of where it was asked to go.
    fwrite(STDERR, "gallery: unexpected argument $extra; options go before the image id\n");
    exit(1);
}

$entries = Gallery::load($manifest)->entries($out);
if ($only !== null) {
    $entries = array_values(array_filter($entries, static fn (array $e): bool => $e['id'] === $only));
    if ($entries === []) {
        fwrite(STDERR, "gallery: no entry named $only\n");
        exit(1);
    }
}

foreach ($entries as $entry) {
    try {
        render($entry['source'], $entry['image'], $entry['pages']);
        echo "Wrote {$entry['image']}\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "gallery: {$entry['source']}: {$e->getMessage()}\n");
        exit(1);
    }
}

function render(string $source, string $image, int $pages): void
{
    $document = (static fn (string $path): mixed => require $path)($source);
    if (!$document instanceof Document) {
        throw new \RuntimeException('the source must return a Papercut\Document');
    }
    $tmp = sys_get_temp_dir() . '/papercut-gallery-' . bin2hex(random_bytes(4));
    mkdir($tmp);
    try {
        file_put_contents("$tmp/doc.pdf", $document->toPdf()->bytes());
        exec(sprintf('pdftoppm -r %d -png %s %s 2>&1', DPI, escapeshellarg("$tmp/doc.pdf"), escapeshellarg("$tmp/page")), $output, $code);
        $files = glob("$tmp/page-*.png") ?: [];
        sort($files);
        if ($code !== 0 || $files === []) {
            throw new \RuntimeException('pdftoppm failed: ' . implode(' ', $output));
        }
        if (count($files) !== $pages) {
            // a source that spills onto another page would lose content from the image without a word
            throw new \RuntimeException(sprintf('renders %d pages, the manifest says %d; make its page taller', count($files), $pages));
        }
        $images = array_map(static fn (string $f): \GdImage => imagecreatefrompng($f) ?: throw new \RuntimeException("cannot read $f"), $files);
        if ($pages === 1) {
            // Cropped to the content, then centred on the page's width, so a narrow component keeps the scale of a wide one.
            $result = Raster::centerOn(Raster::trim($images[0], 24), imagesx($images[0]));
        } else {
            $result = Raster::trim(Raster::sideBySide($images, 32, 1), 24);
        }
        $partial = $image . '.tmp';
        if (!imagepng($result, $partial, 9) || !rename($partial, $image)) {
            @unlink($partial);
            throw new \RuntimeException("cannot write $image");
        }
    } finally {
        array_map('unlink', glob("$tmp/*") ?: []);
        rmdir($tmp);
    }
}
