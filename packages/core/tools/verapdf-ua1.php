<?php

declare(strict_types=1);

// Validates every PDF in a directory against PDF/UA-1 with veraPDF, and fails on any that is not compliant,
// printing the failed rules.
// Usage: php packages/core/tools/verapdf-ua1.php <pdf-dir> [report.json]
// Runs veraPDF from the pinned verapdf/cli Docker image. To use a local install instead, set
// VERAPDF to its command, e.g. VERAPDF=/opt/verapdf/verapdf php packages/core/tools/verapdf-ua1.php out

const VERAPDF_IMAGE = 'verapdf/cli:v1.30.3';

$dir = $argv[1] ?? null;
if ($dir === null || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php verapdf-ua1.php <pdf-dir> [report.json]\n");
    exit(2);
}
$dir = realpath($dir);
$pdfs = glob("$dir/*.pdf") ?: [];
if ($pdfs === []) {
    fwrite(STDERR, "No PDFs in $dir\n");
    exit(2);
}
sort($pdfs);

$local = getenv('VERAPDF');
if ($local !== false && $local !== '') {
    $command = escapeshellcmd($local) . ' --flavour ua1 --format json ' . implode(' ', array_map('escapeshellarg', $pdfs));
} else {
    $command = 'docker run --rm --platform linux/amd64 -v ' . escapeshellarg($dir) . ':/data:ro ' . VERAPDF_IMAGE
        . ' --flavour ua1 --format json ' . implode(' ', array_map(static fn (string $p): string => escapeshellarg('/data/' . basename($p)), $pdfs));
}

$json = shell_exec($command . ' 2>/dev/null');
if (isset($argv[2]) && is_string($json)) {
    file_put_contents($argv[2], $json);
}
$report = is_string($json) ? json_decode($json, true) : null;
$jobs = is_array($report) ? ($report['report']['jobs'] ?? null) : null;
if (!is_array($jobs) || count($jobs) !== count($pdfs)) {
    fwrite(STDERR, "veraPDF produced no usable report for " . count($pdfs) . " file(s)\n");
    exit(2);
}

$bad = 0;
foreach ($jobs as $job) {
    $name = basename((string) ($job['itemDetails']['name'] ?? '?'));
    $result = $job['validationResult'][0] ?? null;
    if ($result === null) {
        fwrite(STDERR, "FAIL $name: no validation result (" . json_encode($job['taskResult'] ?? $job) . ")\n");
        $bad++;
        continue;
    }
    if (($result['compliant'] ?? false) === true) {
        echo "ok $name ({$result['details']['passedRules']} rules passed)\n";
        continue;
    }
    $bad++;
    fwrite(STDERR, "FAIL $name: {$result['details']['failedRules']} failed rule(s)\n");
    foreach ($result['details']['ruleSummaries'] ?? [] as $rule) {
        fwrite(STDERR, sprintf(
            "  %s test %s (%d failed): %s\n",
            $rule['clause'] ?? '?',
            $rule['testNumber'] ?? '?',
            $rule['failedChecks'] ?? 0,
            $rule['description'] ?? '',
        ));
    }
}
echo count($jobs) - $bad . '/' . count($jobs) . " compliant with PDF/UA-1\n";
exit($bad === 0 ? 0 : 1);
