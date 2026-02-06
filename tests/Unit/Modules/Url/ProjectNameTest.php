<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Url;

use App\Modules\Url\Domain\ValueObjects\ProjectName;
use App\Shared\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProjectNameTest extends TestCase
{
    public function test_can_be_created_with_valid_name(): void
    {
        $name = new ProjectName('My Project');

        $this->assertSame('My Project', $name->getValue());
    }

    public function test_trims_whitespace(): void
    {
        $name = new ProjectName('  My Project  ');

        $this->assertSame('My Project', $name->getValue());
    }

    public function test_throws_exception_for_empty_string(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Project name cannot be empty');

        new ProjectName('');
    }

    public function test_throws_exception_for_whitespace_only(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Project name cannot be empty');

        new ProjectName('   ');
    }

    public function test_throws_exception_when_name_exceeds_max_length(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Project name must not exceed 255 characters');

        new ProjectName(str_repeat('a', 256));
    }

    public function test_allows_name_at_max_length(): void
    {
        $name = new ProjectName(str_repeat('a', 255));

        $this->assertSame(255, strlen($name->getValue()));
    }

    public function test_equals_returns_true_for_same_name(): void
    {
        $name1 = new ProjectName('My Project');
        $name2 = new ProjectName('My Project');

        $this->assertTrue($name1->equals($name2));
    }

    public function test_equals_returns_false_for_different_name(): void
    {
        $name1 = new ProjectName('My Project');
        $name2 = new ProjectName('Other Project');

        $this->assertFalse($name1->equals($name2));
    }

    public function test_to_string_returns_value(): void
    {
        $name = new ProjectName('My Project');

        $this->assertSame('My Project', (string) $name);
    }
}
