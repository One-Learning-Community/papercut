<?php

declare(strict_types=1);

// Builds the academic paper Document: a full-width title block and abstract, a two-column body that ends level, and a full-width
// closing note. examples/core/paper.php saves it as PDF, examples/html/render.php as HTML and examples/docx/render.php as Word.
use Papercut\{Column, ColumnFlow, Document, Equation, Page, PageNumber, Row, Span, Spacer, Svg, Table, TableCell, TableRow, Text};
use Papercut\Math\Math;
use Papercut\Value\Edges;

use function Papercut\{mm, pt};

$assets = __DIR__ . '/../../assets';

$authors = [
    ['Marta Lindqvist', 'Institute of Pollinator Ecology, Northfield University'],
    ['Tomas Okafor', 'Department of Applied Biology, Larchmont College'],
    ['Reiko Havel', 'Meadowbank Field Station'],
];

$abstract = 'We tracked 96 honey bee colonies through two winters to ask whether the spacing between hives changes how many survive. '
    . 'Colonies kept 30 cm or more from their neighbours lost fewer bees to drift and robbing, and nearly all of them reached spring. '
    . 'Closer spacing cost colonies little in mild weather but a great deal after a cold snap. The effect held across both sites.';

// A displayed formula and an inline one, built with the math builder.
$logit = Equation::of(Math::row('ln', Math::fenced('(', ')', Math::frac('p', '1 - p')), '=', Math::sub('β', '0'), '+', Math::sub('β', '1'), 'd'))
    ->center()->margin(Edges::of(pt(2), pt(0), pt(6), pt(0)));
$logitIntro = Text::spans(
    Span::text('We compared survival with a logistic model that included site and year, and the weight lost with a linear model. For a colony at spacing '),
    Span::math('d'),
    Span::text(', the model gives the log odds of survival as'),
)->justify();
$weightLoss = Text::spans(
    Span::text('The weight lost over winter fell with spacing too: the fitted slope was '),
    Span::math(Math::sub('β', '1'), '=', '−0.04'),
    Span::text(' kg per cm, or about 0.4 kg for each further 10 cm. Colonies at wide spacing also began spring with more frames of bees. Figure 1 plots survival against spacing for both years together.'),
)->justify();

// Each section is a title and its paragraphs. The invented data in this paper is for illustration only.
$sections = [
    ['1. Introduction', [
        'Beekeepers have long argued about how far apart hives should stand. Close rows are cheap to build and easy to inspect, while wide rows take more ground and more time. Most advice rests on habit rather than measurement, and the few published trials cover a single season at a single site.',
        'Neighbouring colonies interact in three ways that spacing could change. Foragers drift into the wrong entrance, which carries mites and disease from one hive to the next. Strong colonies rob weak ones when nectar is scarce. And a tight row shares shelter, which may help in a frost.',
        'We set out to measure the combined effect over two full winters. This paper reports survival, spring population and the weight lost by each colony, and asks whether any gain from wide spacing is large enough to justify the extra ground.',
        'The paper is organised as follows. Section 2 describes the colonies, the sites and the models. Section 3 gives the results, and Section 4 discusses what they do and do not show. Section 5 closes with a recommendation for beekeepers and a note on the trials that would settle the open questions.',
    ]],
    ['2. Methods', [
        'We placed 96 colonies of similar size at two sites, 48 at each, in rows of twelve. Hives in a row stood 10, 20, 30 or 40 cm apart, and every spacing had one row at each site. Rows were assigned a spacing by lot, and the same colonies kept their positions for both winters.',
        'Each colony began autumn with a young, marked queen and at least eight frames of bees. We weighed the hives in October and again in March, counted frames of bees on a mild day in April, and recorded every colony that had died by then. Treatment against mites followed one schedule at both sites.',
        $logitIntro,
        $logit,
        'Throughout, we report differences with their intervals and give p-values only where they help the reader.',
    ]],
    ['3. Results', [
        'Survival rose with spacing, as Table 1 shows. Of the colonies kept 10 cm apart, 75 percent reached spring; at 40 cm, every one did. The gain came mostly from the second winter, which brought a week of hard frost in January.',
        $weightLoss,
    ]],
    ['4. Discussion', [
        'Our results favour wide spacing, but not by a wide margin in mild years. In the first winter all four spacings did about equally well, and the differences appeared only after the cold snap. A beekeeper who loses one hive in ten may reasonably choose to save the ground.',
        'Two limits apply. We counted deaths but not their causes, so we cannot say whether drift, robbing or shelter explains the gain. And our rows ran across the prevailing wind; rows along it may behave differently.',
        'Cost matters as well. Moving from 10 to 30 cm adds about a third to the length of a row of twelve hives, and the extra ground is the price of the gain we measured.',
    ]],
    ['5. Conclusion', [
        'Moving hives from 10 to 30 cm apart improved winter survival in two winters at two sites. We recommend at least 30 cm where ground allows, and we encourage trials that separate the causes.',
    ]],
];

