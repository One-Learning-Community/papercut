<?php

declare(strict_types=1);

namespace Papercut\Tests\Feature;

use Papercut\{Column, Document, Image, Page, Svg, Text};
use Papercut\Node;
use Papercut\Exception\InvalidStructure;
use Papercut\Export\{Encryption, PdfA, PdfOptions};
use Papercut\Tests\Support\{PdfInspector, TestImages};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** PDF/A output, read back from the bytes. */
final class PdfATest extends TestCase
{
    private function doc(bool $lang = true): Document
    {
        $doc = Document::make(Page::a4()->body(Column::make(Text::h1('Archive'), Text::make('Body text'))))
            ->title('Archive & "Record"')->author('Pat')
            ->createdAt(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        return $lang ? $doc->lang('en-US') : $doc;
    }

    private function pdf(PdfA $level, ?Document $doc = null, bool $tagged = false): string
    {
        return ($doc ?? $this->doc())->toPdf(new PdfOptions(tagged: $tagged, pdfA: $level))->bytes();
    }

    public function test_options_accessors_and_copies_keep_the_level(): void
    {
        $o = new PdfOptions(pdfA: PdfA::A2u);
        self::assertSame(PdfA::A2u, $o->pdfA());
        self::assertNull((new PdfOptions())->pdfA());
        self::assertSame(PdfA::A3b, $o->withPdfA(PdfA::A3b)->pdfA());
        self::assertNull($o->withPdfA(null)->pdfA());
        self::assertSame(PdfA::A2u, $o->withTagged(true)->pdfA());
        self::assertSame(PdfA::A2u, (new PdfOptions(pdfA: PdfA::A2u))->withTagged(true)->withEncryption(null)->pdfA());
    }

    public function test_enum_parts_and_conformance(): void
    {
        self::assertSame([1, 'B', false], [PdfA::A1b->part(), PdfA::A1b->conformance(), PdfA::A1b->requiresTagging()]);
        self::assertSame([2, 'U', false], [PdfA::A2u->part(), PdfA::A2u->conformance(), PdfA::A2u->requiresTagging()]);
        self::assertSame([3, 'A', true], [PdfA::A3a->part(), PdfA::A3a->conformance(), PdfA::A3a->requiresTagging()]);
    }

    /** @return iterable<string, array{PdfA, string, string}> */
    public static function levels(): iterable
    {
        foreach (PdfA::cases() as $level) {
            yield $level->value => [$level, (string) $level->part(), $level->conformance()];
        }
    }

    #[DataProvider('levels')]
    public function test_identifies_the_level_in_xmp(PdfA $level, string $part, string $conf): void
    {
        $pdf = $this->pdf($level);
        PdfInspector::check($pdf);
        $qdf = PdfInspector::qdf($pdf);
        self::assertStringContainsString('<pdfaid:part>' . $part . '</pdfaid:part>', $qdf);
        self::assertStringContainsString('<pdfaid:conformance>' . $conf . '</pdfaid:conformance>', $qdf);
        self::assertStringContainsString('/OutputIntents', $qdf);
        self::assertStringContainsString('sRGB2014', $qdf);
        self::assertStringContainsString('/DestOutputProfile', $qdf);
        $isUa = $level->requiresTagging();
        self::assertSame($isUa, str_contains($qdf, '<pdfuaid:part>1</pdfuaid:part>'));
    }

    public function test_header_version_and_object_streams(): void
    {
        self::assertStringStartsWith('%PDF-1.4', $this->pdf(PdfA::A2u));
        self::assertStringStartsWith('%PDF-1.4', $this->pdf(PdfA::A1b));
        self::assertStringStartsWith('%PDF-1.5', $this->pdf(PdfA::A2a));
        self::assertStringStartsWith('%PDF-1.5', $this->pdf(PdfA::A2u, tagged: true));
        $part1Tagged = $this->pdf(PdfA::A1b, tagged: true);
        self::assertStringStartsWith('%PDF-1.4', $part1Tagged);
        self::assertStringNotContainsString('/ObjStm', $part1Tagged);
    }

    public function test_tagged_with_a_b_or_u_level_adds_pdf_ua(): void
    {
        $qdf = PdfInspector::qdf($this->pdf(PdfA::A2u, tagged: true));
        self::assertStringContainsString('<pdfaid:conformance>U</pdfaid:conformance>', $qdf);
        self::assertStringContainsString('<pdfuaid:part>1</pdfuaid:part>', $qdf);
        self::assertStringContainsString('/StructTreeRoot', $qdf);
    }

    public function test_a_level_tags_the_pdf(): void
    {
        self::assertStringContainsString('/StructTreeRoot', PdfInspector::qdf($this->pdf(PdfA::A3a)));
        self::assertStringNotContainsString('/StructTreeRoot', PdfInspector::qdf($this->pdf(PdfA::A2u)));
    }

    public function test_info_and_xmp_titles_match(): void
    {
        $qdf = PdfInspector::qdf($this->pdf(PdfA::A2u));
        self::assertStringContainsString('/Title (Archive & "Record")', $qdf);
        self::assertMatchesRegularExpression('/<rdf:li[^>]*>Archive &amp; (&quot;|")Record(&quot;|")<\/rdf:li>/', $qdf);
        self::assertSame('Archive & "Record"', PdfInspector::info($this->pdf(PdfA::A2u))['Title']);
    }

    public function test_a_level_needs_title_and_language_like_tagged(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('language');
        $this->pdf(PdfA::A2a, $this->doc(lang: false));
    }

    public function test_a_level_needs_a_title(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('title');
        $this->pdf(PdfA::A3a, Document::make(Page::a4()->body(Text::make('x')))->lang('en'));
    }

    public function test_a_document_without_a_language_is_not_declared_english(): void
    {
        $qdf = PdfInspector::qdf($this->pdf(PdfA::A2u, $this->doc(lang: false)));
        self::assertStringNotContainsString('/Lang (en)', $qdf);
        self::assertStringContainsString('/Lang (en-US)', PdfInspector::qdf($this->pdf(PdfA::A2u)));
    }

    public function test_b_level_does_not_need_title_or_language(): void
    {
        $pdf = $this->pdf(PdfA::A2b, Document::make(Page::a4()->body(Text::make('x'))));
        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_encryption_conflict_throws_early_naming_both(): void
    {
        $options = (new PdfOptions(pdfA: PdfA::A2u))->withEncryption(Encryption::password('x'));
        try {
            $this->doc()->toPdf($options);
            self::fail('expected an exception');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('pdfA', $e->getMessage());
            self::assertStringContainsString('encryption', $e->getMessage());
        }
    }

    /** @return iterable<string, array{Node, string}> */
    public static function transparentContent(): iterable
    {
        $svg = static fn (string $body): Svg => Svg::fromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">' . $body . '</svg>')->alt('x');
        yield 'opacity on a box' => [Column::make(Text::make('x'))->opacity(0.5), 'opacity'];
        yield 'a colour with alpha' => [Text::make('x')->color('#ff000080'), 'alpha'];
        yield 'a background with alpha' => [Column::make(Text::make('x'))->background('#00ff0080'), 'alpha'];
        yield 'an image with an alpha channel' => [Image::bytes(TestImages::png(alpha: true))->alt('x'), 'an image with an alpha channel'];
        yield 'svg opacity' => [$svg('<rect width="5" height="5" opacity="0.5"/>'), 'SVG'];
        yield 'svg fill-opacity' => [$svg('<rect width="5" height="5" fill-opacity="0.4"/>'), 'fill-opacity'];
        yield 'svg stroke-opacity in style' => [$svg('<rect width="5" height="5" style="stroke:red;stroke-opacity:.3"/>'), 'stroke-opacity'];
    }

    #[DataProvider('transparentContent')]
    public function test_part_1_refuses_transparency_naming_it_and_suggesting_a2b(Node $content, string $named): void
    {
        $doc = Document::make(Page::a4()->body(Column::make($content)))->title('T')->lang('en');
        foreach ([false, true] as $tagged) {
            try {
                $this->pdf(PdfA::A1b, $doc, $tagged);
                self::fail('expected an exception');
            } catch (InvalidStructure $e) {
                self::assertStringContainsString('PDF/A-1', $e->getMessage());
                self::assertStringContainsString('transparency', $e->getMessage());
                self::assertStringContainsString($named, $e->getMessage());
                self::assertStringContainsString('PdfA::A2b', $e->getMessage());
            }
        }
    }

    #[DataProvider('transparentContent')]
    public function test_parts_2_and_3_allow_the_same_documents(Node $content, string $named): void
    {
        $doc = Document::make(Page::a4()->body(Column::make($content)))->title('T')->lang('en');
        foreach ([PdfA::A2b, PdfA::A2u, PdfA::A3b, PdfA::A3u] as $level) {
            self::assertStringStartsWith('%PDF-', $this->pdf($level, $doc));
        }
    }

    public function test_part_1_allows_opaque_content_and_harmless_opacity(): void
    {
        $svg = Svg::fromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="5" height="5" opacity="1" fill-opacity="1"/></svg>')->alt('x');
        $doc = Document::make(Page::a4()->body(Column::make(
            Text::make('x')->opacity(1.0),
            Image::bytes(TestImages::png())->alt('x'),
            Image::bytes(TestImages::jpeg())->alt('x'),
            $svg,
        )->background('#00ff00ff')))->title('T')->lang('en');
        self::assertStringStartsWith('%PDF-1.4', $this->pdf(PdfA::A1b, $doc));
    }

    public function test_non_pdfa_output_is_unchanged_by_the_option_default(): void
    {
        $plain = $this->doc()->toPdf(new PdfOptions())->bytes();
        self::assertStringNotContainsString('/OutputIntents', PdfInspector::qdf($plain));
        self::assertStringNotContainsString('pdfaid', PdfInspector::qdf($plain));
    }
}
