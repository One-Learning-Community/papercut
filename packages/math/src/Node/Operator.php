<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Run;
use Papercut\Math\Layout\Stretcher;
use Papercut\Math\OperatorDictionary;
use Papercut\Math\Utf8;

/**
 * mo: an operator or fence character. Its form (prefix/infix/postfix) comes from its position in
 * the enclosing Row (see Forms::of) and decides the spacing around it. Plain operators do not stretch on
 * their own; stretching is done by Fenced.
 */
final class Operator extends Node
{
    public readonly string $char;

    /** The widest explicit spacing, in mu, that script style keeps: the space of TeX's op class (relations, binaries and punctuation lose theirs). */
    private const float SCRIPT_SPACE = 1.0;

    /**
     * Invalid UTF-8 in $char is replaced silently here; the Math builders also record a note.
     *
     * @param ?bool $stretchy null reads the operator dictionary; false never stretches; true stretches
     * @param ?bool $largeop null reads the operator dictionary
     * @param ?bool $movablelimits null reads the operator dictionary
     * @param ?string $form prefix, infix or postfix when the source fixes it (mo form=); null infers it from the position
     * @param ?float $lspace space before the operator in mu (1/18 em); null reads the operator dictionary
     * @param ?float $rspace space after the operator in mu (1/18 em); null reads the operator dictionary
     * @param ?float $minSize target total height in em, clamping the natural height from below; null leaves it free
     * @param ?float $maxSize target total height in em, clamping the natural height from above; null leaves it free.
     *        A size is a target met by the smallest font variant (or assembly) at least that tall, as TeX's \big does,
     *        so the drawn height may overshoot it. An explicit size wins over display-style large-operator sizing.
     */
    public function __construct(
        string $char,
        public readonly ?bool $stretchy = null,
        public readonly ?bool $largeop = null,
        public readonly ?bool $movablelimits = null,
        public readonly ?string $form = null,
        public readonly ?float $lspace = null,
        public readonly ?float $rspace = null,
        public readonly ?float $minSize = null,
        public readonly ?float $maxSize = null,
    ) {
        $this->char = Utf8::clean($char);
        foreach (['lspace' => $lspace, 'rspace' => $rspace, 'minSize' => $minSize, 'maxSize' => $maxSize] as $name => $v) {
            if ($v !== null && ! is_finite($v)) {
                throw new \InvalidArgumentException("Operator $name must be a finite number");
            }
        }
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
        return $this->layoutAs($c, $this->form ?? OperatorDictionary::FORM_PREFIX);
    }

    /** @internal Engine detail, not part of papercut/math's public API. */
    public function layoutAs(Context $c, string $form): Box
    {
        $entry = OperatorDictionary::lookup($this->char, $form);
        [$l, $r] = [$this->lspace ?? $entry['lspace'], $this->rspace ?? $entry['rspace']];
        if ($c->tight) {
            // Side by side: an explicit side keeps what an op has (TeX keeps op spacing in scripts) and loses more; any other
            // side follows the dictionary.
            $drops = OperatorDictionary::dropsSpacingInScripts($this->char);
            $l = $this->lspace !== null ? ($this->lspace > self::SCRIPT_SPACE ? 0 : $l) : ($drops ? 0 : $l);
            $r = $this->rspace !== null ? ($this->rspace > self::SCRIPT_SPACE ? 0 : $r) : ($drops ? 0 : $r);
        }
        if (mb_strlen($this->char) === 1) {
            // An explicit size wins over the display-style size of a large operator.
            $big = $this->minSize !== null || $this->maxSize !== null
                ? $this->sizedBox($c, $c->mu($l), $c->mu($r))
                : ($c->display && $this->isLargeop($form) ? $this->displaySize($c, $c->mu($l), $c->mu($r)) : null);
            if ($big !== null) {
                return $big;
            }
        }
        $glyphs = array_map(static fn (string $ch): array => [mb_ord($ch), $ch], mb_str_split($this->char));
        $b = Run::build($c, $glyphs, $c->mu($l), $c->mu($r));
        if (! $this->isLargeop($form)) {
            $b->italic = 0.0; // a large operator keeps its italics correction: its superscript sits that far right of its subscript
        }

        return $b;
    }

    /**
     * A large operator in display style: the font's first size variant (or an assembly) at least displayOperatorMinHeight
     * tall, centered on the math axis the way Fenced centers delimiters. Null when the font has no glyph for it.
     */
    private function displaySize(Context $c, float $lead, float $trail): ?Box
    {
        $gid = $c->font->gid(mb_ord($this->char));

        return $gid === 0 ? null : Stretcher::axisCentered($c, $this->char, $gid, (float) $c->font->math()->constant('displayOperatorMinHeight'), $lead, $trail, true);
    }

    /**
     * The glyph drawn at minSize/maxSize (em) clamping its natural height. Null when the font has no glyph for it.
     */
    private function sizedBox(Context $c, float $lead, float $trail): ?Box
    {
        $f = $c->font;
        $gid = $f->gid(mb_ord($this->char));
        if ($gid === 0) {
            return null;
        }
        $bb = $f->bbox($gid);
        $target = (float) ($bb[3] - $bb[1]);
        if ($this->minSize !== null) {
            $target = max($target, $this->minSize * $f->unitsPerEm);
        }
        if ($this->maxSize !== null) {
            $target = min($target, $this->maxSize * $f->unitsPerEm);
        }

        return Stretcher::axisCentered($c, $this->char, $gid, $target, $lead, $trail, false);
    }
}
