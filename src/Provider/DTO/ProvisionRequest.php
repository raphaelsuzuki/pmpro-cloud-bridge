<?php
/**
 * ProvisionRequest DTO.
 *
 * Immutable value object passed to CloudProviderInterface::provision().
 *
 * @package CloudBridge\Provider\DTO
 */

declare(strict_types=1);

namespace CloudBridge\Provider\DTO;

/**
 * All the data a driver needs to provision a new server instance.
 */
final class ProvisionRequest {

	/**
	 * @param string      $idempotency_key Derived from order ID: cb_{order_id}.
	 * @param string      $plan_slug       Provider-specific plan/size slug.
	 * @param string      $region_slug     Provider-specific region slug.
	 * @param string      $image_id        OS image ID.
	 * @param string      $hostname        RFC-1123 validated hostname.
	 * @param int         $user_id         WordPress user ID of the instance owner.
	 * @param int         $instance_id     cb_instance CPT post ID.
	 * @param string[]    $ssh_key_ids     Optional list of provider SSH key IDs.
	 */
	public function __construct(
		public readonly string $idempotency_key,
		public readonly string $plan_slug,
		public readonly string $region_slug,
		public readonly string $image_id,
		public readonly string $hostname,
		public readonly int    $user_id,
		public readonly int    $instance_id,
		public readonly array  $ssh_key_ids = [],
	) {}
}
