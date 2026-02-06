<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Services;

use App\Modules\Audit\Domain\Models\Audit;
use App\Modules\Audit\Domain\ValueObjects\Trend;

final class TrendCalculator
{
    /**
     * @param array<Audit> $audits
     */
    public function calculateTrend(array $audits): Trend
    {
        if (count($audits) <= 1) {
            return Trend::STABLE;
        }

        $first = $audits[0];
        $last = $audits[count($audits) - 1];
        $delta = $last->getScore()->delta($first->getScore());

        return Trend::fromDelta($delta);
    }

    /**
     * @param  array<Audit>                           $audits
     * @return array<array{score: int, date: string}>
     */
    public function generateGraphData(array $audits): array
    {
        $data = [];

        foreach ($audits as $audit) {
            $data[] = [
                'score' => $audit->getScore()->getValue(),
                'date' => $audit->getAuditDate()->format('Y-m-d'),
            ];
        }

        return $data;
    }

    /**
     * @param array<Audit> $audits
     */
    public function calculateAverage(array $audits): int
    {
        if ($audits === []) {
            return 0;
        }

        $total = 0;

        foreach ($audits as $audit) {
            $total += $audit->getScore()->getValue();
        }

        return (int) round($total / count($audits));
    }
}
