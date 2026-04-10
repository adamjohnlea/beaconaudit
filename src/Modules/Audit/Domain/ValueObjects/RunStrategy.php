<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\ValueObjects;

enum RunStrategy: string
{
    case DESKTOP = 'desktop';
    case MOBILE = 'mobile';

    public function label(): string
    {
        return match ($this) {
            RunStrategy::DESKTOP => 'Desktop',
            RunStrategy::MOBILE => 'Mobile',
        };
    }
}
