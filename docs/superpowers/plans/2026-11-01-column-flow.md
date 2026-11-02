# ColumnFlow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Word-style columns: a `ColumnFlow` container whose content runs down one column, continues in the next, and moves to the next page after the last, in PDF, HTML and Word.

**Architecture:** A flow is an ordinary `View` carrying a `ColumnSpec` (like `Column::make` returns a `View`), so every existing tree walker handles it unchanged. Only layout, HTML export, Word export and markup learn about it. Layout adds a `ColumnFlowLayouter` that reuses `BlockLayouter` once per column and balances the last page. Forced breaks get a kind on `BreakToken`, so a column break and a page break can be told apart. One shared helper, `Tree\ColumnFlows`, decides placement (on the page, in a box, or blocked) for all three outputs. Word gets continuous sections, with `w:cols` and column breaks written by `DocxPatcher`.

**Tech Stack:** PHP 8.4, PHPUnit, PHPStan; dskripchenko/php-pdf (PDF), dskripchenko/php-docx (Word); Laravel Blade components; VuePress site.

**Spec:** `docs/superpowers/specs/2026-11-01-column-flow-design.md`

## Global Constraints

- Work on a local branch `column-flow` off main. Never commit to main directly. Push and tag only with the user's approval.
- Commits end with exactly one trailer, `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. No Claude-Session trailers or links in commits or PRs.
- Content stays generic: no real customers, institutions or people in tests, examples or docs.
- Naming: `ColumnFlow` / `<x-doc::column-flow>`, and `ColumnBreak` / `<x-doc::column-break />`. Path segment `column-flow`. Never confuse it with the existing `Column`.
- Flow settings stay the ones Word has: count, gap, widths, rule. No `column-span`, no column count from a width.
- Placement: on the page or inside any box. Never inside a table cell, a flex row, another flow, or header/footer/background/overlay/template. In the builder or Blade that's an `InvalidStructure`. A markup-made flow never throws for placement: it lays out as one column and records a fidelity note.
- Word: real section columns only for a flow on the page (only plain wrappers above it). A flow in a box comes out as one column in Word with an `approximated` fidelity note.
- `DocxPatcher` writes `w:cols` and column breaks from the start, editing the exact markup php-docx writes and throwing `LogicException('php-docx output changed: …')` when it isn't found.
- Markup reads column CSS only from the `style` attribute, and only in `StyleMode::Original`.
- Default gap: 36pt (Word's 0.5 in).
- Goldens may change before 1.0. Report intended changes. Unexpected ones are bugs.
- Each package's checks: `vendor/bin/phpunit` and `composer analyse`, run inside `packages/core`, `packages/docx` and `packages/laravel`.

## Review Focus

1. **A `PageBreak` inside a list item or a box inside a flow.** It must end the page, not just the column. The forced kind has to survive every layouter that wraps a child token (Task 3 test `page_break_inside_a_list_item_in_a_flow_ends_the_page`).
2. **A flow resumed on a later page.** It must start again at column 1 under the header, with no content lost or repeated between columns (Task 4 test `content_continues_on_the_next_page_in_column_one`).
3. **A flow inside a box with a fixed height, or inside `continueInPlace`.** It is laid out unbounded and must balance from the total height, not loop or drop content (Task 5 test `unbounded_flow_balances_from_its_total_height`).
4. **A markup flow that the caller puts in a table cell.** It is one column with a note in PDF, HTML and Word, never an exception (Tasks 4, 6 and 9 tests named `markup_flow_in_a_cell_*`).
5. **Several flows on one page, then a new Page template.** Word must get the right sequence of continuous and next-page sections, with a trailing continuous section after the last flow of each template so Word levels it (Task 9 test `flow_before_a_new_template_gets_a_levelling_section`).

---

### Task 1: The ColumnFlow API

**Files:**
- Create: `packages/core/src/Value/ColumnSpec.php`, `packages/core/src/Value/ColumnRule.php`, `packages/core/src/ColumnFlow.php`, `packages/core/src/ColumnBreak.php`
- Modify: `packages/core/src/View.php` (setters, accessor, `debugName`), `packages/core/src/Layout/LayoutEngine.php` (register `ColumnBreak` with the `LeafLayouter`), `packages/core/src/Tree/TreeWalker.php` (only if it lists leaves explicitly)
- Test: `packages/core/tests/Node/ColumnFlowTest.php`

**Interfaces:**
- Produces:
  - `final readonly class ColumnSpec { int $count; ?list<float> $shares; list<float> $gaps /* pt, count-1 entries */; ?ColumnRule $rule; bool $fromMarkup }`
  - `ColumnSpec::equal(int $count): self`, which uses the 36pt gap.
  - `ColumnSpec::columns(float $width): list<array{x: float, width: float}>`: column boxes across a flow `$width` wide. Equal columns split `width - sum(gaps)` evenly. With shares, that space splits in the ratio of the shares.
  - `ColumnSpec::withFromMarkup(): self`
  - `final readonly class ColumnRule { float $width /* pt */; Color $color }`
  - `ColumnFlow::make(Node|Component|string ...$children): View`, a `View` with `ColumnSpec::equal(2)`.
  - On `View`:
    - `columns(int $count): self` (count ≥ 1, else `InvalidValue`)
    - `columnWidths(float|int ...$shares): self` (each share > 0, at least 1; it sets the count)
    - `columnGap(Length ...$gaps): self` (one gap for all, or exactly count-1 gaps, else `InvalidValue`)
    - `columnRule(?Length $width = null, ?Color $color = null): self` (defaults 0.5pt and black)
    - `columnSpec(): ?ColumnSpec`
  - Calling any `column*` setter on a plain `View` makes it a flow.
  - `View::debugName()` returns `'column-flow'` when a spec is set.
  - `ColumnBreak::make(): self`, a `Styled` leaf like `PageBreak`, with debugName `'column-break'`.

- [ ] **Step 1: Write the failing tests** in `ColumnFlowTest`:
  - `make_gives_two_equal_columns_with_a_36pt_gap`: `ColumnFlow::make('a')->columnSpec()` has count 2, gaps `[36.0]`, a null rule, and `columns(200.0)` equal to `[[x 0, w 82], [x 118, w 82]]`.
  - `shares_split_the_space_left_after_gaps`: `->columnWidths(2, 1)->columnGap(pt(20))` gives `columns(200.0)` = `[[0, 120], [140, 60]]`.
  - `one_gap_per_space_between_columns_is_accepted`, plus `wrong_gap_count_is_rejected` (3 columns with 2 gaps is OK, with 4 gaps it's `InvalidValue`).
  - `zero_columns_and_non_positive_shares_are_rejected`.
  - `rule_defaults_to_half_a_point_black`.
  - `debug_name_is_column_flow_and_break_is_column_break`.
  - `setters_keep_children_and_other_style`: `ColumnFlow::make('a')->margin(pt(4))->columns(3)` keeps the child and the margin.
- [ ] **Step 2: Run** `cd packages/core && vendor/bin/phpunit --filter ColumnFlowTest`. Expected: FAIL, class `Papercut\ColumnFlow` not found.
- [ ] **Step 3: Implement** the files above. `View` keeps the spec in a private `?ColumnSpec $columns` and clones on each setter, like `htmlTag`. `columnGap` before `columns` has to work too: store the gaps as given, and validate the count when the spec is read with both known. Builder's choice: throw at the setter if the counts are known to mismatch.
- [ ] **Step 4: Run** the same filter, then the full core suite. Expected: PASS, with no existing test changed.
- [ ] **Step 5: Commit** `feat(core): ColumnFlow and ColumnBreak nodes`.

### Task 2: Placement rules, shared by all outputs

**Files:**
- Create: `packages/core/src/Tree/ColumnFlowPlacement.php` (enum), `packages/core/src/Tree/ColumnFlows.php` (@internal)
- Modify:
  - `packages/core/src/Tree/Visit.php` and `packages/core/src/Tree/TreeWalker.php`: expose ancestors.
  - `packages/core/src/Layout/LayoutContext.php`: an ancestor stack and the current region.
  - `packages/core/src/Layout/LayoutEngine.php`: push/pop the ancestor around `layout()`.
  - `packages/core/src/Layout/Paginator.php`: set the region for each call.
- Test: `packages/core/tests/Tree/ColumnFlowsTest.php`

**Interfaces:**
- Produces:
  - `enum ColumnFlowPlacement { case OnPage; case InBox; case InCell; case InRow; case InFlow; case OutsideBody; }`
  - `ColumnFlowPlacement::isBlocked(): bool`, true for InCell, InRow, InFlow and OutsideBody.
  - `ColumnFlows::isFlow(Node $n): bool`, true for a View with a `columnSpec()`.
  - `ColumnFlows::isPlainWrapper(Node $n, ComputedStyle $s): bool`. True only for a non-flow, column-direction `View` with all of these at their defaults: border width, background, padding, horizontal margin, width, min/max width, height, min/max height and `keepTogether`. Its alignItems must be Stretch, and its alignSelf null or Stretch.
  - `ColumnFlows::placement(list<array{node: Node, style: ComputedStyle}> $ancestors, Region $region): ColumnFlowPlacement`. Ancestors run from the region root to the direct parent. The region root itself is not included, nor the body `View` the paginator builds.
    - A region other than `Region::Body` gives OutsideBody.
    - Otherwise the blockers come first: any `TableCell` ancestor gives InCell, any row-direction View gives InRow, any flow ancestor gives InFlow.
    - Otherwise any ancestor that isn't a plain wrapper gives InBox. Otherwise OnPage.
  - `ColumnFlows::columnBreakAllowed(list<array{node: Node, style: ComputedStyle}> $ancestors): bool`. True when a flow ancestor exists and no `TableCell` or row View lies between the nearest flow and the break.
  - `ColumnFlows::misplaced(ColumnFlowPlacement $p, string $path): InvalidStructure`. The message names the path and the place, for example: `A ColumnFlow can't go inside a table cell (at "page > body > table > row[0] > cell[1] > column-flow"). Columns work on the page or inside a box.`
  - `ColumnFlows::strayBreak(string $path): InvalidStructure`, with the message `A ColumnBreak only works inside a ColumnFlow (at "…").`
  - `Visit::ancestors(): list<array{node: Node, style: ComputedStyle}>`
  - `LayoutContext::ancestors(): list<array{node: Node, style: ComputedStyle}>`
  - `LayoutContext::$region`, a `Region` set by the paginator: `Region::Body` for body calls. Other calls (background, template, header and footer variants, overlay places) get the matching region, or `Region::Background` when none matches.

