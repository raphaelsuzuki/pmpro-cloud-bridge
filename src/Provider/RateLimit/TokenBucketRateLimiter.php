<?php

/**
 * TokenBucketRateLimiter — provider-scoped transient-backed throttling.
 *
 * @package CloudBridge\Provider\RateLimit
 */

declare(strict_types=1);

namespace CloudBridge\Provider\RateLimit;

/**
 * Shared token-bucket limiter used before provider API calls.
 */
final class TokenBucketRateLimiter
{
    private const STATE_PREFIX     = 'cb_rate_limit_';
    private const LOCK_PREFIX      = 'cb_rate_limit_lock_';
    private const LOCK_TTL_SECONDS = 5;
    private const MIN_SLEEP_MICROSECONDS = 10000;
    private const MAX_SLEEP_MICROSECONDS = 500000;
    private const ACQUIRE_TIMEOUT_SECONDS = 30;

    /**
     * Sleep callback used when bucket is empty.
     *
     * @var callable
     */
    private $sleep_callback;

    /**
     * Clock callback returning current timestamp in seconds.
     *
     * @var callable
     */
    private $clock_callback;

    /**
     * Constructor.
     *
     * @param TransientStoreInterface|null $store          State backend.
     * @param callable|null                $sleep_callback Receives microseconds to sleep.
     * @param callable|null                $clock_callback Returns current timestamp float.
     */
    public function __construct(
        private readonly ?TransientStoreInterface $store = null,
        ?callable $sleep_callback = null,
        ?callable $clock_callback = null,
    ) {
        $this->sleep_callback = $sleep_callback ?? static function (int $microseconds): void {
            \usleep($microseconds);
        };
        $this->clock_callback = $clock_callback ?? static fn (): float => \microtime(true);
    }

    /**
     * Acquires a token, blocking with sleep when the bucket is empty.
     *
     * @param string $provider_id              Provider slug.
     * @param int    $max_requests_per_minute  Refill budget per minute.
     * @param int    $burst                    Maximum token capacity.
     */
    public function acquire(string $provider_id, int $max_requests_per_minute, int $burst): void
    {
        $capacity          = \max(1, $burst);
        $refill_per_second = \max(1, $max_requests_per_minute) / 60;
        $state_key         = self::STATE_PREFIX . $provider_id;
        $lock_key          = self::LOCK_PREFIX . $provider_id;
        $ttl_seconds       = \max(60, (int) \ceil($capacity / $refill_per_second) * 2);
        $store             = $this->get_store();
        $started_at        = $this->now();

        while (true) {
            if (($this->now() - $started_at) >= self::ACQUIRE_TIMEOUT_SECONDS) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                \error_log(\sprintf('[CloudBridge] TokenBucketRateLimiter timed out acquiring token for provider "%s".', $provider_id));

                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new \RuntimeException(\sprintf('Timed out waiting for provider rate-limit token for "%s".', $provider_id));
            }

            if (! $this->try_acquire_lock($store, $lock_key)) {
                $this->sleep(self::MIN_SLEEP_MICROSECONDS);
                continue;
            }

            $now   = $this->now();
            $state = $store->get($state_key, null);

            try {
                $tokens     = (float) $capacity;
                $updated_at = $now;
                if (\is_array($state) && isset($state['tokens'], $state['updated_at'])) {
                    $tokens     = (float) $state['tokens'];
                    $updated_at = (float) $state['updated_at'];
                }

                $elapsed = \max(0.0, $now - $updated_at);
                $tokens  = \min((float) $capacity, $tokens + ($elapsed * $refill_per_second));

                if ($tokens >= 1.0) {
                    $success = $store->set(
                        $state_key,
                        array(
                            'tokens'     => $tokens - 1.0,
                            'updated_at' => $now,
                        ),
                        $ttl_seconds
                    );
                    if (false === $success) {
                        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        throw new \RuntimeException(\sprintf('Failed to persist token bucket state for key "%s".', $state_key));
                    }
                    return;
                }

                $seconds_until_next_token = (1.0 - $tokens) / $refill_per_second;
                $microseconds             = (int) \ceil($seconds_until_next_token * 1000000);
                $microseconds             = \max(self::MIN_SLEEP_MICROSECONDS, $microseconds);
            } finally {
                $this->release_lock($store, $lock_key);
            }

            $this->sleep_in_chunks($microseconds);
        }
    }

    /**
     * Attempts to acquire a short-lived lock for state mutation.
     *
     * @param TransientStoreInterface $store    State store.
     * @param string                  $lock_key Lock key.
     */
    private function try_acquire_lock(TransientStoreInterface $store, string $lock_key): bool
    {
        $lock_count = $store->increment($lock_key, 1, self::LOCK_TTL_SECONDS);
        return 1 === $lock_count;
    }

    /**
     * Releases a previously acquired short-lived lock.
     *
     * @param TransientStoreInterface $store    State store.
     * @param string                  $lock_key Lock key.
     */
    private function release_lock(TransientStoreInterface $store, string $lock_key): void
    {
        $store->set($lock_key, 0, 1);
    }

    /**
     * Returns the active limiter state backend.
     *
     * @return TransientStoreInterface
     */
    private function get_store(): TransientStoreInterface
    {
        return $this->store ?? new WpTransientStore();
    }

    /**
     * Reads the current timestamp from the injected clock.
     */
    private function now(): float
    {
        $clock = $this->clock_callback;
        return (float) $clock();
    }

    /**
     * Sleeps for the provided microseconds via injected callback.
     *
     * @param int $microseconds Sleep duration.
     */
    private function sleep(int $microseconds): void
    {
        $sleeper = $this->sleep_callback;
        $sleeper($microseconds);
    }

    /**
     * Sleeps in capped chunks to avoid long single blocking waits.
     *
     * @param int $microseconds Total requested sleep time.
     */
    private function sleep_in_chunks(int $microseconds): void
    {
        $remaining = $microseconds;

        while ($remaining > 0) {
            $chunk = \min($remaining, self::MAX_SLEEP_MICROSECONDS);
            $chunk = \max(self::MIN_SLEEP_MICROSECONDS, $chunk);
            $this->sleep($chunk);
            $remaining -= $chunk;
        }
    }
}
