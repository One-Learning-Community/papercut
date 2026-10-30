<?php

declare(strict_types=1);

// Builds the certificate Document: text and a signature placed on top of a blank PDF made in a design tool.
// examples/core/certificate.php saves it as PDF. Word and HTML exports leave page templates out, so only the PDF is built.
use Papercut\{Document, Page, PdfPage, Place, Svg, Text};
use Papercut\Style\Anchor;

use function Papercut\{in, pt};

$assets = __DIR__ . '/../../assets';

return Document::make(
    Page::template(PdfPage::file($assets . '/certificate-template.pdf'))->overlay(
        Place::at(in(5.5), in(4.15), Text::make('Priya Raman')->size(pt(34))->bold()->color('slate-900'))->anchor(Anchor::Bottom),
        Place::at(in(5.5), in(6.03), Text::make('Introduction to Beekeeping')->size(pt(22))->bold()->color('indigo-600'))->anchor(Anchor::Bottom),
        Place::at(in(3.3), in(6.91), Text::make('12 March 2026')->size(pt(13))->color('slate-900'))->anchor(Anchor::Bottom),
        Place::at(in(7.4), in(7.02), Svg::file($assets . '/signature.svg')->width(in(2.2)))->anchor(Anchor::Bottom),
    ),
)->title('Certificate of Completion')->author('Hivewise Academy')->createdAt(new DateTimeImmutable('2026-03-02T09:00:00Z'));