- [ ] **Step 1: Write failing tests**:
  - `ColumnFlowsTest::placement_*`, one case per enum value, built from hand-made ancestor lists. Include a plain wrapper with only a vertical margin (OnPage), a wrapper with padding (InBox), a Card decorator expanded to its View (InBox), and a cell above a box (InCell, the blocker wins).
  - `plain_wrapper_rejects_each_listed_property`, data-provided, one style per property.
  - `column_break_allowed_only_under_a_flow_without_a_cell_or_row_between`.
  - `visit_ancestors_run_from_region_root_to_parent`, via a recording `TreeVisitor` over a small document.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ColumnFlowsTest`. Expected: FAIL, classes not found.
- [ ] **Step 3: Implement.** The `LayoutEngine::layout()` push happens after `enter()` resolves the style and is popped in its `finally`. Components are expanded first, so a decorator shows up as its View. `intrinsic()` doesn't push.
- [ ] **Step 4: Run** the filter, then the full core suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(core): placement rules for column flows`.

### Task 3: Forced break kinds and ColumnBreak in block layout

**Files:**
- Create: `packages/core/src/Layout/ForcedBreak.php` (enum `Page`, `Column`)
- Modify:
  - `packages/core/src/Layout/BreakToken.php`: add `public ?ForcedBreak $forced = null`, with a default so existing constructions don't change.
  - `packages/core/src/Layout/BlockLayouter.php`
  - `packages/core/src/Layout/ListLayouter.php`, `packages/core/src/Layout/MarkedItemLayouter.php`, `packages/core/src/Layout/LinkLayouter.php`: propagate `forced` wherever they wrap a child token.
