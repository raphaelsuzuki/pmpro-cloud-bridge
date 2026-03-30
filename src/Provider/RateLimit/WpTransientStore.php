<?php

/**
 * WpTransientStore — WordPress transient adapter.
 *
 * @package CloudBridge\Provider\RateLimit
 */

declare(strict_types=1);

namespace CloudBridge\Provider\RateLimit;

/**
 * Uses get_transient/set_transient as the limiter state backend.
 */
final class WpTransientStore implements TransientStoreInterface
{
    /**
     * Reads a value from WordPress transients.
     *
     * @param string $key            Transient key.
     * @param mixed  $fallback_value Value returned when key is absent.
     *
     * @inheritDoc
     */
    public function get(string $key, mixed $fallback_value = null): mixed
    {
        if (\function_exists('wp_cache_get')) {
            $cached_value = \wp_cache_get($key, 'cloud_bridge_rate_limit');
            if (false !== $cached_value && null !== $cached_value) {
                return $cached_value;
            }
        }

        if (! \function_exists('get_transient')) {
            return $fallback_value;
        }

        $value = \get_transient($key);
        if (false === $value) {
            return $fallback_value;
        }

        return $value;
    }

    /**
     * Writes a value to WordPress transients.
     *
     * @param string $key         Transient key.
     * @param mixed  $value       Value to store.
     * @param int    $ttl_seconds Expiry in seconds.
     *
     * @return bool True when write succeeds, false otherwise.
     *
     * @inheritDoc
     */
    public function set(string $key, mixed $value, int $ttl_seconds): bool
    {
        if (
            \function_exists('wp_using_ext_object_cache')
            && \wp_using_ext_object_cache()
            && \function_exists('wp_cache_set')
        ) {
            return (bool) \wp_cache_set($key, $value, 'cloud_bridge_rate_limit', $ttl_seconds);
        }

        if (! \function_exists('set_transient')) {
            return false;
        }

        return (bool) \set_transient($key, $value, $ttl_seconds);
    }

    /**
     * Atomically increments a numeric transient value.
     *
     * Uses object-cache native increment when available. Falls back to
     * option-table upserts with SQL arithmetic.
     *
     * @param string $key         Transient key.
     * @param int    $amount      Increment amount.
     * @param int    $ttl_seconds Expiry in seconds.
     *
     * @return int
     *
     * @throws \RuntimeException When atomic increment cannot be completed.
     */
    public function increment(string $key, int $amount, int $ttl_seconds): int
    {
        $ttl_seconds = \max(1, $ttl_seconds);

        if (
            \function_exists('wp_using_ext_object_cache')
            && \wp_using_ext_object_cache()
            && \function_exists('wp_cache_add')
            && \function_exists('wp_cache_incr')
        ) {
            $group = 'cloud_bridge_rate_limit';
            \wp_cache_add($key, 0, $group, $ttl_seconds);
            $new_value = \wp_cache_incr($key, $amount, $group);

            if (false === $new_value) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                \error_log(\sprintf('[CloudBridge] wp_cache_incr failed for rate-limit key "%s" in group "%s".', $key, $group));

                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new \RuntimeException(\sprintf('Rate-limit cache increment failed for key "%s".', $key));
            }

            // Keep increment atomic: do not issue a subsequent write that can
            // clobber concurrent increments. TTL refresh is backend-specific.
            return (int) $new_value;
        }

        global $wpdb;
        if (! isset($wpdb) || ! ($wpdb instanceof \wpdb)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            \error_log(\sprintf('[CloudBridge] Rate-limit increment fallback in use for key "%s" because $wpdb is unavailable.', $key));

            $lock_handle = $this->acquire_fallback_lock($key);
            if (false === $lock_handle) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new \RuntimeException(\sprintf('Unable to acquire fallback lock for rate-limit key "%s".', $key));
            }

            try {
                $current = (int) $this->get($key, 0);
                $next    = $current + $amount;

                if (! $this->set($key, $next, $ttl_seconds)) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    throw new \RuntimeException(\sprintf('Failed to persist fallback increment for rate-limit key "%s".', $key));
                }

                return $next;
            } finally {
                $this->release_fallback_lock($lock_handle);
            }
        }

        $option_name = '_transient_' . $key;
        $timeout_key = '_transient_timeout_' . $key;
        $now         = \time();
        $expires_at  = $now + $ttl_seconds;

        $timeout_val = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
                $timeout_key
            )
        );

        if (null !== $timeout_val && (int) $timeout_val < $now) {
            $deleted = $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
                    $option_name,
                    $timeout_key
                )
            );

            if (false === $deleted) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                throw new \RuntimeException(\sprintf('Failed deleting expired rate-limit transient keys for "%s".', $key));
            }
        }

        $incremented = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				VALUES (%s, %s, 'off')
				ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(COALESCE(CAST(option_value AS SIGNED), 0) + VALUES(option_value))",
                $option_name,
                (string) $amount
            )
        );

        if (false === $incremented) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \RuntimeException(\sprintf('Failed incrementing rate-limit value for key "%s".', $key));
        }

        $timeout_updated = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				VALUES (%s, %s, 'off')
				ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
                $timeout_key,
                (string) $expires_at
            )
        );

        if (false === $timeout_updated) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new \RuntimeException(\sprintf('Failed updating rate-limit timeout for key "%s".', $key));
        }

        $new_value = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT CAST(option_value AS SIGNED) FROM {$wpdb->options} WHERE option_name = %s",
                $option_name
            )
        );

        return $new_value;
    }

    /**
     * Acquires a local fallback file lock for non-database increment path.
     *
     * @param string $key Rate-limit key.
     *
     * @return resource|false
     */
    private function acquire_fallback_lock(string $key)
    {
        $lock_suffix = \hash('sha256', $key);
        $lock_file   = \rtrim(\sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cloud-bridge-rate-limit-' . $lock_suffix . '.lock';
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = \fopen($lock_file, 'c+');

        if (false === $handle) {
            return false;
        }

        if (! \flock($handle, LOCK_EX)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            \fclose($handle);
            return false;
        }

        return $handle;
    }

    /**
     * Releases a previously acquired fallback file lock.
     *
     * @param resource $handle Lock file handle.
     */
    private function release_fallback_lock($handle): void
    {
        \flock($handle, LOCK_UN);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        \fclose($handle);
    }
}
