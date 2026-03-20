<?php
/**
 * Activation, deactivation, and uninstall logic.
 *
 * All schema creation lives here so it can be unit-tested independently of the
 * main plugin bootstrap. Called from activation/deactivation hooks in the main
 * plugin file and from uninstall.php on deletion.
 *
 * @package CloudBridge
 */

declare(strict_types=1);

namespace CloudBridge;

/**
 * Handles plugin lifecycle: install, upgrade, deactivate, uninstall.
 */
final class Installer {

	/**
	 * Current DB schema version stored in option cb_db_version.
	 */
	private const DB_VERSION = '0.1.0';

	/**
	 * Option key that stores the installed DB schema version.
	 */
	private const DB_VERSION_OPTION = 'cb_db_version';

	/**
	 * Option key that stores the admin's data-removal preference (set on
	 * deactivation dialog; read by uninstall.php).
	 */
	public const UNINSTALL_REMOVE_DATA_OPTION = 'cb_uninstall_remove_data';

	/**
	 * Transient key set on activation to trigger a rewrite-rules flush once
	 * Plugin::init() has registered all CPTs.
	 */
	private const FLUSH_RULES_TRANSIENT = 'cb_flush_rewrite_rules';

	// -------------------------------------------------------------------------
	// Activation
	// -------------------------------------------------------------------------

	/**
	 * Runs on plugin activation.
	 *
	 * Creates/upgrades custom DB tables and registers the custom capability on
	 * the Administrator role. Sets a transient so that Plugin::init() can flush
	 * rewrite rules AFTER CPTs have been registered (calling flush_rewrite_rules
	 * here would persist stale rules because CPTs are not registered yet).
	 *
	 * @param bool $network_wide Whether the plugin is activated network-wide (multisite).
	 * @return void
	 */
	public static function activate( bool $network_wide ): void {
		self::install_schema();
		self::register_capabilities();

		// Signal Plugin::init() to flush rewrite rules on the next load, once
		// CPTs are registered.
		set_transient( self::FLUSH_RULES_TRANSIENT, '1', MINUTE_IN_SECONDS * 5 );
	}

	// -------------------------------------------------------------------------
	// Deactivation
	// -------------------------------------------------------------------------

	/**
	 * Runs on plugin deactivation.
	 *
	 * Unschedules recurring Action Scheduler jobs. Does NOT remove any data —
	 * data removal only happens on plugin deletion via uninstall().
	 *
	 * @param bool $network_wide Whether the plugin is deactivated network-wide.
	 * @return void
	 */
	public static function deactivate( bool $network_wide ): void {
		// Action Scheduler job un-registration will be wired here in later tasks
		// once the JobScheduler class exists.
	}

	// -------------------------------------------------------------------------
	// DB upgrade (runs on every plugin load)
	// -------------------------------------------------------------------------

	/**
	 * Runs schema migrations whenever the stored DB version is behind the code.
	 *
	 * Must be called from Plugin::init() so upgrades run on every plugin load,
	 * not only on activation (activation hooks do not fire during normal updates).
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '' );
		if ( $installed !== self::DB_VERSION ) {
			self::install_schema();
		}
	}

	// -------------------------------------------------------------------------
	// Uninstall (deletion)
	// -------------------------------------------------------------------------

	/**
	 * Runs when the admin deletes the plugin AND has opted in to data removal.
	 *
	 * Drops custom tables, deletes all plugin options, and removes CPT posts.
	 * Called from uninstall.php only.
	 *
	 * @return void
	 */
	public static function uninstall(): void {
		global $wpdb;

		// Drop custom tables.
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}cb_events`" );           // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}cb_provision_intents`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange

		// Delete all plugin options using an explicit allowlist to avoid
		// accidentally removing options from other plugins that share the cb_ prefix.
		foreach (
			[
				self::DB_VERSION_OPTION,
				self::UNINSTALL_REMOVE_DATA_OPTION,
				self::FLUSH_RULES_TRANSIENT,
			] as $option_key
		) {
			delete_option( $option_key );
		}

