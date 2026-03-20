<?php
/**
 * VultrDriver stub.
 *
 * Implements CloudProviderInterface for Vultr Cloud Compute (API v2).
 * Full implementation is delivered in Task 1.x (Provider Drivers phase).
 * This stub satisfies the autoloader and PHPStan during the scaffold phase.
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
 * Vultr Cloud Compute driver (API v2).
 *
 * Authentication: X-API-Key header.
 * Idempotency: X-Request-ID header on POST /instances.
 * Rate limit: 30 req/s.
 */
final class VultrDriver extends AbstractProvider {

	private const API_BASE = 'https://api.vultr.com/v2';

	public function __construct(
		private readonly string $api_key,
	) {}

	public function get_id(): string { return 'vultr'; }
	public function get_label(): string { return 'Vultr'; }
	public function get_api_version(): string { return 'v2'; }

	public function validate_credentials(): ProviderResult {
		return ProviderResult::fail( 'unsupported', 'VultrDriver not yet implemented.' );
	}

	public function get_capabilities(): array {
		return [ 'rebuild' => false, 'console' => false, 'resize' => false ];
	}

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

	public function get_rate_limits(): array {
		return [ 'max_requests_per_minute' => 1800, 'burst' => 30 ];
	}

	public function normalise_state( string $provider_state ): string {
		return match ( $provider_state ) {
			'pending'  => InstanceStatus::PROVISIONING,
			'active'   => InstanceStatus::ACTIVE,
			'stopped'  => InstanceStatus::STOPPED,
			'resizing' => InstanceStatus::REBUILDING,
			'reboot'   => InstanceStatus::REBOOTING,
			default    => InstanceStatus::ERROR,
		};
	}
}
