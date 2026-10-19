<?php

declare(strict_types=1);

namespace Papercut\Laravel\Rendering;

use Illuminate\Support\HtmlString;
use Papercut\Laravel\Exception\InvalidStructure;

final class NodeRegistry
{
    /** @var list<array{nonce: string, nodes: array<int, object>, taken: array<int, true>, next: int}> */
    private array $scopes = [];

    public function scope(callable $fn): mixed
    {
        $this->scopes[] = ['nonce' => bin2hex(random_bytes(8)), 'nodes' => [], 'taken' => [], 'next' => 0];
        try {
            return $fn();
        } finally {
            array_pop($this->scopes);
        }
    }

    public function register(object $node): HtmlString
    {
        $key = array_key_last($this->scopes);
        if ($key === null) {
            throw new InvalidStructure('<x-pdf::*> components can only be rendered through Pdf::view().');
        }
        $id = $this->scopes[$key]['next']++;
        $this->scopes[$key]['nodes'][$id] = $node;

        return new HtmlString('[[pc:' . $this->scopes[$key]['nonce'] . ':' . $id . ']]');
    }

    /** @return list<object|string> */
    public function segments(string $html): array
    {
        $key = array_key_last($this->scopes);
        $parts = preg_split('/(\[\[pc:[0-9a-f]{16}:\d+\]\])/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [$html];
        }

        $out = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $node = null;
            if ($key !== null && preg_match('/^\[\[pc:([0-9a-f]{16}):(\d+)\]\]$/', $part, $m) === 1
                && $m[1] === $this->scopes[$key]['nonce']) {
                $id = (int) $m[2];
                if (isset($this->scopes[$key]['taken'][$id])) {
                    throw new InvalidStructure('Node rendered twice');
                }
                if (isset($this->scopes[$key]['nodes'][$id])) {
                    $this->scopes[$key]['taken'][$id] = true;
                    $node = $this->scopes[$key]['nodes'][$id];
                }
            }
            if ($node !== null) {
                $out[] = $node;
            } elseif ($out !== [] && is_string($out[array_key_last($out)])) {
                $out[array_key_last($out)] .= $part;
            } else {
                $out[] = $part;
            }
        }

        return $out;
    }

    public function remaining(): int
    {
        $key = array_key_last($this->scopes);

        return $key === null ? 0 : count($this->scopes[$key]['nodes']) - count($this->scopes[$key]['taken']);
    }
}
