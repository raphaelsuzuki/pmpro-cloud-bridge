<?php
/**
 * LeasewebDriver — Leaseweb (API v1).
 *
 * Implements CloudProviderInterface for Leaseweb Cloud.
 *
 * Authentication: X-LSW-Auth API key header.
 * Rate limit: 100 req/min (typical).
 * API docs: Check .localdocs/openapi.json for exact spec.
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
 * Leaseweb driver (API v1).
 *
 * Zero WordPress coupling. Configuration injected via constructor.
 */
final class LeasewebDriver extends AbstractProvider {

	private const API_BASE = 'https://api.leaseweb.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $api_key Leaseweb API key.
	 */
	public function __construct(
		private readonly string $api_key,
	) {
	}

	// -------------------------------------------------------------------------
	// Identity
	// -------------------------------------------------------------------------

	/**
	 * Gets the provider identifier.
	 *
	 * @return string Provider identifier.
	 */
	public function get_id(): string {
		return 'leaseweb';
	}

	/**
	 * Gets the provider label.
	 *
	 * @return string Human-readable provider label.
	 */
	public function get_label(): string {
		return 'Leaseweb';
	}

	/**
	 * Gets the provider API version.
	 *
	 * @return string Provider API version.
	 */
	public function get_api_version(): string {
		return 'v1';
	}

	// -------------------------------------------------------------------------
	// Credential Validation
	// -------------------------------------------------------------------------

	/**
	 * Validates the configured API credentials.
	 *
	 * @return ProviderResult Credential validation result.
	 */
	public function validate_credentials(): ProviderResult {
		$response = $this->http_request(
			'GET',
			self::API_BASE . '/instances?limit=1',
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			if ( in_array( $response['code'], array( 401, 403 ), true ) ) {
				return ProviderResult::fail( 'auth_failed', 'Invalid or expired Leaseweb API key.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to validate Leaseweb credentials.' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( true );
	}

	// -------------------------------------------------------------------------
	// Capabilities
	// -------------------------------------------------------------------------

	/**
	 * Gets the provider capability flags.
	 *
	 * @return array Provider capability map.
	 */
	public function get_capabilities(): array {
		return array(
			'rebuild' => true,
			'console' => false,
			'resize'  => false,
		);
	}

	/**
	 * Gets the available instance actions.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @param array  $settings Provider-specific instance settings.
	 * @return array Available actions for the instance.
	 */
	public function get_actions( string $provider_instance_id, array $settings ): array {
		return array();
	}

	// -------------------------------------------------------------------------
	// Provisioning
	// -------------------------------------------------------------------------

	/**
	 * Provisions a new Leaseweb instance.
	 *
	 * @param ProvisionRequest $request Provisioning request payload.
	 * @return ProviderResult Provisioning result.
	 */
	public function provision( ProvisionRequest $request ): ProviderResult {
		$body = array(
			'hostname' => $request->hostname,
			'region'   => $request->region_slug,
			'type'     => $request->plan_slug,
			'image'    => $request->image_id,
		);

		// Add SSH keys if provided.
		if ( ! empty( $request->ssh_key_ids ) ) {
			$body['ssh_keys'] = array_map( 'strval', $request->ssh_key_ids );
		}

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/instances',
			$this->get_headers(),
			$body
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 201 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to provision instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['id'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Leaseweb API.' );
		}

		$ipv4 = $this->extract_ipv4( $decoded );
		$ipv6 = $this->extract_ipv6( $decoded );

		return ProviderResult::ok(
			new ProvisionResult(
				provider_instance_id: (string) $decoded['id'],
				provider_status: $decoded['state'] ?? 'provisioning',
				ipv4: $ipv4,
				ipv6: $ipv6
			)
		);
	}

	// -------------------------------------------------------------------------
	// Instance Destruction
	// -------------------------------------------------------------------------

	/**
	 * Destroys an existing instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Destruction result.
	 */
	public function destroy( string $provider_instance_id ): ProviderResult {
		$response = $this->http_request(
			'DELETE',
			self::API_BASE . '/instances/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		// Leaseweb returns 204 No Content on successful deletion.
		if ( 204 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to destroy instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'leaseweb-destroy-' . $provider_instance_id ) );
	}

	// -------------------------------------------------------------------------
	// Power Operations
	// -------------------------------------------------------------------------

	/**
	 * Powers on an instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Power-on result.
	 */
	public function power_on( string $provider_instance_id ): ProviderResult {
		return $this->send_instance_action( $provider_instance_id, 'start' );
	}

	/**
	 * Powers off an instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Power-off result.
	 */
	public function power_off( string $provider_instance_id ): ProviderResult {
		return $this->send_instance_action( $provider_instance_id, 'stop' );
	}

	/**
	 * Reboots an instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Reboot result.
	 */
	public function reboot( string $provider_instance_id ): ProviderResult {
		return $this->send_instance_action( $provider_instance_id, 'reboot' );
	}

	// -------------------------------------------------------------------------
	// Rebuild
	// -------------------------------------------------------------------------

	/**
	 * Rebuilds an instance from the specified image.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @param string $image_id             Image identifier to rebuild from.
	 * @return ProviderResult Rebuild result.
	 */
	public function rebuild( string $provider_instance_id, string $image_id ): ProviderResult {
		$body = array(
			'image' => $image_id,
		);

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/instances/' . $provider_instance_id . '/actions/rebuild',
			$this->get_headers(),
			$body
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] && 201 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to rebuild instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'leaseweb-rebuild-' . $provider_instance_id ) );
	}

