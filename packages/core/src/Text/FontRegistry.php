<?php

declare(strict_types=1);

namespace Papercut\Text;

use Papercut\Backend\Backend;
use Papercut\Exception\{FontNotFound, InvalidValue};

final class FontRegistry
{
    /** @var array<string, array{name: string, faces: array<string, string>}> family key => faces "weight|style" => path */
    private array $families = [];
    /** @var array<string, CachedFont> keyed by path */
    private array $loaded = [];

    public function __construct(private readonly Backend $backend)
    {
    }

    public static function withDefaults(Backend $backend): self
    {
        $dir = __DIR__ . '/../../resources/fonts/Inter';
        return (new self($backend))
            ->register('Inter', "$dir/Inter-Regular.ttf", 400)
            ->register('Inter', "$dir/Inter-Italic.ttf", 400, FontStyle::Italic)
            ->register('Inter', "$dir/Inter-Bold.ttf", 700)
            ->register('Inter', "$dir/Inter-BoldItalic.ttf", 700, FontStyle::Italic);
    }

    public function register(string $family, string $path, int $weight = 400, FontStyle $style = FontStyle::Normal): self
    {
        if ($weight < 1 || $weight > 1000) {
            throw InvalidValue::for('font weight', (string) $weight);
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new FontNotFound('Font file not found: ' . $path);
        }
        $path = realpath($path) ?: $path;
        $key = strtolower($family);
        $this->families[$key] ??= ['name' => $family, 'faces' => []];
        $this->families[$key]['faces'][$weight . '|' . $style->value] = $path;
        return $this;
    }

    public function has(string $family): bool
    {
        return isset($this->families[strtolower($family)]);
    }

    /** @return list<string> */
    public function families(): array
    {
        return array_values(array_map(static fn (array $f): string => $f['name'], $this->families));
    }

    public function resolve(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal): CachedFont
    {
        $entry = $this->families[strtolower($family)] ?? null;
        if ($entry === null) {
            throw new FontNotFound(sprintf(
                'Unknown font family "%s". Registered families: %s',
                $family,
                implode(', ', $this->families()),
            ));
        }
        $path = $this->match($entry['faces'], $weight, $style);
        return $this->loaded[$path] ??= new CachedFont($this->backend->loadFont($path));
    }

    /** @param array<string, string> $faces */
    private function match(array $faces, int $desired, FontStyle $style): string
    {
        $byStyle = [];
        foreach ($faces as $k => $path) {
            [$w, $s] = explode('|', $k);
            $byStyle[$s][(int) $w] = $path;
        }
        $candidates = $byStyle[$style->value] ?? $byStyle[$style === FontStyle::Italic ? 'normal' : 'italic'];
        $weights = array_keys($candidates);
        sort($weights);
        $up = static fn (int $from, int $to = 1000): array => array_values(array_filter($weights, static fn (int $w): bool => $w >= $from && $w <= $to));
        $down = static fn (int $below): array => array_reverse(array_values(array_filter($weights, static fn (int $w): bool => $w < $below)));
        $order = match (true) {
            $desired >= 400 && $desired <= 500 => [...$up($desired, 500), ...$down($desired), ...$up(501)],
            $desired < 400 => [...array_reverse(array_values(array_filter($weights, static fn (int $w): bool => $w <= $desired))), ...$up($desired + 1)],
            default => [...$up($desired), ...$down($desired)],
        };
        return $candidates[$order[0]];
    }
}
