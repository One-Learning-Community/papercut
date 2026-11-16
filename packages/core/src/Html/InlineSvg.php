<?php

declare(strict_types=1);

namespace Papercut\Html;

/**
 * Writes sanitized SVG markup as an inline element in an HTML page. Every id is prefixed and every reference
 * to it rewritten, and style rules are scoped to the SVG's own root, so two SVGs (or an SVG and the page)
 * cannot touch each other.
 *
 * @internal Part of Papercut's engine, not its public API. It may change in any release.
 */
final class InlineSvg
{
    private const string SVG_NS = 'http://www.w3.org/2000/svg';
    private const array ALLOWED = [
        'svg', 'g', 'defs', 'symbol', 'use', 'a', 'image', 'switch', 'title', 'desc', 'style', 'view',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask', 'marker', 'filter',
        'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix', 'feDiffuseLighting',
        'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR',
        'feGaussianBlur', 'feImage', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset', 'fePointLight',
        'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence', 'animate', 'animateMotion', 'animateTransform', 'set', 'mpath',
    ];

    /**
     * Why this SVG cannot be written inline, or null when it can. A browser parses inline SVG as HTML: an
     * element outside the SVG vocabulary, or markup inside title or desc, can end the svg early or swallow
     * the page. Elements inside metadata are ignored, since write() drops metadata.
     */
    public static function inlinable(string $xml): ?string
    {
        $dom = self::load($xml);
        if ($dom === null || $dom->documentElement === null) {
            return 'the SVG cannot be read';
        }
        $reason = null;
        $walk = static function (\DOMElement $el) use (&$walk, &$reason): void {
            if ($reason !== null) {
                return;
            }
            if ($el->namespaceURI === self::SVG_NS && $el->localName === 'metadata' && $el->nodeName === 'metadata') {
                return;
            }
            if ($el->namespaceURI !== self::SVG_NS || $el->nodeName !== $el->localName || !in_array($el->localName, self::ALLOWED, true)) {
                $reason = '<' . $el->nodeName . "> can't be written inline";

                return;
            }
            foreach ($el->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    if (($el->localName === 'title' || $el->localName === 'desc')) {
                        $reason = '<' . $el->localName . '> holds elements';

                        return;
                    }
                    $walk($child);
                }
            }
        };
        $walk($dom->documentElement);

