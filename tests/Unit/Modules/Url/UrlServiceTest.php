<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Url;

use App\Modules\Url\Application\Services\UrlService;
use App\Modules\Url\Domain\Models\Url;
use App\Modules\Url\Domain\Repositories\UrlRepositoryInterface;
use App\Modules\Url\Domain\ValueObjects\AuditFrequency;
use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Shared\Exceptions\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UrlServiceTest extends TestCase
{
    private UrlRepositoryInterface&MockObject $urlRepository;
    private UrlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->urlRepository = $this->createMock(UrlRepositoryInterface::class);
        $this->service = new UrlService($this->urlRepository);
    }

    public function test_create_saves_url_and_returns_it(): void
    {
        $this->urlRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Url $url): Url {
                $url->setId(1);
                return $url;
            });

        $result = $this->service->create(
            url: 'https://example.com',
            name: 'Example',
            frequency: 'weekly',
        );

        $this->assertSame(1, $result->getId());
        $this->assertSame('https://example.com', $result->getUrl()->getValue());
        $this->assertSame('Example', $result->getName());
        $this->assertSame(AuditFrequency::WEEKLY, $result->getAuditFrequency());
        $this->assertTrue($result->isEnabled());
    }

    public function test_create_with_project_id(): void
    {
        $this->urlRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (Url $url): Url {
                $url->setId(1);
                return $url;
            });

        $result = $this->service->create(
            url: 'https://example.com',
            name: 'Example',
            frequency: 'daily',
            projectId: 5,
        );

        $this->assertSame(5, $result->getProjectId());
        $this->assertSame(AuditFrequency::DAILY, $result->getAuditFrequency());
    }

    public function test_create_throws_exception_for_invalid_url(): void
    {
        $this->urlRepository->expects($this->never())->method('save');

        $this->expectException(ValidationException::class);

        $this->service->create(
            url: 'not-a-url',
            name: 'Invalid',
            frequency: 'weekly',
        );
    }

    public function test_create_throws_exception_for_invalid_frequency(): void
    {
        $this->urlRepository->expects($this->never())->method('save');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid audit frequency');

        $this->service->create(
            url: 'https://example.com',
            name: 'Example',
            frequency: 'invalid',
        );
    }

    public function test_update_modifies_existing_url(): void
    {
        $existingUrl = $this->makeUrl(1, 'https://example.com', 'Original');

        $this->urlRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($existingUrl);

        $this->urlRepository
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (Url $url): Url {
                return $url;
            });

        $result = $this->service->update(
            id: 1,
            name: 'Updated',
            frequency: 'daily',
            enabled: false,
        );

        $this->assertSame('Updated', $result->getName());
        $this->assertSame(AuditFrequency::DAILY, $result->getAuditFrequency());
        $this->assertFalse($result->isEnabled());
    }

    public function test_update_throws_exception_when_url_not_found(): void
    {
        $this->urlRepository
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('URL not found');

        $this->service->update(id: 999, name: 'Updated');
    }

    public function test_delete_removes_url(): void
    {
        $existingUrl = $this->makeUrl(1, 'https://example.com', 'Example');

        $this->urlRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($existingUrl);

        $this->urlRepository
            ->expects($this->once())
            ->method('delete')
            ->with(1);

        $this->service->delete(1);
    }

    public function test_delete_throws_exception_when_url_not_found(): void
    {
        $this->urlRepository
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('URL not found');

        $this->service->delete(999);
    }

    public function test_find_by_id_returns_url(): void
    {
        $url = $this->makeUrl(1, 'https://example.com', 'Example');

        $this->urlRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($url);

        $result = $this->service->findById(1);

        $this->assertNotNull($result);
        $this->assertSame(1, $result->getId());
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $this->urlRepository
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->assertNull($this->service->findById(999));
    }

    public function test_find_all_returns_all_urls(): void
    {
        $urls = [
            $this->makeUrl(1, 'https://one.com', 'One'),
            $this->makeUrl(2, 'https://two.com', 'Two'),
        ];

        $this->urlRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($urls);

        $result = $this->service->findAll();

        $this->assertCount(2, $result);
    }

    private function makeUrl(int $id, string $address, string $name): Url
    {
        $now = new DateTimeImmutable();

        $url = new Url(
            id: $id,
            projectId: null,
            url: new UrlAddress($address),
            name: $name,
            auditFrequency: AuditFrequency::WEEKLY,
            enabled: true,
            alertThresholdScore: null,
            alertThresholdDrop: null,
            lastAuditedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        return $url;
    }
}
