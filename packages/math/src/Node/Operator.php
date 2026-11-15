<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Glyph;
use Papercut\Math\Layout\Run;
use Papercut\Math\Layout\Stretcher;
use Papercut\Math\OperatorDictionary;

/**
 * mo: an operator or fence character. Its form (prefix/infix/postfix) comes from its position in
 * the enclosing Row (see Forms::of) and decides the spacing around it. Plain operators do not stretch on
 * their own; stretching is done by Fenced.
 */
final class Operator extends Node
{
    /**
     * @param ?bool $stretchy null reads the operator dictionary; false never stretches; true stretches
     * @param ?bool $largeop null reads the operator dictionary
     * @param ?bool $movablelimits null reads the operator dictionary
     */
    public function __construct(
        public readonly string $char,
        public readonly ?bool $stretchy = null,
        public readonly ?bool $largeop = null,
        public readonly ?bool $movablelimits = null,
    ) {
    }

    /** Whether this is a large operator (a sum, an integral) in the given form: drawn bigger in display style. */
    public function isLargeop(string $form): bool
    {
        return $this->largeop ?? OperatorDictionary::lookup($this->char, $form)['largeop'];
    }

    /** Whether limits move to the side as scripts in inline style. */
    public function hasMovableLimits(string $form): bool
    {
        return $this->movablelimits ?? OperatorDictionary::lookup($this->char, $form)['movablelimits'];
    }

    /**
     * On its own (not inside a Row) an operator is first in its row, so prefix.
     *
     * @internal Engine detail, not part of papercut/math's public API.
     */
    public function layout(Context $c): Box
    {
        return $this->layoutAs($c, OperatorDictionary::FORM_PREFIX);
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layoutAs(Context $c, string $form): Box
    {
        $entry = OperatorDictionary::lookup($this->char, $form);
        [$l, $r] = [$entry['lspace'], $entry['rspace']];
        if ($c->tight && OperatorDictionary::dropsSpacingInScripts($this->char)) {
            [$l, $r] = [0, 0];
        }
        if ($c->display && $this->isLargeop($form)) {
            $big = $this->displaySize($c, $c->mu($l), $c->mu($r));
            if ($big !== null) {
                return $big;
            }
        }
        $b = Run::build($c, [[mb_ord($this->char), $this->char]], $c->mu($l), $c->mu($r));
        $b->italic = 0.0;

        return $b;
    }

    /**
     * A large operator in display style: the font's first size variant (or an assembly) at least displayOperatorMinHeight
     * tall, centred on the math axis the way Fenced centres delimiters. Null when the font has no glyph for it.
     */
    private function displaySize(Context $c, float $lead, float $trail): ?Box
    {
        $f = $c->font;
        $gid = $f->gid(mb_ord($this->char));
        if ($gid === 0) {
            return null;
        }
        $u = $c->unit();
        $m = $f->math();
        $s = Stretcher::vertical($f, $gid, (float) $m->constant('displayOperatorMinHeight'));
        $shift = $m->constant('axisHeight') - ($s['ymin'] + $s['ymax']) / 2;
        $single = count($s['pieces']) === 1;
        $b = new Box($lead + $s['advance'] * $u + $trail, ($s['ymax'] + $shift) * $u, -($s['ymin'] + $shift) * $u);
        foreach ($s['pieces'] as [$pg, $off]) {
            $b->add(new Glyph($pg, $single ? $this->char : null, $lead, -($off + $shift) * $u, $c->size()));
        }
        if ($single) {
            $b->firstGid = $b->lastGid = $s['pieces'][0][0];
            $b->italic = $m->italicsCorrection($s['pieces'][0][0]) * $u;
        }

        return $b;
    }
}