- Test: `packages/core/tests/Layout/ForcedBreakTest.php`

**Interfaces:**
- Consumes: `ColumnBreak` (Task 1), `ColumnFlows::columnBreakAllowed`, `ColumnFlows::strayBreak`, `LayoutContext::ancestors()` (Task 2).
- Produces:
  - Every `BreakToken` that `BlockLayouter` makes because of a `PageBreak`, a `breakBefore` or a `breakAfter` carries `forced: ForcedBreak::Page`. One made because of a `ColumnBreak` carries `ForcedBreak::Column`.
  - When a block wraps a child's partial token, it copies the child's `forced`.
  - Tokens made because space ran out, or by keep-with-next, have `forced` null.

- [ ] **Step 1: Write failing tests** (lay out a `View` with `BlockLayouter` through a `LayoutEngine`, as `BlockLayouterTest` does):
  - `page_break_token_is_forced_page`, `break_before_and_after_tokens_are_forced_page`.
  - `space_break_token_is_not_forced`.
  - `nested_box_passes_the_forced_kind_up`: a PageBreak two Views deep gives an outer token with `forced` Page.
  - `page_break_inside_a_list_item_in_a_flow_ends_the_page`: the kind survives `ListLayouter`/`MarkedItemLayouter`.
  - `column_break_outside_a_flow_throws_invalid_structure`, with the message from `strayBreak` and the path `page > body > column-break[1]`.
  - `column_break_inside_a_table_cell_inside_a_flow_throws`.
  - `column_break_is_skipped_as_a_last_child_and_inside_a_fixed_height_box`, the same rules `PageBreak` follows today.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ForcedBreakTest`. Expected: FAIL, `ForcedBreak` not found.
- [ ] **Step 3: Implement.** In `BlockLayouter`, `ColumnBreak` takes the `PageBreak` branch, with the kind and the placement check added. `forcesBreakAfter` and `endsWithKeepWithNext` treat a trailing `ColumnBreak` like a trailing `PageBreak`. `BlockLayouter::intrinsic` skips it.
- [ ] **Step 4: Run** the filter and the full core suite. Expected: PASS, with goldens unchanged.
- [ ] **Step 5: Commit** `feat(layout): forced break kinds and column breaks`.

### Task 4: ColumnFlowLayouter, flowing across columns and pages

**Files:**
- Create: `packages/core/src/Layout/ColumnFlowLayouter.php`
- Modify: `packages/core/src/Layout/LayoutEngine.php` (`layouterFor`: a flow View goes to the new layouter, before the row/column check)
- Test: `packages/core/tests/Layout/ColumnFlowLayoutTest.php`

**Interfaces:**
- Consumes: `ColumnSpec::columns()`, `ColumnFlows::placement()` / `misplaced()`, `ForcedBreak`, `LayoutContext::ancestors()` / `$region`, `LayoutContext::approximated()`.
- Produces:
  - `ColumnFlowLayouter implements Layouter`. Its `layout()` behaves as below.
  - Its `intrinsic()` returns, for min and max: count × the widest child size (via `BlockLayouter::intrinsic` on the same node), plus the sum of the gaps.

**Behaviour to implement:**
1. **Placement.** Check placement from `ctx->ancestors()` and `ctx->region`.
   - If it's blocked and the spec is from markup, call `ctx->approximated('columns in <place>')` (a feature string per placement, for example `columns in a table cell`) and return `BlockLayouter::layout` on the same node unchanged.
   - If it's blocked and the spec isn't from markup, throw `ColumnFlows::misplaced(...)`.
2. **No box on the flow.** If the flow's own style has a border width, background or padding, throw `InvalidStructure`: `A ColumnFlow can't have a border, background or padding (at "…"). Put it inside a View with that style.`
3. **Columns on a page.** For each column box, lay out the node's children with `$engine->blockLayouter()->layout($node, $style, new Constraints(colWidth, $c->height, $c->fresh), $token)`, carrying the token from column to column.
   - If column 1 gives DoesNotFit, return DoesNotFit.
   - A later column that gives DoesNotFit ends the page: return Partial with the previous token.
   - A token with `forced === Page` ends the page: return Partial.
   - Otherwise move on to the next column. After the last column, return Partial with the token.
   - Complete content means the flow is done (Task 5 balances it).
