<?php

declare(strict_types=1);

namespace App\Modules\Url\Application\Services;

use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\BulkImportResult;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;

final readonly class BulkImportService
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
    ) {
    }

    public function importFromList(string $text, string $frequency, ?int $projectId): BulkImportResult
    {
        $lines = explode("\n", $text);
        $rows = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $rows[] = ['url' => $trimmed, 'name' => $trimmed, 'frequency' => $frequency];
        }

        return $this->processRows($rows, $frequency, $projectId);
    }

    public function importFromCsv(string $csvContent, string $defaultFrequency, ?int $projectId): BulkImportResult
    {
        $lines = explode("\n", $csvContent);
        $lines = array_filter($lines, static fn (string $line): bool => trim($line) !== '');

        if ($lines === []) {
            return new BulkImportResult(0, 0, []);
        }

        $headerLine = array_shift($lines);
        /** @var array<string> $headers */
        $headers = str_getcsv($headerLine);
        $headers = array_map(static fn (string $h): string => strtolower(trim($h)), $headers);

        $urlIndex = array_search('url', $headers, true);
        if ($urlIndex === false) {
            return new BulkImportResult(0, 0, [['line' => 1, 'url' => '', 'error' => 'CSV must contain a "url" column header']]);
        }

        $nameIndex = array_search('name', $headers, true);
        $frequencyIndex = array_search('frequency', $headers, true);

        $rows = [];
        $lineNumber = 1;
        foreach ($lines as $line) {
            $lineNumber++;
            /** @var array<string> $fields */
            $fields = str_getcsv($line);

            $url = isset($fields[$urlIndex]) ? trim($fields[$urlIndex]) : '';
            if ($url === '') {
                continue;
            }

            $name = ($nameIndex !== false && isset($fields[$nameIndex]) && trim($fields[$nameIndex]) !== '')
                ? trim($fields[$nameIndex])
                : $url;

            $frequency = ($frequencyIndex !== false && isset($fields[$frequencyIndex]) && trim($fields[$frequencyIndex]) !== '')
                ? trim($fields[$frequencyIndex])
                : $defaultFrequency;

            $rows[] = ['url' => $url, 'name' => $name, 'frequency' => $frequency];
        }

        return $this->processRows($rows, $defaultFrequency, $projectId);
    }

    /**
     * @param array<array{url: string, name: string, frequency: string}> $rows
     */
    private function processRows(array $rows, string $defaultFrequency, ?int $projectId): BulkImportResult
    {
        $existingUrls = $this->urlRepository->findAll();
        /** @var array<string, true> $existingSet */
        $existingSet = [];
        foreach ($existingUrls as $existing) {
            $existingSet[$existing->getUrl()->getValue()] = true;
        }

        /** @var array<string, true> $importedSet */
        $importedSet = [];
        $importedCount = 0;
        $skippedCount = 0;
        /** @var array<array{line: int, url: string, error: string}> $errors */
        $errors = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 1;

            try {
                $urlAddress = new UrlAddress($row['url']);
            } catch (ValidationException $e) {
                $errors[] = ['line' => $lineNumber, 'url' => $row['url'], 'error' => $e->getMessage()];
                continue;
            }

            $urlValue = $urlAddress->getValue();

            if (isset($existingSet[$urlValue]) || isset($importedSet[$urlValue])) {
                $skippedCount++;
                continue;
            }

            $frequency = AuditFrequency::tryFrom($row['frequency']) ?? AuditFrequency::tryFrom($defaultFrequency) ?? AuditFrequency::WEEKLY;
            $now = new DateTimeImmutable();

            $urlModel = new Url(
                id: null,
                projectId: $projectId,
                url: $urlAddress,
                name: $row['name'],
                auditFrequency: $frequency,
                enabled: true,
                alertThresholdScore: null,
                alertThresholdDrop: null,
                lastAuditedAt: null,
                createdAt: $now,
                updatedAt: $now,
            );

            $this->urlRepository->save($urlModel);
            $importedSet[$urlValue] = true;
            $importedCount++;
        }

        return new BulkImportResult($importedCount, $skippedCount, $errors);
    }
}
