<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\ValueObjects;

final readonly class ScoreDelta
{
    public function __construct(
        private int $value,
    ) {
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function isImprovement(): bool
    {
        return $this->value > 0;
    }

    public function isDegradation(): bool
    {
        return $this->value < 0;
    }

    public function isStable(): bool
    {
        return $this->value === 0;
    }

    public function absoluteValue(): int
    {
        return abs($this->value);
    }

    public function directionLabel(): string
    {
        if ($this->value > 0) {
            return '+' . $this->value;
        }

        return (string) $this->value;
    }
}
