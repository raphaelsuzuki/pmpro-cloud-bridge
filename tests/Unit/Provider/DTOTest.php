<?php
/**
 * Unit tests for Provider DTOs and ProviderResult.
 *
 * @package CloudBridge\Tests\Unit\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider;

use CloudBridge\Provider\DTO\ProvisionRequest;
use CloudBridge\Provider\DTO\ProvisionResult;
use CloudBridge\Provider\DTO\ActionResult;
use CloudBridge\Provider\Result\ProviderResult;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all DTO classes and the ProviderResult monad.
 */
class DTOTest extends TestCase {
	/**
	 * Test ProvisionRequest construction with required parameters.
	 */
	public function test_provision_request_construction(): void {
		$request = new ProvisionRequest(
			idempotency_key: 'cb_12345',
			plan_slug: 'cloud_basic',
			region_slug: 'us-west-1',
			image_id: 'ubuntu-22.04',
			hostname: 'my-server.example.com',
			user_id: 123,
			instance_id: 456,
			ssh_key_ids: array( 'key-1', 'key-2' ),
		);

		$this->assertSame( 'cb_12345', $request->idempotency_key );
		$this->assertSame( 'cloud_basic', $request->plan_slug );
		$this->assertSame( 'us-west-1', $request->region_slug );
		$this->assertSame( 'ubuntu-22.04', $request->image_id );
		$this->assertSame( 'my-server.example.com', $request->hostname );
		$this->assertSame( 123, $request->user_id );
		$this->assertSame( 456, $request->instance_id );
		$this->assertSame( array( 'key-1', 'key-2' ), $request->ssh_key_ids );
	}

	/**
	 * Test ProvisionRequest with default ssh_key_ids (empty array).
	 */
	public function test_provision_request_construction_without_ssh_keys(): void {
		$request = new ProvisionRequest(
			idempotency_key: 'cb_12345',
			plan_slug: 'cloud_basic',
			region_slug: 'us-west-1',
			image_id: 'ubuntu-22.04',
			hostname: 'my-server',
			user_id: 123,
			instance_id: 456,
		);

		$this->assertSame( array(), $request->ssh_key_ids );
	}

	/**
	 * Test ProvisionResult construction with all fields.
	 */
	public function test_provision_result_construction(): void {
		$result = new ProvisionResult(
			provider_instance_id: 'vultr-567890',
			provider_status: 'pending',
			ipv4: '192.0.2.1',
			ipv6: '2001:db8::1',
		);

		$this->assertSame( 'vultr-567890', $result->provider_instance_id );
		$this->assertSame( 'pending', $result->provider_status );
		$this->assertSame( '192.0.2.1', $result->ipv4 );
		$this->assertSame( '2001:db8::1', $result->ipv6 );
	}

	/**
	 * Test ProvisionResult construction with null IP addresses.
	 */
	public function test_provision_result_construction_without_ips(): void {
		$result = new ProvisionResult(
			provider_instance_id: 'hetzner-123',
			provider_status: 'initializing',
		);

		$this->assertSame( 'hetzner-123', $result->provider_instance_id );
		$this->assertSame( 'initializing', $result->provider_status );
		$this->assertNull( $result->ipv4 );
		$this->assertNull( $result->ipv6 );
	}

	/**
	 * Test ActionResult construction with action ID.
	 */
	public function test_action_result_construction(): void {
		$result = new ActionResult(
			provider_action_id: 'hetzner-action-789',
			provider_status: 'running',
		);

		$this->assertSame( 'hetzner-action-789', $result->provider_action_id );
		$this->assertSame( 'running', $result->provider_status );
	}

	/**
	 * Test ActionResult construction with null status.
	 */
	public function test_action_result_construction_without_status(): void {
		$result = new ActionResult(
			provider_action_id: 'action-123',
		);

		$this->assertSame( 'action-123', $result->provider_action_id );
		$this->assertNull( $result->provider_status );
	}

