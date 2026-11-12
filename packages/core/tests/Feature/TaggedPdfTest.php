<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\{Column, ColumnFlow, Document, Image, Link, ListNode, Page, Span, Table, TableCell, Text, Toc};
use Papercut\Backend\{Backend, FontHandle, ImageHandle, TaggingCapability};
use Papercut\Backend\PhpPdf\{PhpPdfBackend, TaggingSupport};
use Papercut\Exception\{InvalidStructure, UnsupportedFeature};
use Papercut\Export\{Encryption, PdfOptions};
use Papercut\Paint\DocumentMeta;
use Papercut\Tests\Support\{PdfInspector, StructTree, TestImages};
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

/** The structure tree of a tagged PDF, read back from the bytes (also the guard on php-pdf's internal struct methods). */
#[RequiresPhpExtension('gd')]
final class TaggedPdfTest extends TestCase
{
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

    private function small(\Papercut\Node ...$body): Document
    {
        return Document::make(Page::a4()->body(Column::make(...$body)))->title('T')->lang('en');
    }

    /** @return list<array<string, mixed>> the elements below Document */
    private static function tree(Document $doc, ?PdfOptions $options = null): array
    {
        $root = StructTree::of($doc->toPdf($options ?? new PdfOptions(tagged: true))->bytes());
        self::assertCount(1, $root);
        self::assertSame('Document', $root[0]['role']);
        return $root[0]['kids'];
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

    /** @param list<array<string, mixed>> $elements */
    private static function countRole(array $elements, string $role): int
    {
        return count(array_filter(self::roles($elements), static fn (string $r): bool => $r === $role));
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @return list<array<string, mixed>>
     */
    private static function find(array $elements, string $role): array
    {
        $out = [];
        foreach ($elements as $e) {
            if ($e['role'] === $role) {
                $out[] = $e;
            }
            array_push($out, ...self::find($e['kids'], $role));
        }
        return $out;
    }

    private static function long(): string
    {
        return str_repeat('word ', 2500);
    }

    public function test_the_structure_tree_follows_the_content(): void
    {
        $pdf = $this->document()->toPdf(new PdfOptions(tagged: true))->bytes();
        PdfInspector::check($pdf);

        $children = self::tree($this->document());
        // The page header is an artifact, so it is not among them.
        self::assertSame(['H1', 'Link', 'L', 'Table', 'Figure'], array_column($children, 'role'));

        // The link's text and its annotation are one element.
        self::assertTrue($children[1]['objr']);
        self::assertSame(['P'], array_column($children[1]['kids'], 'role'));

        [$list, $table, $figure] = [$children[2], $children[3], $children[4]];
        self::assertSame(['LI', 'LI'], array_column($list['kids'], 'role'));
        self::assertSame(['Lbl', 'LBody'], array_column($list['kids'][0]['kids'], 'role'));
        self::assertSame(['P'], array_column($list['kids'][0]['kids'][1]['kids'], 'role'));

        self::assertSame(['TR', 'TR'], array_column($table['kids'], 'role'));
        self::assertSame(['TH', 'TH'], array_column($table['kids'][0]['kids'], 'role'));
        self::assertSame('/O /Table /Scope /Column', $table['kids'][0]['kids'][0]['attrs']);
        self::assertSame(['TD', 'TD'], array_column($table['kids'][1]['kids'], 'role'));
        self::assertNull($table['kids'][1]['kids'][0]['attrs']);

        self::assertSame('A chart', $figure['alt']);
        self::assertSame(1, self::countRole($children, 'Figure'), 'the image without alt text is an artifact');
        // The link text, two list items and four table cells: the page header's paragraph is not among them.
        self::assertSame(7, self::countRole($children, 'P'));
    }

    public function test_a_list_item_split_across_pages_is_one_item(): void
    {
        $doc = $this->small(ListNode::unordered(self::long(), 'Two'));
        self::assertGreaterThan(2, (int) PdfInspector::info($doc->toPdf()->bytes())['Pages']);
        $tree = self::tree($doc);
        self::assertSame(['L'], array_column($tree, 'role'));
        self::assertSame(['LI', 'LI'], array_column($tree[0]['kids'], 'role'));
        self::assertSame(1, self::countRole([$tree[0]['kids'][0]], 'LBody'));
        self::assertSame(1, self::countRole([$tree[0]['kids'][0]], 'Lbl'));
    }

    public function test_a_table_row_split_across_pages_is_one_row(): void
    {
        $doc = $this->small(Table::make()->columns('1fr 1fr')->head('H1', 'H2')->row(self::long(), 'short'));
        self::assertGreaterThan(2, (int) PdfInspector::info($doc->toPdf()->bytes())['Pages']);
        $tree = self::tree($doc);
        self::assertSame(['Table'], array_column($tree, 'role'));
        self::assertSame(['TR', 'TR'], array_column($tree[0]['kids'], 'role'));
        self::assertSame(['TD', 'TD'], array_column($tree[0]['kids'][1]['kids'], 'role'));
    }

    public function test_content_flowing_through_columns_is_not_repeated(): void
    {
        $items = array_map(static fn (int $i): string => "Item $i", range(1, 12));
        $doc = Document::make(Page::size(pt(220), pt(100))->margin(pt(10))->body(ColumnFlow::make(ListNode::unordered(...$items))->columnGap(pt(20))))->title('T')->lang('en');
        self::assertGreaterThan(1, (int) PdfInspector::info($doc->toPdf()->bytes())['Pages']);
        $tree = self::tree($doc);
        self::assertSame(['L'], array_column($tree, 'role'));
        self::assertSame(12, self::countRole($tree, 'LI'));
    }

    public function test_a_cell_spanning_rows_sits_in_its_first_row(): void
    {
        $tree = self::tree($this->small(Table::make()->columns('1fr 1fr 1fr')->head('A', 'B', 'C')
            ->row('a', TableCell::make('tall')->rowspan(2), 'c')->row('p', 'q')));
        $table = $tree[0];
        self::assertSame(['TR', 'TR', 'TR'], array_column($table['kids'], 'role'));
        self::assertSame(['TD', 'TD', 'TD'], array_column($table['kids'][1]['kids'], 'role'));
        self::assertSame([null, '/O /Table /RowSpan 2', null], array_column($table['kids'][1]['kids'], 'attrs'));
        self::assertSame(['TD', 'TD'], array_column($table['kids'][2]['kids'], 'role'));
    }

    public function test_empty_cells_stay_in_the_tree_and_spans_are_attributes(): void
    {
        $tree = self::tree($this->small(Table::make()->columns('1fr 1fr 1fr')->head('A', 'B', 'C')
            ->row(TableCell::make('wide')->colspan(2), '')));
        $row = $tree[0]['kids'][1];
        self::assertSame(['TD', 'TD'], array_column($row['kids'], 'role'));
        self::assertSame('/ColSpan 2 /O /Table', $row['kids'][0]['attrs']);
        self::assertSame([], $row['kids'][1]['kids'], 'the empty cell has no content');
    }

    public function test_a_header_cell_in_a_body_row_scopes_the_row(): void
    {
        $tree = self::tree($this->small(Table::make()->columns('1fr 1fr')->head('A', 'B')->row(TableCell::th('Name'), 'v')));
        self::assertSame('/O /Table /Scope /Column', $tree[0]['kids'][0]['kids'][0]['attrs']);
        self::assertSame('/O /Table /Scope /Row', $tree[0]['kids'][1]['kids'][0]['attrs']);
    }

    public function test_a_repeated_header_row_is_an_artifact(): void
    {
        $rows = array_map(static fn (int $i): array => ["Row $i", 'x'], range(1, 120));
        $doc = $this->small(Table::make()->columns('1fr 1fr')->head('H1', 'H2')->rows($rows, fn (mixed $r): array => [$r[0], $r[1]]));
        self::assertGreaterThan(1, (int) PdfInspector::info($doc->toPdf()->bytes())['Pages']);
        $tree = self::tree($doc);
        self::assertSame(2, self::countRole($tree, 'TH'), 'only the first header row is in the tree');
        self::assertSame(121, self::countRole($tree, 'TR'));
    }

    public function test_an_inline_link_follows_its_paragraph(): void
    {
        $tree = self::tree($this->small(
            ListNode::unordered('One'),
            Text::spans(Span::text('See '), new Span('the site', href: 'https://example.com'), Span::text(' now.')),
        ));
        self::assertSame(['L', 'P', 'Link'], array_column($tree, 'role'));
        self::assertTrue($tree[2]['objr']);
    }

    public function test_links_in_a_page_header_are_described_and_last_in_document(): void
    {
        $doc = Document::make(Page::a4()->header(Link::to('https://example.com', Text::make('Home')))->body(Text::make('Body')))->title('T')->lang('en');
        $pdf = $doc->toPdf(new PdfOptions(tagged: true))->bytes();
        $root = StructTree::of($pdf);
        self::assertSame(['Document'], array_column($root, 'role'));
        self::assertSame(['P', 'Link'], array_column($root[0]['kids'], 'role'));
        self::assertTrue($root[0]['kids'][1]['objr']);
        self::assertSame(['Home'], self::contents($pdf));
    }

    /** @return list<string> the /Contents of every annotation, in file order */
    private static function contents(string $pdf): array
    {
        preg_match_all('~/Contents (\((?:[^()\\\\]|\\\\.)*\)|<[0-9a-f]+>)~i', StructTree::qdf($pdf), $m);
        return array_map(static fn (string $c): string => $c[0] === '<'
            ? mb_convert_encoding(substr((string) hex2bin(substr($c, 1, -1)), 2), 'UTF-8', 'UTF-16BE')
            : substr($c, 1, -1), $m[1]);
    }

    public function test_a_link_block_is_described_by_all_its_text(): void
    {
        $words = str_repeat('alpha beta ', 40);
        $doc = $this->small(Link::to('https://example.com', Text::make($words), Text::make('and more')));
        $contents = self::contents($doc->toPdf(new PdfOptions(tagged: true))->bytes());
        self::assertSame([trim($words) . ' and more'], $contents);
    }

    public function test_an_inline_link_is_described_by_its_own_text(): void
    {
        $doc = $this->small(Text::spans(Span::text('See '), new Span('the site', href: 'https://example.com'), Span::text(' now and later.')));
        self::assertSame(['the site'], self::contents($doc->toPdf(new PdfOptions(tagged: true))->bytes()));
    }

    public function test_a_contents_entry_is_described_by_its_title(): void
    {
        $doc = $this->small(Toc::make(), Text::h1('Alpha'), Text::h2('Beta'));
        self::assertSame(['Alpha', 'Beta'], self::contents($doc->toPdf(new PdfOptions(tagged: true))->bytes()));
    }

    public function test_a_link_around_an_image_is_described_by_its_alt_text(): void
    {
        $doc = $this->small(Link::to('https://example.com', Image::bytes(TestImages::png(40, 20))->alt('Logo')));
        self::assertSame(['Logo'], self::contents($doc->toPdf(new PdfOptions(tagged: true))->bytes()));
    }

    public function test_a_link_without_text_falls_back_to_its_address(): void
    {
        $doc = $this->small(Link::to('https://example.com', Image::bytes(TestImages::png(40, 20))));
        self::assertSame(['https://example.com'], self::contents($doc->toPdf(new PdfOptions(tagged: true))->bytes()));
    }

    public function test_a_spanning_cell_that_continues_on_the_next_page_stays_in_its_row(): void
    {
        $doc = $this->small(Table::make()->columns('1fr 1fr 1fr')->head('A', 'B', 'C')
            ->row('a', TableCell::make(self::long())->rowspan(2), 'c')->row('p', 'q'));
        self::assertGreaterThan(2, (int) PdfInspector::info($doc->toPdf()->bytes())['Pages']);
        $table = self::tree($doc)[0];
        self::assertSame(['Table'], [$table['role']]);
        self::assertSame(['TR', 'TR', 'TR'], array_column($table['kids'], 'role'));
        self::assertSame(['TD', 'TD', 'TD'], array_column($table['kids'][1]['kids'], 'role'));
        self::assertSame(1, self::countRole([$table['kids'][1]['kids'][1]], 'TD'));
        self::assertGreaterThan(1, self::countRole($table['kids'][1]['kids'][1]['kids'], 'P'));
    }

    public function test_a_link_describes_itself_with_the_text_it_covers(): void
    {
        $qdf = StructTree::qdf($this->small(Link::to('https://example.com', Text::make('the site')))->toPdf(new PdfOptions(tagged: true))->bytes());
        self::assertStringContainsString('/Contents (the site)', $qdf);
    }

    public function test_an_image_without_alt_text_in_a_cell_stays_an_artifact(): void
    {
        $tree = self::tree($this->small(Table::make()->columns('1fr')->row(Image::bytes(TestImages::png(40, 20)))));
        self::assertSame(1, self::countRole($tree, 'TD'));
        self::assertSame([], self::find($tree, 'TD')[0]['kids']);
        self::assertStringContainsString('/Artifact << /Type /Layout >> BDC', StructTree::qdf($this->small(Table::make()->columns('1fr')->row(Image::bytes(TestImages::png(40, 20))))->toPdf(new PdfOptions(tagged: true))->bytes()));
    }

    public function test_whitespace_alt_text_means_no_alt_text(): void
    {
        self::assertNull(Image::bytes(TestImages::png(4, 4))->alt('  ')->altText());
        self::assertSame('A cat', Image::bytes(TestImages::png(4, 4))->alt(' A cat ')->altText());
    }

    public function test_identification_and_language(): void
    {
        $qdf = StructTree::qdf($this->document()->toPdf(new PdfOptions(tagged: true))->bytes());
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $qdf);
        self::assertStringContainsString('/Lang (en-US)', $qdf);
        self::assertMatchesRegularExpression('~/MarkInfo <<\s*/Marked true\s*>>~', $qdf);
        self::assertStringContainsString('/DisplayDocTitle true', $qdf);
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

    /** A backend that reports an old php-pdf: it lacks the struct methods. */
    private function oldBackend(): Backend
    {
        return new class (new PhpPdfBackend()) implements Backend, TaggingCapability {
            public function __construct(private readonly PhpPdfBackend $inner)
            {
            }

            public function loadFont(string $path): FontHandle
            {
                return $this->inner->loadFont($path);
            }

            public function loadImageFile(string $path): ImageHandle
            {
                return $this->inner->loadImageFile($path);
            }

            public function loadImageBytes(string $bytes): ImageHandle
            {
                return $this->inner->loadImageBytes($bytes);
            }

            public function render(DocumentMeta $meta, array $pages, bool $tagged = false): string
            {
                return $this->inner->render($meta, $pages, $tagged);
            }

            public function taggingMissing(): array
            {
                return ['addStructElement'];
            }
        };
    }

    public function test_an_old_php_pdf_warns_and_writes_an_untagged_pdf(): void
    {
        $warnings = [];
        $pdf = $this->document()->toPdf(new PdfOptions(onWarning: function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        }, backend: $this->oldBackend(), tagged: true))->bytes();

        self::assertCount(1, $warnings);
        self::assertStringContainsString('tagged output', $warnings[0]);
        self::assertStringContainsString('addStructElement', $warnings[0]);
        self::assertSame([], StructTree::of($pdf));
        PdfInspector::check($pdf);
    }

    public function test_an_old_php_pdf_without_a_warning_callback_still_writes_a_pdf(): void
    {
        $pdf = $this->document()->toPdf(new PdfOptions(backend: $this->oldBackend(), tagged: true))->bytes();
        self::assertSame([], StructTree::of($pdf));
    }

    public function test_an_old_php_pdf_throws_in_strict_mode(): void
    {
        $this->expectException(UnsupportedFeature::class);
        $this->expectExceptionMessage('tagged output');
        $this->document()->toPdf(new PdfOptions(strict: true, backend: $this->oldBackend(), tagged: true));
    }

    public function test_support_check_passes_with_the_installed_php_pdf(): void
    {
        self::assertSame([], TaggingSupport::missing());
        self::assertSame([], (new PhpPdfBackend())->taggingMissing());
    }

    public function test_support_check_names_what_an_old_release_lacks(): void
    {
        $missing = TaggingSupport::missing([\Dskripchenko\PhpPdf\Pdf\Document::class => \stdClass::class]);
        self::assertSame(['enablePdfUa', 'addStructElement', 'beginStructContainer', 'endStructContainer', 'placeLinkInStructure'], $missing);
    }

    public function test_encrypted_tagged_pdf_keeps_the_accessibility_permission(): void
    {
        $locked = new Encryption('open', 'owner', print: false, copy: false);
        $pdf = $this->document()->toPdf((new PdfOptions(tagged: true))->withEncryption($locked))->bytes();
        $file = tempnam(sys_get_temp_dir(), 'pc');
        self::assertNotFalse($file);
        $pdfFile = $file . '.pdf'; // qpdf's AppArmor profile wants .pdf
        rename($file, $pdfFile);
        file_put_contents($pdfFile, $pdf);
        try {
            exec(sprintf('qpdf --password=open --show-encryption %s 2>&1', escapeshellarg($pdfFile)), $lines);
        } finally {
            unlink($pdfFile);
        }
        $report = implode("\n", $lines);
        self::assertStringContainsString('extract for accessibility: allowed', $report);
        self::assertStringContainsString('extract for any purpose: not allowed', $report);
    }

    /**
     * @param list<string> $warnings
     * @param list<\Papercut\Node> $body
     */
    private function warnings(array &$warnings, bool $tagged, bool $strict, array $body, ?\Papercut\Node $header = null): void
    {
        $page = Page::a4();
        if ($header !== null) {
            $page = $page->header($header);
        }
        $options = new PdfOptions(tagged: $tagged, strict: $strict, onWarning: function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        });
        Document::make($page->body(Column::make(...$body)))->title('T')->lang('en')->toPdf($options);
    }

