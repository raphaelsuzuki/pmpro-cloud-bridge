<?php
/**
 * Plugin singleton bootstrap.
 *
 * Instantiated once by the main plugin file after all dependencies are verified.
 * Registers all hooks that glue the sub-systems together. Sub-systems are
 * responsible for registering their own internal hooks via their own init()
 * methods.
 *
 * @package CloudBridge
 */

declare(strict_types=1);

namespace CloudBridge;

/**
 * Plugin bootstrap singleton.
 *
 * All hook registration for the plugin originates from this class.
 * No business logic lives here — it only wires up other components.
 */
final class Plugin {

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
	 * Returns the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Bootstraps every sub-system by registering its hooks.
	 *
	 * Called once from the main plugin file on the plugins_loaded action.
	 *
	 * @return void
	 */
	public function init(): void {
		// Run DB migrations on every load so plugin updates don't require a
		// manual deactivate/reactivate cycle to apply schema changes.
		Installer::maybe_upgrade();

		// If a fresh activation set the flush-transient, CPTs will be registered
		// in subsequent wiring below. Flush rewrite rules here, after CPTs are
		// registered, so the rules include the new post types.
		if ( get_transient( 'cb_flush_rewrite_rules' ) ) {
			delete_transient( 'cb_flush_rewrite_rules' );
			flush_rewrite_rules();
		}
	}
}
