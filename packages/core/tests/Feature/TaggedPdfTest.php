<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\Decorator\{Badge, Blockquote, Callout, Card, ChoiceList, CodeBlock, Field, KeyValueList, Section, Signature, Stat, Totals};
use Papercut\{Column, ColumnFlow, Document, Image, Link, ListNode, Page, QrCode, Row, Span, Svg, Table, TableCell, Text, Toc};
use Papercut\Backend\{Backend, FontHandle, ImageHandle, TaggingCapability};
use Papercut\Backend\PhpPdf\{PhpPdfBackend, TaggingSupport};
use Papercut\Exception\{InvalidStructure, UnsupportedFeature};
use Papercut\Export\{Encryption, PdfOptions};
use Papercut\Paint\DocumentMeta;
use Papercut\Markup\{Markup, MarkupOptions};
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

    private function small(\Papercut\Node|\Papercut\Component ...$body): Document
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

    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="20" viewBox="0 0 40 20">%s<rect width="40" height="20" fill="#336"/></svg>';

    /** @return list<array<string, mixed>> */
    private static function figures(\Papercut\Node ...$body): array
    {
        $doc = Document::make(Page::a4()->body(Column::make(...$body)))->title('T')->lang('en');
        $bytes = $doc->toPdf(new PdfOptions(tagged: true))->bytes();
        PdfInspector::check($bytes);
        return self::find(self::tree($doc), 'Figure');
    }

    public function test_an_svg_title_is_its_alt_text(): void
    {
        $figures = self::figures(Svg::fromString(sprintf(self::SVG, '<title>Sales chart</title>')));
        self::assertSame(['Sales chart'], array_column($figures, 'alt'));
    }

    public function test_an_explicit_svg_alt_overrides_the_title_and_null_alt_falls_back(): void
    {
        $svg = Svg::fromString(sprintf(self::SVG, '<title>Sales chart</title>'));
        self::assertSame(['Rising sales'], array_column(self::figures($svg->alt(' Rising sales ')), 'alt'));
        self::assertSame(['Sales chart'], array_column(self::figures($svg->alt('Rising sales')->alt(null)), 'alt'));
        self::assertSame(['Only alt'], array_column(self::figures(Svg::fromString(sprintf(self::SVG, ''))->alt('Only alt')), 'alt'));
    }

    public function test_an_svg_without_a_title_or_alt_is_decorative(): void
    {
        self::assertSame([], self::figures(Svg::fromString(sprintf(self::SVG, ''))));
        self::assertSame([], self::figures(Svg::fromString(sprintf(self::SVG, '<title>  </title>'))));
    }

    public function test_a_qr_code_is_a_figure_with_its_alt_or_the_default_label(): void
    {
        $qr = QrCode::make('https://example.com');
        self::assertSame(['Link to the example site'], array_column(self::figures($qr->alt('Link to the example site')), 'alt'));
        self::assertSame(['QR code: https://example.com'], array_column(self::figures($qr), 'alt'));
        self::assertSame(['QR code: https://example.com'], array_column(self::figures($qr->alt(' ')), 'alt'));
        self::assertSame('QR code', QrCode::fromModules([[true, false], [false, true]])->label());
        self::assertSame('Kept', $qr->alt('Kept')->errorCorrection('H')->altText(), 'alt survives re-encoding');
    }

    public function test_markup_img_alt_becomes_the_images_alt_text(): void
    {
        $o = new MarkupOptions(images: fn (string $s): string => TestImages::png(40, 20));
        $view = Markup::html('<img src="a.png" alt=" A cat "><img src="b.png" alt=""><img src="c.png">', $o);
        self::assertSame(['A cat'], array_column(self::figures($view), 'alt'));
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

    public function test_withTagged_copies_the_options_and_keeps_the_rest(): void
    {
        $locked = Encryption::password('x');
        $options = (new PdfOptions(bookmarks: false, encryption: $locked))->withTagged(true);
        self::assertTrue($options->tagged());
        self::assertFalse($options->bookmarks());
        self::assertSame($locked, $options->encryption());
        self::assertFalse($options->withTagged(false)->tagged());
        self::assertSame($locked, $options->withTagged(false)->encryption());
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

            public function headings(): array
            {
                return $this->inner->headings();
            }

            public function structureHasContent(): bool
            {
                return $this->inner->structureHasContent();
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
        self::assertStringContainsString('on page 1 is H3 right after an H1', $w[0]);
        self::assertStringContainsString("Make it H2: Text::h3('…')->asHeading(2)", $w[0]);
        self::assertStringContainsString('->heading(2)', $w[0]);
        self::assertStringContainsString('heading="2"', $w[0]);
    }

    public function test_a_link_that_wraps_is_described_by_all_its_text_on_every_line(): void
    {
        $long = str_repeat('very long link text ', 12);
        $bytes = $this->small(Text::h1('H'), Text::markup('Start <a href="https://long.test">' . $long . '</a> end.'))->toPdf(new PdfOptions(tagged: true))->bytes();
        $contents = StructTree::linkContents($bytes);
        self::assertGreaterThan(1, count($contents));
        self::assertSame(array_fill(0, count($contents), trim($long)), $contents);
    }

    public function test_a_link_around_an_svg_or_qr_code_is_described_by_its_alt_text(): void
    {
        $svg = Svg::fromString('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><title>Company logo</title><rect width="10" height="10"/></svg>');
        $bytes = $this->small(
            Text::h1('H'),
            Link::to('https://logo.test', $svg),
            Link::to('https://qr.test', QrCode::make('https://qr.test')->alt('Scan to open the page')),
        )->toPdf(new PdfOptions(tagged: true))->bytes();
        self::assertSame(['Company logo', 'Scan to open the page'], StructTree::linkContents($bytes));
    }

    public function test_a_blank_alt_makes_an_svg_with_a_title_decorative(): void
    {
        $svg = Svg::fromString('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><title>Company logo</title><rect width="10" height="10"/></svg>');
        $roles = fn (Svg $s): array => self::roles(self::tree($this->small(Text::h1('H'), $s)));
        self::assertSame(['H1', 'Figure'], $roles($svg));
        self::assertSame(['H1', 'Figure'], $roles($svg->alt(null)));
        self::assertSame(['H1'], $roles($svg->alt('')));
        self::assertSame(['H1'], $roles($svg->alt('  ')));
        self::assertSame(['H1', 'Figure'], $roles($svg->alt('')->alt('A logo')));
    }

    public function test_a_preformatted_block_in_markup_is_code(): void
    {
        $tree = self::tree($this->small(Markup::html('<p>Intro</p><pre>a  b' . "\n" . '  c</pre>')));
        self::assertSame(['P', 'Code'], array_column($tree, 'role'));
        self::assertStringContainsString('/Placement /Block', (string) $tree[1]['attrs']);
        self::assertStringContainsString('/O /Layout', (string) $tree[1]['attrs']);
    }

    private static function importedPage(): \Papercut\PdfPage
    {
        $template = Document::make(Page::a4()->body(Text::make('Imported words')))->title('T')->lang('en')->toPdf()->bytes();
        return \Papercut\PdfPage::bytes($template);
    }

    public function test_an_imported_pdf_page_warns_that_screen_readers_cannot_see_it(): void
    {
        $w = [];
        $this->warnings($w, true, false, [Text::h1('Top'), self::importedPage()]);
        self::assertCount(1, $w);
        self::assertStringContainsString('imported PDF page', $w[0]);
        self::assertStringContainsString('screen reader', $w[0]);
        $w = [];
        $this->warnings($w, false, false, [Text::h1('Top'), self::importedPage()]);
        self::assertSame([], $w);
    }

    public function test_an_imported_pdf_page_throws_in_strict_mode(): void
    {
        $w = [];
        $this->expectException(UnsupportedFeature::class);
        $this->expectExceptionMessage('screen reader');
        $this->warnings($w, true, true, [Text::h1('Top'), self::importedPage()]);
    }

    public function test_overlay_content_is_tagged_and_decoration_is_not(): void
    {
        $page = Page::a4()->overlay(
            \Papercut\Place::at(pt(100), pt(100), Text::make('Overlaid words')),
            \Papercut\Place::at(pt(100), pt(300), Column::make()->width(pt(40))->height(pt(40))->background('#000')),
        );
        $doc = Document::make($page)->title('T')->lang('en');
        self::assertSame(['P'], array_column(self::tree($doc), 'role'));
    }

    public function test_an_overlay_repeated_on_every_page_is_tagged_on_the_first_only(): void
    {
        $page = Page::a4()
            ->overlay(\Papercut\Place::at(pt(100), pt(100), Text::make('Overlaid words')))
            ->body(Column::make(Text::make(self::long() . self::long())));
        $doc = Document::make($page)->title('T')->lang('en');
        $bytes = $doc->toPdf(new PdfOptions(tagged: true))->bytes();
        $pages = preg_match_all('#/Type /Page$#m', StructTree::qdf($bytes));
        self::assertGreaterThan(1, $pages);
        // One paragraph piece per page for the body, and the overlay's once.
        self::assertSame(array_fill(0, $pages + 1, 'P'), array_column(self::tree($doc), 'role'));
        PdfInspector::check($bytes);
    }

    public function test_the_certificate_example_has_content_in_its_structure(): void
    {
        $doc = (static fn () => require __DIR__ . '/../../../../examples/core/documents/certificate.php')();
        $roles = self::roles(self::tree($doc));
        self::assertSame(3, self::countRole(self::tree($doc), 'P'), implode(',', $roles));
    }

    public function test_a_tree_with_no_content_warns_naming_the_cause(): void
    {
        $w = [];
        $png = TestImages::png(10, 10);
        $this->warnings($w, true, false, [Image::bytes($png)]);
        self::assertCount(1, $w);
        self::assertStringContainsString('no content', $w[0]);
        self::assertStringContainsString('artifact', $w[0]);
        self::assertStringContainsString('alt', $w[0]);
    }

    public function test_a_tree_with_no_content_throws_in_strict_mode(): void
    {
        $w = [];
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('no content');
        $this->warnings($w, true, true, [Image::bytes(TestImages::png(10, 10))]);
    }

    public function test_a_tree_with_content_does_not_warn_about_being_empty(): void
    {
        $w = [];
        $this->warnings($w, true, false, [Text::h1('Top')]);
        self::assertSame([], $w);
    }

    public function test_a_first_heading_below_h1_warns(): void
    {
        $w = [];
        $this->warnings($w, true, false, [Text::h2('Start')]);
        self::assertCount(1, $w);
        self::assertStringContainsString('The first heading, "Start" on page 1, is H2; a tagged PDF must start with H1', $w[0]);
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
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Heading "B" on page 1 is H3 right after an H1; a tagged PDF can\'t skip heading levels. Make it H2');
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

    public function test_a_skip_is_found_after_a_split_heading_beside_a_later_one(): void
    {
        $w = [];
        $this->warnings($w, true, false, [
            Text::h1('A'),
            Text::h2('B'),
            Row::make(Column::make(Text::h3(str_repeat('split heading ', 500))), Column::make(Text::h1('Right'), Text::make('x'))),
        ]);
        self::assertCount(1, $w);
        self::assertStringContainsString('is H3 right after an H1', $w[0]);
    }

    public function test_a_split_heading_warns_once(): void
    {
        $w = [];
        $this->warnings($w, true, false, [Text::h1('A'), Text::h3(str_repeat('split heading ', 700)), Text::make('x')]);
        self::assertCount(1, $w);
    }

    public function test_a_blockquote_is_a_blockquote_around_its_content(): void
    {
        $tree = self::tree($this->small(Blockquote::make('Said well.')->cite('Someone')));
        self::assertSame(['BlockQuote'], array_column($tree, 'role'));
        self::assertSame(['P', 'P'], array_column($tree[0]['kids'], 'role'));
    }

    public function test_a_code_block_is_code_beside_its_label(): void
    {
        $tree = self::tree($this->small(CodeBlock::make("a = 1\nb = 2")->language('text')));
        self::assertSame(['P', 'Code'], array_column($tree, 'role'));
        self::assertSame([], $tree[1]['kids']);
    }

    public function test_a_section_is_a_sect_holding_its_heading(): void
    {
        $tree = self::tree($this->small(Section::make(Text::make('Body'))->title('Intro')->heading(1)));
        self::assertSame(['Sect'], array_column($tree, 'role'));
        self::assertSame(['H1', 'P'], array_column($tree[0]['kids'], 'role'));
    }

    public function test_a_section_over_pages_is_one_sect(): void
    {
        $tree = self::tree($this->small(Section::make(Text::make(self::long()), Text::make(self::long()))->title('Intro')->heading(1), Text::make('after')));
        self::assertSame(['Sect', 'P'], array_column($tree, 'role'));
        self::assertGreaterThan(2, count($tree[0]['kids']));
        self::assertSame('H1', $tree[0]['kids'][0]['role']);
    }

    public function test_cards_and_callouts_are_divs(): void
    {
        $tree = self::tree($this->small(Card::make(Text::make('c'))->title('Card'), Callout::make(Text::make('n'))->title('Note')));
        self::assertSame(['Div', 'Div'], array_column($tree, 'role'));
        self::assertSame(['P', 'P'], array_column($tree[0]['kids'], 'role'));
        self::assertSame(['P', 'P'], array_column($tree[1]['kids'], 'role'));
    }

    public function test_a_card_heading_stays_a_heading_inside_the_div(): void
    {
        $tree = self::tree($this->small(Card::make(Text::make('c'))->title('Card')->heading(1)));
        self::assertSame(['Div'], array_column($tree, 'role'));
        self::assertSame(['H1', 'P'], array_column($tree[0]['kids'], 'role'));
    }

    public function test_a_contents_list_is_a_toc_of_toci_entries_with_their_links(): void
    {
        $doc = $this->small(Toc::make()->title('Contents'), Text::h1('Alpha'), Text::h2('Beta'));
        $pdf = $doc->toPdf(new PdfOptions(tagged: true))->bytes();
        PdfInspector::check($pdf);
        $tree = self::tree($doc);
        self::assertSame(['TOC', 'H1', 'H2'], array_column($tree, 'role'));
        $toc = $tree[0];
        self::assertSame(['Caption', 'TOCI', 'TOCI'], array_column($toc['kids'], 'role'));
        foreach ([1, 2] as $i) {
            $link = $toc['kids'][$i]['kids'];
            self::assertSame(['Link'], array_column($link, 'role'));
            self::assertTrue($link[0]['objr']);
            self::assertSame(['P'], array_column($link[0]['kids'], 'role'));
        }
        self::assertSame(['Alpha', 'Beta'], self::contents($pdf));
    }

    public function test_decorators_without_a_role_leave_their_content_as_it_was(): void
    {
        $tree = self::tree($this->small(
            Badge::make('New'),
            Stat::make('9')->label('Open'),
            KeyValueList::make()->row('Name', 'Ada'),
            Totals::make()->row('Sum', '1')->total('Total', '2'),
            Field::make(Text::make('v'))->label('Label'),
            Signature::make()->name('Ada'),
            ChoiceList::checkbox()->option('One', true),
        ));
        $roles = self::roles($tree);
        self::assertNotContains('Div', $roles);
        self::assertNotContains('Sect', $roles);
        self::assertContains('Table', $roles);
    }

    public function test_a_choice_list_is_a_list_whose_labels_say_what_is_selected(): void
    {
        $tree = self::tree($this->small(ChoiceList::radio()->option('Alpha', true)->option('Beta')));
        self::assertSame(['L'], array_column($tree, 'role'));
        $items = $tree[0]['kids'];
        self::assertSame(['LI', 'LI'], array_column($items, 'role'));
        foreach ($items as $item) {
            self::assertSame(['Lbl', 'LBody'], array_column($item['kids'], 'role'));
            self::assertSame(['P'], array_column($item['kids'][1]['kids'], 'role'));
        }
        self::assertSame('selected', $items[0]['kids'][0]['alt']);
        self::assertSame('not selected', $items[1]['kids'][0]['alt']);
    }

    public function test_a_choice_list_states_can_be_translated(): void
    {
        $tree = self::tree($this->small(ChoiceList::checkbox()->stateText('ausgewählt', 'nicht ausgewählt')->option('A', true)->option('B')));
        $items = $tree[0]['kids'];
        self::assertSame('ausgewählt', $items[0]['kids'][0]['alt']);
        self::assertSame('nicht ausgewählt', $items[1]['kids'][0]['alt']);
    }

    public function test_choice_list_state_text_cannot_be_blank(): void
    {
        $this->expectException(\Papercut\Exception\InvalidValue::class);
        ChoiceList::radio()->stateText('yes', ' ');
    }

    public function test_a_custom_decorator_keeps_its_contents_roles(): void
    {
        $custom = new class () implements \Papercut\Component {
            public function render(): \Papercut\View
            {
                return \Papercut\View::make(Text::h1('Mine'), Text::make('body'));
            }
        };
        $tree = self::tree($this->small($custom));
        self::assertSame(['H1', 'P'], array_column($tree, 'role'));
    }

    private function decorated(): Document
    {
        return $this->small(Blockquote::make('q'), Section::make('s')->title('T'), Toc::make(), Text::h1('H'), CodeBlock::make('x'));
    }

    public function test_untagged_output_is_the_same_with_decorators(): void
    {
        $default = $this->decorated()->toPdf(new PdfOptions())->bytes();
        self::assertSame($default, $this->decorated()->toPdf(new PdfOptions(tagged: false))->bytes());
        self::assertStringNotContainsString('/StructTreeRoot', $default);
        self::assertStringNotContainsString('/BDC', StructTree::qdf($default));
    }

    public function test_tagged_decorator_output_is_a_valid_pdf(): void
    {
        foreach ([Blockquote::make('q')->cite('c'), Section::make('s')->title('T'), Card::make('c')->title('T'), Callout::make('c')] as $decorator) {
            PdfInspector::check($this->small($decorator)->toPdf(new PdfOptions(tagged: true))->bytes());
        }
        self::addToAssertionCount(1);
    }

    public function test_an_empty_decorator_is_decoration_not_an_element(): void
    {
        $tree = self::tree($this->small(Card::make(), Blockquote::make(), Callout::make(), Text::make('x')));
        self::assertSame(['P'], array_column($tree, 'role'));
        $qdf = StructTree::qdf($this->small(Card::make(), Text::make('x'))->toPdf(new PdfOptions(tagged: true))->bytes());
        self::assertSame(1, substr_count($qdf, '/MCID'), 'only the paragraph has marked content');
    }

    public function test_a_code_block_is_laid_out_as_a_block(): void
    {
        $tree = self::tree($this->small(CodeBlock::make('a = 1')->label(false)));
        self::assertSame('Code', $tree[0]['role']);
        self::assertSame('/O /Layout /Placement /Block', $tree[0]['attrs']);
    }

    public function test_decorators_in_a_page_header_and_footer_stay_artifacts(): void
    {
        $doc = Document::make(Page::a4()->header(Card::make(Text::make('Head')))->footer(Callout::make(Text::make('Foot')))->body(Text::make('Body')))->title('T')->lang('en');
        self::assertSame(['P'], array_column(self::tree($doc), 'role'));
    }

    public function test_a_contents_list_without_a_title_has_no_caption(): void
    {
        $tree = self::tree($this->small(Toc::make(), Text::h1('Alpha')));
        self::assertSame(['TOC', 'H1'], array_column($tree, 'role'));
        self::assertSame(['TOCI'], array_column($tree[0]['kids'], 'role'));
    }

    public function test_a_contents_list_over_pages_is_one_toc(): void
    {
        $headings = [];
        for ($i = 1; $i <= 120; $i++) {
            $headings[] = Text::h2('Entry ' . $i);
        }
        $tree = self::tree($this->small(Toc::make()->levels(1, 2), Text::h1('Top'), ...$headings));
        self::assertSame(1, self::countRole($tree, 'TOC'));
        self::assertSame(121, self::countRole($tree, 'TOCI'));
    }

    public function test_strict_throws_for_a_skip_only_visible_in_structure_order(): void
    {
        // Painted: H1, H2, H3 (page 1), H4 (page 2): no skip. In the structure the H4 belongs to the section, right after its H2.
        $doc = $this->small(
            Text::h1('Top'),
            Row::make(
                Column::make(Section::make(Text::make(self::long()), Text::make('Late')->asHeading(4))->title('First')->heading(2)),
                Column::make(Text::h3('Other')),
            ),
        );
        $this->expectException(InvalidStructure::class);
        $doc->toPdf(new PdfOptions(tagged: true, strict: true));
    }

    public function test_a_backend_that_cannot_tag_warns_and_writes_an_untagged_pdf(): void
    {
        $backend = new class (new PhpPdfBackend()) implements Backend {
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
        };
        $warnings = [];
        $pdf = $this->document()->toPdf(new PdfOptions(onWarning: function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        }, backend: $backend, tagged: true))->bytes();
        self::assertCount(1, $warnings);
        self::assertStringContainsString('cannot write a structure tree', $warnings[0]);
        self::assertSame([], StructTree::of($pdf));
    }

    public function test_headings_are_checked_in_structure_order_when_a_section_continues_over_pages(): void
    {
        $w = [];
        // Painted: H1, H2, H4 (page 1), H3 (page 2). In the structure the H3 belongs to the section, before the H4.
        $this->warnings($w, true, false, [
            Text::h1('Top'),
            Row::make(
                Column::make(Section::make(Text::make(self::long()), Text::h3('Late'))->title('First')->heading(2)),
                Column::make(Text::h4('Other')),
            ),
        ]);
        self::assertSame([], $w);
    }

    public function test_a_skip_in_structure_order_is_still_reported(): void
    {
        $w = [];
        $this->warnings($w, true, false, [
            Text::h1('Top'),
            Row::make(
                Column::make(Section::make(Text::make(self::long()))->title('First')->heading(2)),
                Column::make(Text::h4('Other')),
            ),
        ]);
        self::assertCount(1, $w);
        self::assertStringContainsString('Heading "Other"', $w[0]);
        self::assertStringContainsString('H4 right after an H2', $w[0]);
    }
}
