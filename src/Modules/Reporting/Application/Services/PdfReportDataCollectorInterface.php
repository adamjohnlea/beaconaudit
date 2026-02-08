<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Reporting\Domain\ValueObjects\ProjectReportData;
use App\Modules\Url\Domain\Models\Project;

interface PdfReportDataCollectorInterface
{
    public function collect(Project $project): ProjectReportData;
}
