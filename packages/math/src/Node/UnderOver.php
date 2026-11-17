<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\DisplayItem;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Rule;
use Papercut\Math\Layout\Stretcher;
use Papercut\Math\OperatorDictionary;
use Papercut\Math\Styles;

/**
 * munder / mover / munderover in one class: a base with a part below, above, or both. Depending on the base and the
 * parts it lays out as limits of a large operator, as scripts (movable limits in inline style), as an accent, a bar,
 * a stretched arrow or brace, or as a plain stack.
 */
final class UnderOver extends Node
{
    /** @internal The operators drawn as a rule rather than a glyph. */
    public const BARS = ['¯', '‾', '_'];
    /** @internal Braces drawn above their base. */
    public const BRACES_OVER = ['⏞', '⎴'];
    /** @internal Braces drawn below their base. */
    public const BRACES_UNDER = ['⏟', '⎵'];

    /** @internal Shortest an extensible arrow is drawn, in em (KaTeX's value). */
    public const ARROW_MIN_WIDTH = 1.469;
    /** @internal Room an extensible arrow keeps past each end of its widest label, in em (KaTeX's value). */
    public const ARROW_LABEL_PAD = 0.5;
    /**
     * @internal The horizontal stretchy arrows and harpoons of the operator dictionary (left, right and left-right, with
     * hooks, bars, tails, doubles and long forms) in the Arrows, Supplemental Arrows-A and -B, and Miscellaneous Symbols
     * and Arrows blocks, and `=`, which stretches like them. Vertical and bent arrows, braces and accents are not in it.
     */
    public const ARROWS = '←→↔↚↛↜↝↞↠↢↣↤↦↩↪↫↬↭↮↹↼↽⇀⇁⇄⇆⇇⇉⇋⇌⇍⇎⇏⇐⇒⇔⇚⇛⇜⇝⇠⇢⇤⇥⇦⇨⇰⇴⇶⇷⇸⇹⇺⇻⇼⇽⇾⇿⟴⟵⟶⟷⟸⟹⟺⟻⟼⟽⟾⟿⤀⤁⤂⤃⤄⤅⤆⤇⤌⤍⤎⤏⤐⤑⤔⤕⤖⤗⤘⤙⤚⤛⤜⤝⤞⤟⤠⥂⥃⥄⥅⥆⥇⥈⥊⥋⥎⥐⥒⥓⥖⥗⥚⥛⥞⥟⥢⥤⥦⥧⥨⥩⥪⥫⥬⥭⥰⥱⥲⥳⥴⥵⬄⬅⬌⬰⬱⬲⬳⬴⬵⬶⬷⬸⬹⬺⬻⬼⬽⬾⭀⭁⭂⭃⭄⭅⭆⭇⭈⭉⭊⭋⭌⭠⭢⭤⭪⭬⭰⭲⭺⭼⮀⮂⮄⮆⮕=';

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
        return array_values(array_filter([$this->base, $this->under, $this->over], static fn (?Node $n): bool => $n !== null));
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

