<?php

declare(strict_types=1);

// Run: php examples/core/report.php
require __DIR__ . '/../../packages/core/vendor/autoload.php';

use Papercut\{Column, Document, Page, PageBreak, PageNumber, Row, Spacer, Table, TableRow, Text};
use Papercut\Value\{Color, Edges};

use function Papercut\{mm, pt};

$prose = [
    'Papercut starts from a simple observation: most business documents are layouts, not drawings. An invoice is a column of blocks, a letterhead is a row with a flexible gap, and a transcript is a table that happens to be long. The library therefore models documents as trees of components and leaves the coordinate arithmetic to a layout engine.',
    'Every node is immutable. A modifier such as padding or bold returns a new instance, so a fragment can be reused in a dozen places without one caller changing what another sees. This also makes documents safe to build once and render many times, which matters in long-running workers.',
    'Layout follows a measure-then-place model. Each element reports the space it wants given the width on offer, and containers distribute the remainder using grow factors, gaps, and alignment. The vocabulary is deliberately close to flexbox, so anyone who has written modern CSS can predict the result.',
    'Text is shaped with real font metrics. Lines are broken at word boundaries, justified paragraphs stretch the gaps between words, and inline markup such as bold or italic spans is carried through the line breaker without losing its style. Widths come from the embedded font program, never from an estimate.',
    'Pagination is a first-class concern rather than an afterthought. Blocks can ask to be kept together, a heading can insist on staying with the paragraph that follows it, and tables repeat their header on every page they cross. When nothing else works, the engine splits a block at a line boundary and keeps going.',
    'Output is deterministic. Given the same input and a fixed creation date, two runs produce identical bytes. That property makes golden-file tests practical: a visual regression becomes a byte comparison, and a diff in the PDF points at a diff in the code.',
    'The backend is a thin seam. The layout engine produces a display list of rectangles, lines, text runs, and links in plain points with a top-left origin, and one adapter turns that list into PDF objects. Nothing above the adapter knows how a PDF is encoded, so the engine can be tested without parsing a single file.',
    'Failure is explicit. If a block cannot fit on a page even after every permitted split, the engine throws by default and names the offending node. Callers who prefer degraded output can opt into clipping or shrinking, but silent corruption is never the default.',
    'Styling is data. Colors resolve from a named palette, sizes accept points, millimetres, or percentages, and a theme supplies defaults for family, size, weight, and line height. Because every modifier also accepts a string form, templates written in markup and code written in PHP describe the same documents.',
    'The result is a library that stays out of the way for simple documents and still holds together for hard ones: a forty-page ledger, a certificate with a decorative background, or a report whose tables must not be orphaned from their captions.',
];

$cards = Row::make(
    ...array_map(
        fn (array $s) => Column::make(
            Text::make($s[0])->size(pt(8))->bold()->color('slate-500'),
            Text::make($s[1])->size(pt(22))->bold()->color($s[3]),
            Text::make($s[2])->size(pt(9))->color('slate-600'),
        )->gap(pt(3))->padding(pt(14))->background('slate-50')->radius(pt(8))->grow(1),
        [
            ['PAGES PER SECOND', '212', 'on a mid-range laptop, text-heavy', 'indigo-600'],
            ['OUTPUT SIZE', '38 KB', 'for a ten-page report, fonts subset', 'emerald-600'],
        ],
    ),
)->gap(pt(8));

$table = Table::make()->columns('16mm 1fr 28mm 28mm')
    ->headerRow(TableRow::make(
        Text::make('#')->bold(),
        Text::make('Scenario')->bold(),
        Text::make('Pages')->bold()->right(),
        Text::make('Time (ms)')->bold()->right(),
    )->background('slate-100'));
for ($i = 1; $i <= 80; $i++) {
    $table = $table->bodyRow(TableRow::make(
        Text::make((string) $i)->color('slate-500'),
        sprintf('Benchmark scenario %d: %s', $i, ['single column text', 'nested rows', 'wide table', 'justified prose'][$i % 4]),
        Text::make((string) (1 + $i % 9))->right(),
        Text::make(number_format(12 + ($i * 37) % 90 + ($i % 7) / 10, 1))->right(),
    )->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor(Color::parse('slate-200')));
}

$body = [
    Text::make('Designing Papercut')->bold()->size(pt(32))->color('slate-900'),
    Text::make('A short report on layout, pagination, and deterministic output')->size(pt(13))->color('slate-500'),
    $cards->margin(Edges::of(pt(10), pt(0), pt(10), pt(0))),
    Text::h1('1. Overview'),
];
foreach ($prose as $i => $p) {
    if ($i === 3) {
        $body[] = Text::h2('2. Text and pagination')->margin(Edges::of(pt(10), pt(0), pt(0), pt(0)));
    }
    if ($i === 6) {
        $body[] = Text::h2('3. Backend and failure modes')->margin(Edges::of(pt(10), pt(0), pt(0), pt(0)));
    }
    $body[] = Text::p($p)->justify();
}
$body[] = Text::markup('Rule of thumb: <b>measure first</b>, then <i>place</i>, and only then paint.')->margin(Edges::of(pt(4), pt(0), pt(8), pt(0)));
$body[] = Text::h2('4. Benchmarks')->margin(Edges::of(pt(10), pt(0), pt(0), pt(0)));
$body[] = $table;
$body[] = PageBreak::make();
$body[] = Text::h1('Appendix A: Glossary');
foreach ([['Display list', 'The ordered drawing operations produced by layout.'], ['Fragment', 'The part of a node that fits on one page.'], ['Track', 'A column width definition in a table.']] as [$t, $d]) {
    $body[] = Text::markup("<b>$t</b>: $d")->margin(Edges::of(pt(0), pt(0), pt(6), pt(0)));
}

$document = Document::make(
    Page::a4()->margin(mm(22))
        ->header(Row::make(Text::make('Papercut Design Report')->size(pt(8))->color('slate-500'), Spacer::flex(), Text::make('Confidential')->size(pt(8))->color('slate-500')))
        ->body(Column::make(...$body)->gap(pt(0)))
        ->footer(PageNumber::make('{page} / {total}')->center()->size(pt(8))->color('slate-500')),
)->title('Designing Papercut')->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));

$document->save(__DIR__ . '/report.pdf');
echo "Wrote examples/core/report.pdf\n";
