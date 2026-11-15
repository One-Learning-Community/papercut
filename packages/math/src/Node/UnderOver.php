<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Layout\Stretcher;
use Papercut\Math\OperatorDictionary;

/**
 * munder / mover / munderover in one class: a base with a part below, above, or both. Depending on the base and the
 * parts it lays out as limits of a large operator, as scripts (movable limits in inline style), as an accent, a bar,
 * a stretched arrow or brace, or as a plain stack.
 */
final class UnderOver extends Node
{
    private const BARS = ['¯', '‾', '_'];
    private const LIMIT_NAMES = ['lim', 'max', 'min', 'sup', 'inf', 'det', 'gcd', 'Pr', 'liminf', 'limsup'];

    public function __construct(
        public readonly Node $base,
        public readonly ?Node $under,
        public readonly ?Node $over,
        public readonly bool $accent = false,
        public readonly bool $accentUnder = false,
    ) {
        if ($under === null && $over === null) {
            throw new \InvalidArgumentException('UnderOver needs a part below or above');
        }
    }

    /**
     * @return list<Node>
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function children(): array
    {
        return array_values(array_filter([$this->base, $this->under, $this->over]));
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layout(Context $c): Box
    {
        if (self::isLimitName($this->base)) {
            return $c->display ? $this->limits($c) : (new Scripts($this->base, $this->under, $this->over))->layout($c);
        }
        $op = self::operatorOf($this->base);
        if ($op !== null) {
            if (! $c->display && $op->hasMovableLimits(OperatorDictionary::FORM_PREFIX)) {
                return (new Scripts($this->base, $this->under, $this->over))->layout($c);
            }
            if ($op->isLargeop(OperatorDictionary::FORM_PREFIX)) {
                return $this->limits($c);
            }
        }

        return $this->stack($c, $op);
    }

    /** An upright function name whose limits move like a sum's: lim, max, min, ... */
    private static function isLimitName(Node $n): bool
    {
        if ($n instanceof Row && count($n->children) === 1) {
            $n = $n->children[0];
        }

        return $n instanceof Identifier && $n->upright && in_array($n->name, self::LIMIT_NAMES, true);
    }

    /** The operator a node is: itself, or the only child of a Row. */
    private static function operatorOf(?Node $n): ?Operator
    {
        if ($n instanceof Row && count($n->children) === 1) {
            $n = $n->children[0];
        }

        return $n instanceof Operator ? $n : null;
    }

    /** Limits above and below a large operator, centred on it and nudged apart by its italic correction. */
    private function limits(Context $c): Box
    {
        $b = $this->base->layout($c);
        $over = $this->over?->layout($c->script());
        $under = $this->under?->layout($c->script());
        $half = $b->italic / 2;
        $parts = [[$b, 0.0, 0.0]];
        $asc = $b->ascent;
        $desc = $b->descent;
        if ($over !== null) {
            $up = $b->ascent + max($c->k('upperLimitBaselineRiseMin'), $c->k('upperLimitGapMin') + $over->descent);
            $parts[] = [$over, $half, -$up];
            $asc = max($asc, $up + $over->ascent);
        }
        if ($under !== null) {
            $down = $b->descent + max($c->k('lowerLimitBaselineDropMin'), $c->k('lowerLimitGapMin') + $under->ascent);
            $parts[] = [$under, -$half, $down];
            $desc = max($desc, $down + $under->descent);
        }

        return self::assemble($parts, $b->width, $asc, $desc);
    }

    /**
     * Centre parts on the base's centre line and set the box width to what they span.
     *
     * @param list<array{Box, float, float}> $parts [box, x shift from centred, dy] with the base first
     * @param list<Rule> $rules rules already positioned relative to the base's left edge
     */
    private static function assemble(array $parts, float $baseWidth, float $asc, float $desc, array $rules = []): Box
    {
        $lefts = [];
        $minL = 0.0;
        $maxR = $baseWidth;
        foreach ($parts as $i => [$box, $shift]) {
            $lefts[$i] = $i === 0 ? 0.0 : ($baseWidth - $box->width) / 2 + $shift;
            $minL = min($minL, $lefts[$i]);
            $maxR = max($maxR, $lefts[$i] + $box->width);
        }
        $out = new Box($maxR - $minL, $asc, $desc);
        foreach ($parts as $i => [$box, , $dy]) {
            $out->place($box, $lefts[$i] - $minL, $dy);
        }
        foreach ($rules as $r) {
            $out->add($r->translated(-$minL, 0.0));
        }

        return $out;
    }

