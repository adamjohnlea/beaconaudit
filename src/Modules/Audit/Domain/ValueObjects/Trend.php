<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\ValueObjects;

enum Trend: string
{
    case IMPROVING = 'improving';
    case DEGRADING = 'degrading';
    case STABLE = 'stable';

    public function label(): string
    {
        return match ($this) {
            self::IMPROVING => 'Improving',
            self::DEGRADING => 'Degrading',
            self::STABLE => 'Stable',
        };
    }

    public static function fromDelta(int $delta): self
    {
        return match (true) {
            $delta > 0 => self::IMPROVING,
            $delta < 0 => self::DEGRADING,
            default => self::STABLE,
        };
    }
}
