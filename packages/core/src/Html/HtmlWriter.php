<?php

declare(strict_types=1);

namespace Papercut\Html;

use Papercut\{Document, Image, Link, Node, Page, PageBreak, PageNumber, Spacer, Table, TableCell, TableRow, Text, View};
use Papercut\Exception\InvalidValue;
use Papercut\Export\{Fidelity, HtmlOptions};
use Papercut\Style\ComputedStyle;
use Papercut\Value\{Auto, Fr, Length, Percent};
use Papercut\Tree\{Region, TreeVisitor, Visit};

/** Turns a tree walk into HTML. One method per node type, so new node types slot in without touching the walk. */
final class HtmlWriter implements TreeVisitor
{
    private string $out = '';
    private readonly int $pageCount;
    private ?Page $page = null;

    /** @var list<ComputedStyle> */
    private array $styles = [];
    /** @var list<string> closing markup, one per open node */
    private array $closers = [];
    /** @var list<?string> the open thead/tbody of each open table */
    private array $sections = [];
    /** @var \WeakMap<TableCell, true> cells laid out as plain columns because no row holds them */
    private \WeakMap $strays;
    /** @var array<string,true> */
    private array $families = [];

    public function __construct(
        Document $doc,
        private readonly Fidelity $fidelity,
        private readonly HtmlOptions $options,
    ) {
        $this->strays = new \WeakMap();
        $this->pageCount = count($doc->pages());
    }

    public static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /** @return list<string> font families of every style written, in first-seen order */
    public function usedFamilies(): array
    {
        return array_keys($this->families);
    }

    public function html(): string
    {
        return $this->out;
    }

    public function enterPage(Page $page, int $index): void
    {
        $this->page = $page;
        $path = $this->pageCount > 1 ? sprintf('page[%d]', $index) : 'page';
        $this->fidelity->approximated('page box', $path);
        if ($page->headerNodes() !== [] || $page->footerNodes() !== []) {
            $this->fidelity->approximated('header and footer once per page', $path);
        }
        if ($page->backgroundNodes() !== []) {
            $this->fidelity->approximated('background layer', $path);
        }
        $m = $page->margins();
        $this->out .= '<section class="pc-page" style="' . self::attr([
            'max-width' => Css::pt($page->width()),
            'min-height' => Css::pt($page->height()),
            'padding' => Css::pt($m->top->points) . ' ' . Css::pt($m->right->points) . ' ' . Css::pt($m->bottom->points) . ' ' . Css::pt($m->left->points),
        ]) . '">';
    }

    public function leavePage(Page $page, int $index): void
    {
        $this->out .= '</section>';
    }

    public function enterRegion(Region $region, ComputedStyle $root): void
    {
        $this->styles = [$root];
        $this->families[$root->fontFamily] = true;
        $style = match ($region) {
            Region::Header => ' style="margin-bottom:' . Css::pt($this->page?->headerGapLength()->points ?? 0.0) . '"',
            Region::Footer => ' style="margin-top:' . Css::pt($this->page?->footerGapLength()->points ?? 0.0) . '"',
            default => '',
        };
        $this->out .= '<div class="pc-' . $region->value . '"' . $style . '>';
    }

    public function leaveRegion(Region $region): void
    {
        $this->out .= '</div>';
        $this->styles = [];
    }

    public function enter(Node $node, ComputedStyle $style, Visit $at): bool
    {
        $parent = end($this->styles);
        \assert($parent instanceof ComputedStyle);
        $closer = match (true) {
            $node instanceof View => $this->view($style, $parent, $at),
            $node instanceof Text => $this->text($node, $style, $parent, $at),
            $node instanceof Link => $this->link($node, $style, $parent, $at),
            $node instanceof Spacer => $this->spacer($node, $style),
            $node instanceof PageBreak => $this->pageBreak(),
            $node instanceof PageNumber => '',
            $node instanceof Image => $this->image($node, $style, $at),
            $node instanceof Table => $this->table($node, $style, $at),
            $node instanceof TableRow => $this->tableRow($node, $style, $at),
            $node instanceof TableCell => $this->tableCell($node, $style, $at),
            default => throw new \LogicException('HTML export of ' . $node->debugName() . ' is not supported'),
        };
        $this->styles[] = $style;
        $this->closers[] = $closer;

        return true;
    }

    public function leave(Node $node, ComputedStyle $style, Visit $at): void
    {
        array_pop($this->styles);
        if ($node instanceof Table) {
            $open = array_pop($this->sections);
            $this->out .= $open === null ? '' : '</' . $open . '>';
        }
        $this->out .= (string) array_pop($this->closers);
    }

    /** @return array<string,string> */
    private function textCss(ComputedStyle $s, ?ComputedStyle $parent): array
    {
        $this->families[$s->fontFamily] = true;

        return Css::text($s, $parent, $this->options->fonts()->fallbacks);
    }

