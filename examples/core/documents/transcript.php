<?php

declare(strict_types=1);

// Builds the transcript Document; examples/core/transcript.php saves it as PDF and examples/html/render.php as HTML.
use Papercut\{Column, Document, Page, PageNumber, Row, Spacer, Table, TableRow, Text};
use Papercut\Value\Edges;

use function Papercut\{mm, pt};

$subjects = ['Calculus', 'Linear Algebra', 'Data Structures', 'Algorithms', 'Physics', 'Statistics', 'Databases', 'Compilers', 'Networks', 'Ethics', 'Logic', 'Numerical Methods'];
$levels = ['Introduction to', 'Applied', 'Topics in', 'Advanced', 'Seminar in'];
$grades = ['A' => 4.0, 'A-' => 3.7, 'B+' => 3.3, 'B' => 3.0, 'B-' => 2.7, 'C+' => 2.3];
$gradeKeys = array_keys($grades);

$courses = [];
for ($i = 0; $i < 60; $i++) {
    $term = intdiv($i, 8) + 1;
    $courses[] = [
        sprintf('CS %d', 100 + $i * 7 % 400),
        $levels[intdiv($i, 12)] . ' ' . $subjects[$i % count($subjects)],
        sprintf('Term %d', $term),
        3 + $i % 2,
        $gradeKeys[($i * 5 + intdiv($i, 3)) % count($gradeKeys)],
    ];
}

$credits = array_sum(array_column($courses, 3));
$points = array_sum(array_map(fn (array $c) => $c[3] * $grades[$c[4]], $courses));
$gpa = $points / $credits;

$stat = fn (string $l, string $v) => Column::make(
    Text::make($l)->size(pt(8))->bold()->color('slate-500'),
    Text::make($v)->size(pt(13))->bold(),
)->gap(pt(2))->grow(1);

$card = Column::make(
    Text::make('Ada Lovelace')->bold()->size(pt(16)),
    Text::make('Student ID 2019-04417  |  B.Sc. Computer Science  |  Enrolled 2019')->color('slate-600'),
    Row::make($stat('CREDITS', (string) $credits), $stat('GPA', number_format($gpa, 2)), $stat('STANDING', 'Good'), $stat('COURSES', (string) count($courses)))
        ->gap(pt(8))->margin(Edges::of(pt(8), pt(0), pt(0), pt(0))),
)->gap(pt(3))->padding(pt(12))->background('slate-50')->radius(pt(8))->keepTogether();

$table = Table::make()->columns('22mm 1fr 24mm 18mm 18mm')
    ->headerRow(TableRow::make(
        Text::make('Code')->bold(),
        Text::make('Course')->bold(),
        Text::make('Term')->bold(),
        Text::make('Credits')->bold()->right(),
        Text::make('Grade')->bold()->right(),
    )->background('slate-100'));
foreach ($courses as $c) {
    $table = $table->bodyRow(TableRow::make(
        Text::make($c[0])->color('slate-500'),
        $c[1],
        Text::make($c[2])->color('slate-600'),
        Text::make((string) $c[3])->right(),
        Text::make($c[4])->right()->bold(),
    )->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor('slate-200'));
}

$summary = Row::make(
    Text::make(sprintf('%d credits attempted, %.1f quality points.', $credits, $points))->grow(1),
    Text::make('GPA ' . number_format($gpa, 2))->bold()->size(pt(14)),
)->padding(pt(12))->background('slate-50')->radius(pt(8))->keepTogether();

$document = Document::make(
    Page::a4()->margin(mm(20))
        ->header(Row::make(
            Text::make('Official Transcript')->bold()->size(pt(16))->color('indigo-600'),
            Spacer::flex(),
            Text::make('One Learning Community')->color('slate-500'),
        ))
        ->body(Column::make($card, Text::h2('Course history')->keepWithNext(), $table, Text::h3('Cumulative summary')->keepWithNext(), $summary)->gap(pt(10)))
        ->footer(PageNumber::make('Page {page} of {total}')->center()->size(pt(8))->color('slate-500')),
)->title('Official Transcript')->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));

return $document;
