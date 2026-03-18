<?php
/**
 * DummyDriver — reference implementation and PHPUnit test fixture.
 *
 * Returns hardcoded success/failure responses. Safe to use in development and
 * automated tests without hitting any real provider API.
 *
 * Only registerable when the constant CB_ENABLE_DUMMY_DRIVER is explicitly set:
 *   define( 'CB_ENABLE_DUMMY_DRIVER', true );
 *
 * @package CloudBridge\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Provider\Drivers;

use CloudBridge\Provider\AbstractProvider;
use CloudBridge\Provider\DTO\ActionResult;
use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\DTO\ProvisionRequest;
use CloudBridge\Provider\DTO\ProvisionResult;
use CloudBridge\Provider\Result\ProviderResult;

/**
 * Fully implemented driver that returns predictable hardcoded responses.
 *
 * Developers writing a new driver should copy this file and replace hardcoded
 * responses with real HTTP calls via AbstractProvider::http_request().
 */
final class DummyDriver extends AbstractProvider {

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	public function get_id(): string {
		return 'dummy';
	}

	public function get_label(): string {
		return 'Dummy Provider';
	}

	public function get_api_version(): string {
		return 'v1';
	}

	// -------------------------------------------------------------------------
	// Credential validation
	// -------------------------------------------------------------------------

	public function validate_credentials(): ProviderResult {
		return ProviderResult::ok( true );
	}

	// -------------------------------------------------------------------------
	// Capabilities
	// -------------------------------------------------------------------------

	public function get_capabilities(): array {
		return [
			'rebuild' => true,
			'console' => false,
			'resize'  => false,
		];
	}

	public function get_actions( string $provider_instance_id, array $settings ): array {
		return [];
	}

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	public function provision( ProvisionRequest $request ): ProviderResult {
		return ProviderResult::ok(
			new ProvisionResult(
				provider_instance_id: 'dummy-' . $request->idempotency_key,
				provider_status: 'pending',
				ipv4: '192.0.2.1',
				ipv6: null,
			)
		);
	}

	public function destroy( string $provider_instance_id ): ProviderResult {
		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-destroy' ) );
	}

	// -------------------------------------------------------------------------
	// Power operations
	// -------------------------------------------------------------------------

	public function power_on( string $provider_instance_id ): ProviderResult {
		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-power-on' ) );
	}

	public function power_off( string $provider_instance_id ): ProviderResult {
		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-power-off' ) );
	}

	public function reboot( string $provider_instance_id ): ProviderResult {
		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-reboot' ) );
	}

	// -------------------------------------------------------------------------
	// Rebuild
	// -------------------------------------------------------------------------

	public function rebuild( string $provider_instance_id, string $image_id ): ProviderResult {
		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-rebuild' ) );
	}

	// -------------------------------------------------------------------------
	// Status polling
	// -------------------------------------------------------------------------

	public function get_instance_status( string $provider_instance_id ): ProviderResult {
		return ProviderResult::ok( InstanceStatus::ACTIVE );
	}

	// -------------------------------------------------------------------------
	// Catalogue
	// -------------------------------------------------------------------------

	public function get_available_plans( ?string $region_slug = null ): ProviderResult {
		return ProviderResult::ok( [] );
	}

	public function get_available_regions(): ProviderResult {
		return ProviderResult::ok( [] );
	}

	public function get_available_images(): ProviderResult {
		return ProviderResult::ok( [] );
	}

	// -------------------------------------------------------------------------
	// Rate limits & state normalisation
	// -------------------------------------------------------------------------

	public function get_rate_limits(): array {
		return [
			'max_requests_per_minute' => 600,
			'burst'                   => 60,
		];
	}

	public function normalise_state( string $provider_state ): string {
		return match ( $provider_state ) {
			'pending' => InstanceStatus::PROVISIONING,
			'active'  => InstanceStatus::ACTIVE,
			'stopped' => InstanceStatus::STOPPED,
			default   => InstanceStatus::ERROR,
		};
	}
}
