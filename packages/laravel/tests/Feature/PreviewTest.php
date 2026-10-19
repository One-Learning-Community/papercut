<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Orchestra\Testbench\Attributes\DefineEnvironment;
use Papercut\Laravel\Facades\Pdf;
use Papercut\Laravel\Tests\TestCase;

final class PreviewTest extends TestCase
{
    /** @param \Illuminate\Foundation\Application $app */
    protected function previewLocal($app): void
    {
        $app['config']->set('papercut.preview.enabled', true);
        $app['env'] = 'local';
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function previewTesting($app): void
    {
        $app['config']->set('papercut.preview.enabled', true);
    }

    /** @param \Illuminate\Foundation\Application $app */
    protected function previewLocalDisabled($app): void
    {
        $app['config']->set('papercut.preview.enabled', false);
        $app['env'] = 'local';
    }

    #[DefineEnvironment('previewLocal')]
    public function testIndexListsRegisteredViewsEscaped(): void
    {
        Pdf::preview('pdf.hello', ['name' => 'A']);
        Pdf::preview('pdf.<b>x</b>');

        $response = $this->get('/_papercut');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('href="/_papercut/pdf.hello"', $html);
        $this->assertStringContainsString('pdf.hello', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    #[DefineEnvironment('previewLocal')]
    public function testRendersRegisteredViewAsPdf(): void
    {
        Pdf::preview('pdf.hello', ['name' => 'World']);

        $response = $this->get('/_papercut/pdf.hello');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->baseResponse->getContent());
    }

    #[DefineEnvironment('previewLocal')]
    public function testDataClosureIsCalledPerRequest(): void
    {
        $calls = 0;
        Pdf::preview('pdf.hello', function () use (&$calls): array {
            $calls++;

            return ['name' => 'N' . $calls];
        });

        $this->get('/_papercut/pdf.hello')->assertOk();
        $this->get('/_papercut/pdf.hello')->assertOk();

        $this->assertSame(2, $calls);
    }

    #[DefineEnvironment('previewLocal')]
    public function testUnregisteredViewIs404(): void
    {
        $this->get('/_papercut/pdf.hello')->assertNotFound();
    }

    #[DefineEnvironment('previewTesting')]
    public function testNoRoutesOutsideLocal(): void
    {
        Pdf::preview('pdf.hello', ['name' => 'A']);

        $this->get('/_papercut')->assertNotFound();
        $this->get('/_papercut/pdf.hello')->assertNotFound();
    }

    #[DefineEnvironment('previewLocalDisabled')]
    public function testNoRoutesWhenDisabled(): void
    {
        Pdf::preview('pdf.hello', ['name' => 'A']);

        $this->get('/_papercut')->assertNotFound();
    }
}
