<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notification;

use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Dashboard\Domain\ValueObjects\DashboardSummary;
use App\Modules\Notification\Application\Services\AuditReportNotifier;
use App\Modules\Notification\Application\Services\EmailServiceInterface;
use App\Modules\Notification\Domain\Repositories\EmailSubscriptionRepositoryInterface;
use App\Modules\Reporting\Application\Services\PdfReportDataCollectorInterface;
use App\Modules\Reporting\Application\Services\PdfReportServiceInterface;
use App\Modules\Reporting\Domain\ValueObjects\ProjectReportData;
use App\Modules\Url\Domain\Models\Project;
use App\Modules\Url\Domain\Repositories\ProjectRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\ProjectName;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class AuditReportNotifierTest extends TestCase
{
    private ProjectRepositoryInterface&MockObject $projectRepository;
    private EmailSubscriptionRepositoryInterface&MockObject $subscriptionRepository;
    private PdfReportDataCollectorInterface&MockObject $pdfReportDataCollector;
    private PdfReportServiceInterface&MockObject $pdfReportService;
    private EmailServiceInterface&MockObject $emailService;
    private Environment&MockObject $twig;
    private AuditReportNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRepository = $this->createMock(ProjectRepositoryInterface::class);
        $this->subscriptionRepository = $this->createMock(EmailSubscriptionRepositoryInterface::class);
        $this->pdfReportDataCollector = $this->createMock(PdfReportDataCollectorInterface::class);
        $this->pdfReportService = $this->createMock(PdfReportServiceInterface::class);
        $this->emailService = $this->createMock(EmailServiceInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->notifier = new AuditReportNotifier(
            $this->projectRepository,
            $this->subscriptionRepository,
            $this->pdfReportDataCollector,
            $this->pdfReportService,
            $this->emailService,
            $this->twig,
        );
    }

    #[Test]
    public function it_sends_email_with_pdf_to_subscribers(): void
    {
        $project = $this->makeProject(1, 'My Project');
        $user = $this->makeUser(1, 'user@example.com');
        $reportData = $this->makeReportData();

        $this->projectRepository->method('findById')->with(1)->willReturn($project);
        $this->subscriptionRepository->method('findByProjectId')->with(1)->willReturn([$user]);
        $this->pdfReportDataCollector->method('collect')->with($project)->willReturn($reportData);
        $this->pdfReportService->method('generate')->with($reportData)->willReturn('pdf-content');
        $this->twig->method('render')->willReturn('Email body text');

        $this->emailService->expects(self::once())
            ->method('sendWithAttachment')
            ->with(
                'user@example.com',
                'Audit Report: My Project',
                'Email body text',
                'pdf-content',
                'report-my-project.pdf',
            );

        $this->notifier->notifyForProject(1);
    }

    #[Test]
    public function it_sends_to_multiple_subscribers(): void
    {
        $project = $this->makeProject(1, 'My Project');
        $user1 = $this->makeUser(1, 'user1@example.com');
        $user2 = $this->makeUser(2, 'user2@example.com');
        $reportData = $this->makeReportData();

        $this->projectRepository->method('findById')->with(1)->willReturn($project);
        $this->subscriptionRepository->method('findByProjectId')->with(1)->willReturn([$user1, $user2]);
        $this->pdfReportDataCollector->method('collect')->willReturn($reportData);
        $this->pdfReportService->method('generate')->willReturn('pdf-content');
        $this->twig->method('render')->willReturn('Email body');

        $this->emailService->expects(self::exactly(2))->method('sendWithAttachment');

        $this->notifier->notifyForProject(1);
    }

    #[Test]
    public function it_returns_early_if_project_not_found(): void
    {
        $this->projectRepository->method('findById')->with(99)->willReturn(null);
        $this->subscriptionRepository->expects(self::never())->method('findByProjectId');
        $this->emailService->expects(self::never())->method('sendWithAttachment');

        $this->notifier->notifyForProject(99);
    }

    #[Test]
    public function it_returns_early_if_no_subscribers(): void
    {
        $project = $this->makeProject(1, 'Empty Project');

        $this->projectRepository->method('findById')->with(1)->willReturn($project);
        $this->subscriptionRepository->method('findByProjectId')->with(1)->willReturn([]);
        $this->pdfReportDataCollector->expects(self::never())->method('collect');
        $this->emailService->expects(self::never())->method('sendWithAttachment');

        $this->notifier->notifyForProject(1);
    }

    #[Test]
    public function it_continues_sending_if_one_subscriber_fails(): void
    {
        $project = $this->makeProject(1, 'My Project');
        $user1 = $this->makeUser(1, 'fail@example.com');
        $user2 = $this->makeUser(2, 'success@example.com');
        $reportData = $this->makeReportData();

        $this->projectRepository->method('findById')->willReturn($project);
        $this->subscriptionRepository->method('findByProjectId')->willReturn([$user1, $user2]);
        $this->pdfReportDataCollector->method('collect')->willReturn($reportData);
        $this->pdfReportService->method('generate')->willReturn('pdf-content');
        $this->twig->method('render')->willReturn('Email body');

        $callCount = 0;
        $this->emailService->expects(self::exactly(2))
            ->method('sendWithAttachment')
            ->willReturnCallback(function () use (&$callCount): void {
                $callCount++;
                if ($callCount === 1) {
                    throw new \RuntimeException('SES error');
                }
            });

        $this->notifier->notifyForProject(1);
    }

    private function makeProject(int $id, string $name): Project
    {
        return new Project(
            id: $id,
            name: new ProjectName($name),
            description: null,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
    }

    private function makeUser(int $id, string $email): User
    {
        return new User(
            id: $id,
            email: new EmailAddress($email),
            password: HashedPassword::fromHash('$2y$10$fakehashfakehashfakehashfakehashfakehashfakehashfake'),
            role: UserRole::Viewer,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
    }

    private function makeReportData(): ProjectReportData
    {
        return new ProjectReportData(
            projectName: 'My Project',
            generatedAt: '2025-01-01 12:00:00',
            summary: new DashboardSummary(
                totalUrls: 5,
                totalAudits: 10,
                averageScore: 85,
                urlsNeedingAttention: 1,
                scoreDistribution: ['excellent' => 3, 'good' => 1, 'needsWork' => 1, 'poor' => 0],
            ),
            urlSummaries: [],
            issuesByCategory: [],
            totalIssues: 3,
            severityCounts: ['critical' => 0, 'serious' => 1, 'moderate' => 1, 'minor' => 1],
        );
    }
}