4. **Fragment.** Width `$c->width`, height = the tallest column. Column fragments go at their `x`, at `y` 0.
   - With a rule, add one `Paint\Line` per gap, at the gap's centre, from 0 to the fragment height, with the rule's width and colour.
   - The returned token is the content token itself. Each page restarts at column 1, so no column index is stored.

- [ ] **Step 1: Write failing tests** with the `PaginatorTest` harness. Use a 220×100 page with 10pt margins, `headerGap`/`footerGap` 0, `columnGap(pt(20))` (columns 90 wide at x 10 and 120), and 14pt lines from `FakeBackend`. Assert TextRun texts and x positions per page:
  - `fills_column_one_then_column_two`: lines L1–L8 give L1–L5 at x 10 and L6–L8 at x 120.
  - `content_continues_on_the_next_page_in_column_one`: L1–L14 give page 1 as L1–L10 and page 2 as L11–L14 starting at x 10, nothing lost or repeated.
  - `column_break_moves_to_the_next_column` and `column_break_in_the_last_column_starts_a_new_page`.
  - `page_break_in_a_flow_skips_the_remaining_columns`.
  - `keep_with_next_heading_moves_to_the_next_column_with_its_paragraph`.
  - `full_width_text_after_a_flow_starts_below_its_tallest_column`.
  - `two_flows_on_one_page_with_text_between`.
  - `rule_draws_one_line_per_gap_at_the_gap_centre`: x 110, from the flow top to its bottom.
  - `overflow_path_names_the_flow`: an image taller than the body in a flow gives a `LayoutOverflow` path of `page > body > column-flow > image[0]`.
  - `flow_inside_a_padded_box_gets_the_box_content_width`.
  - `flow_in_a_table_cell_throws_naming_the_cell`, `flow_in_a_row_throws`, `flow_in_a_flow_throws`, `flow_in_a_header_throws`.
  - `markup_flow_in_a_cell_lays_out_one_column_with_a_note`: a spec with `withFromMarkup()` records an approximated note and doesn't throw.
  - `flow_with_its_own_border_throws`.
  - `headings_in_a_flow_keep_their_bookmarks_and_links_work`: a heading in column 2 gets a Bookmark op, and a Link inside a flow gets a link annotation at the column's x.
  - Balancing is Task 5. Here, write the tests so the last page's content fills column 1 first, and accept that.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ColumnFlowLayoutTest`. Expected: FAIL.
