<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Render;

use Papercut\Math\Math;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Tests\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MathmlWriterTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function fixtureNames(): iterable
    {
        foreach (array_keys(Fixtures::all()) as $name) {
            yield $name => [$name];
        }
    }

    private static function render(string $name): string
    {
        return MathmlWriter::write(Fixtures::all()[$name], ! str_ends_with($name, '-inline'));
    }

    #[DataProvider('fixtureNames')]
    public function test_snapshot(string $name): void
    {
        $path = __DIR__ . '/../snapshots/mathml/' . $name . '.xml';
        $actual = self::render($name);
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($path, $actual . "\n");
        }
        self::assertFileExists($path);
        self::assertSame(rtrim((string) file_get_contents($path)), $actual);
    }

    #[DataProvider('fixtureNames')]
    public function test_output_parses(string $name): void
    {
        $doc = \Dom\XMLDocument::createFromString(self::render($name));
        self::assertSame('math', $doc->documentElement?->localName);
    }

    public function test_escapes_xml_specials(): void
    {
        $xml = MathmlWriter::write(Math::row(Math::text('a < b & c'), '< &'));
        self::assertStringContainsString('<mtext>a &lt; b &amp; c</mtext>', $xml);
        self::assertStringContainsString('&lt;</mo>', $xml);
        self::assertStringContainsString('&amp;</mo>', $xml);
        \Dom\XMLDocument::createFromString($xml);
    }

    public function test_empty_slots(): void
    {
        $xml = MathmlWriter::write(Math::frac('', '2'));
        self::assertStringContainsString('<mfrac><mrow></mrow><mn>2</mn></mfrac>', $xml);
        self::assertStringContainsString('<msqrt><mrow></mrow></msqrt>', MathmlWriter::write(Math::sqrt('')));
        self::assertStringContainsString('<msup><mi>x</mi><mrow></mrow></msup>', MathmlWriter::write(Math::sup('x', '')));
    }

    public function test_forms_are_written_when_they_differ_from_the_browser_default(): void
    {
        self::assertStringContainsString('<mo form="prefix">−</mo>', MathmlWriter::write(Math::row('a=-b')));
        self::assertStringContainsString('<mo form="postfix" stretchy="false">|</mo>', MathmlWriter::write(Math::row('|x|+1')));
        self::assertStringContainsString('<msup><mi>x</mi><mo form="prefix">+</mo></msup>', MathmlWriter::write(Math::sup('x', '+')));
    }

    public function test_function_names_are_upright(): void
    {
        self::assertStringContainsString('<mi mathvariant="normal">sin</mi>', MathmlWriter::write(Math::row('sin x')));
    }

    public function test_mtext_keeps_its_spaces(): void
    {
        $xml = MathmlWriter::write(Math::row('x', Math::text(' if  so '), 'x>0'));
        self::assertStringContainsString("<mtext>\u{A0}if\u{A0}\u{A0}so\u{A0}</mtext>", $xml);
    }

    public function test_math_italic_code_points_are_written_as_plain_letters(): void
    {
        $xml = MathmlWriter::write(Math::row(new Identifier("\u{1D465}"), new Identifier("\u{210E}"), new Identifier("\u{1D6FC}")));
        self::assertStringContainsString('<mi>x</mi><mi>h</mi><mi>α</mi>', $xml);
        foreach (array_merge(range(0x41, 0x5A), range(0x61, 0x7A), range(0x391, 0x3A9), range(0x3B1, 0x3C9)) as $cp) {
            if ($cp !== 0x3A2) {
                self::assertSame($cp, \Papercut\Math\Styles::plain(\Papercut\Math\Styles::italic($cp)));
            }
        }
    }

    public function test_only_fenced_delimiters_stretch(): void
    {
        $xml = MathmlWriter::write(Math::row('(a)'));
        self::assertStringContainsString('<mo stretchy="false">(</mo>', $xml);
        self::assertStringNotContainsString('stretchy="true"', $xml);
        self::assertStringContainsString('<mo stretchy="true">(</mo>', MathmlWriter::write(Math::fenced('(', ')', 'a')));
    }
}
