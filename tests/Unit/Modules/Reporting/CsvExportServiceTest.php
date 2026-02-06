<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Reporting;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Reporting\Application\Services\CsvExportService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CsvExportServiceTest extends TestCase
{
    private CsvExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CsvExportService();
    }

    public function test_generates_csv_with_headers(): void
    {
        $csv = $this->service->exportAudits([], 'https://example.com');

        $lines = explode("\n", trim($csv));
        $this->assertSame('Date,Score,Status,Grade', $lines[0]);
    }

    public function test_generates_csv_with_audit_data(): void
    {
        $audits = [
            $this->makeAudit(85, '2024-01-15', AuditStatus::COMPLETED),
            $this->makeAudit(70, '2024-01-08', AuditStatus::COMPLETED),
        ];

        $csv = $this->service->exportAudits($audits, 'https://example.com');

        $lines = explode("\n", trim($csv));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('2024-01-15', $lines[1]);
        $this->assertStringContainsString('85', $lines[1]);
        $this->assertStringContainsString('Completed', $lines[1]);
        $this->assertStringContainsString('B', $lines[1]);
    }

    public function test_generates_csv_with_failed_audit(): void
    {
        $audits = [
            $this->makeAudit(0, '2024-01-15', AuditStatus::FAILED),
        ];

        $csv = $this->service->exportAudits($audits, 'https://example.com');

        $lines = explode("\n", trim($csv));
        $this->assertStringContainsString('Failed', $lines[1]);
    }

    public function test_generates_correct_grades(): void
    {
        $audits = [
            $this->makeAudit(95, '2024-01-04', AuditStatus::COMPLETED),
            $this->makeAudit(75, '2024-01-03', AuditStatus::COMPLETED),
            $this->makeAudit(55, '2024-01-02', AuditStatus::COMPLETED),
            $this->makeAudit(30, '2024-01-01', AuditStatus::COMPLETED),
        ];

        $csv = $this->service->exportAudits($audits, 'https://example.com');

        $lines = explode("\n", trim($csv));
        $this->assertStringContainsString(',A', $lines[1]);
        $this->assertStringContainsString(',B', $lines[2]);
        $this->assertStringContainsString(',C', $lines[3]);
        $this->assertStringContainsString(',F', $lines[4]);
    }

    public function test_export_summary_generates_csv_for_all_urls(): void
    {
        /** @var array<array{name: string, address: string, score: int|null, audits: int, frequency: string}> $urlData */
        $urlData = [
            ['name' => 'Example', 'address' => 'https://example.com', 'score' => 85, 'audits' => 5, 'frequency' => 'Weekly'],
            ['name' => 'Test', 'address' => 'https://test.com', 'score' => null, 'audits' => 0, 'frequency' => 'Daily'],
        ];

        $csv = $this->service->exportSummary($urlData);

        $lines = explode("\n", trim($csv));
        $this->assertSame('Name,URL,Latest Score,Total Audits,Frequency', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('Example', $lines[1]);
        $this->assertStringContainsString('85', $lines[1]);
        $this->assertStringContainsString('N/A', $lines[2]);
    }

    private function makeAudit(int $score, string $date, AuditStatus $status): Audit
    {
        $auditDate = new DateTimeImmutable($date);

        return new Audit(
            id: null,
            urlId: 1,
            score: new AccessibilityScore($score),
            status: $status,
            auditDate: $auditDate,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $auditDate,
        );
    }
}