- [ ] **Step 3: Implement** `ColumnFlowLayouter` as above.
- [ ] **Step 4: Run** the filter and the full core suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(layout): ColumnFlow lays out across columns and pages`.

### Task 5: Ending level

**Files:**
- Modify: `packages/core/src/Layout/ColumnFlowLayouter.php`
- Test: `packages/core/tests/Layout/ColumnFlowBalanceTest.php`

**Interfaces:**
- Consumes: Task 4's per-page column loop. Factor it into a private `columnsAt(float $height, …): array{status, columns: list<Fragment>, token}` so it can be run at a trial height.

**Behaviour:** Balance only when the content completes on this page. Pages that end Partial (filled up, or cut short by a forced page break or a column break in the last column) aren't balanced.
- **Bounded height:** search for the smallest height `h` in `[used / count, used]` at which `columnsAt(h)` still completes in `count` columns. `used` is the tallest column at full height.
- **Unbounded `$c->height`:** lay out once unbounded to get the total height `T`, then search `[T / count, T]` the same way.
- **The search:** stop when the bounds are within 0.5pt, or after 24 iterations. Keep the fragments from the best complete trial. Don't re-lay out afterwards.
- **Column breaks:** content with a `ColumnBreak` is balanced too. The break still forces the next column, and the search only levels what the breaks allow.

- [ ] **Step 1: Write failing tests** (same harness):
  - `last_page_columns_end_level`: L1–L6 in 2 columns gives 3 and 3.
  - `odd_count_puts_the_extra_line_in_column_one`: L1–L7 gives 4 and 3.
  - `full_pages_are_not_balanced`: L1–L13 gives page 1 at 5 and 5, page 2 at 2 and 1.
  - `forced_page_break_page_is_not_balanced`.
  - `keep_together_block_limits_balance_but_nothing_is_lost`.
  - `unbounded_flow_balances_from_its_total_height`: a flow inside a `height(pt(70))` box is laid out unbounded, and L1–L6 still split 3 and 3.
  - `three_columns_balance`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter ColumnFlowBalanceTest`. Expected: FAIL (columns unbalanced).
- [ ] **Step 3: Implement** the search.
- [ ] **Step 4: Run** the filter, `ColumnFlowLayoutTest` (update any Task 4 expectations that assumed column 1 fills first on a last page, and note each one in the ledger), and the full core suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(layout): ColumnFlow columns end level`.

### Task 6: HTML export

**Files:**
- Modify: `packages/core/src/Html/HtmlWriter.php` (the `view()` method and a ColumnBreak case), `packages/core/src/Html/Css.php` (a `columns(ColumnSpec $spec, float $width)` helper), `packages/core/src/Html/Stylesheet.php`
- Test: `packages/core/tests/Html/HtmlColumnFlowTest.php`

**Interfaces:**
- Consumes: `ColumnFlows::placement` with `Visit::ancestors()` / `region()`, plus `ColumnSpec`.
- Produces:
  - A flow that is OnPage or InBox gets `column-count`, `column-gap`, `column-fill: balance` and, with a rule, `column-rule: <w>pt solid <color>`.
  - Unequal shares can't be expressed in CSS columns. They get equal columns and an `approximated('unequal column widths')` note.
  - `ColumnBreak` writes `<div class="pc-column-break"></div>`, styled `.papercut .pc-column-break{break-before:column}`.
  - A blocked placement follows the layout rules: `misplaced` from the builder; from markup, no column CSS and an `approximated` note.
  - `ColumnBreak` outside a flow throws `strayBreak`.

- [ ] **Step 1: Write failing tests**:
  - `flow_writes_column_css`, `rule_writes_column_rule`, `column_break_writes_break_before_column`.
  - `unequal_widths_are_approximated`.
  - `markup_flow_in_a_cell_has_no_column_css_and_a_note`.
  - `builder_flow_in_a_cell_throws`.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter HtmlColumnFlowTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the filter and the full core suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(html): ColumnFlow as CSS columns`.

### Task 7: Markup input

**Files:**
- Modify: `packages/core/src/Markup/InlineCss.php`, `packages/core/src/Markup/HtmlConverter.php`
- Test: `packages/core/tests/Markup/MarkupColumnsTest.php`

