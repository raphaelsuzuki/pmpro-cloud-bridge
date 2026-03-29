<?php

/**
 * Unit tests for the token bucket rate limiter.
 *
 * @package CloudBridge\Tests\Unit\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider;

use CloudBridge\Provider\RateLimit\TokenBucketRateLimiter;
use CloudBridge\Provider\RateLimit\TransientStoreInterface;
use PHPUnit\Framework\TestCase;

/**
 * In-memory test store for limiter state.
 */
final class InMemoryTransientStore implements TransientStoreInterface
{
    /**
     * @var array<string, mixed>
     */
    public array $data = array();

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

class TokenBucketRateLimiterTest extends TestCase
{
    public function test_acquire_delays_when_burst_is_exceeded(): void
    {
        $store       = new InMemoryTransientStore();
        $time        = 1000.0;
        $sleep_calls = 0;

        $limiter = new TokenBucketRateLimiter(
            $store,
            static function (int $microseconds) use (&$time, &$sleep_calls): void {
                ++$sleep_calls;
                $time += $microseconds / 1000000;
            },
            static function () use (&$time): float {
                return $time;
            }
        );

        $limiter->acquire('vultr', 60, 2);
        $limiter->acquire('vultr', 60, 2);
        $this->assertSame(0, $sleep_calls);

        $limiter->acquire('vultr', 60, 2);
        $this->assertGreaterThanOrEqual(1, $sleep_calls);
        $this->assertGreaterThanOrEqual(1001.0, $time);
    }

    public function test_acquire_is_keyed_per_provider(): void
    {
        $store       = new InMemoryTransientStore();
        $time        = 2000.0;
        $sleep_calls = 0;

        $limiter = new TokenBucketRateLimiter(
            $store,
            static function (int $microseconds) use (&$time, &$sleep_calls): void {
                ++$sleep_calls;
                $time += $microseconds / 1000000;
            },
            static function () use (&$time): float {
                return $time;
            }
        );

        $limiter->acquire('vultr', 60, 1);
        $limiter->acquire('hetzner', 60, 1);

        $this->assertSame(0, $sleep_calls);
    }
}
