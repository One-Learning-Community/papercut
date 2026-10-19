<?php

declare(strict_types=1);

namespace Papercut;

use Papercut\Exception\InvalidValue;
use Papercut\Style\Style;
use Papercut\Value\{Edges, Length};

use function Papercut\mm;

final class Page implements Node
{
    private const SIZES = [
        'a3' => [841.89, 1190.55],
        'a4' => [595.28, 841.89],
        'a5' => [419.53, 595.28],
        'a6' => [297.64, 419.53],
        'letter' => [612.0, 792.0],
        'legal' => [612.0, 1008.0],
    ];

    private Edges $margins;
    private Length $headerGap;
    private Length $footerGap;
    private bool $landscape = false;
    /** @var list<Node|Component> */
    private array $header = [];
    /** @var list<Node|Component> */
    private array $footer = [];
    /** @var list<Node|Component> */
    private array $background = [];
    /** @var list<Node|Component> */
    private array $body = [];

    private function __construct(private float $w, private float $h)
    {
        $this->margins = Edges::all(mm(20));
        $this->headerGap = mm(4);
        $this->footerGap = mm(4);
    }

    private static function preset(string $name): self
    {
        [$w, $h] = self::SIZES[$name];
        return new self($w, $h);
    }

    public static function a3(): self
    {
        return self::preset('a3');
    }

    public static function a4(): self
    {
        return self::preset('a4');
    }

    public static function a5(): self
    {
        return self::preset('a5');
    }

    public static function a6(): self
    {
        return self::preset('a6');
    }

    public static function letter(): self
    {
        return self::preset('letter');
    }

    public static function legal(): self
    {
        return self::preset('legal');
    }

    public static function size(Length $w, Length $h): self
    {
        if (!is_finite($w->points) || !is_finite($h->points) || $w->points <= 0 || $h->points <= 0) {
            throw InvalidValue::for('page size', $w . ' ' . $h)->because('width and height must be positive');
        }
        return new self($w->points, $h->points);
    }

    public static function named(string $name): self
    {
        $key = strtolower($name);
        if (!isset(self::SIZES[$key])) {
            throw InvalidValue::for('page size', $name, 'a4');
        }
        return self::preset($key);
    }

    public function landscape(): self
    {
        $clone = clone $this;
        $clone->landscape = true;
        return $clone;
    }

    public function portrait(): self
    {
        $clone = clone $this;
        $clone->landscape = false;
        return $clone;
    }

    public function margin(Length|Edges ...$v): self
    {
        $v = array_values($v);
        if (count($v) === 1 && $v[0] instanceof Edges) {
            $edges = $v[0];
        } else {
            $lengths = [];
            foreach ($v as $item) {
                if (!$item instanceof Length) {
                    throw new \InvalidArgumentException('Pass one Edges or 1-4 Lengths.');
                }
                $lengths[] = $item;
            }
            if ($lengths === [] || count($lengths) > 4) {
                throw new \InvalidArgumentException('Pass one Edges or 1-4 Lengths.');
            }
            $edges = Edges::of(...$lengths);
        }
        $clone = clone $this;
        $clone->margins = $edges;
        return $clone;
    }

    public function headerGap(Length $g): self
    {
        $clone = clone $this;
        $clone->headerGap = $g;
        return $clone;
    }

    public function footerGap(Length $g): self
    {
        $clone = clone $this;
        $clone->footerGap = $g;
        return $clone;
    }

    public function header(Node|Component|string ...$nodes): self
    {
        $clone = clone $this;
        $clone->header = [...$this->header, ...View::normalize($nodes)];
        return $clone;
    }

    public function footer(Node|Component|string ...$nodes): self
    {
        $clone = clone $this;
        $clone->footer = [...$this->footer, ...View::normalize($nodes)];
        return $clone;
    }

    public function background(Node|Component|string ...$nodes): self
    {
        $clone = clone $this;
        $clone->background = [...$this->background, ...View::normalize($nodes)];
        return $clone;
    }

    public function body(Node|Component|string ...$nodes): self
    {
        $clone = clone $this;
        $clone->body = [...$this->body, ...View::normalize($nodes)];
        return $clone;
    }

    public function width(): float
    {
        return $this->landscape ? $this->h : $this->w;
    }

    public function height(): float
    {
        return $this->landscape ? $this->w : $this->h;
    }

    public function margins(): Edges
    {
        return $this->margins;
    }

    public function headerGapLength(): Length
    {
        return $this->headerGap;
    }

    public function footerGapLength(): Length
    {
        return $this->footerGap;
    }

    /** @return list<Node|Component> */
    public function headerNodes(): array
    {
        return $this->header;
    }

    /** @return list<Node|Component> */
    public function footerNodes(): array
    {
        return $this->footer;
    }

    /** @return list<Node|Component> */
    public function backgroundNodes(): array
    {
        return $this->background;
    }

    /** @return list<Node|Component> */
    public function bodyNodes(): array
    {
        return $this->body;
    }

    public function debugName(): string
    {
        return 'page';
    }

    public function style(): Style
    {
        return new Style();
    }
}