**Interfaces:**
- Consumes: `ColumnSpec`, `View::columns()/columnGap()/columnRule()`, `ColumnSpec::withFromMarkup()`.
- Produces:
  - `InlineCss::parse` returns a new `columns` key: `?array{count: ?int, gap: ?Length, rule: ?array{width: Length, color: Color}}`. It reads:
    - `column-count: <int ≥ 1>`
    - `columns: <int>`, alone or with a width. The width part is dropped, with a note naming the declaration.
    - `column-gap: <length> | normal` (`normal` means the default gap)
    - `column-rule: <width> <style> <color>`, in any order. Any style other than `solid` is approximated as solid, with a note. `none` means no rule.
  - These are dropped with a note: `column-width`, `column-span`, `column-fill`, `column-rule-*` longhands.
  - `HtmlConverter`: a `div`, or any block in `UNKNOWN_BLOCKS`, whose CSS has a column count wraps the nodes it produced in a `View` with that spec plus `withFromMarkup()`. Its box styles (border, background, padding, sizes) go on a plain outer `View` around the flow, never on the flow.
  - Column CSS on `p`, headings, lists, tables or cells is dropped with a note (my call).
  - In `StyleMode::Theme`, the `style` attribute is never read, so no flow is made.

- [ ] **Step 1: Write failing tests**:
  - `div_with_column_count_becomes_a_markup_flow` (`StyleMode::Original`), asserting the count, `fromMarkup` true, and the children.
  - `columns_shorthand_with_width_keeps_count_and_notes_the_width`.
  - `column_gap_and_rule_are_read`, `dashed_rule_is_approximated_as_solid`.
  - `column_span_is_dropped_with_a_note`.
  - `theme_mode_ignores_column_css`.
  - `border_on_a_columns_div_goes_on_a_view_around_the_flow`.
  - `column_count_on_a_paragraph_is_dropped`.
  - `markup_flow_renders_in_two_columns`: end to end through `toPdf`, with two x positions for TextRuns.
- [ ] **Step 2: Run** `vendor/bin/phpunit --filter MarkupColumnsTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the filter and the full core suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(markup): column CSS becomes a ColumnFlow`.

### Task 8: Blade components

**Files:**
- Create: `packages/laravel/src/Components/ColumnFlow.php`, `packages/laravel/src/Components/ColumnBreak.php`
- Test: `packages/laravel/tests/Feature/ColumnFlowComponentTest.php`

**Interfaces:**
- Consumes: `ColumnFlow::make`, the `View::column*` setters, `ColumnBreak::make` (Task 1).
- Produces:
  - `<x-doc::column-flow>` with these own attributes, and every other attribute as a style through `styled()`, like `Column`:
    - `columns` (int)
    - `widths` (space-separated shares, for example `"2 1"`)
    - `gap`: a length, or space-separated lengths, one per space between columns. **Ruling:** this is the column gap, not flex `gap`, because a flow has no flex gap.
    - `rule`: boolean, a length, or `"<length> <color>"`
  - `<x-doc::column-break />` with no attributes. Any attribute is `UnknownAttribute`, as in `PageBreak`.

- [ ] **Step 1: Write failing tests**:
  - `column_flow_tag_builds_a_flow`, asserting the spec from `columns="3" gap="12pt" rule`.
  - `widths_attribute_sets_shares`.
  - `rule_with_length_and_colour`.
  - `column_break_tag_rejects_attributes`.
  - `blade_flow_renders_two_columns`, end to end.
