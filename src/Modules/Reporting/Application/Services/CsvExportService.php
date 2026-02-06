<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;

final class CsvExportService
{
    /**
     * @param array<Audit> $audits
     */
    public function exportAudits(array $audits, string $urlAddress): string
    {
        $lines = ['Date,Score,Status,Grade'];

        foreach ($audits as $audit) {
            $score = $audit->getScore()->getValue();
            $lines[] = implode(',', [
                $audit->getAuditDate()->format('Y-m-d H:i:s'),
                (string) $score,
                $audit->getStatus()->label(),
                $this->scoreToGrade($score),
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param array<array{name: string, address: string, score: int|null, audits: int, frequency: string}> $urlData
     */
    public function exportSummary(array $urlData): string
    {
        $lines = ['Name,URL,Latest Score,Total Audits,Frequency'];

        foreach ($urlData as $row) {
            $lines[] = implode(',', [
                $this->escapeCsv($row['name']),
                $this->escapeCsv($row['address']),
                $row['score'] !== null ? (string) $row['score'] : 'N/A',
                (string) $row['audits'],
                $row['frequency'],
            ]);
        }

        return implode("\n", $lines) . "\n";
    }

    private function scoreToGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 70 => 'B',
            $score >= 50 => 'C',
            default => 'F',
        };
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
