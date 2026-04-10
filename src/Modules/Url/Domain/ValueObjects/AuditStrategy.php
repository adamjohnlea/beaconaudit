<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\ValueObjects;

enum AuditStrategy: string
{
    case DESKTOP = 'desktop';
    case MOBILE = 'mobile';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            AuditStrategy::DESKTOP => 'Desktop only',
            AuditStrategy::MOBILE => 'Mobile only',
            AuditStrategy::BOTH => 'Desktop & Mobile',
        };
    }
}
