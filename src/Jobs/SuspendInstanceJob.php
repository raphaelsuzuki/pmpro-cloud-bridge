<?php
/**
 * SuspendInstanceJob — suspends a cloud server on payment failure.
 *
 * Full implementation delivered in the Jobs phase.
 *
 * @package CloudBridge\Jobs
 */

declare(strict_types=1);

namespace CloudBridge\Jobs;

/**
 * Action Scheduler job: cb_suspend_instance.
 *
 * Triggered by pmpro_subscription_payment_failed. Max 3 retries.
 */
final class SuspendInstanceJob {
	// Full implementation delivered in Jobs phase.
}
