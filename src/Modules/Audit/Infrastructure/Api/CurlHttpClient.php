<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Api;

final readonly class CurlHttpClient implements HttpClientInterface
{
    private const int TIMEOUT_SECONDS = 60;

    public function get(string $url): HttpResponse
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'BeaconAudit/1.0',
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new ApiException('HTTP request failed: ' . $error);
        }

        curl_close($ch);

        /** @var int $statusCode */
        return new HttpResponse($statusCode, (string) $body);
    }
}
