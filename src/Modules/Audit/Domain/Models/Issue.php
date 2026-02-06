<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Models;

use App\Modules\Audit\Domain\ValueObjects\IssueCategory;
use App\Modules\Audit\Domain\ValueObjects\IssueSeverity;
use DateTimeImmutable;

final class Issue
{
    public function __construct(
        private ?int $id,
        private int $auditId,
        private IssueSeverity $severity,
        private IssueCategory $category,
        private string $description,
        private ?string $elementSelector,
        private ?string $helpUrl,
        private DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getAuditId(): int
    {
        return $this->auditId;
    }

    public function getSeverity(): IssueSeverity
    {
        return $this->severity;
    }

    public function getCategory(): IssueCategory
    {
        return $this->category;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getElementSelector(): ?string
    {
        return $this->elementSelector;
    }

    public function getHelpUrl(): ?string
    {
        return $this->helpUrl;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
