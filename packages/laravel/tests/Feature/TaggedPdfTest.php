<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\{Document, Page, Text};
use Papercut\Backend\PhpPdf\TaggingSupport;
use Papercut\Export\Encryption;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PendingDocument;
use Papercut\Laravel\Tests\TestCase;

final class TaggedPdfTest extends TestCase
{
    private function document(): PendingDocument
    {
        return Papercut::make(Document::make(Page::a4()->body(Text::h1('Title'), Text::p('Body')))->title('Doc')->lang('en-US'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (TaggingSupport::missing() !== []) {
            $this->markTestSkipped('The installed php-pdf cannot write tagged PDFs.');
        }
    }

    /** Whether a PDF has a structure tree; looks inside object streams (and decrypts with $password). */
    private static function isTagged(string $pdf, string $password = ''): bool
    {
        $file = tempnam(sys_get_temp_dir(), 'pc') . '.pdf'; // qpdf's AppArmor profile wants .pdf
        rename(substr($file, 0, -4), $file);
        file_put_contents($file, $pdf);
        exec(sprintf('qpdf --qdf --object-streams=disable --password=%s %s %s.qdf 2>&1 < /dev/null', escapeshellarg($password), escapeshellarg($file), escapeshellarg($file)));
        $qdf = (string) file_get_contents($file . '.qdf');
        unlink($file);
        @unlink($file . '.qdf');

        return str_contains($qdf, '/StructTreeRoot');
    }

    public function test_untagged_by_default(): void
    {
        $this->assertFalse(self::isTagged($this->document()->pdf()->toString()));
    }

    public function test_the_config_turns_tagging_on(): void
    {
        config(['papercut.tagged' => true]);

        $this->assertTrue(self::isTagged($this->document()->pdf()->toString()));
    }

    public function test_an_invalid_config_value_is_rejected(): void
    {
        config(['papercut.tagged' => 'yes']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.tagged');
        $this->document()->pdf()->toString();
    }

    public function test_tagged_overrides_the_config_per_document(): void
    {
        $this->assertTrue(self::isTagged($this->document()->tagged()->pdf()->toString()));
    }

    public function test_tagged_false_overrides_a_config_that_is_on(): void
    {
        config(['papercut.tagged' => true]);

        $this->assertFalse(self::isTagged($this->document()->tagged(false)->pdf()->toString()));
    }

    public function test_the_override_survives_theme_and_encrypt_in_either_order(): void
    {
        $a = $this->document()->tagged()->theme(['font_size' => 12])->encrypt('pw');
        $b = $this->document()->encrypt('pw')->theme(['font_size' => 12])->tagged();

        foreach ([$a, $b] as $pending) {
            $pdf = $pending->pdf()->toString();
            $this->assertStringContainsString('/Encrypt', $pdf);
            $this->assertTrue(self::isTagged($pdf, 'pw'));
        }
    }

    public function test_encryption_is_kept_with_tagging(): void
    {
        $pdf = $this->document()->tagged()->encrypt(Encryption::password('pw'))->pdf()->toString();

        $this->assertStringContainsString('/Encrypt', $pdf);
        $this->assertTrue(self::isTagged($pdf, 'pw'));
    }
}
