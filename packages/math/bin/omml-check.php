<?php

// Throwaway: writes tests/out/omml-check.docx with every fixture and the chemistry formulas as block equations. Convert with soffice to eyeball.
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../tests/Fixtures.php';

use Quoin\Math\Math;
use Quoin\Math\Render\OmmlWriter;
use Quoin\Math\Tests\Fixtures;

$out = __DIR__ . '/../tests/out';
@mkdir($out, 0777, true);
$body = '';
$chemistry = [
    'chem-iron-hydroxide' => '\ce{Fe(OH)3}',
    'chem-sulfate' => '\ce{SO4^2-}',
    'chem-ammonium' => '\ce{NH4+}',
    'chem-state' => '\ce{NaCl(aq)}',
    'chem-combustion' => '\ce{CH4 + 2O2 -> CO2 + 2H2O}',
    'chem-haber' => '\ce{N2 + 3H2 <=> 2NH3}',
    'chem-labelled-arrow' => '\ce{N2 + 3H2 ->[Fe][450 °C] 2NH3}',
    'chem-decomposition' => '\ce{CaCO3 ->[\Delta] CaO + CO2 ^}',
    'chem-carbon-14' => '\ce{^{14}_{6}C}',
    'chem-carbon-14-ion' => '\ce{^{14}_{6}C^{4+}}',
    'chem-unit' => '\pu{25 kJ/mol}',
];
$nodes = Fixtures::all();
foreach ($chemistry as $name => $tex) {
    $nodes[$name] = Math::tex($tex);
}
foreach ($nodes as $name => $node) {
    $body .= '<w:p><w:r><w:t>' . htmlspecialchars($name) . '</w:t></w:r></w:p><w:p>' . OmmlWriter::oMathPara($node, 'left') . '</w:p>';
}
$doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"><w:body>'
    . $body . '<w:sectPr/></w:body></w:document>';
$zip = new ZipArchive();
$zip->open($out . '/omml-check.docx', ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
$zip->addFromString('word/document.xml', $doc);
$zip->close();
echo "wrote $out/omml-check.docx\n";
