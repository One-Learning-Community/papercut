<?php

declare(strict_types=1);

namespace Papercut\Tests\Html;

use Papercut\{Column, Document, Image, Page, Table, TableCell, TableRow, Text};
use Papercut\Exception\ImageUnreadable;
use Papercut\Export\{HtmlOptions, PdfOptions};
use Papercut\Style\ImageFit;
use Papercut\Tests\Support\TestImages;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class HtmlTablesImagesTest extends TestCase
{
    private function html(Document $doc, ?HtmlOptions $o = null): string
    {
        return $doc->toHtml($o)->bytes();
    }

    private function dom(string $html): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><head><title>t</title></head><body>' . $html . '</body></html>');
    }

    /** @return list<\Dom\Element> */
    private function xpath(string $html, string $query): array
    {
        $xp = new \Dom\XPath($this->dom($html));
        $xp->registerNamespace('h', 'http://www.w3.org/1999/xhtml');
        $query = (string) preg_replace('~(?<=/)([a-z][a-z0-9]*)(?![a-z0-9(\-])~', 'h:$1', $query);
        $found = [];
        foreach ($xp->query($query) as $n) {
            self::assertInstanceOf(\Dom\Element::class, $n);
            $found[] = $n;
        }
        return $found;
    }

    private function style(\Dom\Element $e): string
    {
        return (string) $e->getAttribute('style');
    }

    private function body(\Papercut\Node|\Papercut\Component ...$nodes): Document
    {
        return Document::make(Page::a4()->body(...$nodes));
    }

    public function test_table_has_colgroup_widths_thead_and_colspan(): void
    {
        $messages = [];
        $table = Table::make()->columns('30mm 2fr 1fr auto')
            ->head('A', 'B', 'C', 'D')
            ->row(TableCell::make('wide')->colspan(3), 'x')
            ->row('1', '2', '3', '4');
        $html = $this->html($this->body($table), new HtmlOptions(onWarning: function (string $m) use (&$messages): void {
            $messages[] = $m;
        }));
        $cols = $this->xpath($html, '//table/colgroup/col');
        $fixed = 30 * 72 / 25.4;
        $fixedCss = rtrim(rtrim(number_format($fixed, 3, '.', ''), '0'), '.');
        self::assertCount(4, $cols);
        self::assertSame('width:' . $fixedCss . 'pt', $this->style($cols[0]));
        self::assertSame('', $this->style($cols[1]));
        self::assertSame('', $this->style($cols[2]));
        self::assertSame('', $this->style($cols[3]));
        self::assertStringContainsString('table-layout:auto', $this->style($this->xpath($html, '//table')[0]));
        self::assertCount(1, $this->xpath($html, '//table/thead/tr'));
        self::assertCount(2, $this->xpath($html, '//table/tbody/tr'));
        $wide = $this->xpath($html, '//tbody/tr[1]/td[1]');
        self::assertSame('3', $wide[0]->getAttribute('colspan'));
        self::assertNull($this->xpath($html, '//tbody/tr[1]/td[2]')[0]->getAttribute('colspan'));
        self::assertCount(2, array_filter($messages, static fn (string $m): bool => str_contains($m, 'column widths')));
        self::assertContains('html: approximated fr column widths at page > body > table[0]', $messages);
        self::assertContains('html: approximated auto column widths at page > body > table[0]', $messages);
    }

    public function test_missing_tracks_are_fr_and_extra_ignored(): void
    {
        $html = $this->html($this->body(Table::make()->columns('10pt 20pt 30pt 40pt')->row('a', 'b')));
        self::assertCount(2, $this->xpath($html, '//colgroup/col'));
        $html = $this->html($this->body(Table::make()->columns('10pt')->row('a', 'b')));
        $cols = $this->xpath($html, '//colgroup/col');
        self::assertSame('width:calc((100% - 10pt) * 1)', $this->style($cols[1]));
    }

    public function test_header_row_cells_are_th_and_bold_from_theme(): void
    {
        $html = $this->html($this->body(Table::make()->columns('1fr 1fr')->head('H1', 'H2')->row('a', 'b')));
        $th = $this->xpath($html, '//thead/tr/th');
        self::assertCount(2, $th);
        self::assertStringContainsString('font-weight:700', $this->style($th[0]));
        self::assertStringContainsString('padding:', $this->style($th[0]));
        $td = $this->xpath($html, '//tbody/tr/td');
        self::assertCount(2, $td);
        self::assertStringNotContainsString('font-weight', $this->style($td[0]));
        self::assertCount(0, $this->xpath($html, '//thead/tr/td'));
    }

    public function test_cell_children_render_as_blocks_and_tables_nest(): void
    {
        $inner = Table::make()->columns('1fr')->row('deep');
        $html = $this->html($this->body(Table::make()->columns('1fr 1fr')->row(Column::make(Text::make('one'), Text::make('two')), $inner)));
        self::assertCount(2, $this->xpath($html, '//td[1]/div/p'));
        self::assertCount(1, $this->xpath($html, '//td[2]/table/tbody/tr/td'));
        self::assertCount(1, $this->xpath($html, '//table//table'));
    }

    public function test_row_style_is_emitted(): void
    {
        $html = $this->html($this->body(Table::make()->columns('1fr')->bodyRow(TableRow::make('a')->background(\Papercut\Value\Color::hex('#ff0000')))));
        self::assertStringContainsString('background:#ff0000', $this->style($this->xpath($html, '//tbody/tr')[0]));
    }

    public function test_png_and_jpeg_embed_as_data_uris(): void
    {
        $png = TestImages::png(8, 4);
        $im = imagecreatetruecolor(8, 4);
        self::assertNotFalse($im);
        ob_start();
        imagejpeg($im);
        $jpeg = (string) ob_get_clean();
        $html = $this->html($this->body(Image::bytes($png), Image::bytes($jpeg)));
        $imgs = $this->xpath($html, '//div[@class="pc-body"]/img');
        self::assertCount(2, $imgs);
        self::assertSame('data:image/png;base64,' . base64_encode($png), $imgs[0]->getAttribute('src'));
        self::assertSame('data:image/jpeg;base64,' . base64_encode($jpeg), $imgs[1]->getAttribute('src'));
        self::assertSame('', $imgs[0]->getAttribute('alt'));
    }

    public function test_file_image_is_read_and_embedded(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pc') ?: throw new \RuntimeException('tmp');
        $png = TestImages::png();
        file_put_contents($path, $png);
        try {
            $html = $this->html($this->body(Image::file($path)));
        } finally {
            unlink($path);
        }
        self::assertSame('data:image/png;base64,' . base64_encode($png), $this->xpath($html, '//img')[0]->getAttribute('src'));
    }

    public function test_image_url_callback_wins(): void
    {
        $opts = new HtmlOptions(imageUrl: static fn (Image $i): string => 'https://cdn.example/a.png?x=1&y="2"');
        $html = $this->html($this->body(Image::file('/nonexistent/never-read.png')), $opts);
        self::assertStringContainsString('src="https://cdn.example/a.png?x=1&amp;y=&quot;2&quot;"', $html);
        self::assertSame('https://cdn.example/a.png?x=1&y="2"', $this->xpath($html, '//img')[0]->getAttribute('src'));

        foreach (['img/a.png', 'data:image/gif;base64,R0lGOD'] as $ok) {
            $html = $this->html($this->body(Image::bytes(TestImages::png())), new HtmlOptions(imageUrl: static fn (Image $i): string => $ok));
            self::assertSame($ok, $this->xpath($html, '//img')[0]->getAttribute('src'));
        }
    }

    public function test_null_or_unsafe_callback_result_embeds(): void
    {
        $png = TestImages::png();
        foreach ([null, 'javascript:alert(1)', "java\tscript:alert(1)", 'data:text/html,<script>', '//evil.example/x.png', 'file:///etc/passwd'] as $bad) {
            $html = $this->html($this->body(Image::bytes($png)), new HtmlOptions(imageUrl: static fn (Image $i): ?string => $bad));
            self::assertStringStartsWith('data:image/png;base64,', (string) $this->xpath($html, '//img')[0]->getAttribute('src'), (string) $bad);
        }
    }

    public function test_missing_and_unsupported_images_throw_image_unreadable(): void
    {
        $cases = [
            [Image::file('/no/such/file.png'), 'Cannot read image "/no/such/file.png": file not found or not readable'],
            [Image::bytes('GIF89a....'), 'Cannot read image bytes: Unsupported image format (expected PNG or JPEG).'],
            [Image::bytes(''), 'Cannot read image bytes: Unsupported image format (expected PNG or JPEG).'],
        ];
        foreach ($cases as [$image, $message]) {
            try {
                $this->html($this->body($image));
                self::fail('expected ImageUnreadable');
            } catch (ImageUnreadable $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'pc') ?: throw new \RuntimeException('tmp');
        file_put_contents($path, 'not an image');
        try {
            $this->html($this->body(Image::file($path)));
            self::fail('expected ImageUnreadable');
        } catch (ImageUnreadable $e) {
            self::assertSame(sprintf('Cannot read image "%s": Unsupported image format (expected PNG or JPEG).', $path), $e->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function test_object_fit_maps_fit_mode(): void
    {
        $png = TestImages::png();
        $html = $this->html($this->body(
            Image::bytes($png),
            Image::bytes($png)->fit(ImageFit::Cover),
            Image::bytes($png)->fit(ImageFit::Fill)->width(pt(50)),
        ));
        $imgs = $this->xpath($html, '//img');
        self::assertStringContainsString('object-fit:contain', $this->style($imgs[0]));
        self::assertStringContainsString('object-fit:cover', $this->style($imgs[1]));
        self::assertStringContainsString('object-fit:fill', $this->style($imgs[2]));
        self::assertStringContainsString('width:50pt', $this->style($imgs[2]));
    }

    public function test_image_inside_cell_has_no_flex_item_declarations(): void
    {
        $html = $this->html($this->body(Table::make()->columns('1fr')->row(Image::bytes(TestImages::png())->grow(1))));
        self::assertStringNotContainsString('flex:', $this->style($this->xpath($html, '//td/img')[0]));
    }

    public function test_stray_cell_renders_as_div_and_warns_like_pdf(): void
    {
        $doc = $this->body(Column::make(TableCell::make(Text::make('lonely'))->padding(pt(3)), Text::make('after')));
        $htmlWarnings = [];
        $html = $this->html($doc, new HtmlOptions(onWarning: function (string $m) use (&$htmlWarnings): void {
            $htmlWarnings[] = $m;
        }));
        $pdfWarnings = [];
        $doc->toPdf(new PdfOptions(onWarning: function (string $m) use (&$pdfWarnings): void {
            $pdfWarnings[] = $m;
        }));
        $strayPdf = array_values(array_filter($pdfWarnings, static fn (string $m): bool => str_contains($m, 'outside a table')));
        $strayHtml = array_values(array_filter($htmlWarnings, static fn (string $m): bool => str_contains($m, 'outside a table')));
        self::assertCount(1, $strayPdf);
        self::assertSame($strayPdf, $strayHtml);
        self::assertMatchesRegularExpression('/^\[.+\] A table cell outside a table is laid out as a column$/', $strayHtml[0]);

        $div = $this->xpath($html, '//div[@class="pc-body"]/div/div')[0];
        self::assertStringContainsString('display:flex', $this->style($div));
        self::assertStringContainsString('flex-direction:column', $this->style($div));
        self::assertStringContainsString('padding:3pt', $this->style($div));
        self::assertCount(0, $this->xpath($html, '//td | //th'));
        self::assertSame('lonely', $this->xpath($html, '//div[@class="pc-body"]/div/div/p')[0]->textContent);
    }

    public function test_stray_cell_warning_respects_two_argument_callbacks(): void
    {
        $got = [];
        $this->html($this->body(TableCell::make('x')), new HtmlOptions(onWarning: function (string $m, ?\Papercut\Export\FidelityReport $r = null) use (&$got): void {
            $got[] = [$m, $r];
        }));
        $stray = array_values(array_filter($got, static fn (array $g): bool => str_contains($g[0], 'outside a table')));
        self::assertCount(1, $stray);
        self::assertNull($stray[0][1]);
    }

    public function test_fr_widths_subtract_length_and_percent_tracks(): void
    {
        $cols = $this->xpath($this->html($this->body(Table::make()->columns('25% 1fr')->row('a', 'b'))), '//col');
        self::assertSame('width:25%', $this->style($cols[0]));
        self::assertSame('width:calc((100% - 25%) * 1)', $this->style($cols[1]));
        $cols = $this->xpath($this->html($this->body(Table::make()->columns('30mm 25% 2fr 1fr')->row('a', 'b', 'c', 'd'))), '//col');
        self::assertSame('width:calc((100% - 85.039pt - 25%) * 0.667)', $this->style($cols[2]));
        self::assertSame('width:calc((100% - 85.039pt - 25%) * 0.333)', $this->style($cols[3]));
        $cols = $this->xpath($this->html($this->body(Table::make()->columns('1fr 1fr')->row('a', 'b'))), '//col');
        self::assertSame('width:calc(100% * 0.5)', $this->style($cols[0]));
        self::assertStringNotContainsString('table-layout', $this->style($this->xpath($this->html($this->body(Table::make()->columns('1fr')->row('a'))), '//table')[0]));
    }

    public function test_table_and_row_text_styles_cascade(): void
    {
        $red = \Papercut\Value\Color::hex('#ff0000');
        $blue = \Papercut\Value\Color::hex('#0000ff');
        $html = $this->html($this->body(Table::make()->columns('1fr 1fr')->color($red)->bodyRow(TableRow::make('a', TableCell::make('b')->color($blue))->italic())));
        self::assertStringContainsString('color:#ff0000', $this->style($this->xpath($html, '//table')[0]));
        self::assertStringContainsString('font-style:italic', $this->style($this->xpath($html, '//tr')[0]));
        self::assertStringNotContainsString('color', $this->style($this->xpath($html, '//tr')[0]));
        self::assertStringNotContainsString('italic', $this->style($this->xpath($html, '//td')[0]));
        self::assertStringContainsString('color:#0000ff', $this->style($this->xpath($html, '//td')[1]));
    }

    public function test_unsupported_image_messages_match_pdf(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pc') ?: throw new \RuntimeException('tmp');
        file_put_contents($path, 'not an image');
        try {
            foreach ([Image::bytes('GIF89a'), Image::file($path), Image::file('/no/such.png')] as $image) {
                $doc = $this->body($image);
                $messages = [];
                foreach (['toHtml', 'toPdf'] as $m) {
                    try {
                        $doc->$m();
                    } catch (ImageUnreadable $e) {
                        $messages[] = $e->getMessage();
                    }
                }
                self::assertCount(2, $messages);
                self::assertSame($messages[1] ?? null, $messages[0] ?? null);
            }
        } finally {
            unlink($path);
        }
    }

    public function test_refused_image_url_embeds_and_warns(): void
    {
        $png = TestImages::png();
        foreach (['', '/\\evil.example/x.png', '\\\\host\\x.png', 'javascript:x'] as $bad) {
            $warnings = [];
            $html = $this->html($this->body(Image::bytes($png)), new HtmlOptions(imageUrl: static fn (Image $i): string => $bad, onWarning: function (string $m) use (&$warnings): void {
                $warnings[] = $m;
            }));
            self::assertStringStartsWith('data:image/png', (string) $this->xpath($html, '//img')[0]->getAttribute('src'), $bad);
            self::assertSame(['[page > body > image[0]] imageUrl returned an unsafe URL; the image was embedded instead'], array_values(array_filter($warnings, static fn (string $m): bool => str_contains($m, 'imageUrl'))), $bad);
        }
        $warnings = [];
        $this->html($this->body(Image::bytes($png)), new HtmlOptions(imageUrl: static fn (Image $i): ?string => null, onWarning: function (string $m) use (&$warnings): void {
            $warnings[] = $m;
        }));
        self::assertSame([], array_values(array_filter($warnings, static fn (string $m): bool => str_contains($m, 'imageUrl'))));
    }

    public function test_row_outside_table_throws_like_pdf(): void
    {
        $doc = $this->body(TableRow::make('a'));
        $messages = [];
        foreach (['toHtml', 'toPdf'] as $m) {
            try {
                $doc->$m();
                self::fail('expected LogicException');
            } catch (\LogicException $e) {
                $messages[] = $e->getMessage();
            }
        }
        self::assertSame($messages[1], $messages[0]);
        self::assertStringStartsWith('No layouter registered for Papercut\\TableRow at', $messages[0]);
    }

    public function test_cell_decoration_override_is_approximated(): void
    {
        $messages = [];
        $this->html($this->body(Table::make()->columns('1fr')->underline()->bodyRow(TableRow::make(TableCell::make('a')->underline(false)))), new HtmlOptions(onWarning: function (string $m) use (&$messages): void {
            $messages[] = $m;
        }));
        self::assertCount(1, array_filter($messages, static fn (string $m): bool => str_contains($m, 'text decoration override')));
    }
}
