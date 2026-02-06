<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\ValueObjects;

enum IssueSeverity: string
{
    case CRITICAL = 'critical';
    case SERIOUS = 'serious';
    case MODERATE = 'moderate';
    case MINOR = 'minor';

    public function label(): string
    {
        return match ($this) {
            self::CRITICAL => 'Critical',
            self::SERIOUS => 'Serious',
            self::MODERATE => 'Moderate',
            self::MINOR => 'Minor',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::CRITICAL => 4,
            self::SERIOUS => 3,
            self::MODERATE => 2,
            self::MINOR => 1,
        };
    }
}
