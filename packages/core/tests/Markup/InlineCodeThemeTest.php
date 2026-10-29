<?php

declare(strict_types=1);

namespace Papercut\Tests\Markup;

use Papercut\{Document, Page, Span, Text};
use Papercut\Export\HtmlOptions;
use Papercut\Markup\Markup;
use Papercut\Style\{ComputedStyle, Style, Theme};
use Papercut\Text\{FontRegistry, Itemizer};
use Papercut\Tests\Support\FakeBackend;
use PHPUnit\Framework\TestCase;

use function Papercut\pt;

final class InlineCodeThemeTest extends TestCase
{
    private function theme(): Theme
    {
        return Theme::default()->withStyle('code', Style::make(color: \Papercut\Value\Color::named('red-600')));
    }

    public function test_theme_span_style_is_the_named_style_then_the_spans_own(): void
    {
        $span = new Span('x', Style::make(fontWeight: 700), null, false, 'code');
        $style = $this->theme()->spanStyle($span);
        self::assertSame(700, $style->fontWeight);
        self::assertNotNull($style->color);
        self::assertSame($span->style, $this->theme()->spanStyle(Span::text('x', $span->style)));
    }

    public function test_pdf_itemizer_uses_the_themed_code_style(): void
    {
        $theme = $this->theme();
        $itemizer = new Itemizer(FontRegistry::withDefaults(new FakeBackend()), null, [], $theme);
        $root = ComputedStyle::root($theme);
        $spans = Text::markup('a <code>b</code>')->spanList();
        $items = $itemizer->itemize($spans, $root);
        $word = array_values(array_filter($items, fn ($i) => $i->kind === 'word' && $i->text === 'b'))[0];
        self::assertEquals(\Papercut\Value\Color::named('red-600'), $word->style->color);
    }

    public function test_html_uses_the_themed_code_style_for_markup_and_inline_markup(): void
    {
        $options = new HtmlOptions(theme: $this->theme());
        $red = \Papercut\Html\Css::color(\Papercut\Value\Color::named('red-600'));
        foreach ([Markup::html('<p>a <code>b</code></p>'), Text::markup('a <code>b</code>')] as $node) {
            $html = Document::make(Page::a4()->body($node))->toHtml($options)->bytes();
            self::assertMatchesRegularExpression('~<code style="[^"]*color:' . preg_quote($red, '~') . '[^"]*">b</code>~', $html);
        }
    }
}
