<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests\Package;

use Papercut\{Column, Document, Page, Text};
use Papercut\Docx\Package\{DocxPatcher, PatchSpec};
use Papercut\Docx\Tests\Support\{DocxInspector, Styles};
use Dskripchenko\PhpDocx\{Document as DocxDocument, Section};
use Dskripchenko\PhpDocx\Element\{Paragraph, Run};
use Dskripchenko\PhpDocx\Style\StyleRegistry;
use Dskripchenko\PhpDocx\Writer\Word2007Writer;
use PHPUnit\Framework\TestCase;

final class DocxPatcherTest extends TestCase
{
    use Styles;

    public function test_alignment_is_written_as_left_and_right(): void
    {
        $page = Page::a4()->header(Text::make('h')->right())->body(Column::make(Text::make('r')->right(), Text::make('l')->left()));
        $d = self::export($page);
        foreach (['word/document.xml', 'word/header1.xml'] as $part) {
            self::assertSame(0, $d->count("//w:jc[@w:val='start' or @w:val='end']", $part), $part);
        }
        self::assertSame(1, $d->count("//w:p[.//w:t='r']/w:pPr/w:jc[@w:val='right']"));
        self::assertSame(1, $d->count("//w:jc[@w:val='right']", 'word/header1.xml'));
    }

    public function test_doc_defaults_take_the_theme_font_and_the_language(): void
    {
        $d = self::export(Document::make(Page::a4()->body(Text::make('x')))->lang('en-GB'));
        $styles = 'word/styles.xml';
        self::assertSame('Inter', $d->value('//w:docDefaults//w:rFonts/@w:ascii', $styles));
        self::assertSame('20', $d->value('//w:docDefaults//w:sz/@w:val', $styles));
        self::assertSame('en-GB', $d->value('//w:docDefaults//w:lang/@w:val', $styles));
        self::assertSame([], self::exportedReports());

        $d = self::export(Page::a4()->body(Text::make('x')));
        self::assertSame(0, $d->count('//w:docDefaults//w:lang', $styles));
    }

    public function test_a_missing_anchor_fails_loudly(): void
    {
        $bytes = Document::make(Page::a4()->body(Text::make('x')))->export(new \Papercut\Docx\DocxExporter())->bytes();
        $zip = new \ZipArchive();
        $file = (string) tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($file, $bytes);
        $zip->open($file);
        $zip->addFromString('word/styles.xml', '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>');
        $zip->close();
        $broken = (string) file_get_contents($file);
        unlink($file);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('php-docx output changed: ');
        DocxPatcher::patch($broken, new PatchSpec('Inter', 20, null, new \DateTimeImmutable('2000-01-01')));
    }

    public function test_a_shading_marker_left_unpatched_fails_loudly(): void
    {
        $bytes = Document::make(Page::a4()->body(Text::make('x')))->export(new \Papercut\Docx\DocxExporter())->bytes();
        $zip = new \ZipArchive();
        $file = (string) tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($file, $bytes);
        $zip->open($file);
        $doc = (string) $zip->getFromName('word/document.xml');
        // as if php-docx wrote the highlight another way
        $zip->addFromString('word/document.xml', str_replace('<w:t', '<w:rPr><w:highlight w:val="' . DocxPatcher::shading('ffff00') . '"></w:highlight></w:rPr><w:t', $doc));
        $zip->close();
        $changed = (string) file_get_contents($file);
        unlink($file);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('php-docx output changed: run highlight in word/document.xml not found');
        DocxPatcher::patch($changed, new PatchSpec('Inter', 20, null, new \DateTimeImmutable('2000-01-01')));
    }

    private const string MIRROR = '<w:mirrorMargins/>';

    private static function spec(bool $mirror): PatchSpec
    {
        return new PatchSpec('Inter', 20, null, new \DateTimeImmutable('2000-01-01'), $mirror);
    }

