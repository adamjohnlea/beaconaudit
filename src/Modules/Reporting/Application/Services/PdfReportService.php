<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Services;

use App\Modules\Reporting\Domain\ValueObjects\ProjectReportData;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final readonly class PdfReportService implements PdfReportServiceInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function generate(ProjectReportData $reportData): string
    {
        $html = $this->twig->render('reports/project-pdf.twig', [
            'report' => $reportData,
        ]);

        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
