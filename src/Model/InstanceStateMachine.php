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
	/**
	 * Allowed status transitions from each source status.
	 *
	 * @var array<string, string[]>
	 */
	private const ALLOWED_TRANSITIONS = [
		InstanceStatus::PROVISIONING_QUEUED  => [ InstanceStatus::PROVISIONING ],
		InstanceStatus::PROVISIONING_PENDING => [ InstanceStatus::PROVISIONING, InstanceStatus::CANCEL_REQUESTED ],
		InstanceStatus::PROVISIONING         => [ InstanceStatus::ACTIVE, InstanceStatus::CANCEL_REQUESTED, InstanceStatus::PROVISION_FAILED ],
		InstanceStatus::ACTIVE               => [ InstanceStatus::SUSPENDED, InstanceStatus::CANCEL_REQUESTED, InstanceStatus::REBUILDING, InstanceStatus::STOPPING, InstanceStatus::REBOOTING ],
		InstanceStatus::SUSPENDED            => [ InstanceStatus::ACTIVE, InstanceStatus::TERMINATION_QUEUED ],
		InstanceStatus::STOPPED              => [ InstanceStatus::STARTING ],
		InstanceStatus::STARTING             => [ InstanceStatus::ACTIVE ],
		InstanceStatus::STOPPING             => [ InstanceStatus::STOPPED ],
		InstanceStatus::REBOOTING            => [ InstanceStatus::ACTIVE ],
		InstanceStatus::REBUILDING           => [ InstanceStatus::ACTIVE, InstanceStatus::PROVISION_FAILED ],
		InstanceStatus::PROVISION_FAILED     => [ InstanceStatus::PROVISIONING_PENDING, InstanceStatus::CANCEL_REQUESTED ],
		InstanceStatus::CANCEL_REQUESTED     => [ InstanceStatus::CANCELLING, InstanceStatus::TERMINATION_QUEUED ],
		InstanceStatus::CANCELLING           => [ InstanceStatus::CANCELLED, InstanceStatus::ERROR ],
		InstanceStatus::CANCELLED            => [ InstanceStatus::TERMINATED ],
		InstanceStatus::TERMINATION_QUEUED   => [ InstanceStatus::TERMINATED ],
		InstanceStatus::TERMINATED           => [],
		InstanceStatus::ERROR                => [],
	];

	/**
	 * Validates and returns the next status.
	 *
	 * @throws \LogicException When the transition is invalid.
	 */
	public static function transition( string $current_status, string $new_status ): string {
		if ( ! InstanceStatus::is_valid( $current_status ) ) {
			throw new \LogicException( sprintf( 'Unknown current status "%s".', $current_status ) );
		}

		if ( ! InstanceStatus::is_valid( $new_status ) ) {
			throw new \LogicException( sprintf( 'Unknown target status "%s".', $new_status ) );
		}

		if ( $current_status === $new_status ) {
			return $new_status;
		}

		if ( ! self::can_transition( $current_status, $new_status ) ) {
			throw new \LogicException(
				sprintf(
					'Invalid status transition from "%s" to "%s".',
					$current_status,
					$new_status
				)
			);
		}

		return $new_status;
	}

	/**
	 * Returns true when the transition is allowed by the map.
	 *
	 * @throws \LogicException When either status value is unknown.
	 */
	public static function can_transition( string $current_status, string $new_status ): bool {
		if ( ! InstanceStatus::is_valid( $current_status ) ) {
			throw new \LogicException( sprintf( 'Unknown current status "%s".', $current_status ) );
		}

		if ( ! InstanceStatus::is_valid( $new_status ) ) {
			throw new \LogicException( sprintf( 'Unknown target status "%s".', $new_status ) );
		}

		$allowed = self::ALLOWED_TRANSITIONS[ $current_status ] ?? [];

		return in_array( $new_status, $allowed, true );
	}

	/**
	 * Returns allowed target statuses for a source status.
	 *
	 * @return string[]
	 */
	public static function allowed_targets( string $current_status ): array {
		if ( ! InstanceStatus::is_valid( $current_status ) ) {
			throw new \LogicException( sprintf( 'Unknown current status "%s".', $current_status ) );
		}

		return self::ALLOWED_TRANSITIONS[ $current_status ] ?? [];
	}
}