    /** @param array<string, ?string> $changes part => new content (null removes it) */
    private static function rewrite(string $bytes, array $changes): string
    {
        $zip = new \ZipArchive();
        $file = (string) tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($file, $bytes);
        $zip->open($file);
        foreach ($changes as $name => $content) {
            $content === null ? $zip->deleteName($name) : $zip->addFromString($name, $content);
        }
        $zip->close();
        $out = (string) file_get_contents($file);
        unlink($file);

        return $out;
    }

    private static function part(string $bytes, string $name): string
    {
        return DocxInspector::of($bytes)->part($name);
    }

    /** php-docx's own output, as DocxPatcher receives it. */
    private static function raw(?Section $section = null): string
    {
        $section ??= new Section([new Paragraph([new Run('x')])]);

        return (new Word2007Writer(new StyleRegistry()))->write(new DocxDocument($section));
    }

    private static function plain(): string
    {
        return self::raw();
    }

    private static function withEven(): string
    {
        return self::raw(new Section([new Paragraph([new Run('x')])], evenHeader: [new Paragraph([new Run('e')])]));
    }

    public function test_mirror_margins_creates_the_settings_part(): void
    {
        $out = DocxPatcher::patch(self::plain(), self::spec(true));
        $d = DocxInspector::of($out);
        self::assertStringContainsString(self::MIRROR, $d->part('word/settings.xml'));
        self::assertStringContainsString('<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">', $d->part('word/settings.xml'));
        $rels = $d->part('word/_rels/document.xml.rels');
        self::assertStringContainsString('Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"', $rels);
        self::assertStringContainsString('<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>', $d->part('[Content_Types].xml'));
        preg_match_all('~Id="([^"]+)"~', $rels, $m);
        self::assertSame($m[1], array_values(array_unique($m[1])));
    }

    public function test_mirror_margins_goes_before_even_and_odd_headers(): void
    {
        $out = DocxPatcher::patch(self::withEven(), self::spec(true));
        self::assertStringContainsString('<w:mirrorMargins/><w:evenAndOddHeaders/>', self::part($out, 'word/settings.xml'));
        self::assertSame(1, substr_count(self::part($out, 'word/_rels/document.xml.rels'), 'relationships/settings"'));
    }

    public function test_mirror_margins_off_leaves_settings_alone(): void
    {
        foreach ([self::plain(), self::withEven()] as $bytes) {
            self::assertSame(DocxPatcher::patch($bytes, self::spec(false)), DocxPatcher::patch($bytes, new PatchSpec('Inter', 20, null, new \DateTimeImmutable('2000-01-01'))));
            self::assertStringNotContainsString(self::MIRROR, self::part(DocxPatcher::patch($bytes, self::spec(false)), 'word/document.xml'));
        }
        self::assertFalse(DocxInspector::of(DocxPatcher::patch(self::plain(), self::spec(false)))->has('word/settings.xml'));
    }

    public function test_mirror_patch_fails_loudly_when_markup_changes(): void
    {
        $cases = [
            [self::plain(), 'word/_rels/document.xml.rels', '</Relationships>'],
            [self::plain(), '[Content_Types].xml', '</Types>'],
            [self::withEven(), 'word/settings.xml', '<w:evenAndOddHeaders/>'],
        ];
        foreach ($cases as [$bytes, $name, $remove]) {
            $broken = self::rewrite($bytes, [$name => str_replace($remove, '', self::part($bytes, $name))]);
            try {
                DocxPatcher::patch($broken, self::spec(true));
                self::fail('expected a LogicException for ' . $remove);
            } catch (\LogicException $e) {
                self::assertStringContainsString('php-docx output changed', $e->getMessage());
            }
        }
    }

    public function test_add_part_refuses_a_name_that_already_exists(): void
    {
        $add = new \ReflectionMethod(DocxPatcher::class, 'addPart');
        try {
            $add->invoke(null, ['word/settings.xml' => '<w:settings/>'], 'word/settings.xml', '<x/>', 'type', 'content/type');
            self::fail('expected a LogicException');
        } catch (\LogicException $e) {
            self::assertSame('php-docx output changed: word/settings.xml already exists', $e->getMessage());
        }
    }
}
