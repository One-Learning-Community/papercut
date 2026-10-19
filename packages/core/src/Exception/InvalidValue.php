<?php

declare(strict_types=1);

namespace Papercut\Exception;

use Papercut\PapercutException;

final class InvalidValue extends PapercutException
{
    private function __construct(
        public readonly string $kind,
        public readonly string $input,
        public readonly ?string $suggestion,
        public readonly ?string $context,
        ?\Throwable $previous = null,
    ) {
        $msg = sprintf('Invalid %s "%s"', $kind, $input);
        if ($context !== null) {
            $msg .= ' for ' . $context;
        }
        $msg .= '.';
        if ($suggestion !== null) {
            $msg .= sprintf(' Did you mean "%s"?', $suggestion);
        }
        parent::__construct($msg, 0, $previous);
    }

    public static function for(string $kind, string $input, ?string $suggestion = null): self
    {
        return new self($kind, $input, $suggestion, null);
    }

    public function withContext(string $context): self
    {
        return new self($this->kind, $this->input, $this->suggestion, $context, $this);
    }
}
