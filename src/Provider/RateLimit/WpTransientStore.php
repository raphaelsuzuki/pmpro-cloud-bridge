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
	 * @inheritDoc
	 */
	public function set( string $key, mixed $value, int $ttl_seconds ): bool {
		if ( ! \function_exists( 'set_transient' ) ) {
			return false;
		}

		return (bool) \set_transient( $key, $value, $ttl_seconds );
	}
}
