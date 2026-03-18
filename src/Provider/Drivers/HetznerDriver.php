<?php
/**
 * HetznerDriver stub.
 *
 * @package CloudBridge\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Provider\Drivers;

use CloudBridge\Provider\AbstractProvider;
use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\DTO\ProvisionRequest;
use CloudBridge\Provider\Result\ProviderResult;

/**
 * Hetzner Cloud driver (API v1).
 *
 * Authentication: Authorization: Bearer header.
 * Idempotency: X-Idempotency-Key header.
 * Rate limit: 3600 req/hour per token.
 */
final class HetznerDriver extends AbstractProvider {

	public function __construct(
		private readonly string $api_token,
	) {}

	public function get_id(): string { return 'hetzner'; }
	public function get_label(): string { return 'Hetzner Cloud'; }
	public function get_api_version(): string { return 'v1'; }

	public function validate_credentials(): ProviderResult { return ProviderResult::fail( 'unsupported', 'HetznerDriver not yet implemented.' ); }
	public function get_capabilities(): array { return [ 'rebuild' => true, 'console' => false, 'resize' => false ]; }
	public function get_actions( string $provider_instance_id, array $settings ): array { return []; }
	public function provision( ProvisionRequest $request ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function destroy( string $provider_instance_id ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function power_on( string $provider_instance_id ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function power_off( string $provider_instance_id ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function reboot( string $provider_instance_id ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function rebuild( string $provider_instance_id, string $image_id ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function get_instance_status( string $provider_instance_id ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function get_available_plans( ?string $region_slug = null ): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function get_available_regions(): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }
	public function get_available_images(): ProviderResult { return ProviderResult::fail( 'unsupported', 'Not yet implemented.' ); }

	public function get_rate_limits(): array { return [ 'max_requests_per_minute' => 60, 'burst' => 10 ]; }

	public function normalise_state( string $provider_state ): string {
		return match ( $provider_state ) {
			'initializing' => InstanceStatus::PROVISIONING,
			'starting'     => InstanceStatus::STARTING,
			'running'      => InstanceStatus::ACTIVE,
			'stopping'     => InstanceStatus::STOPPING,
			'off'          => InstanceStatus::STOPPED,
			'deleting'     => InstanceStatus::CANCELLING,
			'rebuilding'   => InstanceStatus::REBUILDING,
			'migrating'    => InstanceStatus::REBUILDING,
			default        => InstanceStatus::ERROR,
		};
	}
}
