<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Url;

use App\Modules\Url\Domain\ValueObjects\UrlAddress;
use App\Shared\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

final class UrlAddressTest extends TestCase
{
    public function test_can_be_created_with_valid_https_url(): void
    {
        $url = new UrlAddress('https://example.com');

        $this->assertSame('https://example.com', $url->getValue());
    }

    public function test_can_be_created_with_valid_http_url(): void
    {
        $url = new UrlAddress('http://example.com');

        $this->assertSame('http://example.com', $url->getValue());
    }

    public function test_can_be_created_with_url_containing_path(): void
    {
        $url = new UrlAddress('https://example.com/path/to/page');

        $this->assertSame('https://example.com/path/to/page', $url->getValue());
    }

    public function test_can_be_created_with_url_containing_query_string(): void
    {
        $url = new UrlAddress('https://example.com?foo=bar&baz=qux');

        $this->assertSame('https://example.com?foo=bar&baz=qux', $url->getValue());
    }

    public function test_trims_whitespace(): void
    {
        $url = new UrlAddress('  https://example.com  ');

        $this->assertSame('https://example.com', $url->getValue());
    }

    public function test_removes_trailing_slash(): void
    {
        $url = new UrlAddress('https://example.com/');

        $this->assertSame('https://example.com', $url->getValue());
    }

    public function test_throws_exception_for_empty_string(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('URL cannot be empty');

        new UrlAddress('');
    }

    public function test_throws_exception_for_whitespace_only(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('URL cannot be empty');

        new UrlAddress('   ');
    }

    public function test_throws_exception_for_invalid_url(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid URL format');

        new UrlAddress('not-a-url');
    }

    public function test_throws_exception_for_url_without_scheme(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('URL must use http or https scheme');

        new UrlAddress('ftp://example.com');
    }

    public function test_throws_exception_for_url_without_host(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid URL format');

        new UrlAddress('https://');
    }

    public function test_equals_returns_true_for_same_url(): void
    {
        $url1 = new UrlAddress('https://example.com');
        $url2 = new UrlAddress('https://example.com');

        $this->assertTrue($url1->equals($url2));
    }

    public function test_equals_returns_false_for_different_url(): void
    {
        $url1 = new UrlAddress('https://example.com');
        $url2 = new UrlAddress('https://other.com');

        $this->assertFalse($url1->equals($url2));
    }

    public function test_to_string_returns_value(): void
    {
        $url = new UrlAddress('https://example.com');

        $this->assertSame('https://example.com', (string) $url);
    }
}
