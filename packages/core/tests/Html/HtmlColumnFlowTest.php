<?php

declare(strict_types=1);

namespace Papercut\Tests\Html;

use Papercut\{ColumnBreak, ColumnFlow, Document, Page, Table, TableCell, Text, View};
use Papercut\Exception\InvalidStructure;
use Papercut\Export\HtmlOptions;
use Papercut\Value\{Color, ColumnSpec};
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class HtmlColumnFlowTest extends TestCase
{
    /** @param list<string> $messages */
    private function html(\Papercut\Node|\Papercut\Component $node, array &$messages = []): string
    {
        $doc = Document::make(Page::a4()->body($node));
        return $doc->toHtml(new HtmlOptions(onWarning: function (string $m) use (&$messages): void {
            $messages[] = $m;
        }))->bytes();
    }

    public function test_flow_writes_column_css(): void
    {
        $html = $this->html(ColumnFlow::make(Text::make('a'))->columns(3)->columnGap(pt(12)));
        self::assertStringContainsString('column-count:3', $html);
        self::assertStringContainsString('column-gap:12pt', $html);
        self::assertStringContainsString('column-fill:balance', $html);
        self::assertStringNotContainsString('column-rule', $html);
        self::assertStringNotContainsString('display:flex;flex-direction:column;column-count', $html);
    }

    public function test_rule_writes_column_rule(): void
    {
        $html = $this->html(ColumnFlow::make(Text::make('a'))->columnRule(pt(1), Color::hex('#ff0000')));
        self::assertStringContainsString('column-rule:1pt solid #ff0000', $html);
    }

    public function test_column_break_writes_break_before_column(): void
    {
        $html = $this->html(ColumnFlow::make(Text::make('a'), ColumnBreak::make(), Text::make('b')));
        self::assertStringContainsString('<div class="pc-column-break"></div>', $html);
        self::assertStringContainsString('.papercut .pc-column-break{break-before:column}', $html);
    }

    public function test_unequal_widths_are_approximated(): void
    {
        $messages = [];
        $flow = ColumnFlow::make(Text::make('a'))->withColumnSpec(new ColumnSpec(2, [1.0, 2.0], [36.0]));
        $html = $this->html($flow, $messages);
        self::assertStringContainsString('column-count:2', $html);
        self::assertCount(1, array_filter($messages, static fn (string $m): bool => str_starts_with($m, 'html: approximated unequal column widths at page > body > ')));
    }

    public function test_markup_flow_in_a_cell_has_no_column_css_and_a_note(): void
    {
        $messages = [];
        $flow = ColumnFlow::make(Text::make('a'));
        $spec = $flow->columnSpec();
        self::assertNotNull($spec);
        $flow = $flow->withColumnSpec($spec->withFromMarkup());
        $html = $this->html(Table::make()->columns('1fr')->row(TableCell::make($flow)), $messages);
        self::assertStringNotContainsString('column-count', $html);
        self::assertCount(1, array_filter($messages, static fn (string $m): bool => str_contains($m, 'approximated columns in a table cell')));
    }

    public function test_builder_flow_in_a_cell_throws(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessageMatches('/inside a table cell/');
        $this->html(Table::make()->columns('1fr')->row(TableCell::make(ColumnFlow::make(Text::make('a')))));
    }

    public function test_stray_column_break_throws(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessageMatches('/ColumnBreak only works inside a ColumnFlow/');
        $this->html(View::make(ColumnBreak::make()));
    }
}