		// Delete all CPT posts and their meta.
		foreach ( [ 'cb_instance', 'cb_plan' ] as $post_type ) {
			$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT ID FROM `{$wpdb->posts}` WHERE `post_type` = %s",
					$post_type
				)
			);

			foreach ( $post_ids as $post_id ) {
				wp_delete_post( (int) $post_id, true );
			}
		}

		// Remove the custom capability from all roles.
		self::remove_capabilities();
	}

	// -------------------------------------------------------------------------
	// Schema installation / upgrade
	// -------------------------------------------------------------------------

	/**
	 * Creates or upgrades the custom database tables.
	 *
	 * Uses dbDelta() so it is safe to call on every activation — it only makes
	 * changes when the schema has drifted from the installed state.
	 *
	 * @return void
	 */
	private static function install_schema(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ------------------------------------------------------------------
		// Audit log table: {prefix}cb_events
		// See spec section 4.3 for the authoritative schema.
		// ------------------------------------------------------------------
		$sql_events = "CREATE TABLE {$wpdb->prefix}cb_events (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			instance_id  BIGINT UNSIGNED NOT NULL,
			user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event_type   VARCHAR(64)     NOT NULL,
			old_status   VARCHAR(32)     NOT NULL DEFAULT '',
			new_status   VARCHAR(32)     NOT NULL DEFAULT '',
			message      TEXT,
			provider_ref VARCHAR(128)    NOT NULL DEFAULT '',
			created_utc  INT UNSIGNED    NOT NULL,
			PRIMARY KEY  (id),
			KEY instance_id (instance_id),
			KEY event_type  (event_type),
			KEY created_utc (created_utc)
		) ENGINE=InnoDB $charset_collate;";

		// ------------------------------------------------------------------
		// ProvisionIntent table: {prefix}cb_provision_intents
		// See spec section 4.4. The UNIQUE KEY on (user_id, order_id) is the
		// hard duplicate guard — dbDelta preserves it on upgrade.
		// ------------------------------------------------------------------
		$sql_intents = "CREATE TABLE {$wpdb->prefix}cb_provision_intents (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id          BIGINT UNSIGNED NOT NULL,
			order_id         VARCHAR(64)     NOT NULL,
			plan_id          BIGINT UNSIGNED NOT NULL,
			idempotency_key  VARCHAR(128)    NOT NULL,
			status           VARCHAR(32)     NOT NULL DEFAULT 'PAYMENT_CONFIRMED',
			instance_id      BIGINT UNSIGNED          DEFAULT NULL,
			error_code       VARCHAR(64)     NOT NULL DEFAULT '',
			error_message    TEXT,
			created_utc      INT UNSIGNED    NOT NULL,
			updated_utc      INT UNSIGNED    NOT NULL,
			PRIMARY KEY      (id),
			UNIQUE KEY order_id (user_id, order_id),
			KEY status      (status),
			KEY instance_id (instance_id)
		) ENGINE=InnoDB $charset_collate;";

		dbDelta( $sql_events );
		dbDelta( $sql_intents );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	// -------------------------------------------------------------------------
	// Capabilities
	// -------------------------------------------------------------------------

	/**
	 * Adds the manage_cloud_bridge capability to the Administrator role.
	 *
	 * Called on activation. Idempotent — safe to call multiple times.
	 *
	 * @return void
	 */
	private static function register_capabilities(): void {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role instanceof \WP_Role ) {
			$admin_role->add_cap( 'manage_cloud_bridge' );
		}
	}

	/**
	 * Removes the manage_cloud_bridge capability from all roles.
	 *
	 * Called only during uninstall to clean up registered capabilities.
	 *
	 * @return void
	 */
	private static function remove_capabilities(): void {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		foreach ( $wp_roles->roles as $role_name => $role_info ) {
			$role = get_role( $role_name );
			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( 'manage_cloud_bridge' );
			}
		}
	}
}
