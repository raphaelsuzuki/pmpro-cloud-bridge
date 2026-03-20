<?php
/**
 * Uninstall handler for Cloud Bridge for PMPro.
 *
 * Runs when a site administrator clicks "Delete" on the Plugins screen.
 * This file is executed directly by WordPress — NOT on deactivation.
 *
 * Data is only removed when the admin has opted in via the
 * 'Remove all plugin data' radio on the deactivation dialog.
 * The preference is stored in option cb_uninstall_remove_data.
 *
 * @package CloudBridge
 */

declare(strict_types=1);

// WordPress will not call this file directly if WP_UNINSTALL_PLUGIN is not set.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only proceed with data removal when the admin explicitly opted in.
if ( '1' !== get_option( 'cb_uninstall_remove_data', '0' ) ) {
	return;
}

// Load the Installer class: prefer the Composer autoloader, fall back to a
// direct require so cleanup always runs even on environments where `composer
// install` has not been run (e.g. edge cases during CI teardown).
$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
} else {
	require_once __DIR__ . '/src/Installer.php';
}

\CloudBridge\Installer::uninstall();
