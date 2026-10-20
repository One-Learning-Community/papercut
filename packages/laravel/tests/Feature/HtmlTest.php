<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use Illuminate\Support\Facades\Route;
use Papercut\Export\FontMode;
use Papercut\Laravel\Facades\Papercut;
use Papercut\Laravel\PapercutManager;
use Papercut\Laravel\Tests\TestCase;

final class HtmlTest extends TestCase
{
    /** @param \Illuminate\Foundation\Application $app */
    protected function previewLocal($app): void
    {
        $app['config']->set('papercut.preview.enabled', true);
        $app['env'] = 'local';
    }

    public function test_html_in_blade_renders_fragment(): void
    {
        $doc = Papercut::view('pdf.hello', ['name' => 'Ann'])->document();
        $out = view('pdf.embedded', ['doc' => Papercut::make($doc)])->render();

        $this->assertSame(1, substr_count($out, 'class="papercut'));
        $this->assertStringNotContainsString('<html', $out);
        $this->assertStringContainsString('Hello Ann', $out);
    }

    public function test_string_forms_are_fragment_and_to_string_is_page(): void
    {
        $h = Papercut::view('pdf.hello', ['name' => 'Ann'])->html();

        $this->assertNotSame((string) $h, $h->toString());
        $this->assertStringNotContainsString('<html', (string) $h);
        $this->assertStringContainsString('<html', $h->toString());

        $escaped = view('pdf.embedded-escaped', ['doc' => Papercut::view('pdf.hello', ['name' => 'Ann'])])->render();
        $this->assertSame(1, substr_count($escaped, 'class="papercut'));
        $this->assertStringNotContainsString('&lt;', $escaped);
    }

    public function test_route_returning_html_is_a_full_page(): void
    {
        Route::get('/h', fn () => Papercut::view('pdf.hello', ['name' => 'Ann'])->html());

        $r = $this->get('/h');

        $r->assertOk();
        $this->assertSame('text/html; charset=UTF-8', $r->headers->get('Content-Type'));
        $this->assertNull($r->headers->get('Content-Disposition'));
        $this->assertSame((string) strlen((string) $r->getContent()), $r->headers->get('Content-Length'));
        $this->assertStringContainsString('<html', (string) $r->getContent());
        $this->assertStringContainsString('Hello Ann', (string) $r->getContent());
    }

    public function test_download_html_has_attachment_headers(): void
    {
        $r = Papercut::view('pdf.hello', ['name' => 'Ann'])->html()->download();

        $this->assertSame('text/html; charset=UTF-8', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=document.html', (string) $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('<html', (string) $r->getContent());
    }

    public function test_html_fonts_config_none_embed_and_invalid(): void
    {
        $this->assertSame(FontMode::None, $this->app()->make(PapercutManager::class)->htmlOptions()->fontMode());

        config(['papercut.html.fonts' => 'embed']);
        $this->app()->forgetInstance(PapercutManager::class); // @phpstan-ignore method.notFound
        $this->assertSame(FontMode::Embed, $this->app()->make(PapercutManager::class)->htmlOptions()->fontMode());

        config(['papercut.html.fonts' => 'link']);
        $this->app()->forgetInstance(PapercutManager::class); // @phpstan-ignore method.notFound
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid papercut.html.fonts: 'link'; expected none or embed");
        $this->app()->make(PapercutManager::class)->htmlOptions();
    }

    public function test_shared_options_reach_both_formats(): void
    {
        config(['papercut.theme' => ['color' => '#ff0000']]);
        $m = $this->app()->make(PapercutManager::class);

        $this->assertSame($m->exportOptions()->theme(), $m->pdfOptions()->theme());
        $this->assertSame($m->exportOptions()->theme(), $m->htmlOptions()->theme());
        $this->assertStringContainsString('#ff0000', Papercut::view('pdf.hello', ['name' => 'A'])->html()->toString());
    }

    #[DefineEnvironment('previewLocal')]
    public function test_preview_format_html_and_unknown(): void
    {
        Papercut::preview('pdf.hello', ['name' => 'World']);

        $html = $this->get('/_papercut/pdf.hello?format=html');
        $html->assertOk();
        $this->assertSame('text/html; charset=UTF-8', $html->headers->get('Content-Type'));
        $this->assertStringContainsString('<html', (string) $html->getContent());

        $this->get('/_papercut/pdf.hello?format=pdf')->assertHeader('Content-Type', 'application/pdf');
        $this->get('/_papercut/pdf.hello')->assertHeader('Content-Type', 'application/pdf');
        $this->get('/_papercut/pdf.hello?format=docx')->assertNotFound();
    }
}
