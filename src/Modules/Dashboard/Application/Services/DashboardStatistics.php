<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Dashboard\Domain\ValueObjects\DashboardSummary;
use App\Modules\Dashboard\Domain\ValueObjects\UrlSummary;
use App\Modules\Url\Domain\Models\Url;

final class DashboardStatistics
{
    /**
     * @param array<Url>               $urls
     * @param array<int, array<Audit>> $auditsByUrl
     */
    public function calculateSummary(array $urls, array $auditsByUrl): DashboardSummary
    {
        $totalAudits = 0;
        $scoreSum = 0;
        $scoredUrls = 0;
        $needsAttention = 0;
        $distribution = ['excellent' => 0, 'good' => 0, 'needsWork' => 0, 'poor' => 0];

        foreach ($urls as $url) {
            $urlId = $url->getId() ?? 0;
            $audits = $auditsByUrl[$urlId] ?? [];
            $totalAudits += count($audits);

            if ($audits === []) {
                continue;
            }

            $latestScore = $audits[0]->getScore()->getValue();
            $scoreSum += $latestScore;
            $scoredUrls++;

            if ($latestScore < 70) {
                $needsAttention++;
            }

            match (true) {
                $latestScore >= 90 => $distribution['excellent']++,
                $latestScore >= 70 => $distribution['good']++,
                $latestScore >= 50 => $distribution['needsWork']++,
                default => $distribution['poor']++,
            };
        }

        $averageScore = $scoredUrls > 0 ? (int) round($scoreSum / $scoredUrls) : 0;

        return new DashboardSummary(
            totalUrls: count($urls),
            totalAudits: $totalAudits,
            averageScore: $averageScore,
            urlsNeedingAttention: $needsAttention,
            scoreDistribution: $distribution,
        );
    }

    /**
     * @param  array<Url>               $urls
     * @param  array<int, array<Audit>> $auditsByUrl
     * @return array<UrlSummary>
     */
    public function generateUrlSummaries(array $urls, array $auditsByUrl): array
    {
        $summaries = [];

        foreach ($urls as $url) {
            $urlId = $url->getId() ?? 0;
            $audits = $auditsByUrl[$urlId] ?? [];
            $latestScore = $audits !== [] ? $audits[0]->getScore()->getValue() : null;

            $summaries[] = new UrlSummary(
                urlId: $urlId,
                name: $url->getName() ?? $url->getUrl()->getValue(),
                address: $url->getUrl()->getValue(),
                latestScore: $latestScore,
                totalAudits: count($audits),
                frequency: $url->getAuditFrequency()->label(),
                enabled: $url->isEnabled(),
            );
        }

        return $summaries;
    }
}
