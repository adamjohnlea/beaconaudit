<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Services;

use App\Modules\Notification\Domain\Repositories\EmailSubscriptionRepositoryInterface;
use App\Modules\Reporting\Application\Services\PdfReportDataCollectorInterface;
use App\Modules\Reporting\Application\Services\PdfReportServiceInterface;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use Twig\Environment;

final readonly class AuditReportNotifier
{
    public function __construct(
        private ProjectRepositoryInterface $projectRepository,
        private EmailSubscriptionRepositoryInterface $subscriptionRepository,
        private PdfReportDataCollectorInterface $pdfReportDataCollector,
        private PdfReportServiceInterface $pdfReportService,
        private EmailServiceInterface $emailService,
        private Environment $twig,
    ) {
    }

    public function notifyForProject(int $projectId): void
    {
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            return;
        }

        $subscribers = $this->subscriptionRepository->findByProjectId($projectId);
        if ($subscribers === []) {
            return;
        }

        $reportData = $this->pdfReportDataCollector->collect($project);
        $pdf = $this->pdfReportService->generate($reportData);

        $projectName = $project->getName()->getValue();
        $filename = 'report-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($projectName)) . '.pdf';
        $subject = 'Audit Report: ' . $projectName;

        $body = $this->twig->render('emails/audit-report.twig', [
            'projectName' => $projectName,
            'date' => date('Y-m-d H:i'),
            'averageScore' => $reportData->getSummary()->getAverageScore(),
            'totalUrls' => $reportData->getSummary()->getTotalUrls(),
            'totalIssues' => $reportData->getTotalIssues(),
        ]);

        foreach ($subscribers as $subscriber) {
            try {
                $this->emailService->sendWithAttachment(
                    $subscriber->getEmail()->getValue(),
                    $subject,
                    $body,
                    $pdf,
                    $filename,
                );
            } catch (\Throwable $e) {
                error_log(
                    'Failed to send audit report email to '
                    . $subscriber->getEmail()->getValue()
                    . ' for project ' . $projectName
                    . ': ' . $e->getMessage(),
                );
            }
        }
    }
}
