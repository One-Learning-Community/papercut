<?php

declare(strict_types=1);

use Papercut\{Column, Document, Page, Text};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Column::make(
        Text::markup('Text can be <b>bold</b>, <i>italic</i>, <u>underlined</u> or <s>struck through</s>, and mixed: <b><i>both at once</i></b>.'),
        Text::markup('Mark a <mark>key phrase</mark>, show <code>inline_code()</code>, or <span color="emerald-700" weight="600">colour a few words</span>.'),
        Text::markup('Links are real: <a href="https://example.com/docs"><span color="indigo-600" underline>read the docs</span></a>.<br>A line break keeps the same paragraph.'),
    )->gap(pt(8)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