	// -------------------------------------------------------------------------
	// Status Polling
	// -------------------------------------------------------------------------

	/**
	 * Gets the normalized status for an instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Instance status lookup result.
	 */
	public function get_instance_status( string $provider_instance_id ): ProviderResult {
		$response = $this->http_request(
			'GET',
			self::API_BASE . '/instances/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			// Not found is expected if instance was deleted.
			if ( 404 === $response['code'] ) {
				return ProviderResult::fail( 'not_found', 'Instance not found on Leaseweb.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to get instance status' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['state'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Leaseweb API.' );
		}

		$provider_status   = (string) $decoded['state'];
		$normalized_status = $this->normalise_state( $provider_status );

		return ProviderResult::ok( $normalized_status );
	}

	// -------------------------------------------------------------------------
	// Catalogue (Plans, Regions, Images)
	// -------------------------------------------------------------------------

	/**
	 * Gets the available plans for the provider.
	 *
	 * @param string|null $region_slug Optional region slug used to filter plans.
	 * @return ProviderResult Available plans result.
	 */
	public function get_available_plans( ?string $region_slug = null ): ProviderResult {
		$url = self::API_BASE . '/instance-types?limit=100';

		$response = $this->http_request( 'GET', $url, $this->get_headers() );

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to fetch plans' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['items'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Leaseweb API.' );
		}

		$plans = array();
		foreach ( $decoded['items'] as $plan ) {
			// Filter by region if requested.
			if ( null !== $region_slug ) {
				if ( ! isset( $plan['regions'] ) || ! in_array( $region_slug, (array) $plan['regions'], true ) ) {
					continue;
				}
			}

			$plans[] = array(
				'slug'          => $plan['id'],
				'name'          => $plan['name'] ?? '',
				'cores'         => $plan['cpu'] ?? 0,
				'memory_gb'     => ( $plan['memory'] ?? 0 ) / 1024,
				'disk_gb'       => ( $plan['storage'] ?? 0 ) / 1024,
				'price_monthly' => (float) ( $plan['price'] ?? 0 ),
				'price_hourly'  => (float) ( $plan['price'] ?? 0 ) / 730,
			);
		}

		return ProviderResult::ok( $plans );
	}

	/**
	 * Gets the available regions for the provider.
	 *
	 * @return ProviderResult Available regions result.
	 */
	public function get_available_regions(): ProviderResult {
		$response = $this->http_request(
			'GET',
			self::API_BASE . '/regions?limit=100',
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to fetch regions' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['items'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Leaseweb API.' );
		}

		$regions = array();
		foreach ( $decoded['items'] as $region ) {
			$regions[] = array(
				'slug'      => $region['id'],
				'name'      => $region['name'] ?? '',
				'continent' => '',
			);
		}

		return ProviderResult::ok( $regions );
	}

	/**
	 * Gets the available images for the provider.
	 *
	 * @return ProviderResult Available images result.
	 */
	public function get_available_images(): ProviderResult {
		$response = $this->http_request(
			'GET',
			self::API_BASE . '/images?limit=200',
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to fetch images' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['items'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Leaseweb API.' );
		}

