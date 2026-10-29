<?php

declare(strict_types=1);

namespace Papercut\Laravel\Components;

use Papercut\Laravel\Exception\InvalidStructure;
use Papercut\Laravel\Rendering\NodeRegistry;
use Papercut\Laravel\Tag;
use Papercut\Span;
use Papercut\Text;

/**
 * Preformatted text: the slot is literal (no tags are parsed, HTML entities are decoded), so
 * `{{ $code }}` comes out exactly as the variable's value.
 */
final class Pre extends PapercutComponent
{
    protected function build(array $data): object
    {
        $spans = [];
        foreach (self::lines(self::literal($this->rawSlot($data))) as $i => $line) {
            if ($i > 0) {
                $spans[] = Span::break();
            }
            if ($line !== '') {
                $spans[] = Span::text($line);
            }
        }

        return $this->styled(Text::spans(...$spans)->apply('pre'), $data);
    }

    /** @param array<string, mixed> $data */
    private function rawSlot(array $data): string
    {
        $text = '';
        foreach (app(NodeRegistry::class)->segments((string) ($data['slot'] ?? '')) as $seg) {
            if (!is_string($seg)) {
                throw new InvalidStructure(Tag::of('pre') . ' can only contain text; tags inside it are not parsed and components are not allowed');
            }
            $text .= $seg;
        }

        return $text;
    }

    private static function literal(string $slot): string
    {
        return html_entity_decode($slot, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Lines with the first and last blank line dropped and the common leading indentation removed.
     * Blade trims the slot, so the first line has already lost its own indentation: it is neither
     * counted nor stripped, and the indentation is taken from the lines after it.
     *
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [''];
        if (trim($lines[0]) === '') {
            array_shift($lines);
        }
        if ($lines !== [] && trim($lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }

        $indent = null;
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $lead = substr($line, 0, strspn($line, " \t"));
            if ($indent === null) {
                $indent = $lead;
                continue;
            }
            $n = 0;
            $max = min(strlen($indent), strlen($lead));
            while ($n < $max && $indent[$n] === $lead[$n]) {
                $n++;
            }
            $indent = substr($indent, 0, $n);
        }
        $indent ??= '';

        $strip = static fn (string $line): string => trim($line) === '' ? '' : (str_starts_with($line, $indent) ? substr($line, strlen($indent)) : $line);

        return $lines === [] ? [] : [trim($lines[0]) === '' ? '' : $lines[0], ...array_map($strip, array_slice($lines, 1))];
    }
}
