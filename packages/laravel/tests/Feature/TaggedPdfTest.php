<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Backend\PhpPdf\TaggingSupport;
use Papercut\Export\Encryption;
use Papercut\Laravel\Tests\Support\ReadsStructure;
use Papercut\Laravel\Tests\TestCase;

/** Tagged output; skipped when the installed php-pdf cannot write it. */
final class TaggedPdfTest extends TestCase
{
    use ReadsStructure;

    protected function setUp(): void
    {
        parent::setUp();
        if (TaggingSupport::missing() !== []) {
            $this->markTestSkipped('The installed php-pdf cannot write tagged PDFs.');
        }
    }

    public function test_the_config_turns_tagging_on(): void
    {
        config(['papercut.tagged' => true]);

        $this->assertTrue(self::isTagged($this->document()->pdf()->toString()));
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