    private function view(ComputedStyle $s, ComputedStyle $parent, Visit $at): string
    {
        $this->checkDecoration($s, $parent, $at->path());
        $decls = [...Css::box($s), ...Css::flexContainer($s)];
        if ($this->parentIsFlex($at)) {
            $decls += Css::flexItem($s);
        }
        $decls = [...$decls, ...Css::pagination($s, $parent), ...$this->textCss($s, $parent)];
        $this->out .= '<div' . self::styleAttr($decls) . '>';

        return '</div>';
    }

    private function text(Text $t, ComputedStyle $s, ComputedStyle $parent, Visit $at): string
    {
        $override = self::overridesDecoration($s, $parent);
        $tag = in_array($t->preset(), ['h1', 'h2', 'h3', 'h4'], true) ? $t->preset() : 'p';
        $decls = [...Css::box($s), ...$this->textCss($s, $parent), ...($this->parentIsFlex($at) ? Css::flexItem($s) : []), ...Css::pagination($s, $parent)];
        $this->out .= '<' . $tag . self::styleAttr($decls) . '>';
        foreach ($t->spanList() as $span) {
            $override = $this->span($span, $s) || $override;
        }
        if ($override) {
            $this->fidelity->approximated('text decoration override', $at->path());
        }

        return '</' . $tag . '>';
    }

    /** @return bool whether the span turns off a decoration the paragraph has on */
    private function span(\Papercut\Span $span, ComputedStyle $text): bool
    {
        if ($span->lineBreak) {
            $this->out .= '<br>';
            return false;
        }
        $style = $text->child($span->style);
        $diff = $this->textCss($style, $text);
        $body = self::esc($span->text);
        if ($diff !== []) {
            $body = '<span' . self::styleAttr($diff) . '>' . $body . '</span>';
        }
        if ($span->href !== null) {
            $body = '<a href="' . self::esc(self::href($span->href)) . '">' . $body . '</a>';
        }
        $this->out .= $body;

        return self::overridesDecoration($style, $text);
    }

    private function link(Link $l, ComputedStyle $s, ComputedStyle $parent, Visit $at): string
    {
        $href = self::href($l->uri());
        $this->checkDecoration($s, $parent, $at->path());
        $decls = ['display' => 'flex', 'flex-direction' => 'column', ...Css::box($s), ...($this->parentIsFlex($at) ? Css::flexItem($s) : []), ...Css::pagination($s, $parent), ...$this->textCss($s, $parent)];
        $this->out .= '<a class="pc-link" href="' . self::esc($href) . '"' . self::styleAttr($decls) . '>';

        return '</a>';
    }

    private function spacer(Spacer $sp, ComputedStyle $s): string
    {
        $fixed = $sp->fixedSize();
        $flex = $fixed !== null ? '0 0 ' . Css::pt($fixed->points) : Css::num($s->grow) . ' 0 0';
        $this->out .= '<div' . self::styleAttr(['flex' => $flex]) . '></div>';

        return '';
    }

    private function pageBreak(): string
    {
        $this->out .= '<div class="pc-break"></div>';

        return '';
    }

    private function image(Image $n, ComputedStyle $s, Visit $at): string
    {
        $decls = Css::box($s);
        if ($this->parentIsFlex($at)) {
            $decls += Css::flexItem($s);
        }
        $decls['object-fit'] = $n->fitMode()->value;
        $this->out .= '<img src="' . self::esc(ImageSource::uri($n, $this->options->imageUrl(), fn () => $this->fidelity->warn(sprintf('[%s] imageUrl returned an unsafe URL; the image was embedded instead', $at->path())))) . '" alt=""' . self::styleAttr($decls) . '>';

        return '';
    }

    private function table(Table $n, ComputedStyle $s, Visit $at): string
    {
        $parent = end($this->styles);
        \assert($parent instanceof ComputedStyle);
        $tracks = self::tracks($n);
        $fixed = 0.0;
        $percent = 0.0;
        $frs = 0.0;
        foreach ($tracks as $t) {
            $fixed += $t instanceof Length ? $t->points : 0.0;
            $percent += $t instanceof Percent ? $t->value : 0.0;
            $frs += $t instanceof Fr ? $t->value : 0.0;
        }
        if ($frs > 0.0) {
            $this->fidelity->approximated('fr column widths', $at->path());
        }
        $hasAuto = in_array(true, array_map(static fn (object $t): bool => $t instanceof Auto, $tracks), true);
        if ($hasAuto) {
            $this->fidelity->approximated('auto column widths', $at->path());
        }

        $decls = [...Css::box($s), ...$this->textCss($s, $parent)];
        if ($this->parentIsFlex($at)) {
            $decls += Css::flexItem($s);
        }
        if ($hasAuto) {
            $decls['table-layout'] = 'auto';
        }
        $this->out .= '<table' . self::styleAttr($decls) . '><colgroup>';
        foreach ($tracks as $t) {
            $width = match (true) {
                $t instanceof Length => Css::pt($t->points),
                $t instanceof Percent => Css::num($t->value) . '%',
                $t instanceof Fr => $hasAuto ? null : self::frWidth($fixed, $percent, $t->value / $frs),
                default => null,
            };
            $this->out .= '<col' . self::styleAttr($width === null ? [] : ['width' => $width]) . '>';
        }
        $this->out .= '</colgroup>';
        $this->sections[] = null;

        return '</table>';
    }

