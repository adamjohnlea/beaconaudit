<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notification;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\AccessibilityScore;
use App\Modules\Audit\Domain\ValueObjects\AuditStatus;
use App\Modules\Audit\Domain\ValueObjects\RunStrategy;
use App\Modules\Auth\Domain\Models\User;
use App\Modules\Auth\Domain\ValueObjects\EmailAddress;
use App\Modules\Auth\Domain\ValueObjects\HashedPassword;
use App\Modules\Auth\Domain\ValueObjects\UserRole;
use App\Modules\Notification\Application\Services\AlertNotifier;
use App\Modules\Notification\Application\Services\EmailServiceInterface;
use App\Modules\Notification\Domain\Repositories\EmailSubscriptionRepositoryInterface;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\AuditStrategy;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

final class AlertNotifierTest extends TestCase
{
    private EmailSubscriptionRepositoryInterface&MockObject $subscriptionRepository;
    private EmailServiceInterface&MockObject $emailService;
    private Environment&MockObject $twig;
    private AlertNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionRepository = $this->createMock(EmailSubscriptionRepositoryInterface::class);
        $this->emailService = $this->createMock(EmailServiceInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->notifier = new AlertNotifier(
            $this->subscriptionRepository,
            $this->emailService,
            $this->twig,
        );
    }

    #[Test]
    public function it_does_nothing_when_alerts_are_disabled(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: false, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 50);

        $this->subscriptionRepository->expects(self::never())->method('findByProjectId');
        $this->emailService->expects(self::never())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_does_nothing_when_url_has_no_project(): void
    {
        $url = $this->makeUrl(projectId: null, alertsEnabled: true, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 50);

        $this->subscriptionRepository->expects(self::never())->method('findByProjectId');
        $this->emailService->expects(self::never())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_does_nothing_when_no_thresholds_are_set(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: null, thresholdDrop: null);
        $audit = $this->makeAudit(urlId: 1, score: 50);

        $this->subscriptionRepository->expects(self::never())->method('findByProjectId');
        $this->emailService->expects(self::never())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_does_nothing_when_score_is_above_threshold(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 85);

        $this->subscriptionRepository->expects(self::never())->method('findByProjectId');
        $this->emailService->expects(self::never())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_sends_alert_when_score_falls_at_threshold(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 70);
        $subscriber = $this->makeUser('admin@example.com');

        $this->subscriptionRepository->method('findByProjectId')->with(1)->willReturn([$subscriber]);
        $this->twig->method('render')->willReturn('Alert body');

        $this->emailService->expects(self::once())
            ->method('send')
            ->with('admin@example.com', self::anything(), 'Alert body');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_sends_alert_when_score_falls_below_threshold(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 55);
        $subscriber = $this->makeUser('admin@example.com');

        $this->subscriptionRepository->method('findByProjectId')->with(1)->willReturn([$subscriber]);
        $this->twig->method('render')->willReturn('Alert body');

        $this->emailService->expects(self::once())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_sends_alert_when_score_drops_by_threshold(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: null, thresholdDrop: 10);
        $audit = $this->makeAudit(urlId: 1, score: 70);
        $previous = $this->makeAudit(urlId: 1, score: 85);
        $subscriber = $this->makeUser('admin@example.com');

        $this->subscriptionRepository->method('findByProjectId')->with(1)->willReturn([$subscriber]);
        $this->twig->method('render')->willReturn('Alert body');

        $this->emailService->expects(self::once())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, $previous);
    }

    #[Test]
    public function it_does_not_alert_on_drop_when_no_previous_audit(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: null, thresholdDrop: 10);
        $audit = $this->makeAudit(urlId: 1, score: 50);

        $this->subscriptionRepository->expects(self::never())->method('findByProjectId');
        $this->emailService->expects(self::never())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_does_not_alert_when_drop_is_below_threshold(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: null, thresholdDrop: 10);
        $audit = $this->makeAudit(urlId: 1, score: 78);
        $previous = $this->makeAudit(urlId: 1, score: 85);

        $this->subscriptionRepository->expects(self::never())->method('findByProjectId');
        $this->emailService->expects(self::never())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, $previous);
    }

    #[Test]
    public function it_does_nothing_when_no_subscribers(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 50);

        $this->subscriptionRepository->method('findByProjectId')->with(1)->willReturn([]);
        $this->emailService->expects(self::never())->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_sends_to_all_subscribers(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 50);
        $subscriber1 = $this->makeUser('user1@example.com');
        $subscriber2 = $this->makeUser('user2@example.com');

        $this->subscriptionRepository->method('findByProjectId')->willReturn([$subscriber1, $subscriber2]);
        $this->twig->method('render')->willReturn('Alert body');

        $this->emailService->expects(self::exactly(2))->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_continues_sending_if_one_subscriber_fails(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: 70);
        $audit = $this->makeAudit(urlId: 1, score: 50);
        $subscriber1 = $this->makeUser('fail@example.com');
        $subscriber2 = $this->makeUser('ok@example.com');

        $this->subscriptionRepository->method('findByProjectId')->willReturn([$subscriber1, $subscriber2]);
        $this->twig->method('render')->willReturn('Alert body');

        $callCount = 0;
        $this->emailService->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function () use (&$callCount): void {
                $callCount++;
                if ($callCount === 1) {
                    throw new \RuntimeException('SES error');
                }
            });

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    #[Test]
    public function it_passes_correct_template_data_for_score_threshold_alert(): void
    {
        $url = $this->makeUrl(projectId: 1, alertsEnabled: true, thresholdScore: 70, thresholdDrop: null);
        $audit = $this->makeAudit(urlId: 1, score: 60);
        $subscriber = $this->makeUser('admin@example.com');

        $this->subscriptionRepository->method('findByProjectId')->willReturn([$subscriber]);

        $this->twig->expects(self::once())
            ->method('render')
            ->with('emails/alert-score.twig', self::callback(function (array $data): bool {
                return $data['currentScore'] === 60
                    && $data['thresholdScore'] === 70
                    && $data['scoreThresholdBreached'] === true
                    && $data['dropThresholdBreached'] === false;
            }))
            ->willReturn('Alert body');

        $this->emailService->method('send');

        $this->notifier->notifyIfThresholdBreached($url, $audit, null);
    }

    private function makeUrl(
        ?int $projectId,
        bool $alertsEnabled,
        ?int $thresholdScore = null,
        ?int $thresholdDrop = null,
    ): Url {
        $now = new DateTimeImmutable();

        return new Url(
            id: 1,
            projectId: $projectId,
            url: new UrlAddress('https://example.com'),
            name: 'Example',
            auditFrequency: AuditFrequency::WEEKLY,
            auditStrategy: AuditStrategy::BOTH,
            enabled: true,
            alertsEnabled: $alertsEnabled,
            alertThresholdScore: $thresholdScore,
            alertThresholdDrop: $thresholdDrop,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function makeAudit(int $urlId, int $score): Audit
    {
        $now = new DateTimeImmutable();

        return new Audit(
            id: 1,
            urlId: $urlId,
            score: new AccessibilityScore($score),
            status: AuditStatus::COMPLETED,
            strategy: RunStrategy::DESKTOP,
            auditDate: $now,
            rawResponse: null,
            errorMessage: null,
            retryCount: 0,
            createdAt: $now,
        );
    }

    private function makeUser(string $email): User
    {
        return new User(
            id: 1,
            email: new EmailAddress($email),
            password: HashedPassword::fromHash('$2y$10$fakehashfakehashfakehashfakehashfakehashfakehashfake'),
            role: UserRole::Viewer,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
    }
}
