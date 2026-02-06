<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use DateTimeImmutable;

final readonly class ScheduledAuditRunner
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
        private AuditServiceInterface $auditService,
    ) {
    }

    /**
     * @return array<Audit>
     */
    public function run(): array
    {
        $enabledUrls = $this->urlRepository->findEnabled();
        $results = [];

        foreach ($enabledUrls as $url) {
            if (!$this->isDueForAudit($url)) {
                continue;
            }

            try {
                $results[] = $this->auditService->runAudit($url->getId() ?? 0);
            } catch (\Throwable) {
                // Continue with next URL on failure
            }
        }

        return $results;
    }

    private function isDueForAudit(Url $url): bool
    {
        $lastAuditedAt = $url->getLastAuditedAt();

        if ($lastAuditedAt === null) {
            return true;
        }

        $now = new DateTimeImmutable();
        $intervalHours = $this->getIntervalHours($url->getAuditFrequency());
        $nextDueAt = $lastAuditedAt->modify('+' . $intervalHours . ' hours');

        return $now >= $nextDueAt;
    }

    private function getIntervalHours(AuditFrequency $frequency): int
    {
        return match ($frequency) {
            AuditFrequency::DAILY => 24,
            AuditFrequency::WEEKLY => 168,
            AuditFrequency::BIWEEKLY => 336,
            AuditFrequency::MONTHLY => 720,
        };
    }
}