        return $reason;
    }

    private static function load(string $xml): ?\DOMDocument
    {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    private const array IDREF_LISTS = ['aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns', 'aria-flowto'];

    /**
     * @param (\Closure(?string, int, bool): string)|null $fontFamily rewrites a font-family list for the page's fonts; null leaves families alone
     */
    public function __construct(private readonly ?\Closure $fontFamily = null)
    {
    }

    /**
     * @param string $xml the sanitized SVG (SvgDocument::$xml)
     * @param string $prefix id prefix such as pc-s3-; the root's scope class is the prefix without its trailing dash
     * @param string $alt the accessible name; '' marks the SVG decorative
     * @param array<string, string> $style box declarations for the root
     * @return array{html: string, approximated: list<string>}
     */
    public function write(string $xml, string $prefix, string $alt, array $style): array
    {
        $dom = self::load($xml);
        $root = $dom?->documentElement;
        if ($dom === null || $root === null) {
            return ['html' => '', 'approximated' => []];
        }
        foreach (iterator_to_array($dom->getElementsByTagName('metadata')) as $meta) {
            $meta->parentNode?->removeChild($meta);
        }
        // An HTML parser reads comments and CDATA by its own rules (<!--> closes at once; CDATA in title
        // or desc is an HTML integration point), so neither is written: comments go, CDATA becomes text.
        $xpath = new \DOMXPath($dom);
        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            if ($comment instanceof \DOMComment) {
                $comment->parentNode?->removeChild($comment);
            }
        }
        foreach (iterator_to_array($xpath->query('//text()') ?: []) as $text) {
            if ($text instanceof \DOMCdataSection) {
                $text->parentNode?->replaceChild($dom->createTextNode($text->data), $text);
            }
        }

        $ids = [];
        foreach ($dom->getElementsByTagName('*') as $el) {
            if ($el->hasAttribute('id')) {
                $ids[$el->getAttribute('id')] = true;
            }
        }
        $dropped = [];
        $scope = rtrim($prefix, '-');
        $hasStyle = false;
        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $el) {
            $this->rewriteAttributes($el, $ids, $prefix);
            if (strtolower($el->localName ?? '') === 'style') {
                $hasStyle = true;
                $css = $this->rewriteStyleFamilies($this->scopeStyles($el->textContent, $scope, $ids, $prefix, $dropped));
                while ($el->firstChild !== null) {
                    $el->removeChild($el->firstChild);
                }
                $el->appendChild($dom->createCDATASection(str_replace(']]>', ']] >', $css)));
            }
        }

        if ($this->fontFamily !== null) {
            $this->rewriteFamilies($root, 400, false);
            // The default for text that names no family: an attribute, so any rule of the SVG's own still wins.
            if (!$root->hasAttribute('font-family') && !self::hasDeclaration($root->getAttribute('style'), 'font-family')) {
                $root->setAttribute('font-family', ($this->fontFamily)(null, 400, false));
            }
        }
        if ($hasStyle) {
            $root->setAttribute('class', trim($root->getAttribute('class') . ' ' . $scope));
        }
        $declarations = array_filter([$root->getAttribute('style') !== '' ? rtrim(trim($root->getAttribute('style')), ';') . ';' : '', Css::attr($style)]);
        if ($declarations !== []) {
            $root->setAttribute('style', implode(' ', $declarations));
        }
        if ($alt === '') {
            $root->removeAttribute('role');
            $root->removeAttribute('aria-label');
            $root->setAttribute('aria-hidden', 'true');
        } else {
            $root->removeAttribute('aria-hidden');
            $root->removeAttribute('aria-labelledby');
            $root->setAttribute('role', 'img');
            $root->setAttribute('aria-label', $alt);
        }

        $html = $dom->saveXML($root);

        return [
            'html' => $html === false ? '' : $html,
            'approximated' => array_map(static fn (string $kind): string => 'inline SVG style ' . $kind, array_keys($dropped)),
        ];
    }

    /** Rewrites the families set on $el and below; weight and italic are what the parent passes down. */
    private function rewriteFamilies(\DOMElement $el, int $weight, bool $italic): void
    {
        $style = $el->getAttribute('style');
        $weight = self::weightOf($el->getAttribute('font-weight')) ?? $weight;
        $italic = self::italicOf($el->getAttribute('font-style')) ?? $italic;
        $declared = self::declarations($style);
        foreach ($declared as [$name, $value]) {
            if ($name === 'font-weight') {
                $weight = self::weightOf($value) ?? $weight;
            } elseif ($name === 'font-style') {
                $italic = self::italicOf($value) ?? $italic;
            }
        }
        if ($el->hasAttribute('font-family')) {
            $el->setAttribute('font-family', $this->family($el->getAttribute('font-family'), $weight, $italic));
        }
        if ($style !== '' && self::hasDeclaration($style, 'font-family')) {
            $parts = [];
            foreach ($declared as [$name, $value, $raw]) {
                $parts[] = $name === 'font-family' ? $name . ':' . $this->family($value, $weight, $italic) : $raw;
            }
            $el->setAttribute('style', implode(';', $parts));
        }
        foreach ($el->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->rewriteFamilies($child, $weight, $italic);
            }
        }
    }

    /** Replaces the family in every rule of a stylesheet, except @font-face; weight and style are the rule's own. */
    private function rewriteStyleFamilies(string $css): string
    {
        if ($this->fontFamily === null || !str_contains($css, 'font-family')) {
            return $css;
        }

        return preg_replace_callback('/([^{}]*)\{([^{}]*)\}/', function (array $m): string {
            if (str_starts_with(ltrim($m[1]), '@font-face') || !str_contains($m[2], 'font-family')) {
                return $m[0];
            }
            $weight = 400;
            $italic = false;
            $declared = self::declarations($m[2]);
            foreach ($declared as [$name, $value]) {
                if ($name === 'font-weight') {
                    $weight = self::weightOf($value) ?? $weight;
                } elseif ($name === 'font-style') {
                    $italic = self::italicOf($value) ?? $italic;
                }
            }
            $parts = [];
            foreach ($declared as [$name, $value, $raw]) {
                $parts[] = $name === 'font-family' ? $name . ':' . $this->family($value, $weight, $italic) : $raw;
            }

            return $m[1] . '{' . implode(';', $parts) . '}';
        }, $css) ?? $css;
    }

    /** A family list, with any !important kept, rewritten by the closure. */
    private function family(string $value, int $weight, bool $italic): string
    {
        $important = '';
        if (preg_match('/\s*!\s*important\s*$/i', $value, $m) === 1) {
            $important = ' !important';
            $value = substr($value, 0, -strlen($m[0]));
        }

        return ($this->fontFamily?->__invoke(trim($value), $weight, $italic) ?? trim($value)) . $important;
    }

    /** @return list<array{string, string, string}> property (lowercased), value and the declaration as written */
    private static function declarations(string $css): array
    {
        $out = [];
        $at = 0;
        $n = strlen($css);
        while ($at < $n) {
            $end = self::scanTo($css, $at, ';');
            $raw = substr($css, $at, $end - $at);
            $at = $end + 1;
            if (trim($raw) === '') {
                continue;
            }
            $colon = strpos($raw, ':');
            $out[] = $colon === false
                ? ['', '', $raw]
                : [strtolower(trim(substr($raw, 0, $colon))), trim(substr($raw, $colon + 1)), $raw];
        }

        return $out;
    }

    private static function hasDeclaration(string $style, string $property): bool
    {
        foreach (self::declarations($style) as [$name]) {
            if ($name === $property) {
                return true;
            }
        }

        return false;
    }

    /** 700 for bold, bolder or 600 and up, 400 for normal, lighter or less; null when the value says nothing (inherit, empty). */
    private static function weightOf(string $value): ?int
    {
        $value = strtolower(trim(preg_replace('/\s*!\s*important\s*$/i', '', $value) ?? $value));

        return match (true) {
            $value === 'bold' || $value === 'bolder' => 700,
            $value === 'normal' || $value === 'lighter' => 400,
            is_numeric($value) => (float) $value >= 600 ? 700 : 400,
            default => null,
        };
    }

    private static function italicOf(string $value): ?bool
    {
        $value = strtolower(trim(preg_replace('/\s*!\s*important\s*$/i', '', $value) ?? $value));

        return match (true) {
            str_starts_with($value, 'italic') || str_starts_with($value, 'oblique') => true,
            $value === 'normal' => false,
            default => null,
        };
    }

    /** @param array<string, true> $ids */
    private function rewriteAttributes(\DOMElement $el, array $ids, string $prefix): void
    {
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = $attr->nodeName;
            $value = $attr->value;
            if ($name === 'id') {
                $new = $prefix . $value;
            } elseif (($name === 'href' || $name === 'xlink:href') && str_starts_with($value, '#') && isset($ids[substr($value, 1)])) {
                $new = '#' . $prefix . substr($value, 1);
            } elseif (in_array($name, self::IDREF_LISTS, true)) {
                $new = implode(' ', array_map(static fn (string $id): string => isset($ids[$id]) ? $prefix . $id : $id, preg_split('/\s+/', trim($value)) ?: []));
            } elseif ($name === 'begin' || $name === 'end') {
                $new = implode(';', array_map(static function (string $item) use ($ids, $prefix): string {
                    return preg_match('/^(\s*)([A-Za-z_][\w.-]*?)\.(?=\S)/', $item, $m) === 1 && isset($ids[$m[2]])
                        ? $m[1] . $prefix . $m[2] . substr($item, strlen($m[1]) + strlen($m[2]))
                        : $item;
                }, explode(';', $value)));
            } else {
                $new = self::rewriteUrls($value, $ids, $prefix);
            }
            if ($new !== $value) {
                $attr->value = $new;
            }
        }
    }

    /** @param array<string, true> $ids */
    private static function rewriteUrls(string $css, array $ids, string $prefix): string
    {
        if (!str_contains($css, 'url(')) {
            return $css;
        }

        return preg_replace_callback(
            '/url\(\s*(["\']?)#([^)"\'\s]+)\1\s*\)/',
            static fn (array $m): string => isset($ids[$m[2]]) ? 'url(' . $m[1] . '#' . $prefix . $m[2] . $m[1] . ')' : $m[0],
            $css,
        ) ?? $css;
    }

    /**
     * @param array<string, true> $ids
     * @param array<string, true> $dropped kinds of at-rule removed, such as @import
     */
    private function scopeStyles(string $css, string $scope, array $ids, string $prefix, array &$dropped): string
    {
        $css = preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;

        return self::rewriteUrls($this->scopeBlock($css, $scope, $ids, $prefix, $dropped), $ids, $prefix);
    }

    /**
     * @param array<string, true> $ids
     * @param array<string, true> $dropped
     */
    private function scopeBlock(string $css, string $scope, array $ids, string $prefix, array &$dropped): string
    {
        $out = '';
        $n = strlen($css);
        $i = 0;
        while ($i < $n) {
            while ($i < $n && ctype_space($css[$i])) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }
            $start = $i;
            $i = self::scanTo($css, $i, '{;');
            $prelude = trim(substr($css, $start, $i - $start));
            if ($i >= $n || $css[$i] === ';') {
                $i++;
                if (str_starts_with($prelude, '@')) {
                    $dropped[self::atName($prelude)] = true;
                }
                continue;
            }
            $bodyStart = $i + 1;
            $i = self::endOfBlock($css, $bodyStart);
            $body = substr($css, $bodyStart, $i - $bodyStart);
            $i++;
            if (str_starts_with($prelude, '@')) {
                $name = self::atName($prelude);
                if ($name === '@media') {
                    $out .= $prelude . '{' . $this->scopeBlock($body, $scope, $ids, $prefix, $dropped) . '}';
                } elseif ($name === '@font-face') {
                    $out .= $prelude . '{' . $body . '}';
                } else {
                    $dropped[$name] = true;
                }
                continue;
            }
            $selectors = [];
            foreach (self::splitTop($prelude) as $selector) {
                $selector = trim($selector);
                if ($selector === '') {
                    continue;
                }
                $selector = preg_replace_callback(
                    '/#(-?[_a-zA-Z][\w-]*)/',
                    static fn (array $m): string => isset($ids[$m[1]]) ? '#' . $prefix . $m[1] : $m[0],
                    $selector,
                ) ?? $selector;
                foreach (self::scoped($selector, $scope) as $form) {
                    $selectors[] = $form;
                }
            }
            if ($selectors !== []) {
                $out .= implode(', ', $selectors) . '{' . $body . '}';
            }
        }

        return $out;
    }

    /**
     * The forms of a selector that match inside the SVG and, when it can name the root, the root itself.
     *
     * @return list<string>
     */
    private static function scoped(string $selector, string $scope): array
    {
        $first = self::scanTo($selector, 0, " \t\r\n>+~");
        $head = substr($selector, 0, $first);
        $rest = substr($selector, $first);
        $forms = [];
        if (preg_match('/^:root(?![\w-])/i', $head) === 1) {
            return ['svg.' . $scope . substr($head, 5) . $rest];
        }
        if (preg_match('/^svg(?![\w-])/i', $head) === 1) {
            $forms[] = 'svg.' . $scope . substr($head, 3) . $rest;
        } elseif ($head !== '' && str_contains('.#[:*', $head[0])) {
            $forms[] = '.' . $scope . ($head[0] === '*' ? substr($head, 1) : $head) . $rest;
        }
        $forms[] = '.' . $scope . ' ' . $selector;

        return $forms;
    }

    private static function atName(string $prelude): string
    {
        return preg_match('/^@[a-zA-Z-]+/', $prelude, $m) === 1 ? strtolower($m[0]) : '@rule';
    }

    /** Index of the first of the characters in $stop at or after $i, outside strings and parentheses; the length if none. */
    private static function scanTo(string $css, int $i, string $stop): int
    {
        $n = strlen($css);
        $depth = 0;
        for (; $i < $n; $i++) {
            $c = $css[$i];
            if ($c === '"' || $c === "'") {
                $i = self::endOfString($css, $i);
            } elseif ($c === '(') {
                $depth++;
            } elseif ($c === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($depth === 0 && str_contains($stop, $c)) {
                return $i;
            }
        }

        return $n;
    }

    /** Index of the brace closing the block whose body starts at $i; the length if it never closes. */
    private static function endOfBlock(string $css, int $i): int
    {
        $n = strlen($css);
        $depth = 1;
        for (; $i < $n; $i++) {
            $c = $css[$i];
            if ($c === '"' || $c === "'") {
                $i = self::endOfString($css, $i);
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}' && --$depth === 0) {
                return $i;
            }
        }

        return $n;
    }

    private static function endOfString(string $css, int $i): int
    {
        $n = strlen($css);
        $quote = $css[$i];
        for ($i++; $i < $n && $css[$i] !== $quote; $i++) {
            if ($css[$i] === '\\') {
                $i++;
            }
        }

        return $i;
    }

    /** @return list<string> */
    private static function splitTop(string $list): array
    {
        $parts = [];
        $depth = 0;
        $quote = '';
        $current = '';
        foreach (str_split($list) as $c) {
            if ($quote !== '') {
                $quote = $c === $quote ? '' : $quote;
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $parts[] = $current;

        return $parts;
    }
}