$table = Table::make()->columns('1fr 1fr 1fr')
    ->headerRow(TableRow::make(
        Text::make('Spacing')->bold(),
        Text::make('Colonies')->bold(),
        Text::make('Survived')->bold(),
    )->background('slate-100'));
foreach ([['10 cm', '24', '75%'], ['20 cm', '24', '88%'], ['30 cm', '24', '96%'], ['40 cm', '24', '100%']] as $row) {
    $table = $table->bodyRow(TableRow::make(...array_map(
        static fn (string $cell): TableCell => TableCell::make(Text::make($cell)),
        $row,
    ))->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor('slate-200'));
}

$caption = static fn (string $text) => Text::make($text)->size(pt(8.5))->color('slate-600');

$body = [];
foreach ($sections as $i => [$title, $paragraphs]) {
    $body[] = Text::h3($title)->margin(Edges::of($i === 0 ? pt(0) : pt(8), pt(0), pt(0), pt(0)));
    foreach ($paragraphs as $p) {
        $body[] = is_string($p) ? Text::p($p)->justify() : $p;
    }
    if ($title === '3. Results') {
        $body[] = Column::make(
            $caption('Table 1. Survival to spring, by spacing between hives.')->bold(),
            $table,
        )->gap(pt(4))->margin(Edges::of(pt(4), pt(0), pt(8), pt(0)))->keepTogether();
        $body[] = Column::make(
            Svg::file($assets . '/survival-chart.svg')->width(mm(80)),
            $caption('Figure 1. Colonies alive in spring at 10, 20, 30 and 40 cm (left to right); the gridlines mark 25, 50, 75 and 100 percent.')->bold(),
        )->gap(pt(4))->margin(Edges::of(pt(4), pt(0), pt(8), pt(0)))->keepTogether();
    }
}

$header = Row::make(
    Text::make('Hive Spacing and Winter Survival')->size(pt(8))->color('slate-500'),
    Spacer::flex(),
    Text::make('Preprint')->size(pt(8))->color('slate-500'),
)->padding(pt(0), pt(0), pt(4), pt(0))->borderWidth(Edges::of(pt(0), pt(0), pt(0.5), pt(0)))->borderColor('slate-200');

$title = Column::make(
    Text::h1('Wider Hive Spacing Improves Winter Survival of Honey Bee Colonies')->center(),
    Row::make(...array_map(
        static fn (array $a) => Column::make(
            Text::make($a[0])->bold(),
            Text::make($a[1])->size(pt(8.5))->color('slate-600'),
        )->gap(pt(1))->grow(1)->basis(pt(0)),
        $authors,
    ))->gap(pt(14)),
    Column::make(
        Text::make('Abstract')->bold(),
        Text::p($abstract)->justify(),
    )->gap(pt(3))->padding(pt(8))->background('slate-50'),
)->gap(pt(12))->margin(Edges::of(pt(0), pt(0), pt(14), pt(0)));

$note = Column::make(
    Text::make('Data availability')->bold(),
    Text::p('The colony records behind Table 1 and Figure 1 are invented for this example and are not measurements.')->size(pt(9))->color('slate-600'),
)->gap(pt(2))->margin(Edges::of(pt(14), pt(0), pt(0), pt(0)))->borderWidth(Edges::of(pt(0.75), pt(0), pt(0), pt(0)))->borderColor('slate-300')->padding(pt(6), pt(0), pt(0), pt(0));

return Document::make(
    Page::a4()->margin(mm(18))
        ->header($header)
        ->body(
            $title,
            ColumnFlow::make(...$body)->columns(2)->columnGap(mm(6))->columnRule(),
            $note,
        )
        ->footer(PageNumber::make('Page {page} of {total}')->center()->size(pt(8))->color('slate-500')),
)->title('Wider Hive Spacing Improves Winter Survival of Honey Bee Colonies')->author('Marta Lindqvist, Tomas Okafor, Reiko Havel')->createdAt(new DateTimeImmutable('2026-03-12T09:00:00Z'));
