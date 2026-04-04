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
     * Atomically increments a numeric value using a fixed-window TTL.
     *
     * The TTL is set when the key is first created; it is NOT refreshed on
     * subsequent increments. This creates a fixed window (not sliding) that
     * allows predictable rate-limit bucket boundaries.
     *
     * ATOMICITY IS REQUIRED. Implementations MUST guarantee atomic increments
     * to prevent lost updates under concurrent access. Implementations expecting
     * to back non-atomic storage (e.g., WordPress transients without object cache)
     * MUST employ compare-and-swap semantics, locks, or fail initialization with
     * a clear error indicating the backing store cannot provide the required
     * atomicity guarantees for rate limiting.
     *
     * Race conditions in increment operations will cause rate-limiting bypass
     * and service abuse; therefore non-atomic implementations are not acceptable
     * for security-sensitive rate-limiting use cases.
     *
     * @param string $key         Storage key.
     * @param int    $amount      Increment amount.
     * @param int    $ttl_seconds Expiry in seconds; set once at key creation.
     *
     * @return int New value after increment (atomically guaranteed).
     */
    public function increment(string $key, int $amount, int $ttl_seconds): int;
}
