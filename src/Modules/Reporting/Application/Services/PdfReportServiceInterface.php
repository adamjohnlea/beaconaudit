<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Reporting\Domain\ValueObjects\ProjectReportData;

interface PdfReportServiceInterface
{
    public function generate(ProjectReportData $reportData): string;
}
