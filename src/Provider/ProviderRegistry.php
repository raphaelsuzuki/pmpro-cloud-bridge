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

/**
 * Singleton registry for all active CloudProviderInterface implementations.
 */
final class ProviderRegistry {

	/**
	 * Registered drivers, keyed by driver ID.
	 *
	 * @var array<string, CloudProviderInterface>
	 */
	private array $drivers = [];

	/** @var self|null */
	private static ?self $instance = null;

	private function __construct() {}

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
		$raw = apply_filters( 'cloud_bridge_providers', [] );

		$this->drivers = [];

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
