<?php
/**
 * Capabilities — registers and checks custom WordPress capabilities.
 *
 * Full implementation delivered in the Security phase.
 *
 * @package CloudBridge\Security
 */

declare(strict_types=1);

namespace CloudBridge\Security;

/**
 * Defines and registers the manage_cloud_bridge custom capability.
 *
 * All admin actions require current_user_can('manage_cloud_bridge').
 */
final class Capabilities {

	/** The single custom capability required for all admin operations. */
	public const MANAGE = 'manage_cloud_bridge';

	// Full implementation delivered in Security phase.
}
