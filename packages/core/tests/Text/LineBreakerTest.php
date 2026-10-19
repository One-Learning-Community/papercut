<?php

declare(strict_types=1);

namespace Papercut\Tests\Text;

use Papercut\Span;
use Papercut\Style\{ComputedStyle, Theme};
use Papercut\Tests\Support\FakeBackend;
use Papercut\Text\{FontRegistry, Itemizer, Line, LineBreaker};
use PHPUnit\Framework\TestCase;

final class LineBreakerTest extends TestCase
{
    /** @return list<Line> */
    private function lines(string $text, float $width): array
    {
        $items = (new Itemizer(FontRegistry::withDefaults(new FakeBackend())))->itemize([Span::text($text)], ComputedStyle::root(Theme::default()));
        return LineBreaker::break($items, $width);
    }

    /**
     * @param list<Line> $lines
     * @return list<string>
     */
    private function texts(array $lines): array
    {
        return array_map(fn (Line $l) => implode('', array_map(fn ($i) => $i->text, $l->items)), $lines);
    }

    public function test_greedy_wrap(): void
    {
        // each char = 5pt at 10pt FakeFont; "aaa bbb ccc" -> words 15pt, space 5pt
        self::assertSame(['aaa bbb', 'ccc'], $this->texts($this->lines('aaa bbb ccc', 35)));
        self::assertSame(['aaa', 'bbb', 'ccc'], $this->texts($this->lines('aaa bbb ccc', 34)));
    }

    public function test_line_width_excludes_trailing_space(): void
    {
        $lines = $this->lines('aaa bbb ccc', 35);
        self::assertSame(35.0, $lines[0]->width);
        self::assertFalse($lines[0]->isLast);
        self::assertTrue($lines[1]->isLast);
    }

    public function test_overlong_word_breaks_by_character(): void
    {
        self::assertSame(['abcd', 'efgh', 'ij'], $this->texts($this->lines('abcdefghij', 20)));
    }

    public function test_tiny_width_still_progresses(): void
    {
        self::assertCount(3, $this->lines('abc', 0));
    }

    public function test_hyphen_break_opportunity(): void
    {
        self::assertSame(['well-', 'known'], $this->texts($this->lines('well-known', 30)));
    }

    public function test_empty(): void
    {
        self::assertSame([], $this->lines('', 100));
    }
}
