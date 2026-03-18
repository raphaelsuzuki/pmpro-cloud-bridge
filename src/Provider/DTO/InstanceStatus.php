<?php
/**
 * InstanceStatus — all valid instance status values.
 *
 * This class is the single source of truth for status strings used in the state
 * machine, the database, and the provider normalise_state() method.
 * No status string may appear anywhere else as a bare string literal.
 *
 * @package CloudBridge\Provider\DTO
 */

declare(strict_types=1);

namespace CloudBridge\Provider\DTO;

/**
 * Enum-like constants for instance status values.
 *
 * PHP 8.1 backed enums would be ideal here but string-backed enums cannot be
 * stored directly in WordPress meta without casting. Constants on a final class
 * keep things simple and PHPStan-friendly while allowing direct string comparison.
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
	 * Transitional states — those that trigger active polling.
	 *
	 * @var string[]
	 */
	public const TRANSITIONAL = [
		self::PROVISIONING_QUEUED,
		self::PROVISIONING_PENDING,
		self::PROVISIONING,
		self::STARTING,
		self::STOPPING,
		self::REBOOTING,
		self::REBUILDING,
		self::CANCEL_REQUESTED,
		self::CANCELLING,
		self::TERMINATION_QUEUED,
	];

	/** Prevent instantiation — this class is a namespace for constants only. */
	private function __construct() {}
}
