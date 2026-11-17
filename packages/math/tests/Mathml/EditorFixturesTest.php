<?php

declare(strict_types=1);

namespace Papercut\Math\Tests\Mathml;

use Papercut\Math\Math;
use Papercut\Math\Mathml\MathmlReader;
use Papercut\Math\Mathml\SafeXml;
use Papercut\Math\NoteLevel;
use Papercut\Math\Render\MathmlWriter;
use Papercut\Math\Render\OmmlWriter;
use Papercut\Math\Render\SvgRenderer;
use Papercut\Math\Render\TextWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Real editor output (see the header comment of each fixture): MathType/Wiris, MathLive, Word, CKEditor, TinyMCE. */
final class EditorFixturesTest extends TestCase
{
    private const DIR = __DIR__ . '/../fixtures/editors';

    /**
     * Every formula in an HTML fragment with the path that found it: Wiris `img.Wirisformula` data-mathml attributes
     * ('img', from one HTML parse), safeXml runs in the text outside images ('safexml'), then math elements ('math').
     *
     * @return list<array{string, string}> source and path
     */
    public static function found(string $html): array
    {
        $html = (string) preg_replace('/<!--.*?-->/s', '', $html);
        $found = [];
        $doc = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>' . $html, LIBXML_NOERROR, 'UTF-8');
        foreach ($doc->querySelectorAll('img.Wirisformula') as $img) {
            if ($img->hasAttribute('data-mathml')) {
                $found[] = [(string) $img->getAttribute('data-mathml'), 'img'];
            }
            $img->remove();
        }
        $text = (string) $doc->body?->textContent;
        foreach (SafeXml::find($text) as [$at, $len]) {
            $found[] = [substr($text, $at, $len), 'safexml'];
        }
        $rest = $html;
        foreach ($found as [$src]) {
            $rest = str_replace($src, '', $rest);
        }
        if (preg_match_all('~<((?:[\w.-]+:)?math)\b.*?</\1>~is', $rest, $m) > 0) {
            foreach ($m[0] as $src) {
                $found[] = [$src, 'math'];
            }
        }

        return $found;
    }

    /** @return list<string> */
    public static function formulas(string $html): array
    {
        return array_map(static fn (array $f): string => $f[0], self::found($html));
    }

    /** @return iterable<string, array{string}> */
    public static function fixtureFiles(): iterable
    {
        foreach (glob(self::DIR . '/*.html') ?: [] as $path) {
            yield basename($path, '.html') => [$path];
        }
    }

    #[DataProvider('fixtureFiles')]
    public function test_fixture(string $path): void
    {
        $name = basename($path, '.html');
        $formulas = self::formulas((string) file_get_contents($path));
        self::assertNotSame([], $formulas, $name);
        $update = getenv('UPDATE_SNAPSHOTS') === '1';

        foreach ($formulas as $i => $src) {
            $r = MathmlReader::read($src);
            foreach ([true, false] as $display) {
                $box = Math::layout($r->node, 12.0, $display);
                self::assertGreaterThan(0.0, $box->width, "$name #$i " . ($display ? 'display' : 'inline'));
            }
            $svgFile = __DIR__ . "/../snapshots/editors/svg/$name-$i.svg";
            $svg = (new SvgRenderer(Math::stixTwoMath()))->render(Math::layout($r->node, 12.0, true), 0.0);
            if ($update) {
                if (! is_dir(dirname($svgFile))) {
                    mkdir(dirname($svgFile), 0777, true);
                }
                file_put_contents($svgFile, $svg . "\n");
            }
            self::assertFileExists($svgFile);
            self::assertSame(rtrim($svg), rtrim((string) file_get_contents($svgFile)), "$name #$i svg");
            $notes = $r->notes();
            self::assertSame([], array_map(static fn ($n): string => $n->reason, $notes), "$name #$i");
            $outputs = [
                'mathml' => MathmlWriter::write($r->node, $r->display),
                'omml' => OmmlWriter::oMath($r->node),
                'text' => TextWriter::write($r->node),
            ];
            foreach ($outputs as $kind => $actual) {
                $file = __DIR__ . "/../snapshots/editors/$kind/$name-$i." . ($kind === 'text' ? 'txt' : 'xml');
                if ($update) {
                    if (! is_dir(dirname($file))) {
                        mkdir(dirname($file), 0777, true);
                    }
                    file_put_contents($file, $actual . "\n");
                }
                self::assertFileExists($file);
                self::assertSame(rtrim($actual), rtrim((string) file_get_contents($file)), "$name #$i $kind");
            }
        }
        self::assertCount(count($formulas), glob(__DIR__ . "/../snapshots/editors/text/$name-[0-9]*.txt") ?: []);
    }

    public function test_each_wiris_form_goes_through_its_own_path(): void
    {
        $paths = static fn (string $f): array => array_column(self::found((string) file_get_contents(self::DIR . '/' . $f . '.html')), 1);
        self::assertSame(['img'], $paths('wiris-img-captured'));
        self::assertSame(['img', 'img', 'img', 'img'], $paths('wiris-img'));
        self::assertSame(['safexml'], $paths('wiris-safexml-docs'));
        self::assertSame(['safexml', 'safexml', 'safexml'], $paths('wiris-safexml'));
        self::assertSame(['math', 'math'], $paths('mml-prefixed-quirks'));
    }

    public function test_corpus_size(): void
    {
        $total = 0;
        foreach (self::fixtureFiles() as [$path]) {
            $total += count(self::formulas((string) file_get_contents($path)));
        }
        self::assertGreaterThanOrEqual(15, $total);
    }
}
