<?php

declare(strict_types=1);

namespace Papercut\Export;

use Papercut\Backend\DefaultBackend;
use Papercut\Document;
use Papercut\Html\{Css, FontFaces, HtmlWriter, Stylesheet};
use Papercut\Style\StyleResolver;
use Papercut\Tree\TreeWalker;

final class HtmlExporter implements Exporter
{
    public function export(Document $doc, ExportOptions $options): Output
    {
        $options = HtmlOptions::from($options);
        if ($options->fontMode() === FontMode::Link && $options->fontUrl() === null) {
            throw new \InvalidArgumentException('FontMode::Link needs a fontUrl callback');
        }
        $styles = new StyleResolver($options->theme());
        $writer = new HtmlWriter($doc, new Fidelity('html', $options), $options, DefaultBackend::make(false));
        (new TreeWalker($styles))->walk($doc, $writer);

        $meta = $doc->meta();
        $root = $styles->root();
        $families = array_values(array_unique([...$writer->usedFamilies(), $root->fontFamily, ...$options->fonts()->fallbacks]));
        $css = Stylesheet::css($doc, $options, FontFaces::css($families, $options->fonts(), $options->fontMode(), $options->fontUrl()));
        $lang = $meta->lang !== null ? ' lang="' . HtmlWriter::esc($meta->lang) . '"' : '';
        $rootDiv = '<div class="papercut"' . $lang . ' style="' . HtmlWriter::esc(Css::attr(Css::text($root, null, $options->fonts()->fallbacks))) . '">' . $writer->html() . '</div>';
        $style = '<style>' . $css . '</style>';

        if (!$options->fullPage()) {
            return new Output($style . $rootDiv, 'text/html; charset=UTF-8', 'html');
        }

        $head = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . HtmlWriter::esc($meta->title ?? '') . '</title>';
        foreach (['author' => $meta->author, 'description' => $meta->subject, 'keywords' => $meta->keywords] as $name => $value) {
            if ($value !== null) {
                $head .= '<meta name="' . $name . '" content="' . HtmlWriter::esc($value) . '">';
            }
        }

        return new Output(
            '<!DOCTYPE html><html lang="' . HtmlWriter::esc($meta->lang ?? 'en') . '"><head>' . $head . $style . '</head><body>' . $rootDiv . '</body></html>',
            'text/html; charset=UTF-8',
            'html',
        );
    }

    public function defaultOptions(): ExportOptions
    {
        return new HtmlOptions();
    }
}