    private function stack(Context $c, ?Operator $baseOp): Box
    {
        $f = $c->font;
        $m = $f->math();
        $u = $c->unit();
        $baseStretch = $baseOp !== null && self::horizontalGid($c, $baseOp) !== null;
        $baseAnchor = null;
        [$overKind, $overOp] = $this->classify($c, $this->over, $this->accent);
        [$underKind, $underOp] = $this->classify($c, $this->under, $this->accentUnder);

        // Plain parts first: the widths the stretchy ones stretch to.
        $baseBox = $baseStretch ? null : $this->base->layout($c);
        $overBox = $overKind === 'plain' && $this->over !== null ? $this->over->layout($c->script()) : null;
        $underBox = $underKind === 'plain' && $this->under !== null ? $this->under->layout($c->script()) : null;
        $width = static fn (?Box $b): float => $b === null ? 0.0 : $b->width;
        $widest = max($width($baseBox), $width($overBox), $width($underBox));
        if ($baseStretch) {
            [$baseBox, $baseAnchor] = self::stretched($c, $baseOp->char, $widest);
        }
        if ($baseBox === null) {
            throw new \LogicException('UnderOver base was not laid out');
        }
        // Stretchy parts stretch to the widest child that does not stretch.
        $overAnchor = 0.0;
        if ($overOp !== null && ($overKind === 'accent' || $overKind === 'stretch')) {
            [$overBox, $overAnchor] = self::stretched($c, $overOp->char, $widest);
        } elseif ($overKind === 'accent' && $this->over !== null) {
            $overBox = $this->over->layout($c); // an accent that is not an operator stays at the base's level
            $overAnchor = $overBox->width / 2;
        }
        if ($underOp !== null && ($underKind === 'accent' || $underKind === 'stretch')) {
            [$underBox] = self::stretched($c, $underOp->char, $widest);
        } elseif ($underKind === 'accent' && $this->under !== null) {
            $underBox = $this->under->layout($c);
        }

        $asc = $baseBox->ascent;
        $desc = $baseBox->descent;
        $wb = $baseBox->width;
        $parts = [[$baseBox, 0.0, 0.0]];
        $rules = [];
        $thick = $c->k('overbarRuleThickness');

        if ($overKind === 'bar') {
            $y = $baseBox->ascent + $c->k('overbarVerticalGap') + $thick;
            $rules[] = new Rule(0.0, -$y, $wb, $thick);
            $asc = max($asc, $y + $c->k('overbarExtraAscender'));
        } elseif ($overBox !== null) {
            $up = match (true) {
                $overKind === 'accent' => $baseBox->ascent - min($baseBox->ascent, $c->k('accentBaseHeight')),
                $baseStretch => max($c->k('stretchStackTopShiftUp'), $baseBox->ascent + $c->k('stretchStackGapAboveMin') + $overBox->descent),
                default => $baseBox->ascent + $c->k('overbarVerticalGap') + $overBox->descent,
            };
            $shift = $overKind === 'accent' ? ($baseAnchor ?? self::baseAttachment($c, $baseBox)) - $overAnchor - ($wb - $overBox->width) / 2 : 0.0;
            $parts[] = [$overBox, $shift, -$up];
            $asc = max($asc, $up + $overBox->ascent + ($overKind === 'accent' || $baseStretch ? 0.0 : $c->k('overbarExtraAscender')));
        }

        if ($underKind === 'bar') {
            $y = $baseBox->descent + $c->k('underbarVerticalGap');
            $t = $c->k('underbarRuleThickness');
            $rules[] = new Rule(0.0, $y, $wb, $t);
            $desc = max($desc, $y + $t + $c->k('underbarExtraDescender'));
        } elseif ($underBox !== null) {
            $down = match (true) {
                $underKind === 'accent' => $baseBox->descent + $c->k('underbarVerticalGap') + $underBox->ascent,
                $baseStretch => max($c->k('stretchStackBottomShiftDown'), $baseBox->descent + $c->k('stretchStackGapBelowMin') + $underBox->ascent),
                default => $baseBox->descent + $c->k('underbarVerticalGap') + $underBox->ascent,
            };
            $parts[] = [$underBox, 0.0, $down];
            $desc = max($desc, $down + $underBox->descent + ($underKind === 'accent' || $baseStretch ? 0.0 : $c->k('underbarExtraDescender')));
        }

        $out = self::assemble($parts, $wb, $asc, $desc, $rules);
        if ($baseStretch) {
            // The stretched operator keeps the spacing the dictionary gives an infix operator, as an arrow between operands does.
            $e = OperatorDictionary::lookup($baseOp->char, OperatorDictionary::FORM_INFIX);
            $l = $c->mu($e['lspace']);
            $shifted = new Box($out->width + $l + $c->mu($e['rspace']), $out->ascent, $out->descent);
            $shifted->place($out, $l, 0.0);

            return $shifted;
        }

        return $out;
    }

