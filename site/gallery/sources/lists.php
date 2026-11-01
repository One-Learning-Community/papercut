<?php

declare(strict_types=1);

use Papercut\{Document, ListItem, ListNode, Page, Row, Text};

use function Papercut\{in, pt};

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make(
        ListNode::ordered(
            'Create an account',
            ListItem::make(Text::make('Add a payment method'), ListNode::unordered('Card', 'Bank transfer')),
            'Invite your team',
        )->grow(1),
        ListNode::unordered(
            'Unlimited projects',
            'Daily backups',
            ListItem::make(Text::make('Priority support'), ListNode::unordered('Email', 'Phone')),
        )->grow(1),
    )->gap(pt(24)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