	/**
	 * Test ProviderResult::ok() wraps a value.
	 */
	public function test_provider_result_ok(): void {
		$data   = array( 'instance_id' => 'test-123' );
		$result = ProviderResult::ok( $data );

		$this->assertTrue( $result->is_ok() );
		$this->assertSame( $data, $result->unwrap() );
		$this->assertSame( '', $result->get_error_code() );
		$this->assertSame( '', $result->get_error_message() );
	}

	/**
	 * Test ProviderResult::ok() with null data.
	 */
	public function test_provider_result_ok_with_null(): void {
		$result = ProviderResult::ok();

		$this->assertTrue( $result->is_ok() );
		$this->assertNull( $result->unwrap() );
	}

	/**
	 * Test ProviderResult::fail() creates failure result.
	 */
	public function test_provider_result_fail(): void {
		$result = ProviderResult::fail( 'auth_failed', 'Invalid API key' );

		$this->assertFalse( $result->is_ok() );
		$this->assertSame( 'auth_failed', $result->get_error_code() );
		$this->assertSame( 'Invalid API key', $result->get_error_message() );
	}

	/**
	 * Test ProviderResult::unwrap() throws on failure.
	 */
	public function test_provider_result_unwrap_throws_on_failure(): void {
		$result = ProviderResult::fail( 'not_found', 'Instance does not exist' );

		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( '[not_found] Instance does not exist' );

		$result->unwrap();
	}

	/**
	 * Test ProviderResult with all valid error codes.
	 */
	public function test_provider_result_all_valid_error_codes(): void {
		$codes = array(
			'auth_failed',
			'not_found',
			'rate_limited',
			'quota_exceeded',
			'invalid_plan',
			'api_error',
			'http_error',
			'unsupported',
		);

		foreach ( $codes as $code ) {
			$result = ProviderResult::fail( $code, "Test error: $code" );
			$this->assertFalse( $result->is_ok() );
			$this->assertSame( $code, $result->get_error_code() );
		}
	}

	/**
	 * Test ProviderResult error code constants.
	 */
	public function test_provider_result_error_codes_constant(): void {
		$this->assertIsArray( ProviderResult::ERROR_CODES );
		$this->assertContains( 'auth_failed', ProviderResult::ERROR_CODES );
		$this->assertContains( 'not_found', ProviderResult::ERROR_CODES );
		$this->assertContains( 'rate_limited', ProviderResult::ERROR_CODES );
		$this->assertContains( 'quota_exceeded', ProviderResult::ERROR_CODES );
		$this->assertContains( 'invalid_plan', ProviderResult::ERROR_CODES );
		$this->assertContains( 'api_error', ProviderResult::ERROR_CODES );
		$this->assertContains( 'http_error', ProviderResult::ERROR_CODES );
		$this->assertContains( 'unsupported', ProviderResult::ERROR_CODES );
	}

	/**
	 * Test ProviderResult with object data.
	 */
	public function test_provider_result_ok_with_object(): void {
		$provision_result = new ProvisionResult(
			provider_instance_id: 'id-123',
			provider_status: 'active',
		);

		$result = ProviderResult::ok( $provision_result );

		$this->assertTrue( $result->is_ok() );
		$unwrapped = $result->unwrap();
		$this->assertInstanceOf( ProvisionResult::class, $unwrapped );
		$this->assertSame( 'id-123', $unwrapped->provider_instance_id );
	}

	/**
	 * Test ProvisionRequest properties are public and accessible.
	 */
	public function test_provision_request_properties_are_accessible(): void {
		$request = new ProvisionRequest(
			idempotency_key: 'cb_1',
			plan_slug: 'basic',
			region_slug: 'us-east',
			image_id: 'ubuntu',
			hostname: 'test',
			user_id: 1,
			instance_id: 2,
		);

		// Verify all properties are readable.
		$this->assertIsString( $request->idempotency_key );
		$this->assertIsString( $request->plan_slug );
		$this->assertIsString( $request->region_slug );
		$this->assertIsInt( $request->user_id );
	}
}
