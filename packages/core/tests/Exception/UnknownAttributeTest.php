<?php

declare(strict_types=1);

namespace Papercut\Tests\Exception;

use Papercut\Exception\UnknownAttribute;
use PHPUnit\Framework\TestCase;

final class UnknownAttributeTest extends TestCase
{
    public function test_plain_message(): void
    {
        self::assertSame('Unknown attribute "foo".', UnknownAttribute::for('foo')->getMessage());
    }

    public function test_message_with_suggestion(): void
    {
        self::assertSame(
            'Unknown attribute "paddng". Did you mean "padding"?',
            UnknownAttribute::for('paddng', 'padding')->getMessage(),
        );
    }

    public function test_message_with_context_and_suggestion(): void
    {
        $base = UnknownAttribute::for('paddng', 'padding');
        $e = $base->withContext('on <x-pdf::column>');
        self::assertSame(
            'Unknown attribute "paddng" on <x-pdf::column>. Did you mean "padding"?',
            $e->getMessage(),
        );
        self::assertSame($base, $e->getPrevious());
        self::assertSame('on <x-pdf::column>', $e->context);
    }

    public function test_context_without_suggestion_and_custom_noun(): void
    {
        self::assertSame(
            'Unknown style "x" in theme.',
            UnknownAttribute::for('x', null, 'style')->withContext('in theme')->getMessage(),
        );
    }
}
