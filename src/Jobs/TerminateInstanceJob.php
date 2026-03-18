<?php
/**
 * TerminateInstanceJob — destroys a cloud server after grace period.
 *
 * Full implementation delivered in the Jobs phase.
 *
 * @package CloudBridge\Jobs
 */

declare(strict_types=1);

namespace CloudBridge\Jobs;

/**
 * Action Scheduler job: cb_terminate_instance.
 *
 * Only dispatched when instance status is TERMINATION_QUEUED.
 * Calling destroy() from any other state is a \LogicException.
 * Max 3 retries.
 */
final class TerminateInstanceJob {
	// Full implementation delivered in Jobs phase.
}
