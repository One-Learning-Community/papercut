<?php

declare(strict_types=1);

namespace Papercut\Laravel\Tests\Feature;

use Illuminate\Support\Facades\{Log, Route};
use Papercut\Exception\{ImageUnreadable, InvalidValue};
use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Facades\Pdf;
use Papercut\Laravel\PdfManager;
use Papercut\Laravel\Preview\PreviewController;
use Papercut\Laravel\Tests\TestCase;
use Papercut\OverflowMode;

final class HardeningTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/papercut-hard-' . getmypid() . '-' . bin2hex(random_bytes(3));
        mkdir($this->dir . '/img', 0777, true);
        file_put_contents($this->dir . '/img/logo.png', 'x');
        file_put_contents($this->dir . '/secret.txt', 'secret');
        config(['papercut.image_path' => $this->dir . '/img']);
    }

    // ---- 1. image containment

    public function test_image_traversal_is_rejected(): void
    {
        $this->expectException(ImageUnreadable::class);
        $this->expectExceptionMessage('Image path escapes the image directory: ../secret.txt');
        $this->nodeFrom('<x-pdf::image src="../secret.txt" />');
    }

    public function test_image_sibling_prefix_directory_is_rejected(): void
    {
        mkdir($this->dir . '/img-evil');
        file_put_contents($this->dir . '/img-evil/a.png', 'x');
        $this->expectException(ImageUnreadable::class);
        $this->expectExceptionMessage('escapes the image directory');
        $this->nodeFrom('<x-pdf::image src="../img-evil/a.png" />');
    }

    public function test_image_inner_dotdot_that_stays_inside_is_allowed(): void
    {
        mkdir($this->dir . '/img/sub');
        $img = $this->nodeFrom('<x-pdf::image src="sub/../logo.png" />');
        self::assertInstanceOf(\Papercut\Image::class, $img);
    }

    public function test_image_absolute_path_is_trusted(): void
    {
        $img = $this->nodeFrom('<x-pdf::image :src="$p" />', ['p' => $this->dir . '/secret.txt']);
        self::assertInstanceOf(\Papercut\Image::class, $img);
    }

    public function test_image_stream_wrapper_and_drive_paths_count_as_absolute(): void
    {
        // Not resolved against the base, so reported as not found rather than joined under it.
        foreach (['file:///nonexistent/x.png', 'C:\\x.png', 'C:/x.png'] as $p) {
            try {
                $this->nodeFrom('<x-pdf::image :src="$p" />', ['p' => $p]);
                self::fail("$p accepted");
            } catch (ImageUnreadable $e) {
                self::assertStringContainsString("Image not found: $p", $e->getMessage());
            }
        }
    }

    public function test_image_presence_only_src_or_data_is_rejected(): void
    {
        foreach (['<x-pdf::image src />', '<x-pdf::image data />'] as $blade) {
            try {
                $this->nodeFrom($blade);
                self::fail("$blade accepted");
            } catch (InvalidStructure $e) {
                self::assertStringContainsString('<x-pdf::image> src must be a string path', $e->getMessage());
            }
        }
        $this->expectException(InvalidStructure::class);
        $this->nodeFrom('<x-pdf::image :src="$p" />', ['p' => ['a']]);
    }

    // ---- 2. preview

    public function test_preview_actions_abort_outside_local(): void
    {
        $registry = $this->laravel()->make(\Papercut\Laravel\Preview\PreviewRegistry::class);
        $registry->register('pdf.hello', ['name' => 'A']);
        $controller = $this->laravel()->make(PreviewController::class);
        foreach ([fn () => $controller->index(), fn () => $controller->show('pdf.hello')] as $call) {
            try {
                $call();
                self::fail('not aborted');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                self::assertSame(404, $e->getStatusCode());
            }
        }
    }

    public function test_preview_index_urlencodes_names(): void
    {
        $this->setEnv('local');
        Pdf::preview('pdf.a b?c');
        $html = (string) $this->laravel()->make(PreviewController::class)->index()->getContent();
        self::assertStringContainsString('href="/_papercut/pdf.a%20b%3Fc"', $html);
    }

    public function test_preview_closure_must_return_array(): void
    {
        $this->setEnv('local');
        Pdf::preview('pdf.hello', fn () => 'nope');
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Preview data for pdf.hello must be an array');
        $this->laravel()->make(PreviewController::class)->show('pdf.hello');
    }

    // ---- 3. named style errors

    public function test_unknown_named_style_has_context_suggestion_and_file(): void
    {
        config(['papercut.styles' => ['card' => ['padding' => '12pt']]]);
        try {
            Pdf::view('pdf.bad-style')->toString();
            self::fail('accepted');
        } catch (\Throwable $e) {
            self::assertStringContainsString('Unknown style "crad" on <x-pdf::view>', $e->getMessage());
            self::assertStringContainsString('Did you mean "card"', $e->getMessage());
            self::assertStringContainsString('bad-style.blade.php', $e->getMessage());
        }
    }

    public function test_known_named_style_and_no_style_still_render(): void
    {
        config(['papercut.styles' => ['card' => ['padding' => '12pt']]]);
        $v = $this->nodeFrom('<x-pdf::view style="card">x</x-pdf::view>');
        self::assertInstanceOf(\Papercut\View::class, $v);
        self::assertInstanceOf(\Papercut\View::class, $this->nodeFrom('<x-pdf::view>x</x-pdf::view>'));
    }

    // ---- 4. root validation names the view

    public function test_root_errors_name_the_view(): void
    {
        foreach (['pdf.two-roots', 'pdf.no-root'] as $view) {
            try {
                Pdf::view($view)->toString();
                self::fail('accepted');
            } catch (\Throwable $e) {
                $root = $e;
                while ($root->getPrevious() !== null && !$root instanceof InvalidStructure) {
                    $root = $root->getPrevious();
                }
                self::assertStringEndsWith(" in view [$view]", $root->getMessage());
            }
        }
    }

    // ---- 5. filenames

    public function test_download_filename_slashes_replaced(): void
    {
        Route::get('/d1', fn () => Pdf::view('pdf.hello', ['name' => 'A'])->download('a/b.pdf'));
        $this->get('/d1')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=a-b.pdf');
    }

    public function test_download_filename_unicode_fallback(): void
    {
        Route::get('/d2', fn () => Pdf::view('pdf.hello', ['name' => 'A'])->download('résumé.pdf'));
        $h = (string) $this->get('/d2')->assertOk()->headers->get('Content-Disposition');
        self::assertStringContainsString('filename=resume.pdf', $h);
        self::assertStringContainsString("filename*=utf-8''r%C3%A9sum%C3%A9.pdf", $h);
    }

    // ---- 6. invalid config values

    public function test_overflow_config_is_validated_case_insensitively(): void
    {
        config(['papercut.overflow' => 'CLIP']);
        self::assertSame(OverflowMode::Clip, Pdf::options()->overflow);
    }

    public function test_invalid_overflow_throws(): void
    {
        config(['papercut.overflow' => 'bogus']);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid papercut.overflow');
        Pdf::options();
    }

    public function test_overflow_default_depends_on_environment(): void
    {
        config(['papercut.overflow' => null]);
        self::assertSame(OverflowMode::Throw, Pdf::options()->overflow);
        $this->laravel()->forgetInstance(PdfManager::class);
        \Illuminate\Support\Facades\Facade::clearResolvedInstance(PdfManager::class);
        $this->setEnv('production');
        self::assertSame(OverflowMode::Clip, Pdf::options()->overflow);
    }

    public function test_non_string_page_size_throws(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('page size');
        $this->nodeFrom('<x-pdf::page :size="$s">x</x-pdf::page>', ['s' => ['a4']]);
    }

    public function test_non_scalar_document_metadata_throws(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('title');
        $this->nodeFrom('<x-pdf::document :title="$t"><x-pdf::text>x</x-pdf::text></x-pdf::document>', ['t' => ['a']]);
    }

    public function test_non_string_page_number_format_throws(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('format');
        $this->nodeFrom('<x-pdf::page-number :format="$f" />', ['f' => 5]);
    }

    // ---- 7. small items

    public function test_empty_thead_message(): void
    {
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('<x-pdf::thead> must not be empty');
        $this->nodeFrom('<x-pdf::table><x-pdf::thead></x-pdf::thead></x-pdf::table>');
    }

    public function test_facade_docblock_has_single_options_line(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../../src/Facades/Pdf.php');
        self::assertSame(1, substr_count($src, 'static \Papercut\RenderOptions options()'));
    }

    public function test_link_accepts_stringable_and_rejects_bad_href(): void
    {
        $url = new class implements \Stringable {
            public function __toString(): string
            {
                return 'https://example.com';
            }
        };
        self::assertInstanceOf(\Papercut\Link::class, $this->nodeFrom('<x-pdf::link :href="$u">x</x-pdf::link>', ['u' => $url]));
        foreach ([[], 5, ''] as $bad) {
            try {
                $this->nodeFrom('<x-pdf::link :href="$u">x</x-pdf::link>', ['u' => $bad]);
                self::fail('accepted');
            } catch (InvalidStructure $e) {
                self::assertStringContainsString('href must be a non-empty string', $e->getMessage());
            }
        }
        try {
            $this->nodeFrom('<x-pdf::link href>x</x-pdf::link>');
            self::fail('accepted');
        } catch (InvalidStructure $e) {
            self::assertStringContainsString('href must be a non-empty string', $e->getMessage());
        }
    }

    // ---- 8. coverage additions

    public function test_styled_tr_reaches_core(): void
    {
        $t = $this->nodeFrom('<x-pdf::table><x-pdf::tr background="slate-50"><x-pdf::td>a</x-pdf::td></x-pdf::tr></x-pdf::table>');
        self::assertInstanceOf(\Papercut\Table::class, $t);
        $row = $t->bodyRows()[0];
        self::assertNotNull($row->style()->background);
    }

    public function test_blade_comment_between_foreach_rows_adds_no_child(): void
    {
        $t = $this->nodeFrom(
            '<x-pdf::table>@foreach ([1, 2] as $i){{-- c --}}<x-pdf::tr><x-pdf::td>{{ $i }}</x-pdf::td></x-pdf::tr>{{-- c --}}@endforeach</x-pdf::table>',
        );
        self::assertInstanceOf(\Papercut\Table::class, $t);
        self::assertCount(2, $t->bodyRows());
    }

    public function test_remaining_counts_untaken_nodes(): void
    {
        $registry = $this->laravel()->make(\Papercut\Laravel\Rendering\NodeRegistry::class);
        $registry->scope(function () use ($registry): null {
            $registry->register(new \stdClass());
            $second = $registry->register(new \stdClass());
            self::assertSame(2, $registry->remaining());
            $registry->segments((string) $second);
            self::assertGreaterThan(0, $registry->remaining());
            self::assertLessThan(2, $registry->remaining());

            return null;
        });
    }

    public function test_scope_pops_after_exception(): void
    {
        $registry = $this->laravel()->make(\Papercut\Laravel\Rendering\NodeRegistry::class);
        try {
            $registry->scope(function (): never {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
        $this->expectException(InvalidStructure::class);
        $registry->register(new \stdClass());
    }

    public function test_on_warning_writes_to_log(): void
    {
        $spy = Log::spy();
        $cb = Pdf::options()->onWarning;
        self::assertNotNull($cb);
        $cb('careful');
        $spy->shouldHaveReceived('warning')->with('[papercut] careful')->once();
    }

    public function test_make_and_save(): void
    {
        $doc = \Papercut\Document::make(\Papercut\Page::named('a4')->body('hello'));
        $pending = Pdf::make($doc);
        $path = $this->dir . '/out.pdf';
        self::assertSame($pending, $pending->save($path));
        self::assertStringStartsWith('%PDF-', (string) file_get_contents($path));
    }

    public function test_implicit_page_uses_configured_paper_and_margin(): void
    {
        config(['papercut.paper' => 'letter', 'papercut.margin' => '10mm']);
        $doc = $this->nodeFrom('<x-pdf::document><x-pdf::text>x</x-pdf::text></x-pdf::document>');
        self::assertInstanceOf(\Papercut\Document::class, $doc);
        $page = $doc->pages()[0];
        self::assertEqualsWithDelta(612.0, $page->width(), 0.5);
        self::assertEqualsWithDelta(28.35, $page->margins()->top->points, 0.1);
    }

    private function laravel(): \Illuminate\Foundation\Application
    {
        self::assertNotNull($this->app);

        return $this->app;
    }

    private function setEnv(string $env): void
    {
        $app = $this->laravel();
        $app['env'] = $env;
    }
}