    private static function frWidth(float $fixed, float $percent, float $share): string
    {
        $minus = ($fixed > 0.0 ? ' - ' . Css::pt($fixed) : '') . ($percent > 0.0 ? ' - ' . Css::num($percent) . '%' : '');

        return ($minus === '' ? 'calc(100%' : 'calc((100%' . $minus . ')') . ' * ' . Css::num($share) . ')';
    }

    /**
     * One track per column, as the PDF layout counts them: the widest row's total span; missing tracks are 1fr, extra ones ignored.
     *
     * @return list<Length|Percent|Fr|Auto>
     */
    private static function tracks(Table $table): array
    {
        $columns = 0;
        foreach ([...$table->headerRows(), ...$table->bodyRows()] as $row) {
            $columns = max($columns, array_sum(array_map(static fn (TableCell $c): int => $c->span(), $row->cells())));
        }
        $given = $table->trackList()->tracks ?? [];
        $tracks = [];
        for ($k = 0; $k < $columns; $k++) {
            $tracks[] = $given[$k] ?? Fr::of(1);
        }

        return $tracks;
    }

    private function tableRow(TableRow $n, ComputedStyle $s, Visit $at): string
    {
        if (!$at->parent() instanceof Table) {
            throw new \LogicException(sprintf('No layouter registered for %s at "%s".', TableRow::class, $at->path()));
        }
        $parent = end($this->styles);
        \assert($parent instanceof ComputedStyle);
        $want = $at->inHeader() ? 'thead' : 'tbody';
        $open = end($this->sections);
        if ($open !== $want) {
            $this->out .= ($open === null ? '' : '</' . $open . '>') . '<' . $want . '>';
            array_pop($this->sections);
            $this->sections[] = $want;
        }
        $this->out .= '<tr' . self::styleAttr([...Css::box($s), ...$this->textCss($s, $parent)]) . '>';

        return '</tr>';
    }

    private function tableCell(TableCell $n, ComputedStyle $s, Visit $at): string
    {
        $parent = end($this->styles);
        \assert($parent instanceof ComputedStyle);
        if (!$at->parent() instanceof TableRow) {
            return $this->strayCell($n, $s, $parent, $at);
        }
        $this->checkDecoration($s, $parent, $at->path());
        $tag = $n->header() ? 'th' : 'td';
        $span = $n->span() > 1 ? ' colspan="' . $n->span() . '"' : '';
        $this->out .= '<' . $tag . $span . self::styleAttr([...Css::box($s), ...$this->textCss($s, $parent)]) . '>';

        return '</' . $tag . '>';
    }

    /** A cell outside a table is laid out as a column; it becomes a div and warns like the PDF layout does. */
    private function strayCell(TableCell $n, ComputedStyle $s, ComputedStyle $parent, Visit $at): string
    {
        if (!isset($this->strays[$n])) {
            $this->strays[$n] = true;
            $this->fidelity->warn(sprintf('[%s] A table cell outside a table is laid out as a column', $at->path()));
        }
        $this->checkDecoration($s, $parent, $at->path());
        $decls = [...Css::box($s), ...($this->parentIsFlex($at) ? Css::flexItem($s) : []), ...Css::flexContainer($s), ...Css::pagination($s, $parent), ...$this->textCss($s, $parent)];
        $this->out .= '<div' . self::styleAttr($decls) . '>';

        return '</div>';
    }

    /** Flex items only make sense inside flex containers: Views, Links and region roots. */
    private function parentIsFlex(Visit $at): bool
    {
        $p = $at->parent();

        return $p === null || $p instanceof View || $p instanceof Link || ($p instanceof TableCell && isset($this->strays[$p]));
    }

    /** CSS cannot switch off a decoration an ancestor drew, so turning one off is only approximated. */
    private function checkDecoration(ComputedStyle $s, ComputedStyle $parent, string $path): void
    {
        if (self::overridesDecoration($s, $parent)) {
            $this->fidelity->approximated('text decoration override', $path);
        }
    }

    private static function overridesDecoration(ComputedStyle $s, ComputedStyle $parent): bool
    {
        return ($parent->underline && !$s->underline) || ($parent->strike && !$s->strike);
    }

    private static function href(string $uri): string
    {
        if (!\Papercut\Paint\Link::allowed($uri)) {
            throw InvalidValue::for('link', $uri)->because('only http, https, mailto, tel and #fragment links are allowed');
        }

        return ltrim($uri);
    }

    /** @param array<string,string> $decls */
    private static function attr(array $decls): string
    {
        return self::esc(Css::attr($decls));
    }

    /** @param array<string,string> $decls */
    private static function styleAttr(array $decls): string
    {
        return $decls === [] ? '' : ' style="' . self::attr($decls) . '"';
    }
}
