<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\Text;

final class PreCodeComponentsTest extends TestCase
{
    /** Lines of a text as strings, with "\n" for each line break. */
    private static function flat(Text $t): string
    {
        $out = '';
        foreach ($t->spanList() as $span) {
            $out .= $span->lineBreak ? "\n" : $span->text;
        }

        return $out;
    }

    public function test_pre_strips_common_indent_and_outer_blank_lines(): void
    {
        $t = $this->nodeFrom("<x-doc::pre>\n    function f()\n    {\n        return 1;\n    }\n</x-doc::pre>");
        self::assertInstanceOf(Text::class, $t);
        self::assertSame('pre', $t->styleName());
        self::assertSame("function f()\n{\n    return 1;\n}", self::flat($t));
    }

    public function test_pre_tab_and_space_indents_share_nothing(): void
    {
        $t = $this->nodeFrom("<x-doc::pre>\n    x\n\ta\n    b\n</x-doc::pre>");
        self::assertSame("x\n\ta\n    b", self::flat($t));
    }

    public function test_pre_does_not_parse_tags(): void
    {
        $t = $this->nodeFrom('<x-doc::pre><b>x</b></x-doc::pre>');
        self::assertSame('<b>x</b>', self::flat($t));
        self::assertNull($t->spanList()[0]->style->fontWeight);
    }

    public function test_pre_round_trips_escaped_blade_output(): void
    {
        $code = 'if (a < b && c) { echo "x"; } // it\'s &amp; done';
        $t = $this->nodeFrom('<x-doc::pre>{{ $code }}</x-doc::pre>', ['code' => $code]);
        self::assertSame($code, self::flat($t));
    }

    public function test_code_inline_in_paragraph(): void
    {
        $t = $this->nodeFrom('<x-doc::p>Run <x-doc::code>a < b</x-doc::code> now</x-doc::p>');
        $spans = $t->spanList();
        self::assertSame(['Run ', 'a < b', ' now'], array_map(fn ($s) => $s->text, $spans));
        self::assertSame('code', $spans[1]->styleName);
        self::assertNull($spans[0]->styleName);
    }

    public function test_code_round_trips_escaped_blade_output(): void
    {
        $t = $this->nodeFrom('<x-doc::text><x-doc::code>{{ $c }}</x-doc::code></x-doc::text>', ['c' => '<i> & "q"']);
        self::assertSame('<i> & "q"', $t->spanList()[0]->text);
        self::assertSame('code', $t->spanList()[0]->styleName);
    }

    public function test_pre_rejects_nested_components(): void
    {
        try {
            $this->nodeFrom('<x-doc::pre>a <x-doc::text>b</x-doc::text></x-doc::pre>');
            self::fail('expected InvalidStructure');
        } catch (InvalidStructure $e) {
            self::assertStringContainsString('pre', $e->getMessage());
        }
    }

    public function test_pre_style_attributes_apply(): void
    {
        $t = $this->nodeFrom('<x-doc::pre font-size="8pt">x</x-doc::pre>');
        self::assertSame(8.0, $t->style()->fontSize?->points);
        self::assertSame('pre', $t->styleName());
    }

    public function test_code_style_attributes_apply(): void
    {
        $t = $this->nodeFrom('<x-doc::p><x-doc::code bold>x</x-doc::code></x-doc::p>');
        self::assertSame(700, $t->spanList()[0]->style->fontWeight);
    }
}
