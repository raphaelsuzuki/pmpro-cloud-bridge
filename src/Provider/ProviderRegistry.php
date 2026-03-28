<?php
/**
 * ProviderRegistry — manages registered cloud provider drivers.
 *
 * Drivers are registered via the cloud_bridge_providers filter, fired once on
 * init. Third-party plugins add drivers by hooking this filter. The registry
 * validates that each registered object implements CloudProviderInterface before
 * storing it.
 *
 * @package CloudBridge\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Provider;

use CloudBridge\Provider\Drivers\DummyDriver;

/**
 * Singleton registry for all active CloudProviderInterface implementations.
 */
final class ProviderRegistry {

	/**
	 * Registered drivers, keyed by driver ID.
	 *
	 * @var array<string, CloudProviderInterface>
	 */
	private array $drivers = array();

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Returns the singleton registry instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Fires the cloud_bridge_providers filter and validates the result.
	 *
	 * Should be called once on the init hook, after all plugins have loaded.
	 *
	 * @return void
	 */
	public function load(): void {
		/**
		 * Filters the list of registered cloud provider drivers.
		 *
		 * Third-party plugins add drivers here:
		 *   add_filter( 'cloud_bridge_providers', function( array $providers ): array {
		 *       $providers['myprovider'] = new MyDriver( $config );
		 *       return $providers;
		 *   } );
		 *
		 * @param array<string, CloudProviderInterface> $providers
		 */
		$raw = apply_filters( 'cloud_bridge_providers', array() );

		$this->drivers = array();

		if ( ! is_array( $raw ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[CloudBridge] Ignoring cloud_bridge_providers filter result: expected array, got %s.',
					gettype( $raw )
				)
			);
			return;
		}

		foreach ( $raw as $id => $driver ) {
			if ( $driver instanceof DummyDriver && ! self::dummy_driver_is_enabled() ) {
				continue;
			}

			if ( ! $driver instanceof CloudProviderInterface ) {
				// Log and skip non-conforming drivers rather than crashing.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[CloudBridge] Skipping driver "%s": does not implement CloudProviderInterface.', $id ) );
				continue;
			}

			$this->drivers[ (string) $id ] = $driver;
		}
	}

	/**
	 * Returns true when DummyDriver is explicitly enabled for tests/dev.
	 */
	private static function dummy_driver_is_enabled(): bool {
		return defined( 'CB_ENABLE_DUMMY_DRIVER' ) && true === constant( 'CB_ENABLE_DUMMY_DRIVER' );
	}

	/**
	 * Returns a driver by its ID.
	 *
	 * @param string $id Driver slug, e.g. 'vultr'.
	 * @return CloudProviderInterface
	 * @throws \InvalidArgumentException If driver is not registered.
	 */
	public function get( string $id ): CloudProviderInterface {
		if ( ! isset( $this->drivers[ $id ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Cloud provider driver "%s" is not registered.', esc_html( $id ) )
			);
		}

		return $this->drivers[ $id ];
	}

	/**
	 * Returns all registered drivers.
	 *
	 * @return array<string, CloudProviderInterface>
	 */
	public function all(): array {
		return $this->drivers;
	}

	/**
	 * Returns whether a driver with the given ID is registered.
	 *
	 * @param string $id Driver slug.
	 */
	public function has( string $id ): bool {
		return isset( $this->drivers[ $id ] );
	}
}
