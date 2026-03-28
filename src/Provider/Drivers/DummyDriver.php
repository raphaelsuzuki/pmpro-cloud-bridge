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
	/**
	 * Per-method override responses used by tests.
	 *
	 * Supported keys:
	 * - validate_credentials, provision, destroy, power_on, power_off,
	 *   reboot, rebuild, get_instance_status, get_available_plans,
	 *   get_available_regions, get_available_images (ProviderResult)
	 * - capabilities (array<string, bool>)
	 * - actions (array<int, array<string, mixed>>)
	 * - rate_limits (array{max_requests_per_minute:int, burst:int})
	 * - state_map (array<string, string>)
	 *
	 * @var array<string, mixed>
	 */
	private array $overrides;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $overrides Optional per-method response overrides.
	 */
	public function __construct( array $overrides = array() ) {
		$this->overrides = $overrides;
	}

	/**
	 * Returns a configured override when present.
	 *
	 * @param string $key Override map key.
	 */
	private function get_override( string $key ): mixed {
		return $this->overrides[ $key ] ?? null;
	}

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	/**
	 * Returns the unique provider ID.
	 */
	public function get_id(): string {
		return 'dummy';
	}

	/**
	 * Returns the provider label.
	 */
	public function get_label(): string {
		return 'Dummy Provider';
	}

	/**
	 * Returns the provider API version.
	 */
	public function get_api_version(): string {
		return 'v1';
	}

	// -------------------------------------------------------------------------
	// Credential validation
	// -------------------------------------------------------------------------

	/**
	 * Validates credentials.
	 *
	 * @return ProviderResult<bool>
	 */
	public function validate_credentials(): ProviderResult {
		$override = $this->get_override( 'validate_credentials' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok( true );
	}

	// -------------------------------------------------------------------------
	// Capabilities
	// -------------------------------------------------------------------------

	/**
	 * Returns supported optional capabilities.
	 *
	 * @return array<string, bool>
	 */
	public function get_capabilities(): array {
		$override = $this->get_override( 'capabilities' );
		if ( is_array( $override ) ) {
			return $override;
		}

		return array(
			'rebuild' => true,
			'console' => false,
			'resize'  => false,
		);
	}

	/**
	 * Returns provider action descriptors.
	 *
	 * @param string               $provider_instance_id Provider-side instance ID.
	 * @param array<string, mixed> $settings Driver-specific settings.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_actions( string $provider_instance_id, array $settings ): array {
		$override = $this->get_override( 'actions' );
		if ( is_array( $override ) ) {
			return $override;
		}

		return array();
	}

	// -------------------------------------------------------------------------
	// Lifecycle
	// -------------------------------------------------------------------------

	/**
	 * Provisions a new instance.
	 *
	 * @param ProvisionRequest $request Provisioning request payload.
	 * @return ProviderResult<ProvisionResult>
	 */
	public function provision( ProvisionRequest $request ): ProviderResult {
		$override = $this->get_override( 'provision' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok(
			new ProvisionResult(
				provider_instance_id: 'dummy-' . $request->idempotency_key,
				provider_status: 'pending',
				ipv4: '192.0.2.1',
				ipv6: null,
			)
		);
	}

	/**
	 * Destroys an instance.
	 *
	 * @param string $provider_instance_id Provider-side instance ID.
	 * @return ProviderResult<ActionResult>
	 */
	public function destroy( string $provider_instance_id ): ProviderResult {
		$override = $this->get_override( 'destroy' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-destroy' ) );
	}

	// -------------------------------------------------------------------------
	// Power operations
	// -------------------------------------------------------------------------

	/**
	 * Powers on an instance.
	 *
	 * @param string $provider_instance_id Provider-side instance ID.
	 * @return ProviderResult<ActionResult>
	 */
	public function power_on( string $provider_instance_id ): ProviderResult {
		$override = $this->get_override( 'power_on' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-power-on' ) );
	}

	/**
	 * Powers off an instance.
	 *
	 * @param string $provider_instance_id Provider-side instance ID.
	 * @return ProviderResult<ActionResult>
	 */
	public function power_off( string $provider_instance_id ): ProviderResult {
		$override = $this->get_override( 'power_off' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-power-off' ) );
	}

	/**
	 * Reboots an instance.
	 *
	 * @param string $provider_instance_id Provider-side instance ID.
	 * @return ProviderResult<ActionResult>
	 */
	public function reboot( string $provider_instance_id ): ProviderResult {
		$override = $this->get_override( 'reboot' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-reboot' ) );
	}

	// -------------------------------------------------------------------------
	// Rebuild
	// -------------------------------------------------------------------------

	/**
	 * Rebuilds an instance from an image.
	 *
	 * @param string $provider_instance_id Provider-side instance ID.
	 * @param string $image_id Image ID.
	 * @return ProviderResult<ActionResult>
	 */
	public function rebuild( string $provider_instance_id, string $image_id ): ProviderResult {
		$override = $this->get_override( 'rebuild' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'dummy-action-rebuild' ) );
	}

	// -------------------------------------------------------------------------
	// Status polling
	// -------------------------------------------------------------------------

	/**
	 * Returns the canonical instance status string.
	 *
	 * @param string $provider_instance_id Provider-side instance ID.
	 * @return ProviderResult<string>
	 */
	public function get_instance_status( string $provider_instance_id ): ProviderResult {
		$override = $this->get_override( 'get_instance_status' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		return ProviderResult::ok( InstanceStatus::ACTIVE );
	}

	// -------------------------------------------------------------------------
	// Catalogue
	// -------------------------------------------------------------------------

	/**
	 * Returns available plans.
	 *
	 * @param string|null $region_slug Optional region filter.
	 * @return ProviderResult<array<int, array<string, mixed>>>
	 */
	public function get_available_plans( ?string $region_slug = null ): ProviderResult {
		$override = $this->get_override( 'get_available_plans' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		$plans = array(
			array(
				'slug'  => 'dummy-basic',
				'label' => 'Dummy Basic',
			),
		);

		/**
		 * Normalize plans payload to interface-declared generic shape.
		 *
		 * @var array<int, array<string, mixed>> $plans
		 */
		return ProviderResult::ok( $plans );
	}

	/**
	 * Returns available regions.
	 *
	 * @return ProviderResult<array<int, array<string, mixed>>>
	 */
	public function get_available_regions(): ProviderResult {
		$override = $this->get_override( 'get_available_regions' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		$regions = array(
			array(
				'slug'  => 'dummy-region-1',
				'label' => 'Dummy Region 1',
			),
		);

		/**
		 * Normalize regions payload to interface-declared generic shape.
		 *
		 * @var array<int, array<string, mixed>> $regions
		 */
		return ProviderResult::ok( $regions );
	}

	/**
	 * Returns available images.
	 *
	 * @return ProviderResult<array<int, array<string, mixed>>>
	 */
	public function get_available_images(): ProviderResult {
		$override = $this->get_override( 'get_available_images' );
		if ( $override instanceof ProviderResult ) {
			return $override;
		}

		$images = array(
			array(
				'id'    => 'dummy-ubuntu-22-04',
				'label' => 'Dummy Ubuntu 22.04',
			),
		);

		/**
		 * Normalize images payload to interface-declared generic shape.
		 *
		 * @var array<int, array<string, mixed>> $images
		 */
		return ProviderResult::ok( $images );
	}

	// -------------------------------------------------------------------------
	// Rate limits & state normalisation
	// -------------------------------------------------------------------------

	/**
	 * Returns static rate limits for the dummy provider.
	 *
	 * @return array{max_requests_per_minute: int, burst: int}
	 */
	public function get_rate_limits(): array {
		$override = $this->get_override( 'rate_limits' );
		if ( is_array( $override ) ) {
			return $override;
		}

		return array(
			'max_requests_per_minute' => 600,
			'burst'                   => 60,
		);
	}

	/**
	 * Maps provider state to canonical status.
	 *
	 * @param string $provider_state Raw provider state.
	 */
	public function normalise_state( string $provider_state ): string {
		$state_map = $this->get_override( 'state_map' );
		if ( is_array( $state_map ) && isset( $state_map[ $provider_state ] ) && is_string( $state_map[ $provider_state ] ) ) {
			return $state_map[ $provider_state ];
		}

		return match ( $provider_state ) {
			'pending' => InstanceStatus::PROVISIONING,
			'active'  => InstanceStatus::ACTIVE,
			'stopped' => InstanceStatus::STOPPED,
			default   => InstanceStatus::ERROR,
		};
	}
}
