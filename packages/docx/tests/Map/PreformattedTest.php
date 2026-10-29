<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests\Map;

use Papercut\Docx\Tests\Support\{DocxInspector, Styles};
use Papercut\Markup\Markup;
use PHPUnit\Framework\TestCase;

final class PreformattedTest extends TestCase
{
    use Styles;

    private function docx(string $html): DocxInspector
    {
        return self::export(Markup::html($html));
    }

    public function test_docx_pre_preserves_spaces(): void
    {
        $doc = $this->docx("<pre>a  b\n  c</pre>");
        self::assertStringContainsString('<w:t xml:space="preserve">a  b</w:t>', $doc->part('word/document.xml'));
        self::assertStringContainsString('<w:t xml:space="preserve">  c</w:t>', $doc->part('word/document.xml'));
        self::assertSame(1, $doc->count('//w:r/w:br'));
    }

    public function test_docx_pre_tabs_are_w_tab(): void
    {
        $doc = $this->docx("<pre>a\tb</pre>");
        self::assertSame(1, $doc->count('//w:r/w:tab'));
        foreach ($doc->values('//w:t') as $text) {
            self::assertStringNotContainsString("\t", $text);
        }
    }

    public function test_docx_pre_uses_courier_new(): void
    {
        $doc = $this->docx('<pre>a  b</pre><p>plain <code>x</code></p>');
        self::assertSame('Courier New', $doc->value('//w:r[w:t="a  b"]/w:rPr/w:rFonts/@w:ascii'));
        self::assertSame('Courier New', $doc->value('//w:r[w:t="x"]/w:rPr/w:rFonts/@w:ascii'));
        self::assertSame('Inter', $doc->value('//w:r[w:t="plain "]/w:rPr/w:rFonts/@w:ascii'));
    }

    public function test_docx_pre_line_breaks_carry_the_mono_run_properties(): void
    {
        $doc = $this->docx("<pre>a\n\nb</pre>");
        self::assertSame(2, $doc->count('//w:r[w:br]'));
        self::assertSame(2, $doc->count('//w:r[w:br]/w:rPr/w:rFonts[@w:ascii="Courier New"]'));
    }
}
