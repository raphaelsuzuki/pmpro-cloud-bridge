<?php
/**
 * JobScheduler — registers all Action Scheduler job hooks.
 *
 * Full implementation delivered in the Jobs phase.
 *
 * @package CloudBridge\Jobs
 */

declare(strict_types=1);

namespace CloudBridge\Jobs;

/**
 * Registers and schedules all cb_* Action Scheduler recurring jobs.
 *
 * Hook naming (from spec section 12.2):
 *   cb_{verb}_{noun}  e.g. cb_provision_instance, cb_reconcile_instances
 */
final class JobScheduler {
	// Full implementation delivered in Jobs phase.
}
