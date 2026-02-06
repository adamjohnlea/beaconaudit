<?php

declare(strict_types=1);

namespace App\Modules\Url\Domain\Models;

use App\Modules\Url\Domain\ValueObjects\ProjectName;
use DateTimeImmutable;

final class Project
{
    public function __construct(
        private ?int $id,
        private ProjectName $name,
        private ?string $description,
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

    public function getName(): ProjectName
    {
        return $this->name;
    }

    public function setName(ProjectName $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
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
