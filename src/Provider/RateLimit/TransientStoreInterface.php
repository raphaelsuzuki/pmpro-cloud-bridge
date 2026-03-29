<?php

/**
 * TransientStoreInterface — key/value storage for rate-limiter state.
 *
 * @package CloudBridge\Provider\RateLimit
 */

declare(strict_types=1);

namespace CloudBridge\Provider\RateLimit;

/**
 * Minimal store abstraction so the limiter can be unit tested.
 */
interface TransientStoreInterface
{
    /**
     * Reads a value from transient storage.
     *
     * @param string $key            Storage key.
     * @param mixed  $fallback_value Value returned when key is missing.
     *
     * @return mixed
     */
    public function get(string $key, mixed $fallback_value = null): mixed;

    /**
     * Writes a value to transient storage.
     *
     * @param string $key         Storage key.
     * @param mixed  $value       Value to persist.
     * @param int    $ttl_seconds Expiry in seconds.
     *
     * @return bool True when write succeeds, false otherwise.
     */
    public function set(string $key, mixed $value, int $ttl_seconds): bool;

    /**
     * Atomically increments a numeric value.
     *
     * Implementations should use native atomic primitives when available
     * (e.g. Redis/Memcached increment operations) and set TTL when creating
     * the key.
     *
     * @param string $key         Storage key.
     * @param int    $amount      Increment amount.
     * @param int    $ttl_seconds Expiry in seconds.
     *
     * @return int New value after increment.
     */
    public function increment(string $key, int $amount, int $ttl_seconds): int;
}
