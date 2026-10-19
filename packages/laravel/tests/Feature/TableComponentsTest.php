<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Table;
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;

final class TableComponentsTest extends TestCase
{
    private const string TABLE = <<<'BLADE'
<x-pdf::table columns="3fr 25mm 20mm" :repeat-header="false">
  <x-pdf::thead><x-pdf::th>Course</x-pdf::th><x-pdf::th>Credits</x-pdf::th><x-pdf::th>Grade</x-pdf::th></x-pdf::thead>
  @foreach ($courses as $c)
    <x-pdf::tr>
      <x-pdf::td>{{ $c[0] }}</x-pdf::td>
      <x-pdf::td>{{ $c[1] }}</x-pdf::td>
      <x-pdf::td align="right" colspan="1">{{ $c[2] }}</x-pdf::td>
    </x-pdf::tr>
  @endforeach
</x-pdf::table>
BLADE;

    public function test_table_from_blade(): void
    {
        $t = $this->nodeFrom(self::TABLE, ['courses' => [['Math', 4, 'A'], ['Art', 2, 'B']]]);
        self::assertInstanceOf(Table::class, $t);
        self::assertNotNull($t->trackList());
        self::assertCount(3, $t->trackList()->tracks);
        self::assertCount(1, $t->headerRows());
        self::assertTrue($t->headerRows()[0]->cells()[0]->header());
        self::assertCount(2, $t->bodyRows());
        self::assertFalse($t->repeatsHeader());
    }

    public function test_thead_with_multiple_rows(): void
    {
        $t = $this->nodeFrom('<x-pdf::table><x-pdf::thead><x-pdf::tr><x-pdf::th>A</x-pdf::th></x-pdf::tr><x-pdf::tr><x-pdf::th>B</x-pdf::th></x-pdf::tr></x-pdf::thead></x-pdf::table>');
        self::assertInstanceOf(Table::class, $t);
        self::assertCount(2, $t->headerRows());
        self::assertTrue($t->repeatsHeader());
    }

    public function test_repeat_header_presence_and_string_false(): void
    {
        $t = $this->nodeFrom('<x-pdf::table repeat-header />');
        self::assertInstanceOf(Table::class, $t);
        self::assertTrue($t->repeatsHeader());
        $t = $this->nodeFrom('<x-pdf::table repeat-header="false" />');
        self::assertInstanceOf(Table::class, $t);
        self::assertFalse($t->repeatsHeader());
    }

    public function test_colspan_and_align(): void
    {
        $t = $this->nodeFrom('<x-pdf::table><x-pdf::tr><x-pdf::td colspan="2" align="right" padding="2mm">x</x-pdf::td></x-pdf::tr></x-pdf::table>');
        self::assertInstanceOf(Table::class, $t);
        self::assertSame(2, $t->bodyRows()[0]->cells()[0]->span());
    }

    /** @return iterable<string, array{string, string}> */
    public static function misplaced(): iterable
    {
        yield 'td in column' => ['<x-pdf::column><x-pdf::td>x</x-pdf::td></x-pdf::column>', '<x-pdf::td> must be inside <x-pdf::tr>'];
        yield 'tr in column' => ['<x-pdf::column><x-pdf::tr><x-pdf::td>x</x-pdf::td></x-pdf::tr></x-pdf::column>', '<x-pdf::tr> must be inside <x-pdf::table>'];
        yield 'thead in column' => ['<x-pdf::column><x-pdf::thead><x-pdf::th>x</x-pdf::th></x-pdf::thead></x-pdf::column>', '<x-pdf::thead> must be inside <x-pdf::table>'];
        yield 'text in tr' => ['<x-pdf::table><x-pdf::tr>loose</x-pdf::tr></x-pdf::table>', '<x-pdf::tr> can only contain <x-pdf::td> or <x-pdf::th>'];
        yield 'column in table' => ['<x-pdf::table><x-pdf::column /></x-pdf::table>', '<x-pdf::table> can only contain'];
        yield 'mixed thead' => ['<x-pdf::table><x-pdf::thead><x-pdf::th>a</x-pdf::th><x-pdf::tr><x-pdf::th>b</x-pdf::th></x-pdf::tr></x-pdf::thead></x-pdf::table>', '<x-pdf::thead> can only contain'];
        yield 'two theads' => ['<x-pdf::table><x-pdf::thead><x-pdf::th>a</x-pdf::th></x-pdf::thead><x-pdf::thead><x-pdf::th>b</x-pdf::th></x-pdf::thead></x-pdf::table>', '<x-pdf::table> can only contain'];
        yield 'thead after body' => ['<x-pdf::table><x-pdf::tr><x-pdf::td>a</x-pdf::td></x-pdf::tr><x-pdf::thead><x-pdf::th>b</x-pdf::th></x-pdf::thead></x-pdf::table>', '<x-pdf::table> can only contain'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('misplaced')]
    public function test_structure_errors(string $blade, string $message): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage($message);
        $this->nodeFrom($blade);
    }
}
