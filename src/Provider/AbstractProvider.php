<?php

/**
 * AbstractProvider — shared base for all provider drivers.
 *
 * Provides HTTP helper methods and contextual logging utilities that every driver
 * needs. Subclasses MUST NOT add WordPress function calls beyond what is
 * explicitly provided here; all WordPress coupling must remain in this class,
 * minimised and clearly documented.
 *
 * @package CloudBridge\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Provider;

use CloudBridge\Provider\Http\HttpClientInterface;
use CloudBridge\Provider\Http\WpHttpClient;
use CloudBridge\Provider\RateLimit\TokenBucketRateLimiter;
use CloudBridge\Provider\Result\ProviderResult;

/**
 * Base class for cloud provider drivers.
 *
 * Concrete drivers extend this class and implement CloudProviderInterface.
 */
abstract class AbstractProvider implements CloudProviderInterface
{
    private const DEFAULT_HTTP_TIMEOUT = 30;

    /**
     * Lazily-resolved HTTP adapter.
     *
     * @var HttpClientInterface|null
     */
    private ?HttpClientInterface $http_client = null;

    /**
     * Lazily-resolved shared provider rate limiter.
     *
     * @var TokenBucketRateLimiter|null
     */
    private ?TokenBucketRateLimiter $rate_limiter = null;

    /**
     * Constructor.
     *
     * Drivers may pass explicit dependencies, but this class also supports
     * lazy defaults for existing constructors that do not call parent.
     *
     * @param HttpClientInterface|null    $http_client  Optional HTTP client implementation.
     * @param TokenBucketRateLimiter|null $rate_limiter Optional provider rate limiter.
     */
    public function __construct(?HttpClientInterface $http_client = null, ?TokenBucketRateLimiter $rate_limiter = null)
    {
        $this->http_client  = $http_client;
        $this->rate_limiter = $rate_limiter;
    }

    /**
     * Allows tests or drivers to replace the HTTP adapter.
     *
     * @param HttpClientInterface $http_client HTTP adapter implementation.
     */
    protected function set_http_client(HttpClientInterface $http_client): void
    {
        $this->http_client = $http_client;
    }

    /**
     * Allows tests or drivers to replace the shared limiter.
     *
     * @param TokenBucketRateLimiter $rate_limiter Provider limiter.
     */
    protected function set_rate_limiter(TokenBucketRateLimiter $rate_limiter): void
    {
        $this->rate_limiter = $rate_limiter;
    }

    /**
     * Makes an HTTP request to the provider API.
     *
     * This method throttles requests via token bucket before sending.
     * Transport implementation is injected via HttpClientInterface.
     *
     * @param string               $method  HTTP method: GET, POST, DELETE, PATCH.
     * @param string               $url     Fully qualified provider API URL.
     * @param array<string, mixed> $headers Additional request headers (auth, idempotency, etc.).
     * @param mixed                $body    Request body (will be JSON-encoded if non-null).
     * @return array{code:int, body:string}|ProviderResult<null>
     */
    protected function http_request(string $method, string $url, array $headers = array(), mixed $body = null)
    {
        $rate_limits = $this->get_rate_limits();
        $this->get_rate_limiter()->acquire(
            $this->get_id(),
            (int) $rate_limits['max_requests_per_minute'],
            (int) $rate_limits['burst']
        );

        $payload = null;
        if (null !== $body) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
            $encoded = \json_encode($body);
            if (false === $encoded) {
                return ProviderResult::fail(
                    'api_error',
                    sprintf('Failed to JSON-encode provider request body: %s', \json_last_error_msg())
                );
            }
            $payload = $encoded;
        }

        $headers = array_merge(
            array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            $headers
        );

        try {
            $response = $this->get_http_client()->request(
                $method,
                $url,
                $headers,
                $payload,
                self::DEFAULT_HTTP_TIMEOUT
            );
        } catch (\RuntimeException $exception) {
            return ProviderResult::fail('http_error', $exception->getMessage());
        }

        return array(
            'code' => $response->status_code,
            'body' => $response->body,
        );
    }

    /**
     * Decodes a JSON response body.
     *
     * @param string $body Raw response body.
     * @return array<mixed>|null Decoded array, or null on parse failure.
     */
    protected function decode_json(string $body): ?array
    {
        $decoded = \json_decode($body, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Returns the active HTTP adapter.
     */
    private function get_http_client(): HttpClientInterface
    {
        if (null === $this->http_client) {
            $this->http_client = new WpHttpClient();
        }

        return $this->http_client;
    }

    /**
     * Returns the active provider rate limiter.
     */
    private function get_rate_limiter(): TokenBucketRateLimiter
    {
        if (null === $this->rate_limiter) {
            $this->rate_limiter = new TokenBucketRateLimiter();
        }

        return $this->rate_limiter;
    }
}
