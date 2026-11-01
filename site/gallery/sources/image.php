<?php

declare(strict_types=1);

use Papercut\{Column, Document, Image, Page, Row, Text, View};

use function Papercut\{in, pt};

// A landscape drawn with GD, so the gallery needs no photo: a wide picture shown in square frames.
$w = 900;
$h = 450;
$img = imagecreatetruecolor($w, $h);
for ($y = 0; $y < $h; $y++) {
    $t = $y / $h;
    $sky = imagecolorallocate($img, (int) (99 + 150 * $t), (int) (102 + 80 * $t), (int) (241 - 60 * $t));
    imageline($img, 0, $y, $w, $y, (int) $sky);
}
imagefilledellipse($img, 640, 190, 150, 150, (int) imagecolorallocate($img, 253, 224, 71));
imagefilledpolygon($img, [0, 450, 0, 300, 180, 160, 360, 290, 520, 190, 720, 330, 900, 230, 900, 450], (int) imagecolorallocate($img, 49, 46, 129));
imagefilledpolygon($img, [0, 450, 0, 380, 260, 300, 480, 390, 700, 320, 900, 400, 900, 450], (int) imagecolorallocate($img, 30, 27, 75));
ob_start();
imagepng($img);
$png = (string) ob_get_clean();

$framed = static fn (string $fit, string $caption): View => Column::make(
    Image::bytes($png)->fit($fit)->width(in(1.7))->height(in(1.7))->background('slate-100'),
    Text::make($caption)->size(pt(9))->color('slate-500'),
)->gap(pt(4));

return Document::make(Page::size(in(6), in(8))->margin(in(0.25))->body(
    Row::make($framed('cover', 'fit="cover" fills the box'), $framed('contain', 'fit="contain" shows it all'), $framed('fill', 'fit="fill" stretches it'))->gap(pt(14)),
))->createdAt(new DateTimeImmutable('2026-01-01T00:00:00Z'));