    /**
     * Whether a part is a stretched brace: a brace glyph (above or below) that is not told to stay unstretched.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function isBrace(?Operator $op, bool $over): bool
    {
        return $op !== null && $op->stretchy !== false && in_array($op->char, $over ? self::BRACES_OVER : self::BRACES_UNDER, true);
    }

    /**
     * An upright function name whose limits move like a sum's: lim, max, min, ...
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function isLimitName(Node $n): bool
    {
        $n = self::nameNode($n);
        if ($n instanceof Operator) {
            return $n->movablelimits !== false && OperatorDictionary::isLimitName($n->char);
        }

        return $n instanceof Identifier && $n->upright && in_array($n->name, OperatorDictionary::LIMIT_NAMES, true);
    }

    /**
     * The node a base stands for once wrappers that only annotate it (a Fallback, a DisplayStyle, a one-child Row) are peeled.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function nameNode(Node $n): Node
    {
        $n = Fallback::unwrap($n);
        if ($n instanceof Row && count($n->children) === 1) {
            $n = Fallback::unwrap($n->children[0]);
        }

        return $n;
    }

    /**
     * The operator a node is: itself, or the only child of a Row.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function operatorOf(?Node $n): ?Operator
    {
        if ($n === null) {
            return null;
        }
        $n = Fallback::unwrap($n);
        if ($n instanceof Row && count($n->children) === 1) {
            $n = Fallback::unwrap($n->children[0]);
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
        // An accent that is not an operator stays at the base's level and does not stretch, so it counts as plain here.
        if ($overKind === 'accent' && $overOp === null && $this->over !== null) {
            $overBox = $this->over->layout($c);
        }
        if ($underKind === 'accent' && $underOp === null && $this->under !== null) {
            $underBox = $this->under->layout($c);
        }
        $width = static fn (?Box $b): float => $b === null ? 0.0 : $b->width;
        $widest = max($width($baseBox), $width($overBox), $width($underBox));
        if ($baseStretch) {
            // An arrow reaches past its labels and has a minimum length; a brace or accent only spans its base.
            $target = self::isArrow($baseOp->char) && ($overBox !== null || $underBox !== null)
                ? max($widest + 2 * self::ARROW_LABEL_PAD * $c->size(), self::ARROW_MIN_WIDTH * $c->size())
                : $widest;
            [$baseBox, $baseAnchor] = self::stretched($c, $baseOp->char, $target);
            // The target is an advance width; the minimum and the pad are guarantees about ink, so make up a side-bearing shortfall.
            if ($target > $widest && $baseBox->width < $target) {
                [$baseBox, $baseAnchor] = self::stretched($c, $baseOp->char, $target + ($target - $baseBox->width));
            }
        }
        if ($baseBox === null) {
            throw new \LogicException('UnderOver base was not laid out');
        }
        // Stretchy parts stretch to the widest child that does not stretch.
        $overAnchor = 0.0;
        $overChar = $overOp?->char;
        if ($overOp !== null && $overKind === 'accent') {
            $overChar = self::combiningForm($c, $overOp, $widest) ?? $overOp->char;
        }
        if ($overOp !== null && ($overKind === 'accent' || $overKind === 'stretch')) {
            [$overBox, $overAnchor] = self::stretched($c, $overChar ?? $overOp->char, $overOp->stretchy === false ? 0.0 : $widest);
        } elseif ($overKind === 'accent' && $overBox !== null) {
            $overAnchor = $overBox->width / 2;
        }
        if ($underOp !== null && ($underKind === 'accent' || $underKind === 'stretch')) {
            [$underBox] = self::stretched($c, $underOp->char, $widest);
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
                // A combining mark's ink already sits above x-height, so the font's accentBaseHeight rule raises it just enough.
                // A spacing mark with a covered combining form (^, ˆ, ¨, an arrow) is drawn as that form and follows the same rule.
                $overKind === 'accent' && ($overOp !== null ? self::isCombining($overChar ?? '') : self::operatorOf($this->over) === null) => $baseBox->ascent - min($baseBox->ascent, $c->k('accentBaseHeight')),
                // Anything else (a name such as .., a mark with no combining form) goes by its real ink: the bottom clears the
                // base top by the gap an overbar leaves.
                $overKind === 'accent' => $baseBox->ascent + $c->k('overbarVerticalGap') - self::inkBottom($c, $overBox),
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
     * Whether a stretchy operator is an arrow (or `=`), which an extensible-arrow stack pads and gives a minimum length.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public static function isArrow(string $char): bool
    {
        return mb_strlen($char) === 1 && str_contains(self::ARROWS, $char);
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

        if (mb_strlen($op->char) !== 1) {
            // Only a single glyph can be an accent or stretch; a longer operator is laid out whole, as text.
            return [$accent ? 'accent' : 'plain', null];
        }

        return match (true) {
            in_array($op->char, self::BARS, true) => ['bar', $op],
            $accent => ['accent', $op],
            self::horizontalGid($c, $op) !== null => ['stretch', $op],
            default => ['plain', null],
        };
    }

    /**
     * The combining character an accent mark stands for, when the font has a glyph for it and it can stretch as far as the
     * spacing mark can (U+20D1 has size variants only, U+21C0 assembles to any width).
     */
    private static function combiningForm(Context $c, Operator $op, float $required): ?string
    {
        $mark = Styles::accentMark($op->char);
        $gid = $mark === null ? 0 : $c->font->gid(mb_ord($mark));
        if ($mark === null || $gid === 0) {
            return null;
        }
        if ($op->stretchy === false || $mark === $op->char) {
            return $mark;
        }
        $units = $required / $c->unit();

        return self::reaches($c, $gid, $units) || ! self::reaches($c, $c->font->gid(mb_ord($op->char)), $units) ? $mark : null;
    }

    /** Whether a glyph can be stretched horizontally to $units font units: a variant that wide, or an assembly. */
    private static function reaches(Context $c, int $gid, float $units): bool
    {
        if ($gid === 0) {
            return false;
        }
        $con = $c->font->math()->horizontal($gid);
        if ($con === null) {
            return $c->font->advance($gid) >= $units;
        }
        $widest = 0;
        foreach ($con->variants as [, $adv]) {
            $widest = max($widest, $adv);
        }

        return $widest >= $units || ($con->parts ?? []) !== [];
    }

    /** Height of a box's lowest ink above its baseline (negative below it), from the glyphs' bounding boxes. */
    private static function inkBottom(Context $c, Box $b): float
    {
        $low = null;
        $b->each(static function (DisplayItem $i, float $dx, float $dy) use ($c, &$low): void {
            if ($i instanceof Glyph) {
                $bb = $c->font->bbox($i->gid);
                $h = -($i->y + $dy) + $bb[1] * $i->sy * $i->size / $c->font->unitsPerEm;
                $low = $low === null ? $h : min($low, $h);
            }
        });

        return $low ?? -$b->descent;
    }

    /** Whether a character is a combining mark (its ink is drawn relative to the base, above or below the origin). */
    private static function isCombining(string $char): bool
    {
        $cp = mb_ord($char);

        return ($cp >= 0x300 && $cp <= 0x36F) || ($cp >= 0x20D0 && $cp <= 0x20FF);
    }

    /** The operator's glyph id when it is a stretchy operator the font can stretch horizontally. */
    private static function horizontalGid(Context $c, Operator $op): ?int
    {
        if (mb_strlen($op->char) !== 1) {
            return null; // a name such as lim is drawn as text, never stretched
        }
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
        foreach ($s['pieces'] as $pc) {
            [$pg, $off] = $pc;
            $b->add(new Glyph($pg, $single && $pg === $gid ? $char : null, ($off - $s['xmin']) * $u, 0.0, $c->size(), $pc[2]));
        }
        $att = $single ? $f->math()->topAccentAttachment($s['pieces'][0][0]) : null;

        return [$b, $att !== null ? ($att - $s['xmin']) * $u : $b->width / 2];
    }
}
