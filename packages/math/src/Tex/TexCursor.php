<?php

declare(strict_types=1);

namespace Papercut\Math\Tex;

/**
 * A position in a token list (see {@see TexLexer}), with the small readers every part of the TeX reader shares.
 *
 * @internal Part of papercut/math's engine, not its public API. It may change in any release.
 */
final class TexCursor
{
    public int $p = 0;

    /** Whether the last rawGroup() found the `}` that closes its group (true when it read no group). */
    public bool $closed = true;

    /**
     * @param list<array{string, string, int}> $t
     * @param int $end the length of the source in bytes: where the end of the input is
     */
    public function __construct(public readonly array $t, private readonly int $end = 0)
    {
    }

    /** @return ?array{string, string, int} */
    public function peek(): ?array
    {
        return $this->t[$this->p] ?? null;
    }

    public function skipSp(): void
    {
        while (($this->t[$this->p][0] ?? '') === 'sp') {
            $this->p++;
        }
    }

    public function peekIs(string $type, ?string $val = null): bool
    {
        $t = $this->peek();

        return $t !== null && $t[0] === $type && ($val === null || $t[1] === $val);
    }

    /** The byte offset of the current token (the end of the source at the end). */
    public function offset(): int
    {
        return $this->t[$this->p][2] ?? $this->end;
    }

    /** Past the `}` that closes a group whose `{` is already read. */
    public function skipBalanced(): void
    {
        $depth = 1;
        while (($t = $this->peek()) !== null) {
            $this->p++;
            if ($t[0] === '{') {
                $depth++;
            } elseif ($t[0] === '}' && --$depth === 0) {
                return;
            }
        }
    }

    /** The characters of a group, as plain text (or the one character that follows). */
    public function rawGroup(): string
    {
        $this->closed = true;
        $this->skipSp();
        if (! $this->peekIs('{')) {
            $t = $this->peek();
            if ($t === null || $t[0] !== 'ch') {
                return '';
            }
            $this->p++;

            return $t[1];
        }
        $this->p++;
        $this->closed = false;
        $s = '';
        $depth = 1;
        while (($t = $this->peek()) !== null) {
            $this->p++;
            if ($t[0] === '{') {
                $depth++;
            } elseif ($t[0] === '}' && --$depth === 0) {
                $this->closed = true;
                break;
            } elseif ($t[0] === 'ch' || $t[0] === 'sp') {
                $s .= $t[1];
            }
        }

        return $s;
    }

    /**
     * @param array{string, string, int} $t
     * @param list<string> $stops token types (`}` `&` `nl`), control word names or characters that end the expression
     */
    public static function isStop(array $t, array $stops): bool
    {
        foreach ($stops as $s) {
            // A stop is a token type (`}` `&` `nl`), a control word name (`right`), or a character (`]` `$`): never a control symbol against a character.
            $hit = match (true) {
                $s === '}' || $s === '&' || $s === 'nl' => $t[0] === $s,
                ctype_alpha($s) => $t[0] === 'cs' && $t[1] === $s,
                default => $t[0] === 'ch' && $t[1] === $s,
            };
            if ($hit) {
                return true;
            }
        }

        return false;
    }
}
