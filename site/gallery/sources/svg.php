<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, Svg, Text};

use function Papercut\{in, pt};

// A bar chart drawn as SVG, as a Blade component or a chart library would produce it.
$months = ['Jan' => 42, 'Feb' => 55, 'Mar' => 48, 'Apr' => 67, 'May' => 74, 'Jun' => 88];
$bars = '';
$x = 40;
foreach ($months as $label => $value) {
    $h = $value * 1.6;
    $bars .= sprintf('<rect x="%d" y="%.1f" width="36" height="%.1f" rx="3" fill="#6366f1"/>', $x, 170 - $h, $h);
    $bars .= sprintf('<text x="%d" y="188" font-size="11" text-anchor="middle" fill="#64748b">%s</text>', $x + 18, $label);
    $x += 56;
}
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 380 200"><line x1="30" y1="170" x2="370" y2="170" stroke="#cbd5e1"/>' . $bars . '</svg>';

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Text::make('Monthly sign-ups')->bold(),
        Svg::fromString($svg)->width(in(5)),
    )->gap(pt(6)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
