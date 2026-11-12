<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\{Column, Document, Image, Link, ListNode, Page, Table, Text};
use Papercut\Backend\PhpPdf\{PhpPdfBackend, TaggingSupport};
use Papercut\Exception\InvalidStructure;
use Papercut\Export\{Encryption, PdfOptions};
use Papercut\Tests\Support\{PdfInspector, StructTree, TestImages};
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/** The structure tree of a tagged PDF, read back from the bytes (also the guard on php-pdf's internal struct methods). */
#[RequiresPhpExtension('gd')]
final class TaggedPdfTest extends TestCase
{
    protected function tearDown(): void
    {
        TaggingSupport::$documentClass = \Dskripchenko\PhpPdf\Pdf\Document::class;
    }

    private function document(): Document
    {
        $png = TestImages::png(40, 20);
        return Document::make(
            Page::a4()
                ->header(Text::make('Running header'))
                ->body(Column::make(
                    Text::h1('Title'),
                    Link::to('https://example.com', Text::make('the site')),
                    ListNode::unordered('One', 'Two'),
                    Table::make()->columns('1fr 1fr')->head('A', 'B')->rows([['1', '2']], fn (mixed $r): array => [$r[0], $r[1]]),
                    Image::bytes($png)->alt('A chart'),
                    Image::bytes($png),
                )),
        )->title('Tagged')->lang('en-US')->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @return list<string>
     */
    private static function roles(array $elements): array
    {
        $out = [];
        foreach ($elements as $e) {
            $out[] = $e['role'];
            array_push($out, ...self::roles($e['kids']));
        }
        return $out;
    }

    public function test_the_structure_tree_follows_the_content(): void
    {
        $pdf = $this->document()->toPdf(new PdfOptions(tagged: true))->bytes();
        PdfInspector::check($pdf);

        $root = StructTree::of($pdf);
        self::assertCount(1, $root);
        self::assertSame('Document', $root[0]['role']);
        $children = $root[0]['kids'];
        // The link is a sibling of its paragraph (a paragraph is one marked-content sequence); the header is an artifact.
        self::assertSame(['H1', 'Link', 'P', 'L', 'Table', 'Figure'], array_column($children, 'role'));

        self::assertSame('Link', $children[1]['role']);
        self::assertTrue($children[1]['objr']);

        [$list, $table, $figure] = [$children[3], $children[4], $children[5]];
        self::assertSame(['LI', 'LI'], array_column($list['kids'], 'role'));
        self::assertSame(['Lbl', 'LBody'], array_column($list['kids'][0]['kids'], 'role'));
        self::assertSame(['P'], array_column($list['kids'][0]['kids'][1]['kids'], 'role'));

        self::assertSame(['TR', 'TR'], array_column($table['kids'], 'role'));
        self::assertSame(['TH', 'TH'], array_column($table['kids'][0]['kids'], 'role'));
        self::assertSame('/O /Table /Scope /Column', $table['kids'][0]['kids'][0]['attrs']);
        self::assertSame(['TD', 'TD'], array_column($table['kids'][1]['kids'], 'role'));
        self::assertNull($table['kids'][1]['kids'][0]['attrs']);

        self::assertSame('A chart', $figure['alt']);
        self::assertSame(1, count(array_filter(self::roles($root), static fn (string $r): bool => $r === 'Figure')), 'the image without alt text is an artifact');
        // The link text, two list items and four table cells: the page header's paragraph is not among them.
        self::assertCount(7, array_filter(self::roles($root), static fn (string $r): bool => $r === 'P'));
    }

    public function test_identification_and_language(): void
    {
        $qdf = StructTree::qdf($this->document()->toPdf(new PdfOptions(tagged: true))->bytes());
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $qdf);
        self::assertStringContainsString('/Lang (en-US)', $qdf);
        self::assertMatchesRegularExpression('~/MarkInfo <<\s*/Marked true\s*>>~', $qdf);
        self::assertStringContainsString('/Artifact << /Type /Pagination >> BDC', $qdf);
    }

    public function test_untagged_output_has_no_structure(): void
    {
        $pdf = $this->document()->toPdf()->bytes();
        self::assertSame([], StructTree::of($pdf));
        self::assertStringNotContainsString('pdfuaid', StructTree::qdf($pdf));
    }

    public function test_tagged_option_survives_withEncryption(): void
    {
        $options = (new PdfOptions(tagged: true))->withEncryption(Encryption::password('x'));
        self::assertTrue($options->tagged());
        self::assertFalse((new PdfOptions())->tagged());
    }

    public function test_a_missing_title_throws(): void
    {
        $doc = Document::make(Page::a4()->body(Text::make('x')))->lang('en');
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Document::title()');
        $doc->toPdf(new PdfOptions(tagged: true));
    }

    public function test_a_missing_language_throws(): void
    {
        $doc = Document::make(Page::a4()->body(Text::make('x')))->title('T');
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Document::lang()');
        $doc->toPdf(new PdfOptions(tagged: true));
    }

    public function test_title_and_language_are_not_needed_untagged(): void
    {
        $pdf = Document::make(Page::a4()->body(Text::make('x')))->toPdf()->bytes();
        self::assertStringStartsWith('%PDF', $pdf);
    }

    public function test_an_old_php_pdf_warns_and_writes_an_untagged_pdf(): void
    {
        TaggingSupport::$documentClass = \stdClass::class;
        $warnings = [];
        $pdf = $this->document()->toPdf(new PdfOptions(onWarning: function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        }, tagged: true))->bytes();

        self::assertCount(1, $warnings);
        self::assertStringContainsString('tagged output skipped', $warnings[0]);
        self::assertStringContainsString('addStructElement', $warnings[0]);
        self::assertSame([], StructTree::of($pdf));
        PdfInspector::check($pdf);
    }

    public function test_support_check_passes_with_the_installed_php_pdf(): void
    {
        self::assertSame([], TaggingSupport::missing());
    }

    public function test_encrypted_tagged_pdf_keeps_the_accessibility_permission(): void
    {
        $locked = new Encryption('open', 'owner', print: false, copy: false);
        $pdf = $this->document()->toPdf((new PdfOptions(tagged: true))->withEncryption($locked))->bytes();
        $file = tempnam(sys_get_temp_dir(), 'pc') . '.pdf'; // qpdf's AppArmor profile wants .pdf
        file_put_contents($file, $pdf);
        try {
            exec(sprintf('qpdf --password=open --show-encryption %s 2>&1', escapeshellarg($file)), $lines);
        } finally {
            unlink($file);
        }
        $report = implode("\n", $lines);
        self::assertStringContainsString('extract for accessibility: allowed', $report);
        self::assertStringContainsString('extract for any purpose: not allowed', $report);
    }
}
