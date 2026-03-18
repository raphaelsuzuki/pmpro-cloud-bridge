<?php
/**
 * ActionResult DTO.
 *
 * Wrapped inside ProviderResult on a successful lifecycle action
 * (power_on, power_off, reboot, rebuild, destroy).
 *
 * @package CloudBridge\Provider\DTO
 */

declare(strict_types=1);

namespace CloudBridge\Provider\DTO;

/**
 * The data a driver returns after successfully initiating a lifecycle action.
 */
final class ActionResult {

	/**
	 * @param string      $provider_action_id Provider-side action/task ID for polling.
	 * @param string|null $provider_status     Raw provider status string after action, if known.
	 */
	public function __construct(
		public readonly string  $provider_action_id,
		public readonly ?string $provider_status = null,
	) {}
}
