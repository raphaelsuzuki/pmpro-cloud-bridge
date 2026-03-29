<?php

/**
 * Unit tests for AbstractProvider HTTP helper behavior.
 *
 * @package CloudBridge\Tests\Unit\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider;

use CloudBridge\Provider\AbstractProvider;
use CloudBridge\Provider\DTO\ProvisionRequest;
use CloudBridge\Provider\Http\HttpClientInterface;
use CloudBridge\Provider\Http\HttpResponse;
use CloudBridge\Provider\RateLimit\TokenBucketRateLimiter;
use CloudBridge\Provider\RateLimit\TransientStoreInterface;
use CloudBridge\Provider\Result\ProviderResult;
use PHPUnit\Framework\TestCase;

/**
 * Fake HTTP client that records the last request.
 */
final class RecordingHttpClient implements HttpClientInterface
{
    public string $method = '';
    public string $url = '';

    /**
     * @var array<string, string>
     */
    public array $headers = array();

    public ?string $body = null;

    public function request(string $method, string $url, array $headers = array(), ?string $body = null, int $timeout = 30): HttpResponse
    {
        unset($timeout);
        $this->method  = $method;
        $this->url     = $url;
        $this->headers = $headers;
        $this->body    = $body;

        return new HttpResponse(202, '{"ok":true}');
    }
}

/**
 * HTTP client that throws transport exceptions.
 */
final class ThrowingHttpClient implements HttpClientInterface
{
    public function request(string $method, string $url, array $headers = array(), ?string $body = null, int $timeout = 30): HttpResponse
    {
        unset($method, $url, $headers, $body, $timeout);
        throw new \RuntimeException('network failed');
    }
}

/**
 * Minimal provider for exercising AbstractProvider internals.
 */
final class TestHttpProvider extends AbstractProvider
{
    public function __construct(HttpClientInterface $http_client, TokenBucketRateLimiter $rate_limiter)
    {
        parent::__construct($http_client, $rate_limiter);
    }

    /**
     * @return array{code:int, body:string}|ProviderResult<null>
     */
    public function call_http_request(string $method, string $url, array $headers = array(), mixed $body = null): array|ProviderResult
    {
        return $this->http_request($method, $url, $headers, $body);
    }

    public function get_id(): string
    {
        return 'test-provider';
    }
    public function get_label(): string
    {
        return 'Test Provider';
    }
    public function get_api_version(): string
    {
        return 'v1';
    }
    public function validate_credentials(): ProviderResult
    {
        return ProviderResult::ok(true);
    }
    public function get_capabilities(): array
    {
        return array();
    }
    public function get_actions(string $provider_instance_id, array $settings): array
    {
        return array();
    }
    public function provision(ProvisionRequest $request): ProviderResult
    {
        return ProviderResult::fail('unsupported', 'Not used.');
    }
    public function destroy(string $provider_instance_id): ProviderResult
    {
        return ProviderResult::fail('unsupported', 'Not used.');
    }
    public function power_on(string $provider_instance_id): ProviderResult
    {
        return ProviderResult::fail('unsupported', 'Not used.');
    }
    public function power_off(string $provider_instance_id): ProviderResult
    {
        return ProviderResult::fail('unsupported', 'Not used.');
    }
    public function reboot(string $provider_instance_id): ProviderResult
    {
        return ProviderResult::fail('unsupported', 'Not used.');
    }
    public function rebuild(string $provider_instance_id, string $image_id): ProviderResult
    {
        return ProviderResult::fail('unsupported', 'Not used.');
    }
    public function get_instance_status(string $provider_instance_id): ProviderResult
    {
        return ProviderResult::fail('unsupported', 'Not used.');
    }
    public function get_available_plans(?string $region_slug = null): ProviderResult
    {
        return ProviderResult::ok(array());
    }
    public function get_available_regions(): ProviderResult
    {
        return ProviderResult::ok(array());
    }
    public function get_available_images(): ProviderResult
    {
        return ProviderResult::ok(array());
    }

    public function get_rate_limits(): array
    {
        return array(
            'max_requests_per_minute' => 60,
            'burst' => 1,
        );
    }

    public function normalise_state(string $provider_state): string
    {
        return $provider_state;
    }
}

/**
 * In-memory transient store for limiter tests.
 */
final class AbstractProviderStore implements TransientStoreInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $data = array();

    public function get(string $key, mixed $fallback_value = null): mixed
    {
        return $this->data[ $key ] ?? $fallback_value;
    }

    public function set(string $key, mixed $value, int $ttl_seconds): bool
    {
        unset($ttl_seconds);
        $this->data[ $key ] = $value;
        return true;
    }

    public function increment(string $key, int $amount, int $ttl_seconds): int
    {
        unset($ttl_seconds);
        $current           = (int) ($this->data[ $key ] ?? 0);
        $this->data[ $key ] = $current + $amount;
        return (int) $this->data[ $key ];
    }
}

class AbstractProviderTest extends TestCase
{
    public function test_http_request_uses_injected_http_client_and_encodes_json_body(): void
    {
        $time    = 100.0;
        $client  = new RecordingHttpClient();
        $limiter = new TokenBucketRateLimiter(
            new AbstractProviderStore(),
            static function (int $microseconds) use (&$time): void {
                $time += $microseconds / 1000000;
            },
            static function () use (&$time): float {
                return $time;
            }
        );

        $provider = new TestHttpProvider($client, $limiter);
        $result   = $provider->call_http_request(
            'POST',
            'https://api.example.test/v1/instances',
            array( 'X-Test' => '1' ),
            array( 'name' => 'server-1' )
        );

        $this->assertIsArray($result);
        $this->assertSame(202, $result['code']);
        $this->assertSame('{"ok":true}', $result['body']);
        $this->assertSame('POST', $client->method);
        $this->assertSame('https://api.example.test/v1/instances', $client->url);
        $this->assertSame('{"name":"server-1"}', $client->body);
        $this->assertSame('application/json', $client->headers['Content-Type']);
        $this->assertSame('application/json', $client->headers['Accept']);
        $this->assertSame('1', $client->headers['X-Test']);
    }

    public function test_http_request_returns_http_error_on_transport_failure(): void
    {
        $time    = 100.0;
        $limiter = new TokenBucketRateLimiter(
            new AbstractProviderStore(),
            static function (int $microseconds) use (&$time): void {
                $time += $microseconds / 1000000;
            },
            static function () use (&$time): float {
                return $time;
            }
        );
        $provider = new TestHttpProvider(new ThrowingHttpClient(), $limiter);

        $result = $provider->call_http_request('GET', 'https://api.example.test/v1/ping');

        $this->assertInstanceOf(ProviderResult::class, $result);
        $this->assertFalse($result->is_ok());
        $this->assertSame('http_error', $result->get_error_code());
    }

    public function test_http_request_waits_when_rate_limit_is_exceeded(): void
    {
        $time        = 500.0;
        $sleep_calls = 0;
        $client      = new RecordingHttpClient();
        $limiter     = new TokenBucketRateLimiter(
            new AbstractProviderStore(),
            static function (int $microseconds) use (&$time, &$sleep_calls): void {
                ++$sleep_calls;
                $time += $microseconds / 1000000;
            },
            static function () use (&$time): float {
                return $time;
            }
        );
        $provider = new TestHttpProvider($client, $limiter);

        $provider->call_http_request('GET', 'https://api.example.test/v1/ping');
        $provider->call_http_request('GET', 'https://api.example.test/v1/ping');

        $this->assertGreaterThanOrEqual(1, $sleep_calls);
    }
}
