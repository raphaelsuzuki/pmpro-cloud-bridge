<?php
/**
 * InstanceStatus — canonical status constants for cb_instance lifecycle.
 *
 * @package CloudBridge\Model
 */

declare(strict_types=1);

namespace CloudBridge\Model;

/**
 * Enum-like status constants used by state machine transitions.
 */
final class InstanceStatus {

	public const PROVISIONING_QUEUED  = 'PROVISIONING_QUEUED';
	public const PROVISIONING_PENDING = 'PROVISIONING_PENDING';
	public const PROVISIONING         = 'PROVISIONING';
	public const ACTIVE               = 'ACTIVE';
	public const SUSPENDED            = 'SUSPENDED';
	public const STOPPED              = 'STOPPED';
	public const STARTING             = 'STARTING';
	public const STOPPING             = 'STOPPING';
	public const REBOOTING            = 'REBOOTING';
	public const REBUILDING           = 'REBUILDING';
	public const PROVISION_FAILED     = 'PROVISION_FAILED';
	public const CANCEL_REQUESTED     = 'CANCEL_REQUESTED';
	public const CANCELLING           = 'CANCELLING';
	public const CANCELLED            = 'CANCELLED';
	public const TERMINATION_QUEUED   = 'TERMINATION_QUEUED';
	public const TERMINATED           = 'TERMINATED';
	public const ERROR                = 'ERROR';

	/**
	 * All valid status values.
	 *
	 * @var string[]
	 */
	public const ALL = [
		self::PROVISIONING_QUEUED,
		self::PROVISIONING_PENDING,
		self::PROVISIONING,
		self::ACTIVE,
		self::SUSPENDED,
		self::STOPPED,
		self::STARTING,
		self::STOPPING,
		self::REBOOTING,
		self::REBUILDING,
		self::PROVISION_FAILED,
		self::CANCEL_REQUESTED,
		self::CANCELLING,
		self::CANCELLED,
		self::TERMINATION_QUEUED,
		self::TERMINATED,
		self::ERROR,
	];

	/**
	 * Returns true when the input is a known lifecycle status.
	 */
	public static function is_valid( string $status ): bool {
		return in_array( $status, self::ALL, true );
	}

	private function __construct() {}
}