    public function test_a_skipped_heading_level_warns_naming_the_heading(): void
    {
        $w = [];
        $this->warnings($w, true, false, [Text::h1('Top'), Text::h3('A rather deep heading that is much too long to quote in full')]);
        self::assertCount(1, $w);
        self::assertStringContainsString('"A rather deep heading that is much too …"', $w[0]);
        self::assertStringContainsString('H3 after H1', $w[0]);
        self::assertStringContainsString('H2', $w[0]);
        self::assertStringContainsString('style the heading smaller', $w[0]);
    }

    public function test_a_first_heading_below_h1_warns(): void
    {
        $w = [];
        $this->warnings($w, true, false, [Text::h2('Start')]);
        self::assertCount(1, $w);
        self::assertStringContainsString('after no heading', $w[0]);
    }

    public function test_orderly_heading_levels_do_not_warn(): void
    {
        $w = [];
        $this->warnings($w, true, false, [
            Text::h1('A'), Text::h2('B'), Text::h1('C'), Text::h2('D'), Text::h3('E'), Text::h1('F'), Text::make('body'),
        ]);
        self::assertSame([], $w);
    }

    public function test_strict_mode_throws_for_a_skipped_level(): void
    {
        $w = [];
        $this->expectException(UnsupportedFeature::class);
        $this->expectExceptionMessage('skipping a level');
        $this->warnings($w, true, true, [Text::h1('A'), Text::h3('B')]);
    }

    public function test_untagged_output_does_not_check_heading_levels(): void
    {
        $w = [];
        $this->warnings($w, false, true, [Text::h3('B')]);
        self::assertSame([], $w);
    }

    public function test_headings_in_the_page_header_are_ignored(): void
    {
        $w = [];
        $this->warnings($w, true, false, [Text::h1('A'), Text::h2('B')], Text::h4('Running header'));
        self::assertSame([], $w);
    }

    public function test_a_heading_laid_out_more_than_once_warns_once(): void
    {
        $w = [];
        $table = Table::make()->columns('1fr 1fr')->head('A', 'B')->rows([['1', '2']], fn (mixed $r): array => [Text::h3('In a cell'), $r[1]]);
        $this->warnings($w, true, false, [Text::h1('A'), $table, ...array_map(fn (int $n): Text => Text::make(str_repeat('word ', 400)), range(1, 6))]);
        self::assertCount(1, $w);
    }
}
