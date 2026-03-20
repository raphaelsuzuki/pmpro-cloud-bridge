<?php
/**
 * ProvisionInstanceJob — provisions a cloud server via the provider API.
 *
 * Full implementation delivered in the Jobs phase.
 *
 * @package CloudBridge\Jobs
 */

declare(strict_types=1);

namespace CloudBridge\Jobs;

/**
 * Action Scheduler job: cb_provision_instance.
 *
 * Dispatched only through ProvisionIntentService — never directly.
 * Max 3 retries with exponential backoff.
 * Pre-flight guard: exits without calling provision() if
 * _cb_provider_instance_id is already set (double-provisioning guard).
 */
final class ProvisionInstanceJob {
	// Full implementation delivered in Jobs phase.
}
