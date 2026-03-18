<?php
/**
 * Instance model — value object wrapping a cb_instance CPT post + meta.
 *
 * @package CloudBridge\Model
 */

declare(strict_types=1);

namespace CloudBridge\Model;

/**
 * Read-only value object representing a single cloud instance.
 *
 * Populated by InstanceRepository::find(). Never write to this object directly —
 * all mutations go through the repository and the state machine.
 */
final class Instance {
	// Full implementation delivered in Model phase.
}
