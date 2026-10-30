<?php

declare(strict_types=1);

// Builds the responses Document: a feedback-form record made from eight of the decorators.
// examples/core/responses.php saves it as PDF and examples/html/render.php as HTML.
use Papercut\{Column, Document, Page, PageNumber, Text};
use Papercut\Decorator\{Badge, Blockquote, Callout, Card, ChoiceList, Field, KeyValueList, Section};

use function Papercut\{mm, percent, pt};

$items = [
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
    ['type' => 'quote', 'text' => 'I came for the PDFs and stayed for the pagination.', 'cite' => 'Jane Smith, closing round'],
];

$body = [
    Text::h2('Workshop feedback'),
    Card::make(
        KeyValueList::make()
            ->row('Respondent', 'Jane Smith')
            ->row('Submitted', '2026-03-02 14:30')
            ->row('Course', 'Papercut workshop, spring cohort')
            ->labelWidth(percent(30)),
    )->title('Response details'),
];

$n = 0;
foreach ($items as $item) {
    if ($item['type'] === 'instruction') {
        $body[] = Callout::make($item['text'])->tone('note')->title($item['prompt']);
        continue;
    }
    if ($item['type'] === 'quote') {
        $body[] = Blockquote::make($item['text'])->cite($item['cite'])->keepTogether();
        continue;
    }

    $answer = match ($item['type']) {
        'text' => Field::make($item['answer'])->placeholder('No response')->lines(2),
        'choices' => array_reduce(
            $item['options'],
            fn (ChoiceList $list, string $option) => $list->option($option, selected: in_array($option, $item['selected'], true)),
            ($item['multiple'] ? ChoiceList::checkbox() : ChoiceList::radio())->emphasizeSelected(),
        ),
        'ratings' => array_reduce(
            $item['rows'],
            fn (KeyValueList $list, array $row) => $list->row($row[0], Badge::make($row[1])->tone($row[2])),
            KeyValueList::make()->labelWidth(percent(60)),
        ),
    };

    $body[] = Section::make($answer)->number(++$n)->title($item['prompt'])->rule()->keepTogether();
}

$document = Document::make(
    Page::a4()->margin(mm(20))
        ->body(Column::make(...$body)->gap(pt(12)))
        ->footer(PageNumber::make('Page {page} of {total}')->center()->size(pt(8))->color('slate-500')),
)->title('Feedback form response')->author('Northwind Studio')->createdAt(new DateTimeImmutable('2026-03-02T14:30:00Z'));

return $document;
