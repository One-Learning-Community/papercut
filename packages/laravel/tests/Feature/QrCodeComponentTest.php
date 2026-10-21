<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Papercut\Barcode\ErrorCorrection;
use Papercut\Exception\{InvalidValue, UnknownAttribute};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Tests\TestCase;
use Papercut\QrCode;

final class QrCodeComponentTest extends TestCase
{
    public function test_value_level_quiet_zone_and_style(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="https://acme.test/pay/142" level="H" quiet-zone="2" width="30mm" color="indigo-700" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame('https://acme.test/pay/142', $n->value());
        self::assertSame(ErrorCorrection::High, $n->level());
        self::assertSame(2, $n->quietZoneModules());
        self::assertEqualsWithDelta(\Papercut\mm(30)->points, $n->style()->width instanceof \Papercut\Value\Length ? $n->style()->width->points : null, 1e-9);
    }

    public function test_bound_values_and_default_level(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code :value="$url" />', ['url' => 'https://acme.test/x']);

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(ErrorCorrection::Medium, $n->level());
        self::assertSame(QrCode::DEFAULT_QUIET_ZONE, $n->quietZoneModules());
    }

    public function test_level_accepts_names(): void
    {
        $n = $this->nodeFrom('<x-doc::qr-code value="x" level="quartile" />');

        self::assertInstanceOf(QrCode::class, $n);
        self::assertSame(ErrorCorrection::Quartile, $n->level());
    }

    public function test_a_value_is_required(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('requires a value');

        $this->nodeFrom('<x-doc::qr-code />');
    }

    public function test_content_is_rejected(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('use the value attribute');

        $this->nodeFrom('<x-doc::qr-code value="x">https://acme.test</x-doc::qr-code>');
    }

    public function test_a_bad_level_is_explained(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('expected L, M, Q or H');

        $this->nodeFrom('<x-doc::qr-code value="x" level="extreme" />');
    }

    public function test_a_bad_quiet_zone_is_explained(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('whole number of modules');

        $this->nodeFrom('<x-doc::qr-code value="x" quiet-zone="-1" />');
    }

    public function test_unknown_attributes_suggest_the_right_one(): void
    {
        $this->expectException(UnknownAttribute::class);
        $this->expectExceptionMessage('Did you mean "value"');

        $this->nodeFrom('<x-doc::qr-code valeu="x" />');
    }

    public function test_it_renders_to_pdf(): void
    {
        $pdf = \Papercut\Laravel\Facades\Papercut::make(\Papercut\Document::make(\Papercut\Page::a4()->body(QrCode::make('x'))))->pdf()->toString();

        self::assertStringStartsWith('%PDF-', $pdf);
    }
}
