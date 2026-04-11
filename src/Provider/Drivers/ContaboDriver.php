<?php
/**
 * ContaboDriver — Contabo (API v1).
 *
 * Implements CloudProviderInterface for Contabo VPS.
 *
 * Authentication: Authorization Bearer token header.
 * Rate limit: 100 req/min (typical).
 * API docs: https://contabo.com/api-docs/
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
 * Contabo driver (API v1).
 *
 * Zero WordPress coupling. Configuration injected via constructor.
 */
final class ContaboDriver extends AbstractProvider {

	private const API_BASE = 'https://api.contabo.com/v1';

	/**
	 * Constructor.
	 *
	 * @param string $api_token Contabo API token.
	 */
	public function __construct(
		private readonly string $api_token,
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
		return 'contabo';
	}

	/**
	 * Gets the provider label.
	 *
	 * @return string Human-readable provider label.
	 */
	public function get_label(): string {
		return 'Contabo';
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
			self::API_BASE . '/compute/instances?page=1&size=1',
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			if ( in_array( $response['code'], array( 401, 403 ), true ) ) {
				return ProviderResult::fail( 'auth_failed', 'Invalid or expired Contabo API token.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to validate Contabo credentials.' );
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
	 * Provisions a new Contabo instance.
	 *
	 * @param ProvisionRequest $request Provisioning request payload.
	 * @return ProviderResult Provisioning result.
	 */
	public function provision( ProvisionRequest $request ): ProviderResult {
		$body = array(
			'name'       => $request->hostname,
			'regionCode' => $request->region_slug,
			'productId'  => $request->plan_slug,
			'imageId'    => $request->image_id,
		);

		// Add SSH keys if provided.
		if ( ! empty( $request->ssh_key_ids ) ) {
			$body['sshKeys'] = array_map( 'intval', $request->ssh_key_ids );
		}

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/compute/instances',
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
		if ( null === $decoded || ! isset( $decoded['instanceId'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Contabo API.' );
		}

		$ipv4 = $this->extract_ipv4( $decoded );
		$ipv6 = $this->extract_ipv6( $decoded );

		return ProviderResult::ok(
			new ProvisionResult(
				provider_instance_id: (string) $decoded['instanceId'],
				provider_status: $decoded['currentStatus'] ?? 'provisioning',
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
			self::API_BASE . '/compute/instances/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		// Contabo returns 204 No Content on successful deletion.
		if ( 204 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to destroy instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'contabo-destroy-' . $provider_instance_id ) );
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
		return $this->send_instance_action( $provider_instance_id, 'restart' );
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
			'imageId' => $image_id,
		);

		$response = $this->http_request(
			'PATCH',
			self::API_BASE . '/compute/instances/' . $provider_instance_id,
			$this->get_headers(),
			$body
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to rebuild instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'contabo-rebuild-' . $provider_instance_id ) );
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
			self::API_BASE . '/compute/instances/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			// Not found is expected if instance was deleted.
			if ( 404 === $response['code'] ) {
				return ProviderResult::fail( 'not_found', 'Instance not found on Contabo.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to get instance status' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['currentStatus'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Contabo API.' );
		}

		$provider_status   = (string) $decoded['currentStatus'];
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
		$url = self::API_BASE . '/compute/products?page=1&size=100';

		$response = $this->http_request( 'GET', $url, $this->get_headers() );

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to fetch plans' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['data'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Contabo API.' );
		}

		$plans = array();
		foreach ( $decoded['data'] as $plan ) {
			// Filter by region if requested.
			if ( null !== $region_slug ) {
				if ( ! isset( $plan['regions'] ) || ! in_array( $region_slug, (array) $plan['regions'], true ) ) {
					continue;
				}
			}

			$plans[] = array(
				'slug'          => $plan['productId'],
				'name'          => $plan['name'] ?? '',
				'cores'         => $plan['cpu'] ?? 0,
				'memory_gb'     => ( $plan['ram'] ?? 0 ) / 1024,
				'disk_gb'       => ( $plan['storage'] ?? 0 ) / 1024,
				'price_monthly' => (float) ( $plan['pricePerMonthNet'] ?? 0 ),
				'price_hourly'  => (float) ( $plan['pricePerMonthNet'] ?? 0 ) / 730,
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
			self::API_BASE . '/compute/regions',
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
		if ( null === $decoded || ! isset( $decoded['data'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Contabo API.' );
		}

		$regions = array();
		foreach ( $decoded['data'] as $region ) {
			$regions[] = array(
				'slug'      => $region['regionCode'],
				'name'      => $region['name'] ?? '',
				'continent' => $region['continent'] ?? '',
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
			self::API_BASE . '/compute/images?page=1&size=200',
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
		if ( null === $decoded || ! isset( $decoded['data'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Contabo API.' );
		}

		$images = array();
		foreach ( $decoded['data'] as $image ) {
			// Filter to public images only.
			if ( isset( $image['imageType'] ) && 'public' !== $image['imageType'] ) {
				continue;
			}

			$images[] = array(
				'id'   => $image['imageId'] ?? '',
				'name' => $image['imageName'] ?? '',
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
			default        => InstanceStatus::ERROR,
		};
	}

	// -------------------------------------------------------------------------
	// Private Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns standard headers for Contabo API requests.
	 *
	 * @return array<string, string>
	 */
	private function get_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_token,
		);
	}

	/**
	 * Sends an action command to a Contabo instance.
	 *
	 * @param string $instance_id Provider instance ID.
	 * @param string $action Action name ('start', 'stop', or 'restart').
	 * @return ProviderResult<ActionResult>
	 *
	 * @throws \InvalidArgumentException If action is not one of the accepted constants.
	 */
	private function send_instance_action( string $instance_id, string $action ): ProviderResult {
		$action_map = array(
			'start'   => 'start',
			'stop'    => 'stop',
			'restart' => 'restart',
		);

		if ( ! isset( $action_map[ $action ] ) ) {
			throw new \InvalidArgumentException(
				'Unsupported action. Valid actions: start, stop, restart.'
			);
		}

		$body = array(
			'action' => $action_map[ $action ],
		);

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/compute/instances/' . $instance_id . '/actions',
			$this->get_headers(),
			$body
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to send action to instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'contabo-' . $action . '-' . $instance_id ) );
	}

	/**
	 * Extracts IPv4 from instance data.
	 *
	 * @param array<string, mixed> $instance Instance data.
	 * @return string|null
	 */
	private function extract_ipv4( array $instance ): ?string {
		if ( isset( $instance['ipv4Address'] ) && ! empty( $instance['ipv4Address'] ) ) {
			return $instance['ipv4Address'];
		}

		if ( isset( $instance['ipAddresses'] ) && is_array( $instance['ipAddresses'] ) ) {
			foreach ( $instance['ipAddresses'] as $ip ) {
				if ( isset( $ip['ipv4Address'] ) && ! empty( $ip['ipv4Address'] ) ) {
					return $ip['ipv4Address'];
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
		if ( isset( $instance['ipv6Address'] ) && ! empty( $instance['ipv6Address'] ) ) {
			return $instance['ipv6Address'];
		}

		if ( isset( $instance['ipAddresses'] ) && is_array( $instance['ipAddresses'] ) ) {
			foreach ( $instance['ipAddresses'] as $ip ) {
				if ( isset( $ip['ipv6Address'] ) && ! empty( $ip['ipv6Address'] ) ) {
					return $ip['ipv6Address'];
				}
			}
		}

		return null;
	}

	/**
	 * Extracts error message from Contabo API response.
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
			if ( isset( $decoded['error'] ) ) {
				return (string) $decoded['error'];
			}
		}

		return $fallback;
	}
}
