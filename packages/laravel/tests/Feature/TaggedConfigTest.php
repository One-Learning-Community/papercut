<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Laravel\Tests\Support\ReadsStructure;
use Papercut\Laravel\Tests\TestCase;

/** The tagged config key's cases that need no tagging support in php-pdf, so they run with any release. */
final class TaggedConfigTest extends TestCase
{
    use ReadsStructure;

    public function test_untagged_by_default(): void
    {
        $this->assertFalse(self::isTagged($this->document()->pdf()->toString()));
    }

    public function test_an_invalid_config_value_is_rejected(): void
    {
        config(['papercut.tagged' => 'yes']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.tagged');
        $this->document()->pdf()->toString();
    }
}