		$images = array();
		foreach ( $decoded['items'] as $image ) {
			$images[] = array(
				'id'   => $image['id'] ?? '',
				'name' => $image['name'] ?? '',
				'arch' => 'x64',
			);
		}

		return ProviderResult::ok( $images );
	}

	// -------------------------------------------------------------------------
	// Rate Limits & State Normalization
	// -------------------------------------------------------------------------

	/**
	 * Gets the provider rate limit metadata.
	 *
	 * @return array Provider rate limit settings.
	 */
	public function get_rate_limits(): array {
		return array(
			'max_requests_per_minute' => 100,
			'burst'                   => 20,
		);
	}

	/**
	 * Normalizes a provider-specific state value.
	 *
	 * @param string $provider_state Provider-specific state value.
	 * @return string Normalized instance status.
	 */
	public function normalise_state( string $provider_state ): string {
		return match ( $provider_state ) {
			'provisioning' => InstanceStatus::PROVISIONING,
			'running'      => InstanceStatus::ACTIVE,
			'stopped'      => InstanceStatus::STOPPED,
			'rebooting'    => InstanceStatus::REBOOTING,
			default        => InstanceStatus::ERROR,
		};
	}

	// -------------------------------------------------------------------------
	// Private Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns standard headers for Leaseweb API requests.
	 *
	 * @return array<string, string>
	 */
	private function get_headers(): array {
		return array(
			'X-LSW-Auth' => $this->api_key,
		);
	}

	/**
	 * Sends an action command to a Leaseweb instance.
	 *
	 * @param string $instance_id Provider instance ID.
	 * @param string $action Action name ('start', 'stop', or 'reboot').
	 * @return ProviderResult<ActionResult>
	 *
	 * @throws \InvalidArgumentException If action is not one of the accepted constants.
	 */
	private function send_instance_action( string $instance_id, string $action ): ProviderResult {
		$action_map = array(
			'start'  => 'start',
			'stop'   => 'stop',
			'reboot' => 'reboot',
		);

		if ( ! isset( $action_map[ $action ] ) ) {
			throw new \InvalidArgumentException(
				'Unsupported action. Valid actions: start, stop, reboot.'
			);
		}

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/instances/' . $instance_id . '/actions/' . $action_map[ $action ],
			$this->get_headers(),
			array()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] && 201 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to send action to instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'leaseweb-' . $action . '-' . $instance_id ) );
	}

	/**
	 * Extracts IPv4 from instance data.
	 *
	 * @param array<string, mixed> $instance Instance data.
	 * @return string|null
	 */
	private function extract_ipv4( array $instance ): ?string {
		if ( isset( $instance['ipv4'] ) && ! empty( $instance['ipv4'] ) ) {
			return $instance['ipv4'];
		}

		if ( isset( $instance['ipAddresses'] ) && is_array( $instance['ipAddresses'] ) ) {
			foreach ( $instance['ipAddresses'] as $ip ) {
				if ( isset( $ip['version'] ) && 'v4' === $ip['version'] && isset( $ip['ip'] ) && ! empty( $ip['ip'] ) ) {
					return $ip['ip'];
				}
			}
		}

		return null;
	}

	/**
	 * Extracts IPv6 from instance data.
	 *
	 * @param array<string, mixed> $instance Instance data.
	 * @return string|null
	 */
	private function extract_ipv6( array $instance ): ?string {
		if ( isset( $instance['ipv6'] ) && ! empty( $instance['ipv6'] ) ) {
			return $instance['ipv6'];
		}

		if ( isset( $instance['ipAddresses'] ) && is_array( $instance['ipAddresses'] ) ) {
			foreach ( $instance['ipAddresses'] as $ip ) {
				if ( isset( $ip['version'] ) && 'v6' === $ip['version'] && isset( $ip['ip'] ) && ! empty( $ip['ip'] ) ) {
					return $ip['ip'];
				}
			}
		}

		return null;
	}

	/**
	 * Extracts error message from Leaseweb API response.
	 *
	 * @param string $response_body Response body.
	 * @param string $fallback Fallback message.
	 * @return string
	 */
	private function extract_error_message( string $response_body, string $fallback ): string {
		$decoded = $this->decode_json( $response_body );
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['message'] ) ) {
				return (string) $decoded['message'];
			}
			if ( isset( $decoded['error'] ) && is_array( $decoded['error'] ) && isset( $decoded['error']['message'] ) ) {
				return (string) $decoded['error']['message'];
			}
		}

		return $fallback;
	}
}
