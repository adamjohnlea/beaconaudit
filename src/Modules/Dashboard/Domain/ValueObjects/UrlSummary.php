<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Domain\ValueObjects;

final readonly class UrlSummary
{
    public function __construct(
        private int $urlId,
        private string $name,
        private string $address,
        private ?int $latestScore,
        private int $totalAudits,
        private string $frequency,
        private bool $enabled,
    ) {
    }

    public function getUrlId(): int
    {
        return $this->urlId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLatestScore(): ?int
    {
        return $this->latestScore;
    }

    public function getTotalAudits(): int
    {
        return $this->totalAudits;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getScoreGrade(): string
    {
        if ($this->latestScore === null) {
            return 'N/A';
        }

        return match (true) {
            $this->latestScore >= 90 => 'A',
            $this->latestScore >= 70 => 'B',
            $this->latestScore >= 50 => 'C',
            default => 'F',
        };
    }
}