- [ ] **Step 2: Run** `cd packages/laravel && vendor/bin/phpunit --filter ColumnFlowComponentTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the filter and the full laravel suite. Expected: PASS.
- [ ] **Step 5: Commit** `feat(laravel): column-flow and column-break components`.

### Task 9: Word export

**Files:**
- Create: `packages/docx/src/Map/ColumnsMark.php` (a Papercut-owned php-docx `BlockElement` that marks where a column section starts or ends)
- Modify: `packages/docx/src/Walk/DocxVisitor.php`, `packages/docx/src/Map/Flow.php` (pass marks through unchanged), `packages/docx/src/DocxExporter.php` (`sections()` splits at marks), `packages/docx/src/Package/PatchSpec.php`, `packages/docx/src/Package/DocxPatcher.php`
- Test: `packages/docx/tests/ColumnFlowDocxTest.php`, plus patcher unit tests in `packages/docx/tests/Package/DocxPatcherColumnsTest.php`

**Interfaces:**
- Consumes: `ColumnFlows::placement` with `Visit::ancestors()` / `region()`, `ColumnSpec::columns()`.
- Produces:
  - `final readonly class ColumnsMark implements BlockElement { ?string $cols /* the w:cols XML for the section that starts here; null ends the column section */ }`
  - `DocxPatcher::colsXml(ColumnSpec $spec, float $widthPt): string`:
    - Equal columns: `<w:cols w:num="N" w:space="TW" [w:sep="1"]/>`
    - Shares: `<w:cols w:num="N" w:equalWidth="0" [w:sep="1"]>` + `<w:col w:w="TW" w:space="TW"/>` per column (the last has no `w:space`) + `</w:cols>`
    - Twips are `round(pt * 20)`.
  - `PatchSpec::$columns`, `array<int, string>`: section index → `w:cols` XML. The patcher inserts it right after that section's `<w:pgMar …/>` in `word/document.xml`, counting `<w:sectPr>` in document order. It throws `php-docx output changed: section N properties not found` if one is missing.
  - `DocxPatcher::COLUMN_BREAK`, a sentinel text. The visitor writes a `ColumnBreak` as a paragraph whose single run holds only that text. The patcher replaces that run with `<w:r><w:br w:type="column"/></w:r>`, and throws if the sentinel's run markup isn't as expected.

**Behaviour:**
- **The visitor:**
  - For an OnPage flow in the body, its blocks become `[ColumnsMark(cols), …blocks, ColumnsMark(null)]`.
  - An InBox flow gives its blocks with no marks, plus `approximated('columns inside a box', path)`.
  - A blocked placement follows Task 4's rules: builder throws, markup gets a note.
  - A `ColumnBreak` inside an OnPage flow becomes the sentinel paragraph. Inside an InBox flow it's dropped with an `approximated` note.
- **`sections()`:** split each group's body at marks.
  - The first segment of a group keeps the group's start (next page). Each later segment is `SectionStart::Continuous`, with the same header, footer and page setup.
  - The section a start mark opens gets `PatchSpec::$columns[index]`.
  - After an end mark comes a one-column continuous section. If nothing follows in the group, it's a hairline-only continuous section, so Word levels the columns before the next template or the end.
  - Every segment ends in a paragraph, using the existing `Flow::hairline()` rule. `withoutLeadingBreak` applies only to a group's first segment.

- [ ] **Step 1: Write failing tests**:
  - `DocxPatcherColumnsTest`: `cols_xml_for_equal_columns`, `cols_xml_for_shares_and_rule`, `inserts_cols_after_pgmar_of_the_right_section`, `throws_when_sectpr_is_missing`, `column_break_sentinel_becomes_a_column_break`.
  - `ColumnFlowDocxTest` (unzip `word/document.xml` as the existing docx tests do):
    - `on_page_flow_is_a_continuous_section_with_cols`
    - `title_then_flow_then_note_gives_three_sections_on_one_page`: next page, then continuous with cols, then continuous.
    - `flow_before_a_new_template_gets_a_levelling_section`
    - `flow_in_a_card_is_one_column_with_a_note`
    - `markup_flow_in_a_cell_is_one_column_with_a_note`
    - `builder_flow_in_a_cell_throws`
    - `column_break_becomes_w_br_column`
    - `documents_without_flows_are_byte_identical`: the existing docx goldens and `DeterminismTest` stay green.
- [ ] **Step 2: Run** `cd packages/docx && vendor/bin/phpunit --filter 'ColumnFlowDocxTest|DocxPatcherColumnsTest'`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the filter and the full docx suite. Expected: PASS. Open one generated file in a docx validator if one is available locally. Otherwise check the XML order against ECMA-376: in `w:sectPr`, `w:cols` comes after `w:pgMar` and before `w:titlePg`.
- [ ] **Step 5: Commit** `feat(docx): ColumnFlow as Word section columns`.

### Task 10: The academic paper example

**Files:**
- Create: `examples/core/documents/paper.php`, `examples/core/paper.php` (runner like `article.php`), `examples/laravel/views/paper.blade.php`, the generated `examples/core/paper.pdf`, `examples/html/paper.html`, `examples/docx/paper.docx`, `examples/laravel/paper.pdf`, `examples/laravel/paper.html`
- Modify:
  - `examples/html/render.php`, `examples/docx/render.php`, `examples/laravel/render.php`, `examples/laravel/data.php`: add `paper`.
  - `packages/core/tests/Feature/ExamplesTest.php`, `packages/laravel/tests/Feature/ExamplesTest.php`: add `paper` with its page count.
  - `site/src/examples.md`: a row, a thumbnail block, the "seven example documents" count becomes eight, and the Letter/A4 sentence names the paper as A4.
- Follow the commit that added the article example (`git show 0ad6889 --stat`) for every place an example is listed, including thumbnails.

**Interfaces:**
- Consumes: everything from Tasks 1–9.

**Content (generic, invented):**
- A4 with millimetre margins.
- A full-width title, three invented authors with invented affiliations, and an abstract.
- Then a two-column `ColumnFlow`:
  - Numbered section headings, which the theme keeps with their next paragraph.
  - Enough body text that at least one heading reaches the bottom of column 1 and moves to column 2 with its paragraph.
  - A small results table and a figure, each fitting one column (an SVG chart from `examples/assets`, or an image already there).
  - Columns ending level.
- Then a full-width closing note (for example, data availability).
- The Blade view builds the same document.

- [ ] **Step 1: Write the failing tests**:
  - `ExamplesTest` gets a `paper` case with `[2, 2]` pages.
  - `test_paper_has_two_column_body_and_full_width_abstract`: the PDF's TextRuns on page 1 include the abstract at the body's left x and body text at two distinct x values.
  - The Laravel `ExamplesTest` gets the matching case.
- [ ] **Step 2: Run** both ExamplesTests. Expected: FAIL, file missing.
- [ ] **Step 3: Write the example and the Blade view**, then run the four render scripts to produce the outputs. Look at the PDF pages as images (`pdftoppm`) to check that the columns, the levelling and the full-width parts look right. Open the docx XML to confirm its three sections.
- [ ] **Step 4: Run** both ExamplesTests and the full suites of all three packages. Expected: PASS. Report any golden changes.
- [ ] **Step 5: Commit** `feat(examples): academic paper with ColumnFlow`.

### Task 11: Docs, gallery, translator, changelog

**Files:**
- Modify:
  - `site/src/guide/layout.md`: a "Column flow" section covering the builder, Blade, settings, placement, ending level, column breaks and Word behaviour. Say whether a `Toc` works inside a flow, as tested.
  - `site/src/guide/rich-text.md`: the column CSS markup reads, in Original mode only.
  - `site/src/guide/word-export.md`: section columns, and one column inside boxes.
  - `site/src/guide/html-export.md`: CSS columns, and unequal widths approximated.
  - `site/src/guide/README.md` and the root `README.md`: remove "multi-column text flow" from Current limitations.
  - `CHANGELOG.md`, under `## [Unreleased]`.
  - `site/gallery/gallery.json`: a `column-flow` tile in the Layout group, with variants `rule` and `widths`.
  - `site/gallery/sources/column-flow.php`, `column-flow.rule.php`, `column-flow.widths.php`, and the rendered PNGs.
  - `site/src/.vuepress/components/tailwind-translate.js` (+ `tailwind-samples.js` if samples are listed): map `columns-N` to `<x-doc::column-flow columns="N">`, `gap-x-*` on that element to `gap`, and `divide-x` to `rule`.