    /**
     * Which way a part is drawn: bar (a rule), accent (an accent glyph), stretch (a stretchy glyph), plain (laid out as a node).
     *
     * @return array{string, ?Operator}
     */
    private function classify(Context $c, ?Node $part, bool $accent): array
    {
        $op = self::operatorOf($part);
        if ($op === null) {
            return [$part === null ? 'none' : ($accent ? 'accent' : 'plain'), null];
        }

        return match (true) {
            in_array($op->char, self::BARS, true) => ['bar', $op],
            $accent => ['accent', $op],
            self::horizontalGid($c, $op) !== null => ['stretch', $op],
            default => ['plain', null],
        };
    }

    /** The operator's glyph id when it is a stretchy operator the font can stretch horizontally. */
    private static function horizontalGid(Context $c, Operator $op): ?int
    {
        if (! ($op->stretchy ?? OperatorDictionary::lookup($op->char, OperatorDictionary::FORM_POSTFIX)['stretchy'])) {
            return null;
        }
        $gid = $c->font->gid(mb_ord($op->char));

        return $gid !== 0 && $c->font->math()->horizontal($gid) !== null ? $gid : null;
    }

    /** Where an accent attaches to the base: its top accent attachment when it is one glyph, else the middle. */
    private static function baseAttachment(Context $c, Box $b): float
    {
        if ($b->simple && $b->firstGid !== null && $b->firstGid === $b->lastGid) {
            $att = $c->font->math()->topAccentAttachment($b->firstGid);
            if ($att !== null) {
                return $att * $c->unit();
            }
        }

        return $b->width / 2;
    }

    /**
     * An operator glyph (or assembly) stretched to $required points, as a box whose left edge is the ink's left edge
     * and whose ascent/descent are the ink's.
     *
     * @return array{Box, float} the box and where an accent attaches, in points from its left edge
     */
    private static function stretched(Context $c, string $char, float $required): array
    {
        $f = $c->font;
        $u = $c->unit();
        $gid = $f->gid(mb_ord($char));
        $s = Stretcher::horizontal($f, $gid, $required / $u);
        $single = count($s['pieces']) === 1;
        $b = new Box(($s['xmax'] - $s['xmin']) * $u, $s['ascent'] * $u, $s['descent'] * $u);
        foreach ($s['pieces'] as [$pg, $off]) {
            $b->add(new Glyph($pg, $single && $pg === $gid ? $char : null, ($off - $s['xmin']) * $u, 0.0, $c->size()));
        }
        $att = $single ? $f->math()->topAccentAttachment($s['pieces'][0][0]) : null;

        return [$b, $att !== null ? ($att - $s['xmin']) * $u : $b->width / 2];
    }
}
