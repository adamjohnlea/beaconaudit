<?php

declare(strict_types=1);

namespace App\Modules\Url\Application\Services;

use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;

final readonly class UrlService
{
    public function __construct(
        private UrlRepositoryInterface $urlRepository,
    ) {
    }

    public function create(
        string $url,
        string $name,
        string $frequency,
        ?int $projectId = null,
    ): Url {
        $urlAddress = new UrlAddress($url);

        $existing = $this->urlRepository->findByUrl($urlAddress->getValue());
        if ($existing !== null) {
            throw new ValidationException('This URL has already been added.');
        }

        $auditFrequency = $this->resolveFrequency($frequency);
        $now = new DateTimeImmutable();

        $urlModel = new Url(
            id: null,
            projectId: $projectId,
            url: $urlAddress,
            name: $name,
            auditFrequency: $auditFrequency,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        return $this->urlRepository->save($urlModel);
    }

    public function update(
        int $id,
        ?string $name = null,
        ?string $frequency = null,
        ?bool $enabled = null,
        ?int $projectId = null,
    ): Url {
        $url = $this->urlRepository->findById($id);

        if ($url === null) {
            throw new ValidationException('URL not found');
        }

        if ($name !== null) {
            $url->setName($name);
        }

        if ($frequency !== null) {
            $url->setAuditFrequency($this->resolveFrequency($frequency));
        }

        if ($enabled !== null) {
            $url->setEnabled($enabled);
        }

        if ($projectId !== null) {
            $url->setProjectId($projectId);
        }

        $url->setUpdatedAt(new DateTimeImmutable());

        return $this->urlRepository->update($url);
    }

    public function delete(int $id): void
    {
        $url = $this->urlRepository->findById($id);

        if ($url === null) {
            throw new ValidationException('URL not found');
        }

        $this->urlRepository->delete($id);
    }

    public function findById(int $id): ?Url
    {
        return $this->urlRepository->findById($id);
    }

    /**
     * @return array<Url>
     */
    public function findAll(): array
    {
        return $this->urlRepository->findAll();
    }

    private function resolveFrequency(string $frequency): AuditFrequency
    {
        $auditFrequency = AuditFrequency::tryFrom($frequency);

        if ($auditFrequency === null) {
            throw new ValidationException('Invalid audit frequency: ' . $frequency);
        }

        return $auditFrequency;
    }
}
