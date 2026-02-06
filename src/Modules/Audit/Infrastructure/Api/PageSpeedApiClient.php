<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Api;

final readonly class PageSpeedApiClient implements PageSpeedClientInterface
{
    private const string API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey = '',
    ) {
    }

    public function runAudit(string $url): ApiResponse
    {
        $params = [
            'url' => $url,
            'category' => 'accessibility',
            'strategy' => 'desktop',
        ];

        if ($this->apiKey !== '') {
            $params['key'] = $this->apiKey;
        }

        $queryString = http_build_query($params);
        $response = $this->httpClient->get(self::API_URL . '?' . $queryString);

        if ($response->getStatusCode() === 429) {
            throw new RateLimitException('Rate limit exceeded', 429);
        }

        if ($response->getStatusCode() !== 200) {
            throw new ApiException('API request failed', $response->getStatusCode());
        }

        return ApiResponse::fromJson($response->getBody());
    }
}
