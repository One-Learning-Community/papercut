<?php

declare(strict_types=1);

namespace Papercut\Tests\Html;

use Papercut\{Document, Image, Page, Text};
use Papercut\Export\{FontMode, HtmlOptions};
use Papercut\Html\{FontAnswer, FontRequest};
use Papercut\Tests\Support\TestImages;
use PHPUnit\Framework\TestCase;

use function Papercut\mm;

final class HtmlPageVariantsTest extends TestCase
{
    private function page(): Page
    {
        return Page::a4()->header(Text::make('Running'))->footer(Text::make('Normal foot'))->body(Text::make('Body'));
    }

    private function html(Page $page, ?HtmlOptions $o = null): string
    {
        return Document::make($page)->toHtml($o)->bytes();
    }

    public function test_first_header_replaces_the_normal_one_at_the_top(): void
    {
        $html = $this->html($this->page()->firstHeader(Text::make('Letterhead')));
        self::assertMatchesRegularExpression('~<div class="pc-header"[^>]*>.*Letterhead.*?</div>~s', $html);
        self::assertStringNotContainsString('Running', $html);
        self::assertSame(1, substr_count($html, 'class="pc-header"'));
    }

    public function test_set_empty_first_header_shows_no_header(): void
    {
        $html = $this->html($this->page()->firstHeader());
        self::assertStringNotContainsString('class="pc-header"', $html);
        self::assertStringNotContainsString('Running', $html);
    }

    public function test_footer_is_the_normal_one(): void
    {
        $html = $this->html($this->page()->firstFooter(Text::make('First foot')));
        self::assertStringContainsString('Normal foot', $html);
        self::assertStringNotContainsString('First foot', $html);
        self::assertSame(1, substr_count($html, 'class="pc-footer"'));
    }

    public function test_even_variants_are_hidden_and_noted(): void
    {
        $messages = [];
        $html = $this->html(
            $this->page()->evenHeader(Text::make('Even top'))->evenFooter(Text::make('Even bottom')),
            new HtmlOptions(onWarning: function (string $m) use (&$messages): void {
                $messages[] = $m;
            }),
        );
        self::assertStringNotContainsString('Even top', $html);
        self::assertStringNotContainsString('Even bottom', $html);
        self::assertStringContainsString('Running', $html);
        self::assertContains('html: approximated even-page header and footer at page', $messages);
        self::assertSame(1, count(array_keys($messages, 'html: approximated even-page header and footer at page')));
    }

    public function test_hidden_regions_record_no_fonts_and_resolve_no_images(): void
    {
        $families = [];
        $source = static function (FontRequest $r) use (&$families): ?FontAnswer {
            $families[] = $r->family;

            return null;
        };
        $images = 0;
        $page = $this->page()
            ->evenHeader(Text::make('Even top')->font('Hidden Serif'), Image::bytes(TestImages::png()))
            ->evenFooter(Text::make('Even bottom')->font('Hidden Serif'));
        $html = $this->html($page, new HtmlOptions(
            imageUrl: static function (Image $i) use (&$images): ?string {
                $images++;

                return null;
            },
            fontMode: FontMode::Sources,
            fontSources: [$source],
        ));
        self::assertNotContains('Hidden Serif', $families);
        self::assertStringNotContainsString('Hidden Serif', $html);
        self::assertSame(0, $images);
        self::assertStringNotContainsString('<img', $html);
    }

    public function test_mirrored_first_run_adds_a_left_page_rule(): void
    {
        $page = $this->page()->margin(mm(10), mm(15), mm(20), mm(25));
        $full = new HtmlOptions(fullPage: true);
        $css = $this->html($page->mirrorMargins(), $full);
        $top = \Papercut\Html\Css::pt(mm(10)->points);
        $inside = \Papercut\Html\Css::pt(mm(25)->points);
        $outside = \Papercut\Html\Css::pt(mm(15)->points);
        $bottom = \Papercut\Html\Css::pt(mm(20)->points);
        self::assertStringContainsString("@page :left{margin:$top $inside $bottom $outside}", $css);
        self::assertStringNotContainsString('@page :left', $this->html($page, $full));
        self::assertStringNotContainsString('@page', $this->html($page->mirrorMargins()));
    }
}
