<?php
/**
 * InstanceStateMachine — enforces valid instance status transitions.
 *
 * Full implementation delivered in Task 1.4.
 *
 * @package CloudBridge\Model
 */

declare(strict_types=1);

namespace CloudBridge\Model;

/**
 * Enforces the allowed-transitions map from spec section 2.3.
 *
 * transition() is the ONLY permitted way to write to _cb_instance_status.
 * Invalid transitions throw \LogicException.
 * destroy() on the provider may only be called from TERMINATION_QUEUED —
 * violation throws \LogicException at the job layer.
 */
final class InstanceStateMachine {
	// Full implementation delivered in Task 1.4.
}
