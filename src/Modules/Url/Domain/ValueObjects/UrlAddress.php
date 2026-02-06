<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\ValueObjects;

use App\Shared\Exceptions\ValidationException;
use Stringable;

final readonly class UrlAddress implements Stringable
{
    private string $value;

    public function __construct(string $url)
    {
        $url = trim($url);

        if ($url === '') {
            throw new ValidationException('URL cannot be empty');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException('Invalid URL format');
        }

        $parsed = parse_url($url);

        if ($parsed === false || !isset($parsed['host']) || $parsed['host'] === '') {
            throw new ValidationException('Invalid URL format');
        }

        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'], true)) {
            throw new ValidationException('URL must use http or https scheme');
        }

        $this->value = rtrim($url, '/');
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
