<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Audit;

use App\Modules\Audit\Domain\ValueObjects\IssueCategory;
use PHPUnit\Framework\TestCase;

final class IssueCategoryTest extends TestCase
{
    public function test_key_cases_exist(): void
    {
        $this->assertSame('color_contrast', IssueCategory::COLOR_CONTRAST->value);
        $this->assertSame('aria', IssueCategory::ARIA->value);
        $this->assertSame('forms', IssueCategory::FORMS->value);
        $this->assertSame('images', IssueCategory::IMAGES->value);
        $this->assertSame('navigation', IssueCategory::NAVIGATION->value);
        $this->assertSame('tables', IssueCategory::TABLES->value);
        $this->assertSame('other', IssueCategory::OTHER->value);
    }

    public function test_label_returns_human_readable_string(): void
    {
        $this->assertSame('Color Contrast', IssueCategory::COLOR_CONTRAST->label());
        $this->assertSame('ARIA', IssueCategory::ARIA->label());
        $this->assertSame('Forms', IssueCategory::FORMS->label());
        $this->assertSame('Images', IssueCategory::IMAGES->label());
    }

    public function test_from_string_creates_valid_category(): void
    {
        $this->assertSame(IssueCategory::ARIA, IssueCategory::from('aria'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(IssueCategory::tryFrom('nonexistent'));
    }
}
