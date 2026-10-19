<?php

declare(strict_types=1);

namespace Papercut\Tests\Layout;

use Papercut\Image;
use Papercut\Layout\Status;
use Papercut\Paint\{Clip, DrawImage};
use Papercut\Style\ImageFit;
use Papercut\Tests\Support\LayoutHarness;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class ImageLayouterTest extends TestCase
{
    // FakeBackend images are 100x50 px => 75x37.5 pt intrinsic
    private function draw(?\Papercut\Layout\Fragment $f): DrawImage
    {
        foreach ($f?->ops() ?? [] as $op) {
            if ($op instanceof DrawImage) { return $op; }
            if ($op instanceof Clip && $op->ops[0] instanceof DrawImage) { return $op->ops[0]; }
        }
        self::fail('no image op');
    }

    public function test_intrinsic_size(): void
    {
        $f = (new LayoutHarness())->layout(Image::bytes('x'), 500)->fragment;
        self::assertSame([75.0, 37.5], [$f?->width, $f?->height]);
    }

    public function test_scales_down_to_available_width(): void
    {
        $f = (new LayoutHarness())->layout(Image::bytes('x'), 30)->fragment;
        self::assertSame([30.0, 15.0], [$f?->width, $f?->height]);
    }

    public function test_one_dimension_keeps_aspect(): void
    {
        $f = (new LayoutHarness())->layout(Image::bytes('x')->height(pt(10)), 500)->fragment;
        self::assertSame([20.0, 10.0], [$f?->width, $f?->height]);
    }

    public function test_contain_and_cover(): void
    {
        $h = new LayoutHarness();
        $contain = $this->draw($h->layout(Image::bytes('x')->width(pt(40))->height(pt(40)), 500)->fragment);
        self::assertSame([0.0, 10.0, 40.0, 20.0], [$contain->x, $contain->y, $contain->width, $contain->height]);
        $f = $h->layout(Image::bytes('x')->width(pt(40))->height(pt(40))->fit(ImageFit::Cover), 500)->fragment;
        self::assertInstanceOf(Clip::class, $f?->ops()[0]);
        $cover = $this->draw($f);
        self::assertSame([-20.0, 0.0, 80.0, 40.0], [$cover->x, $cover->y, $cover->width, $cover->height]);
    }

    public function test_fill_stretches(): void
    {
        $f = (new LayoutHarness())->layout(Image::bytes('x')->width(pt(40))->height(pt(40))->fit(ImageFit::Fill), 500)->fragment;
        $d = $this->draw($f);
        self::assertSame([0.0, 0.0, 40.0, 40.0], [$d->x, $d->y, $d->width, $d->height]);
    }

    public function test_does_not_fit(): void
    {
        self::assertSame(Status::DoesNotFit, (new LayoutHarness())->layout(Image::bytes('x'), 500, 10)->status);
    }

    public function test_does_not_fit_even_when_fresh(): void
    {
        self::assertSame(Status::DoesNotFit, (new LayoutHarness())->layout(Image::bytes('x'), 500, 10, null, true)->status);
    }

    public function test_intrinsic_widths(): void
    {
        $h = new LayoutHarness();
        $root = \Papercut\Style\ComputedStyle::root(\Papercut\Style\Theme::default());
        self::assertSame(['min' => 75.0, 'max' => 75.0], $h->engine->intrinsic(Image::bytes('x'), $root));
        self::assertSame(['min' => 40.0, 'max' => 40.0], $h->engine->intrinsic(Image::bytes('x')->width(pt(40)), $root));
    }
}
