<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName
/**
 * DriverContractTest — verifies DummyDriver satisfies CloudProviderInterface.
 *
 * Full test suite delivered in Task 1.4 / Provider phase.
 *
 * @package CloudBridge\Tests\Unit\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider;

use CloudBridge\Provider\DTO\ActionResult;
use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\DTO\ProvisionRequest;
use CloudBridge\Provider\DTO\ProvisionResult;
use CloudBridge\Provider\Drivers\DummyDriver;
use CloudBridge\Provider\Result\ProviderResult;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests that run against DummyDriver and ensure every driver method
 * returns the correct types and honours the ProviderResult contract.
 */
class DriverContractTest extends TestCase {
	/**
	 * System under test.
	 */
	private DummyDriver $driver;

	/**
	 * Sets up a fresh driver instance for each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->driver = new DummyDriver();
	}

	/**
	 * Verifies identity methods return stable values.
	 */
	public function test_identity_contract(): void {
		$this->assertSame( 'dummy', $this->driver->get_id() );
		$this->assertSame( 'Dummy Provider', $this->driver->get_label() );
		$this->assertSame( 'v1', $this->driver->get_api_version() );
	}

	/**
	 * Verifies credentials validation returns ProviderResult<bool>.
	 */
	public function test_validate_credentials_returns_provider_result_bool(): void {
		$result = $this->driver->validate_credentials();
		$this->assertInstanceOf( ProviderResult::class, $result );
		$this->assertTrue( $result->is_ok() );
		$this->assertTrue( $result->unwrap() );
	}

	/**
	 * Verifies capabilities and actions response shapes.
	 */
	public function test_capabilities_and_actions_contract(): void {
		$capabilities = $this->driver->get_capabilities();
		$this->assertIsArray( $capabilities );
		$this->assertArrayHasKey( 'rebuild', $capabilities );
		$this->assertArrayHasKey( 'console', $capabilities );
		$this->assertArrayHasKey( 'resize', $capabilities );

		$actions = $this->driver->get_actions( 'instance-1', array() );
		$this->assertIsArray( $actions );
	}

	/**
	 * Verifies provision returns ProviderResult<ProvisionResult>.
	 */
	public function test_provision_returns_provider_result_with_provision_result(): void {
		$request = new ProvisionRequest(
			idempotency_key: 'cb_101',
			plan_slug: 'dummy-basic',
			region_slug: 'dummy-region-1',
			image_id: 'dummy-ubuntu-22-04',
			hostname: 'dummy-host',
			user_id: 10,
			instance_id: 99,
			ssh_key_ids: array(),
		);

		$result = $this->driver->provision( $request );

		$this->assertInstanceOf( ProviderResult::class, $result );
		$this->assertTrue( $result->is_ok() );

		$payload = $result->unwrap();
		$this->assertInstanceOf( ProvisionResult::class, $payload );
		$this->assertSame( 'dummy-cb_101', $payload->provider_instance_id );
	}

	/**
	 * Verifies each action method returns ActionResult payloads.
	 *
	 * @param string $method Action method name.
	 * @dataProvider provide_action_methods
	 */
	public function test_action_methods_return_action_result_payloads( string $method ): void {
		if ( 'rebuild' === $method ) {
			$result = $this->driver->rebuild( 'instance-1', 'dummy-image' );
		} else {
			$result = $this->driver->{$method}( 'instance-1' );
		}

		$this->assertInstanceOf( ProviderResult::class, $result );
		$this->assertTrue( $result->is_ok() );
		$this->assertInstanceOf( ActionResult::class, $result->unwrap() );
	}

	/**
	 * Provides action methods that should return ActionResult payloads.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_action_methods(): array {
		return array(
			'destroy'   => array( 'destroy' ),
			'power_on'  => array( 'power_on' ),
			'power_off' => array( 'power_off' ),
			'reboot'    => array( 'reboot' ),
			'rebuild'   => array( 'rebuild' ),
		);
	}

	/**
	 * Verifies state polling and normalisation behavior.
	 */
	public function test_instance_status_and_normalise_state_contract(): void {
		$status = $this->driver->get_instance_status( 'instance-1' );
		$this->assertInstanceOf( ProviderResult::class, $status );
		$this->assertTrue( $status->is_ok() );
		$this->assertContains( $status->unwrap(), InstanceStatus::ALL );

		$this->assertSame( InstanceStatus::PROVISIONING, $this->driver->normalise_state( 'pending' ) );
		$this->assertSame( InstanceStatus::ACTIVE, $this->driver->normalise_state( 'active' ) );
		$this->assertSame( InstanceStatus::STOPPED, $this->driver->normalise_state( 'stopped' ) );
		$this->assertSame( InstanceStatus::ERROR, $this->driver->normalise_state( 'unknown' ) );
	}

	/**
	 * Verifies catalog endpoints and rate limit shape.
	 */
	public function test_catalogue_and_rate_limits_contract(): void {
		$plans = $this->driver->get_available_plans( 'dummy-region-1' );
		$this->assertTrue( $plans->is_ok() );
		$this->assertIsArray( $plans->unwrap() );

		$regions = $this->driver->get_available_regions();
		$this->assertTrue( $regions->is_ok() );
		$this->assertIsArray( $regions->unwrap() );

		$images = $this->driver->get_available_images();
		$this->assertTrue( $images->is_ok() );
		$this->assertIsArray( $images->unwrap() );

		$limits = $this->driver->get_rate_limits();
		$this->assertArrayHasKey( 'max_requests_per_minute', $limits );
		$this->assertArrayHasKey( 'burst', $limits );
		$this->assertIsInt( $limits['max_requests_per_minute'] );
		$this->assertIsInt( $limits['burst'] );
	}

	/**
	 * Verifies per-method override responses are honored.
	 */
	public function test_method_override_responses_are_respected(): void {
		$driver = new DummyDriver(
			array(
				'provision' => ProviderResult::fail( 'rate_limited', 'Dummy throttle' ),
			)
		);

		$request = new ProvisionRequest(
			idempotency_key: 'cb_202',
			plan_slug: 'dummy-basic',
			region_slug: 'dummy-region-1',
			image_id: 'dummy-ubuntu-22-04',
			hostname: 'dummy-host-2',
			user_id: 11,
			instance_id: 100,
			ssh_key_ids: array(),
		);

		$result = $driver->provision( $request );
		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'rate_limited', $result->get_error_code() );
	}
}