- Follow `git show d7003b9 --stat` for how gallery tiles and their renders are added.

- [ ] **Step 1: Write the failing test.** The gallery test that checks tiles against sources (find it in `site/gallery` tests, or the core test that loads `gallery.json`) fails for the new tile until its source and render exist. If the translator has tests, add `columns_2_becomes_column_flow`.
- [ ] **Step 2: Run** them. Expected: FAIL.
- [ ] **Step 3: Write** the docs, the gallery sources and renders, the translator mapping and the changelog. Build the site (`cd site && npm run build`) and check that the new section and tile render.
- [ ] **Step 4: Run** the tests and the site build. Expected: PASS, with no broken links.
- [ ] **Step 5: Commit** `docs: ColumnFlow guide, gallery tile and changelog`.

### Task 12: Upstream php-docx PR

**Files:** in `~/work/olc/php-docx` (remote `personal` = serpentblade/php-docx), on a branch `feat/section-columns` off `origin/main`.

**Interfaces:**
- Produces, in php-docx's own conventions:
  - A `Columns` style value (count, space, separator, optional per-column widths and spaces).
  - `Section::$columns`, written as `w:cols` in `renderSectPr` after `w:pgMar`, and read back by `SectionReader`.
  - A `ColumnBreak` element, written as `<w:p><w:r><w:br w:type="column"/></w:r></w:p>` and read back by `BodyReader`.

- [ ] **Step 1: Write failing tests** next to the existing Section and PageBreak tests: writer output, a round trip through the reader, and equal/unequal/separator cases.
- [ ] **Step 2: Run** php-docx's suite. Expected: the new tests FAIL.
- [ ] **Step 3: Implement**, as one commit for section columns and one for the column break.
- [ ] **Step 4: Run** php-docx's full suite and its static analysis. Expected: PASS.
- [ ] **Step 5: Push** the branch to `personal`. **Ask the user before opening the PR.** It's a public action. Once they approve, open it with maintainer edits allowed. The body ends with the "🤖 Generated with Claude Code" line and has no session link.
- Swapping Papercut from `DocxPatcher` to the real API waits for php-docx to release this. It's a follow-up, not part of this plan.
