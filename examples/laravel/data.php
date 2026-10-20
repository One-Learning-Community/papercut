<?php

declare(strict_types=1);

// Shared sample data for the Blade examples (used by render.php and by ExamplesTest).

// --- invoice ---------------------------------------------------------------
$items = [
    ['Layout engine consulting', 'Flexbox-style design review and API workshop', 12, 140.00],
    ['PDF backend integration', 'Font subsetting and deterministic output', 8, 140.00],
    ['Table pagination', 'Repeating headers, keep-together rules', 6, 140.00],
    ['Template migration', 'Port 14 legacy reports to components', 20, 120.00],
    ['Quality assurance', 'Visual regression suite and PDF validation', 10, 110.00],
    ['Documentation', 'Developer guide and examples', 5, 95.00],
];
$subtotal = array_sum(array_map(fn (array $i) => $i[2] * $i[3], $items));
$tax = round($subtotal * 0.0825, 2);

// --- transcript ------------------------------------------------------------
$subjects = ['Calculus', 'Linear Algebra', 'Data Structures', 'Algorithms', 'Physics', 'Statistics', 'Databases', 'Compilers', 'Networks', 'Ethics', 'Logic', 'Numerical Methods'];
$grades = ['A' => 4.0, 'A-' => 3.7, 'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7, 'C+' => 2.3];
$gradeKeys = array_keys($grades);
$courses = [];
for ($i = 0; $i < 60; $i++) {
    $courses[] = [
        sprintf('CS %d', 100 + $i * 7 % 400),
        $subjects[$i % count($subjects)] . ($i >= 12 ? ' ' . (intdiv($i, 12) + 1) : ''),
        sprintf('Term %d', intdiv($i, 8) + 1),
        3 + $i % 2,
        $gradeKeys[($i * 5 + intdiv($i, 3)) % count($gradeKeys)],
    ];
}
$credits = array_sum(array_column($courses, 3));
$points = array_sum(array_map(fn (array $c) => $c[3] * $grades[$c[4]], $courses));

// --- report ----------------------------------------------------------------
$prose = [
    'Papercut starts from a simple observation: most business documents are layouts, not drawings. An invoice is a column of blocks, a letterhead is a row with a flexible gap, and a transcript is a table that happens to be long. The library therefore models documents as trees of components and leaves the coordinate arithmetic to a layout engine.',
    'Every node is immutable. A modifier such as padding or bold returns a new instance, so a fragment can be reused in a dozen places without one caller changing what another sees. This also makes documents safe to build once and render many times, which matters in long-running workers.',
    'Layout follows a measure-then-place model. Each element reports the space it wants given the width on offer, and containers distribute the remainder using grow factors, gaps, and alignment. The vocabulary is deliberately close to flexbox, so anyone who has written modern CSS can predict the result.',
    'Text is shaped with real font metrics. Lines are broken at word boundaries, justified paragraphs stretch the gaps between words, and inline markup such as bold or italic spans is carried through the line breaker without losing its style. Widths come from the embedded font program, never from an estimate.',
    'Pagination is a first-class concern rather than an afterthought. Blocks can ask to be kept together, a heading can insist on staying with the paragraph that follows it, and tables repeat their header on every page they cross. When nothing else works, the engine splits a block at a line boundary and keeps going.',
    'Output is deterministic. Given the same input and a fixed creation date, two runs produce identical bytes. That property makes golden-file tests practical: a visual regression becomes a byte comparison, and a diff in the PDF points at a diff in the code.',
    'The backend is a thin seam. The layout engine produces a display list of rectangles, lines, text runs, and links in plain points with a top-left origin, and one adapter turns that list into PDF objects. Nothing above the adapter knows how a PDF is encoded, so the engine can be tested without parsing a single file.',
    'Failure is explicit. If a block cannot fit on a page even after every permitted split, the engine throws by default and names the offending node. Callers who prefer degraded output can opt into clipping the overflowing element, but silent corruption is never the default.',
    'Styling is data. Colors resolve from a named palette, sizes accept points, millimetres, or percentages, and a theme supplies defaults for family, size, weight, and line height. Because every modifier also accepts a string form, templates written in markup and code written in PHP describe the same documents.',
    'The result is a library that stays out of the way for simple documents and still holds together for hard ones: a forty-page ledger, a certificate with a decorative background, or a report whose tables must not be orphaned from their captions.',
];
$scenarios = [];
$kinds = ['single column text', 'nested rows', 'wide table', 'justified prose'];
for ($i = 1; $i <= 80; $i++) {
    $scenarios[] = [$i, sprintf('Sample scenario %d: %s', $i, $kinds[$i % 4]), 1 + $i % 9, 10 + ($i * 37) % 90];
}

// --- responses -------------------------------------------------------------
$responseItems = [
    ['type' => 'instruction', 'prompt' => 'Before you continue', 'text' => 'Answer from your own experience in the workshop. Skip anything you did not attend.'],
    ['type' => 'text', 'prompt' => 'What did you find most useful?', 'answer' => 'The pagination session. Seeing a table split across pages with a repeated header answered a question we had been stuck on for weeks.'],
    ['type' => 'text', 'prompt' => 'What should we change for next time?', 'answer' => ''],
    ['type' => 'choices', 'multiple' => true, 'prompt' => 'Which sessions did you attend?', 'options' => ['Layout basics', 'Pagination', 'Fonts and HTML export', 'Templates in Blade'], 'selected' => ['Layout basics', 'Pagination']],
    ['type' => 'choices', 'multiple' => false, 'prompt' => 'How would you rate the pace?', 'options' => ['Too slow', 'About right', 'Too fast'], 'selected' => ['About right']],
    ['type' => 'ratings', 'prompt' => 'How far do you agree?', 'rows' => [
        ['The examples matched problems I have at work.', 'Strongly agree', 'tip'],
        ['I could follow the material without preparation.', 'Agree', 'note'],
        ['The workshop was long enough to cover the topics in depth.', 'Disagree', 'warning'],
    ]],
];

return [
    'invoice' => [
        'items' => $items,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $subtotal + $tax,
    ],
    'transcript' => [
        'student' => 'Ada Lovelace',
        'courses' => $courses,
        'credits' => $credits,
        'points' => $points,
        'gpa' => $points / $credits,
    ],
    'report' => [
        'prose' => $prose,
        'scenarios' => $scenarios,
        'glossary' => [
            ['Display list', 'The ordered drawing operations produced by layout.'],
            ['Fragment', 'The part of a node that fits on one page.'],
            ['Track', 'A column width definition in a table.'],
        ],
    ],
    'responses' => [
        'respondent' => 'Jane Smith',
        'submitted' => '2026-03-02 14:30',
        'course' => 'Papercut workshop, spring cohort',
        'items' => $responseItems,
    ],
];
