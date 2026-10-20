<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\{Document, Page};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

/** The words pdftotext finds in the PDF equal the words in the HTML text (body only: no header, footer or background). */
final class ContentParityTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function documents(): iterable
    {
        foreach (['fixtures' => __DIR__ . '/../Visual/fixtures', 'examples' => __DIR__ . '/../../../../examples/core/documents'] as $kind => $dir) {
            $files = glob("$dir/*.php") ?: [];
            sort($files);
            foreach ($files as $file) {
                yield $kind . '-' . basename($file, '.php') => [$file];
            }
        }
    }

    protected function setUp(): void
    {
        exec('command -v pdftotext 2>/dev/null', $out, $code);
        if ($code !== 0) {
            self::markTestSkipped('pdftotext (poppler) is not installed.');
        }
    }

    #[DataProvider('documents')]
    public function test_pdf_and_html_have_the_same_words(string $file): void
    {
        $source = require $file;
        self::assertInstanceOf(Document::class, $source);
        $pages = array_map(static fn (Page $p): Page => Page::size(pt($p->width()), pt($p->height()))->margin($p->margins())->body(...$p->bodyNodes()), $source->pages());
        $document = Document::make(...$pages);

        self::assertSame($this->words($this->pdfText($document)), $this->words($this->htmlText($document)));
    }

    #[DataProvider('documents')]
    public function test_html_export_does_not_change_pdf_bytes(string $file): void
    {
        $document = require $file;
        self::assertInstanceOf(Document::class, $document);
        $document = $document->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $before = $document->toPdf()->bytes();
        $document->toHtml();
        $document->toHtml(new \Papercut\Export\HtmlOptions(fontMode: \Papercut\Export\FontMode::Embed));
        self::assertSame($before, $document->toPdf()->bytes());
    }

    private function pdfText(Document $document): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pc') ?: self::fail('tempnam');
        try {
            file_put_contents($path, $document->toPdf()->bytes());
            return (string) shell_exec('pdftotext -enc UTF-8 ' . escapeshellarg($path) . ' - 2>/dev/null');
        } finally {
            @unlink($path);
        }
    }

    private function htmlText(Document $document): string
    {
        $dom = \Dom\HTMLDocument::createFromString($document->toHtml(new \Papercut\Export\HtmlOptions(fullPage: true))->bytes());
        return $dom->body === null ? '' : $this->textOf($dom->body);
    }

    /** textContent with a space around every block-level element, as a browser would lay it out (only span and a are inline). */
    private function textOf(\Dom\Node $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \Dom\Text) {
                $text .= $child->data;
            } elseif ($child instanceof \Dom\Element) {
                $inline = in_array($child->localName, ['span', 'a'], true);
                $text .= ($inline ? '' : ' ') . $this->textOf($child) . ($inline ? '' : ' ');
            }
        }
        return $text;
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        // pdftotext dehyphenates a hyphenated word that wraps at the hyphen ("break-after" becomes "breakafter"), which
        // the HTML text cannot show, so hyphens are ignored on both sides. This is the only normalisation applied.
        $text = str_replace('-', '', $text);
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_unique($words));
        sort($words, SORT_STRING);
        return $words;
    }
}
