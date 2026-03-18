<?php
/**
 * HealthCheck — cron and driver health monitoring.
 *
 * Full implementation delivered in the Admin phase.
 *
 * @package CloudBridge\Admin
 */

declare(strict_types=1);

namespace CloudBridge\Admin;

/**
 * Checks on every admin_init that cb_reconcile_instances ran within 8 hours.
 * Shows error-level admin notice if overdue; warning at 2 hours.
 * Also surfaces driver validate_credentials() failures.
 */
final class HealthCheck {
	// Full implementation delivered in Admin phase.
}
