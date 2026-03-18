<?php
/**
 * ReconcileInstancesJob — detects drift between DB state and provider state.
 *
 * Full implementation delivered in the Jobs phase.
 *
 * @package CloudBridge\Jobs
 */

declare(strict_types=1);

namespace CloudBridge\Jobs;

/**
 * Action Scheduler job: cb_reconcile_instances.
 *
 * Tiered polling: 2 min for transitional states, 6 hours for stable states.
 * Logs any drift to cb_events and fires admin notice per drifted instance.
 */
final class ReconcileInstancesJob {
	// Full implementation delivered in Jobs phase.
}
