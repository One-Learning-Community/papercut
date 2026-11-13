<?php

declare(strict_types=1);

namespace Papercut\Math\Node;

use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Layout\Run;
use Papercut\Math\OperatorDictionary;

/**
 * mo: an operator or fence character. Its form (prefix/infix/postfix) comes from its position in
 * the enclosing Row (see Forms::of) and decides the spacing around it. Plain operators do not stretch on
 * their own; stretching is done by Fenced.
 */
final class Operator extends Node
{
    public function __construct(public readonly string $char, public readonly bool $stretchy = false)
    {
    }

    /** On its own (not inside a Row) an operator is first in its row, so prefix. */
    public function layout(Context $c): Box
    {
        return $this->layoutAs($c, OperatorDictionary::FORM_PREFIX);
    }

    public function layoutAs(Context $c, string $form): Box
    {
        $entry = OperatorDictionary::lookup($this->char, $form);
        [$l, $r] = [$entry['lspace'], $entry['rspace']];
        if ($c->tight && OperatorDictionary::dropsSpacingInScripts($this->char)) {
            [$l, $r] = [0, 0];
        }
        $b = Run::build($c, [[mb_ord($this->char), $this->char]], $c->mu($l), $c->mu($r));
        $b->italic = 0.0;

        return $b;
    }
}
