<?php
/**
 * Plugin Name:       Cloud Bridge for PMPro
 * Plugin URI:        https://github.com/raphaelsuzuki/cloud-bridge-for-pmpro
 * Description:       Sell and manage cloud server/VPS subscriptions through Paid Memberships Pro.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Raphael Suzuki
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cloud-bridge-for-pmpro
 * Domain Path:       /languages
 *
 * @package CloudBridge
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'CB_VERSION', '0.1.0' );
define( 'CB_DB_VERSION', '0.1.0' );
define( 'CB_PLUGIN_FILE', __FILE__ );
define( 'CB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CB_TEXT_DOMAIN', 'cloud-bridge-for-pmpro' );

/**
 * Checks that all required dependencies are present.
 *
 * Must run before any plugin code initialises. If a dependency is missing
 * the plugin deactivates itself and surfaces an admin notice.
 *
 * @return bool True when all dependencies are satisfied.
 */
function cb_check_dependencies(): bool {
	$missing = [];

	// Paid Memberships Pro ≥ 3.1.
	if ( ! defined( 'PMPRO_VERSION' ) || version_compare( PMPRO_VERSION, '3.1', '<' ) ) {
		$missing[] = sprintf(
			/* translators: %s: minimum required version */
			__( 'Paid Memberships Pro (version %s or higher)', 'cloud-bridge-for-pmpro' ),
			'3.1'
		);
	}

	// PMPro Magic Levels ≥ 1.2.
	if ( ! defined( 'PMPRO_MAGIC_LEVELS_VERSION' ) || version_compare( PMPRO_MAGIC_LEVELS_VERSION, '1.2', '<' ) ) {
		$missing[] = sprintf(
			/* translators: %s: minimum required version */
			__( 'PMPro Magic Levels (version %s or higher)', 'cloud-bridge-for-pmpro' ),
			'1.2'
		);
	}

	// Action Scheduler — bundled with PMPro; verify the core function exists.
	if ( ! function_exists( 'as_enqueue_async_action' ) ) {
		$missing[] = __( 'Action Scheduler (bundled with Paid Memberships Pro)', 'cloud-bridge-for-pmpro' );
	}

	if ( empty( $missing ) ) {
		return true;
	}

	// Deactivate the plugin and show a notice.
	add_action(
		'admin_notices',
		static function () use ( $missing ): void {
			$list = '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $missing ) ) . '</li></ul>';
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong></p>%s</div>',
				esc_html__( 'Cloud Bridge for PMPro has been deactivated. The following required plugins are missing or outdated:', 'cloud-bridge-for-pmpro' ),
				wp_kses_post( $list )
			);
		}
	);

	// Deactivate without triggering uninstall hooks.
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins( plugin_basename( __FILE__ ) );

	// Prevent the "Plugin activated." notice.
	if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	return false;
}

/**
 * Initialises the plugin after all plugins have loaded.
 *
 * Dependency check runs on plugins_loaded at priority 5 (before default 10) so
 * that the deactivation routine fires before any other plugin bootstraps us.
 */
function cb_plugins_loaded(): void {
	// Load text domain first so dependency error messages are translatable.
	load_plugin_textdomain(
		'cloud-bridge-for-pmpro',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	if ( ! cb_check_dependencies() ) {
		return;
	}

	// Autoloader registered by Composer; bail if it has not been generated yet.
	$autoload = CB_PLUGIN_DIR . 'vendor/autoload.php';
	if ( ! file_exists( $autoload ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html__( 'Cloud Bridge for PMPro: Composer autoloader not found. Please run `composer install` in the plugin directory.', 'cloud-bridge-for-pmpro' )
				);
			}
		);
		return;
	}

	require_once $autoload;

	// Boot the plugin singleton.
	\CloudBridge\Plugin::get_instance()->init();
}
add_action( 'plugins_loaded', 'cb_plugins_loaded', 5 );

/**
 * Activation hook.
 *
 * Installs DB tables, registers CPTs (flush rewrite rules), and sets the
 * initial capability on the admin role. Does NOT provision anything.
 */
function cb_activate( bool $network_wide ): void {
	// Dependency check — if deps are missing, abort activation silently; the
	// plugins_loaded notice will surface the problem to the admin.
	if ( ! cb_check_dependencies() ) {
		return;
	}

	require_once CB_PLUGIN_DIR . 'vendor/autoload.php';

	\CloudBridge\Installer::activate( $network_wide );
}
register_activation_hook( __FILE__, 'cb_activate' );

/**
 * Deactivation hook.
 *
 * Unschedules recurring Action Scheduler jobs. Does NOT drop tables or data —
 * that only happens on plugin deletion via uninstall.php.
 */
function cb_deactivate( bool $network_wide ): void {
	if ( ! file_exists( CB_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		return;
	}

	require_once CB_PLUGIN_DIR . 'vendor/autoload.php';

	\CloudBridge\Installer::deactivate( $network_wide );
}
register_deactivation_hook( __FILE__, 'cb_deactivate' );
