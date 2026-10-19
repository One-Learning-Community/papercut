<?php

declare(strict_types=1);

namespace Papercut\Tests\Exception;

use Papercut\Exception\InvalidValue;
use Papercut\PapercutException;
use PHPUnit\Framework\TestCase;

final class InvalidValueTest extends TestCase
{
    public function test_message_without_suggestion(): void
    {
        $e = InvalidValue::for('length', '20px!');
        self::assertInstanceOf(PapercutException::class, $e);
        self::assertSame('Invalid length "20px!".', $e->getMessage());
    }

    public function test_message_with_suggestion(): void
    {
        $e = InvalidValue::for('length', '20 mm', '20mm');
        self::assertSame('Invalid length "20 mm". Did you mean "20mm"?', $e->getMessage());
        self::assertSame('20mm', $e->suggestion);
    }

    public function test_with_context_returns_new_exception_with_context_in_message(): void
    {
        $base = InvalidValue::for('length', '20 mm', '20mm');
        $e = $base->withContext('margin on <x-pdf::page>');
        self::assertNotSame($base, $e);
        self::assertSame(
            'Invalid length "20 mm" for margin on <x-pdf::page>. Did you mean "20mm"?',
            $e->getMessage(),
        );
        self::assertSame($base, $e->getPrevious());
    }
}
