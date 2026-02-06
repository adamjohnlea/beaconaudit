<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\ValueObjects;

enum IssueCategory: string
{
    case COLOR_CONTRAST = 'color_contrast';
    case ARIA = 'aria';
    case FORMS = 'forms';
    case IMAGES = 'images';
    case NAVIGATION = 'navigation';
    case TABLES = 'tables';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::COLOR_CONTRAST => 'Color Contrast',
            self::ARIA => 'ARIA',
            self::FORMS => 'Forms',
            self::IMAGES => 'Images',
            self::NAVIGATION => 'Navigation',
            self::TABLES => 'Tables',
            self::OTHER => 'Other',
        };
    }
}
