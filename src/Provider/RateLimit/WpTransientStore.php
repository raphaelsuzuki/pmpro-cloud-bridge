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
final class WpTransientStore implements TransientStoreInterface {
	/**
	 * Reads a value from WordPress transients.
	 *
	 * @param string $key            Transient key.
	 * @param mixed  $fallback_value Value returned when key is absent.
	 *
	 * @inheritDoc
	 */
	public function get( string $key, mixed $fallback_value = null ): mixed {
		if ( ! \function_exists( 'get_transient' ) ) {
			return $fallback_value;
		}

		$value = \get_transient( $key );
		if ( false === $value ) {
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
	public function set( string $key, mixed $value, int $ttl_seconds ): bool {
		if ( ! \function_exists( 'set_transient' ) ) {
			return false;
		}

		return (bool) \set_transient( $key, $value, $ttl_seconds );
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
	 */
	public function increment( string $key, int $amount, int $ttl_seconds ): int {
		$ttl_seconds = \max( 1, $ttl_seconds );

		if (
			\function_exists( 'wp_using_ext_object_cache' )
			&& \wp_using_ext_object_cache()
			&& \function_exists( 'wp_cache_add' )
			&& \function_exists( 'wp_cache_incr' )
			&& \function_exists( 'wp_cache_set' )
		) {
			$group = 'cloud_bridge_rate_limit';
			\wp_cache_add( $key, 0, $group, $ttl_seconds );
			$new_value = \wp_cache_incr( $key, $amount, $group );

			if ( false !== $new_value ) {
				\wp_cache_set( $key, (int) $new_value, $group, $ttl_seconds );
				return (int) $new_value;
			}
		}

		global $wpdb;
		if ( ! isset( $wpdb ) || ! ( $wpdb instanceof \wpdb ) ) {
			$current = (int) $this->get( $key, 0 );
			$next    = $current + $amount;
			$this->set( $key, $next, $ttl_seconds );
			return $next;
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

		if ( null !== $timeout_val && (int) $timeout_val < $now ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name IN (%s, %s)",
					$option_name,
					$timeout_key
				)
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				VALUES (%s, %s, 'off')
				ON DUPLICATE KEY UPDATE option_value = CAST(option_value AS SIGNED) + %d",
				$option_name,
				(string) $amount,
				$amount
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload)
				VALUES (%s, %s, 'off')
				ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
				$timeout_key,
				(string) $expires_at
			)
		);

		$new_value = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
				$option_name
			)
		);

		return $new_value;
	}
}
