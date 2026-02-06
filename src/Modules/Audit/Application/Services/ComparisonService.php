<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\Models\AuditComparison;
use App\Modules\Audit\Domain\ValueObjects\ScoreDelta;
use App\Modules\Audit\Domain\ValueObjects\Trend;
use DateTimeImmutable;

final class ComparisonService
{
    public function compare(Audit $current, Audit $previous): AuditComparison
    {
        $delta = $current->getScore()->delta($previous->getScore());

        $currentDescriptions = $this->getIssueDescriptions($current);
        $previousDescriptions = $this->getIssueDescriptions($previous);

        $newIssues = array_diff($currentDescriptions, $previousDescriptions);
        $resolvedIssues = array_diff($previousDescriptions, $currentDescriptions);
        $persistentIssues = array_intersect($currentDescriptions, $previousDescriptions);

        return new AuditComparison(
            id: null,
            currentAuditId: $current->getId() ?? 0,
            previousAuditId: $previous->getId() ?? 0,
            scoreDelta: new ScoreDelta($delta),
            newIssuesCount: count($newIssues),
            resolvedIssuesCount: count($resolvedIssues),
            persistentIssuesCount: count($persistentIssues),
            trend: Trend::fromDelta($delta),
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @return array<string>
     */
    private function getIssueDescriptions(Audit $audit): array
    {
        $descriptions = [];

        foreach ($audit->getIssues() as $issue) {
            $descriptions[] = $issue->getDescription();
        }

        return $descriptions;
    }
}
