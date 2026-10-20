<?php

declare(strict_types=1);

namespace Papercut\Export;

use Papercut\Exception\UnsupportedFeature;

/** Collects what an exporter could not render faithfully; strict mode turns drops into exceptions. */
final class Fidelity
{
    /** @var list<FidelityReport> */
    private array $reports = [];

    public function __construct(
        private readonly string $format,
        private readonly ExportOptions $options,
    ) {
    }

    public function approximated(string $feature, string $path): void
    {
        $this->record(new FidelityReport(FidelityLevel::Approximated, $this->format, $feature, $path));
    }

    /** @throws UnsupportedFeature in strict mode */
    public function dropped(string $feature, string $path): void
    {
        $report = new FidelityReport(FidelityLevel::Dropped, $this->format, $feature, $path);
        if ($this->options->strict) {
            throw UnsupportedFeature::from($report);
        }
        $this->record($report);
    }

    /** @return list<FidelityReport> */
    public function reports(): array
    {
        return $this->reports;
    }

    private function record(FidelityReport $report): void
    {
        $this->reports[] = $report;
        $onWarning = $this->options->onWarning();
        if ($onWarning !== null) {
            $onWarning($report->message(), $report);
        }
    }
}
