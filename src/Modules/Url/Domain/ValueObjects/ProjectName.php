<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\ValueObjects;

use App\Shared\Exceptions\ValidationException;
use Stringable;

final readonly class ProjectName implements Stringable
{
    private const int MAX_LENGTH = 255;

    private string $value;

    public function __construct(string $name)
    {
        $name = trim($name);

        if ($name === '') {
            throw new ValidationException('Project name cannot be empty');
        }

        if (strlen($name) > self::MAX_LENGTH) {
            throw new ValidationException('Project name must not exceed 255 characters');
        }

        $this->value = $name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
