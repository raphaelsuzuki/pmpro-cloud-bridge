<?php
/**
 * PollInstanceStatusJob — polls provider for instance status after provisioning.
 *
 * Full implementation delivered in the Jobs phase.
 *
 * @package CloudBridge\Jobs
 */

declare(strict_types=1);

namespace CloudBridge\Jobs;

/**
 * Action Scheduler job: cb_poll_instance_status.
 *
 * Adaptive backoff: 10s → 20s → 40s → 60s → 120s ceiling.
 * Max 10 polls (~14 minutes total at ceiling). On timeout: status → PROVISION_FAILED.
 */
final class PollInstanceStatusJob {
	// Full implementation delivered in Jobs phase.
}
