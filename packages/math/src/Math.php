<?php

declare(strict_types=1);

namespace Papercut\Math;

use Papercut\Math\Font\TrueTypeFont;
use Papercut\Math\Layout\Box;
use Papercut\Math\Layout\Context;
use Papercut\Math\Node\Fallback;
use Papercut\Math\Node\Fenced;
use Papercut\Math\Node\Fraction;
use Papercut\Math\Node\Identifier;
use Papercut\Math\Node\Node;
use Papercut\Math\Node\Phantom;
use Papercut\Math\Node\Root;
use Papercut\Math\Node\Row;
use Papercut\Math\Node\Scripts;
use Papercut\Math\Node\Space;
use Papercut\Math\Node\Text;

/**
 * Builder API. Wherever a Node is expected a plain string works too and is tokenized
 * (a string that yields several tokens becomes a Row).
 */
final class Math
{
    /** The bundled STIX Two Math, opened once per process. */
    private static ?TrueTypeFont $stix = null;

    /** Path of the bundled STIX Two Math font (SIL OFL 1.1, see resources/fonts/OFL.txt). */
    public static function stixTwoMathPath(): string
    {
        return __DIR__ . '/../resources/fonts/STIXTwoMath-Regular.ttf';
    }

    public static function stixTwoMath(): TrueTypeFont
    {
        return self::$stix ??= TrueTypeFont::fromFile(self::stixTwoMathPath());
    }

    public static function row(string|Node ...$items): Node
    {
        $nodes = [];
        foreach ($items as $it) {
            if (is_string($it)) {
                array_push($nodes, ...Tokenizer::tokenize($it));
            } else {
                $nodes[] = $it;
            }
        }

        return count($nodes) === 1 ? $nodes[0] : new Row($nodes);
    }

    public static function frac(string|Node $num, string|Node $den): Fraction
    {
        return new Fraction(self::row($num), self::row($den));
    }

    /** A fraction without a rule (MathML linethickness="0"). */
    public static function stack(string|Node $top, string|Node $bottom): Fraction
    {
        return new Fraction(self::row($top), self::row($bottom), false);
    }

    /** A binomial coefficient: a barless fraction in parentheses. */
    public static function binom(string|Node $top, string|Node $bottom): Fenced
    {
        return new Fenced('(', ')', self::stack($top, $bottom));
    }

    /** Horizontal space of $em times the font size; negative pulls the next item back. */
    public static function space(float $em): Space
    {
        return new Space($em);
    }

    /** Takes the size of its content and draws nothing. */
    public static function phantom(string|Node ...$content): Phantom
    {
        return new Phantom(self::row(...$content));
    }

    /**
     * Text drawn in a MathML mathvariant (double-struck, bold, script, ...) as an upright identifier of the mapped code points.
     *
     * @throws \InvalidArgumentException for a variant outside Styles::VARIANTS
     */
    public static function styled(string $text, string $variant): Identifier
    {
        if ($text === '') {
            throw new \InvalidArgumentException('Styled text is empty');
        }
        if (! in_array($variant, Styles::VARIANTS, true)) {
            throw new \InvalidArgumentException('Unknown math variant "' . $variant . '"');
        }

        return new Identifier(implode('', array_map(static fn (string $ch): string => mb_chr(Styles::variant(mb_ord($ch), $variant)), mb_str_split($text))), false);
    }

    public static function sqrt(string|Node ...$content): Root
    {
        return new Root(self::row(...$content));
    }

    public static function root(string|Node $radicand, string|Node $index): Root
    {
        return new Root(self::row($radicand), self::row($index));
    }

    public static function sup(string|Node $base, string|Node $sup): Scripts
    {
        return new Scripts(self::row($base), null, self::row($sup));
    }

    public static function sub(string|Node $base, string|Node $sub): Scripts
    {
        return new Scripts(self::row($base), self::row($sub), null);
    }

    public static function subSup(string|Node $base, string|Node $sub, string|Node $sup): Scripts
    {
        return new Scripts(self::row($base), self::row($sub), self::row($sup));
    }

    public static function fenced(string $open, string $close, string|Node ...$content): Fenced
    {
        return new Fenced($open, $close, self::row(...$content));
    }

    public static function text(string $text): Text
    {
        return new Text($text);
    }

    /**
     * The notes of every Fallback in the tree, depth first in reading order.
     *
     * @return list<MathNote>
     */
    public static function notes(Node $root): array
    {
        $notes = [];
        if ($root instanceof Fallback) {
            $notes[] = $root->note;
        }
        foreach ($root->children() as $child) {
            array_push($notes, ...self::notes($child));
        }

        return $notes;
    }

    /**
     * Lay a formula out. $display true = display style (block equation), false = inline/text style.
     *
     * @param float $size font size in points
     * @param bool $scriptAlternates false keeps scripts on the ordinary glyphs instead of the font's `ssty`
     *                               script-size forms (for backends that can only draw glyphs reachable from a code point)
     * @param (\Closure(string, float): ?float)|null $fallbackAdvance asked for the advance, in points, of a character the font has no glyph
     *                               for (called with the character and the size it is set at); a float replaces the .notdef
     *                               advance, null keeps it. For backends that draw such characters in another font.
     */
    public static function layout(
        Node $root,
        float $size = 12.0,
        bool $display = true,
        ?TrueTypeFont $font = null,
        bool $scriptAlternates = true,
        ?\Closure $fallbackAdvance = null,
    ): Box {
        return $root->layout(new Context($font ?? self::stixTwoMath(), $size, 0, $display, false, false, $scriptAlternates, $fallbackAdvance));
    }
}
