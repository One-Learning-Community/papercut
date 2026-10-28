<?php

declare(strict_types=1);

namespace Papercut\Docx\Tests\Map;

use Papercut\Docx\Map\LineMetrics;
use Papercut\Text\FontSet;
use PHPUnit\Framework\TestCase;

final class LineMetricsTest extends TestCase
{
    public function test_bundled_inter_natural_line_height(): void
    {
        $m = new LineMetrics(new FontSet());
        self::assertSame(1.2099609375, $m->natural('Inter'));
        self::assertSame(1.2099609375, $m->natural('inter'));
    }

    public function test_unknown_family_falls_back(): void
    {
        self::assertSame(1.2, (new LineMetrics(new FontSet()))->natural('Nope'));
    }
}
