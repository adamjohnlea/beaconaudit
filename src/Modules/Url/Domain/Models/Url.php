<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\Models;

use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use DateTimeImmutable;

final class Url
{
    public function __construct(
        private ?int $id,
        private ?int $projectId,
        private UrlAddress $url,
        private ?string $name,
        private AuditFrequency $auditFrequency,
        private bool $enabled,
        private ?int $alertThresholdScore,
        private ?int $alertThresholdDrop,
        private ?DateTimeImmutable $lastAuditedAt,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
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

    public function getProjectId(): ?int
    {
        return $this->projectId;
    }

    public function setProjectId(?int $projectId): void
    {
        $this->projectId = $projectId;
    }

    public function getUrl(): UrlAddress
    {
        return $this->url;
    }

    public function setUrl(UrlAddress $url): void
    {
        $this->url = $url;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getAuditFrequency(): AuditFrequency
    {
        return $this->auditFrequency;
    }

    public function setAuditFrequency(AuditFrequency $auditFrequency): void
    {
        $this->auditFrequency = $auditFrequency;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getAlertThresholdScore(): ?int
    {
        return $this->alertThresholdScore;
    }

    public function setAlertThresholdScore(?int $alertThresholdScore): void
    {
        $this->alertThresholdScore = $alertThresholdScore;
    }

    public function getAlertThresholdDrop(): ?int
    {
        return $this->alertThresholdDrop;
    }

    public function setAlertThresholdDrop(?int $alertThresholdDrop): void
    {
        $this->alertThresholdDrop = $alertThresholdDrop;
    }

    public function getLastAuditedAt(): ?DateTimeImmutable
    {
        return $this->lastAuditedAt;
    }

    public function setLastAuditedAt(?DateTimeImmutable $lastAuditedAt): void
    {
        $this->lastAuditedAt = $lastAuditedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
