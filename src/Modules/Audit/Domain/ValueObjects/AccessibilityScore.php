<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\ValueObjects;

use App\Shared\Exceptions\ValidationException;

final readonly class AccessibilityScore
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 0 || $value > 100) {
            throw new ValidationException('Score must be between 0 and 100');
        }

        $this->value = $value;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function delta(self $previous): int
    {
        return $this->value - $previous->value;
    }

    public function grade(): string
    {
        return match (true) {
            $this->value >= 90 => 'Excellent',
            $this->value >= 70 => 'Good',
            $this->value >= 50 => 'Needs Improvement',
            default => 'Poor',
        };
    }
}
