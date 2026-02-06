<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\ValueObjects;

final readonly class BulkImportResult
{
    /**
     * @param array<array{line: int, url: string, error: string}> $errors
     */
    public function __construct(
        public int $importedCount,
        public int $skippedCount,
        public array $errors,
    ) {
    }

    public function getTotalProcessed(): int
    {
        return $this->importedCount + $this->skippedCount + count($this->errors);
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
