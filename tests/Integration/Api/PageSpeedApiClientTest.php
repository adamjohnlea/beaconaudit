<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Modules\Audit\Infrastructure\Api\ApiException;
use App\Modules\Audit\Infrastructure\Api\ApiResponse;
use App\Modules\Audit\Infrastructure\Api\HttpClientInterface;
use App\Modules\Audit\Infrastructure\Api\HttpResponse;
use App\Modules\Audit\Infrastructure\Api\PageSpeedApiClient;
use App\Modules\Audit\Infrastructure\Api\RateLimitException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PageSpeedApiClientTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private PageSpeedApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new PageSpeedApiClient($this->httpClient);
    }

    public function test_run_audit_returns_valid_response(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/fixtures/valid_response.json');

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn(new HttpResponse(200, $json));

        $response = $this->client->runAudit('https://example.com');

        $this->assertInstanceOf(ApiResponse::class, $response);
        $this->assertSame(85, $response->getScore());
    }

    public function test_run_audit_extracts_failing_audits(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/fixtures/valid_response.json');

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn(new HttpResponse(200, $json));

        $response = $this->client->runAudit('https://example.com');

        $failingAudits = $response->getFailingAudits();
        $this->assertCount(2, $failingAudits);
        $this->assertSame('color-contrast', $failingAudits[0]['id']);
        $this->assertSame('image-alt', $failingAudits[1]['id']);
    }

    public function test_run_audit_throws_rate_limit_exception_on_429(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn(new HttpResponse(429, ''));

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->client->runAudit('https://example.com');
    }

    public function test_run_audit_throws_api_exception_on_server_error(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn(new HttpResponse(500, ''));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('API request failed');

        $this->client->runAudit('https://example.com');
    }

    public function test_run_audit_throws_api_exception_on_client_error(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn(new HttpResponse(400, '{"error": {"message": "Bad request"}}'));

        $this->expectException(ApiException::class);

        $this->client->runAudit('https://example.com');
    }

    public function test_run_audit_constructs_correct_api_url(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/fixtures/valid_response.json');

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with($this->callback(static function (string $url): bool {
                return str_contains($url, 'category=accessibility')
                    && str_contains($url, 'url=https%3A%2F%2Fexample.com')
                    && str_contains($url, 'strategy=desktop');
            }))
            ->willReturn(new HttpResponse(200, $json));

        $this->client->runAudit('https://example.com');
    }

    public function test_run_audit_with_api_key(): void
    {
        $json = (string) file_get_contents(__DIR__ . '/fixtures/valid_response.json');

        $clientWithKey = new PageSpeedApiClient($this->httpClient, 'test-api-key');

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with($this->callback(static function (string $url): bool {
                return str_contains($url, 'key=test-api-key');
            }))
            ->willReturn(new HttpResponse(200, $json));

        $clientWithKey->runAudit('https://example.com');
    }
}
