<?php

declare(strict_types=1);

namespace Papercut\Exception;

use Papercut\PapercutException;

final class UnknownAttribute extends PapercutException
{
    private function __construct(
        public readonly string $name,
        public readonly ?string $suggestion,
        public readonly ?string $context,
        public readonly string $noun,
        ?\Throwable $previous = null,
    ) {
        $msg = sprintf('Unknown %s "%s"', $noun, $name);
        if ($context !== null) {
            $msg .= ' ' . $context;
        }
        $msg .= '.';
        if ($suggestion !== null) {
            $msg .= sprintf(' Did you mean "%s"?', $suggestion);
        }
        parent::__construct($msg, 0, $previous);
    }

    public static function for(string $name, ?string $suggestion = null, string $noun = 'attribute'): self
    {
        return new self($name, $suggestion, null, $noun);
    }

    public function withContext(string $context): self
    {
        return new self($this->name, $this->suggestion, $context, $this->noun, $this);
    }
}
