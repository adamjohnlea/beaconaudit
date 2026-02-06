<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Url;

use App\Modules\Url\Domain\ValueObjects\BulkImportResult;
use PHPUnit\Framework\TestCase;

final class BulkImportResultTest extends TestCase
{
    public function test_get_total_processed_returns_sum(): void
    {
        $result = new BulkImportResult(
            importedCount: 3,
            skippedCount: 2,
            errors: [
                ['line' => 1, 'url' => 'bad-url', 'error' => 'Invalid URL format'],
            ],
        );

        $this->assertSame(6, $result->getTotalProcessed());
    }

    public function test_has_errors_returns_true_when_errors_exist(): void
    {
        $result = new BulkImportResult(
            importedCount: 1,
            skippedCount: 0,
            errors: [
                ['line' => 2, 'url' => 'not-valid', 'error' => 'Invalid URL format'],
            ],
        );

        $this->assertTrue($result->hasErrors());
    }

    public function test_has_errors_returns_false_when_no_errors(): void
    {
        $result = new BulkImportResult(
            importedCount: 5,
            skippedCount: 1,
            errors: [],
        );

        $this->assertFalse($result->hasErrors());
    }
}
