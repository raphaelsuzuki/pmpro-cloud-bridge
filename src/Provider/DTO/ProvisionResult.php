<?php
/**
 * ProvisionResult DTO.
 *
 * Wrapped inside ProviderResult on a successful provision() call.
 *
 * @package CloudBridge\Provider\DTO
 */

declare(strict_types=1);

namespace CloudBridge\Provider\DTO;

/**
 * The data a driver returns after successfully initiating server provisioning.
 */
final class ProvisionResult {

	/**
	 * Constructor.
	 *
	 * @param string      $provider_instance_id Provider-side instance ID.
	 * @param string      $provider_status       Raw provider status string (will be normalised).
	 * @param string|null $ipv4                  IPv4 address if already assigned, null otherwise.
	 * @param string|null $ipv6                  IPv6 address if already assigned, null otherwise.
	 */
	public function __construct(
		public readonly string $provider_instance_id,
		public readonly string $provider_status,
		public readonly ?string $ipv4 = null,
		public readonly ?string $ipv6 = null,
	) {}
}
