# More decorators: stat, totals, signature, and four built-in variants

Date: 2026-11-01. Status: approved in conversation.

The decorators double as examples for people building their own, so the set stays small. These three are the ones our own examples build by hand (the report's stat cards, the invoice's totals) or that forms and contracts need with nothing to cover them (a signature line).

## Stat

A label, a large value and an optional note, on a tinted panel.

```blade
<x-doc::stat label="Revenue" note="Up 12% on Q2">$48,200</x-doc::stat>
```

```php
Stat::make('$48,200')->label('Revenue')->note('Up 12% on Q2');
```

- The slot (or `make()` argument) is the value, as inline text like a badge. `label` and `note` are attributes or named slots, strings or components.
- Parts: `stat` (the panel), `stat.label`, `stat.value`, `stat.note`. Order top to bottom: label, value, note.
- Defaults, sized in `em` so they follow the theme: panel `slate-50`, 6pt radius, 10pt 12pt padding, 2pt gap; label `0.75em` bold `slate-500`; value `1.8em` bold `slate-900`; note `0.8em` `slate-600`.
- No `grow` by default: in a row, `grow="1"` on each makes equal tiles, and the docs show that.

## Totals

Label and amount rows, amounts right-aligned, with emphasised total rows. Built as a borderless two-column table, like the key-value list.

```blade
<x-doc::totals>
    <x-doc::kv label="Subtotal">$1,000.00</x-doc::kv>
    <x-doc::kv label="Tax (8.25%)">$82.50</x-doc::kv>
    <x-doc::kv label="Total due" total>$1,082.50</x-doc::kv>
</x-doc::totals>
```

```php
Totals::make()->row('Subtotal', '$1,000.00')->row('Tax (8.25%)', '$82.50')->total('Total due', '$1,082.50');
```

- Rows reuse `<x-doc::kv>`. A new `total` boolean on `kv` marks an emphasised row. It is only valid inside `<x-doc::totals>`: a total row in `<x-doc::key-value>` is an error, and so is `KeyValueList` given a total pair in PHP.
- `KeyValuePair` gains a third constructor parameter, `bool $total = false` (and `of(..., total: true)`), which is backwards compatible.
- Any row can be a total, so "Total" followed by "Amount paid" and "Balance due" works.
- Parts: `totals` (the table), `totals.label`, `totals.value`, and on total rows also `totals.total-label`, `totals.total-value`.
- Defaults: the table is 45% wide and aligned to the end of its container (`align-self: end`), since totals sit at the bottom right. Labels `slate-600`, values right-aligned; 2pt vertical cell padding, 12pt between columns. Total cells: bold `slate-900` at `1.1em`, a 1pt `slate-300` rule above and 4pt padding over the text. Override the width with `width="100%"`.

## Signature

A line to sign on, with the signer's name, role and date under it.

```blade
<x-doc::signature name="Jane Smith" role="Director" date="12 March 2026" />
<x-doc::signature name="Jane Smith"><x-doc::svg src="signature.svg" height="28pt" /></x-doc::signature>
```

```php
Signature::make()->name('Jane Smith')->role('Director')->date('12 March 2026');
Signature::make(Svg::fromFile('signature.svg'))->name('Jane Smith');
```

- The slot is what sits on the line: a signature image, a typed name, or nothing for a blank to sign. `name`, `role` and `date` are optional strings (or named slots); the date is shown as written.
- Parts: `signature` (the block), `signature.line` (the box with the rule along its bottom), `signature.name`, `signature.role`, `signature.date`.
- Defaults: the block is 216pt (3in) wide and kept together on one page. The line has a 0.75pt `slate-700` bottom rule, at least 30pt high, with its content resting on the rule. Name bold, 4pt below the line; role and date `0.85em` `slate-600`.

## Built-in variants

Until now only the code block had built-in variants. Four more, each a real document style that also shows how variants work:

| Variant | Styles | Look |
|---|---|---|
| `field` `line` | `field.line` | A form line: only a 1pt `slate-400` bottom rule, no radius, 2pt 0 padding. |
| `badge` `outline` | `badge.outline` | Transparent background with a 0.75pt border in the tone's colour. Each `badge.<tone>` gains a `-300` border colour, which shows only when a border is drawn. |
| `card` `flat` | `card.flat`, `card.title.flat` | No border; a `slate-50` panel with a `slate-100` title strip and no rule under the title. |
| `section` `large` | `section.title.large`, `section.number.large`, `section.large` | Title and number at `1.35em`, 8pt gap, 10pt below. Matches the example in the variants guide. |

## Not changing

- HTML export: decorators render to boxes, text and tables, which it already handles.
- Word export needed four small fixes, found while checking the output: a table's alignment, bordered badges as bordered runs, a box's fill on stacked rows, and `justify` as the cell's vertical alignment.
- Existing goldens: the badge tone styles gain a border colour with no border width, so filled badges look the same.

## Testing

- Core unit tests per decorator (structure, part names, variants), and the default style values.
- Laravel feature tests per tag (attributes, slots, the `kv total` rules).
- A new visual snapshot fixture with all three decorators and the four variants.
- The report example uses `<x-doc::stat>` in place of its `stat-card` component, and the invoice uses `<x-doc::totals>`. Example outputs regenerated.
