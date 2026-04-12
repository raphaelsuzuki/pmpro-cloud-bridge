<?php
/**
 * HetznerDriver — Hetzner Cloud (API v1).
 *
 * Implements CloudProviderInterface for Hetzner Cloud.
 *
 * Authentication: Authorization Bearer token header.
 * Idempotency: X-Idempotency-Key header.
 * Rate limit: 3600 req/hour per token (60 req/min, burst 10).
 * API docs: https://docs.hetzner.cloud/
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
 * Hetzner Cloud driver (API v1).
 *
 * Zero WordPress coupling. Configuration injected via constructor.
 */
final class HetznerDriver extends AbstractProvider {

	private const API_BASE = 'https://api.hetzner.cloud/v1';

	/**
	 * Constructor.
	 *
	 * @param string $api_token Hetzner API token.
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
		return 'hetzner';
	}

	/**
	 * Gets the provider label.
	 *
	 * @return string Human-readable provider label.
	 */
	public function get_label(): string {
		return 'Hetzner Cloud';
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
			self::API_BASE . '/account',
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			if ( in_array( $response['code'], array( 401, 403 ), true ) ) {
				return ProviderResult::fail( 'auth_failed', 'Invalid or expired Hetzner API token.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to validate Hetzner credentials.' );
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
	 * Provisions a new Hetzner instance.
	 *
	 * @param ProvisionRequest $request Provisioning request payload.
	 * @return ProviderResult Provisioning result.
	 */
	public function provision( ProvisionRequest $request ): ProviderResult {
		$body = array(
			'name'        => $request->hostname,
			'server_type' => $request->plan_slug,
			'location'    => $request->region_slug,
			'image'       => $request->image_id,
			'public_net'  => array(
				'enable_ipv4' => true,
				'enable_ipv6' => true,
			),
		);

		// Add SSH keys if provided.
		if ( ! empty( $request->ssh_key_ids ) ) {
			$body['ssh_keys'] = array_map( 'intval', $request->ssh_key_ids );
		}

		$headers = array_merge(
			$this->get_headers(),
			array(
				'X-Idempotency-Key' => 'cb_' . $request->idempotency_key,
			)
		);

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/servers',
			$headers,
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
		if ( null === $decoded || ! isset( $decoded['server'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Hetzner API.' );
		}

		$instance = $decoded['server'];

		$ipv4 = $this->extract_ipv4( $instance );
		$ipv6 = $this->extract_ipv6( $instance );

		return ProviderResult::ok(
			new ProvisionResult(
				provider_instance_id: (string) $instance['id'],
				provider_status: $instance['status'] ?? 'initializing',
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
			self::API_BASE . '/servers/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		// Hetzner returns 200 with an action object on successful deletion.
		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to destroy instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'hetzner-destroy-' . $provider_instance_id ) );
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
		return $this->send_instance_command( $provider_instance_id, 'power_on' );
	}

	/**
	 * Powers off an instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Power-off result.
	 */
	public function power_off( string $provider_instance_id ): ProviderResult {
		return $this->send_instance_command( $provider_instance_id, 'power_off' );
	}

	/**
	 * Reboots an instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Reboot result.
	 */
	public function reboot( string $provider_instance_id ): ProviderResult {
		return $this->send_instance_command( $provider_instance_id, 'reboot' );
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
			self::API_BASE . '/servers/' . $provider_instance_id . '/actions/rebuild',
			$this->get_headers(),
			$body
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 201 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to rebuild instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'hetzner-rebuild-' . $provider_instance_id ) );
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
			self::API_BASE . '/servers/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			// Not found is expected if instance was deleted.
			if ( 404 === $response['code'] ) {
				return ProviderResult::fail( 'not_found', 'Instance not found on Hetzner.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to get instance status' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['server'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Hetzner API.' );
		}

		$provider_status   = (string) ( $decoded['server']['status'] ?? 'unknown' );
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
		$response = $this->http_request( 'GET', self::API_BASE . '/server_types', $this->get_headers() );

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to fetch plans' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['server_types'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Hetzner API.' );
		}

		$plans = array();
		foreach ( $decoded['server_types'] as $plan ) {
			// Filter by region if requested.
			if ( null !== $region_slug ) {
				if ( ! isset( $plan['prices'] ) || ! is_array( $plan['prices'] ) ) {
					continue;
				}
				$found = false;
				foreach ( $plan['prices'] as $price ) {
					if ( isset( $price['location'] ) && $price['location'] === $region_slug ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					continue;
				}
			}

			$monthly_price = 0;
			if ( isset( $plan['prices'] ) && is_array( $plan['prices'] ) && count( $plan['prices'] ) > 0 ) {
				$first_price = $plan['prices'][0];
				if ( isset( $first_price['price_monthly'] ) && is_array( $first_price['price_monthly'] ) ) {
					$monthly_price = (float) ( $first_price['price_monthly']['gross'] ?? $first_price['price_monthly']['net'] ?? 0 );
				}
			}

			$plans[] = array(
				'slug'          => $plan['name'],
				'name'          => $plan['description'] ?? '',
				'cores'         => $plan['cores'] ?? 0,
				'memory_gb'     => (float) ( $plan['memory'] ?? 0 ),
				'disk_gb'       => (int) ( $plan['disk'] ?? 0 ),
				'price_monthly' => $monthly_price,
				'price_hourly'  => $monthly_price > 0 ? ( $monthly_price / 730 ) : 0,
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
			self::API_BASE . '/locations',
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
		if ( null === $decoded || ! isset( $decoded['locations'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Hetzner API.' );
		}

		$regions = array();
		foreach ( $decoded['locations'] as $region ) {
			$regions[] = array(
				'slug'      => $region['id'],
				'name'      => $region['description'] ?? '',
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
			self::API_BASE . '/images?type=system',
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
		if ( null === $decoded || ! isset( $decoded['images'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from Hetzner API.' );
		}

		$images = array();
		foreach ( $decoded['images'] as $image ) {
			$images[] = array(
				'id'   => (string) $image['id'],
				'name' => $image['name'] ?? '',
				'arch' => $image['architecture'] ?? 'x64',
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
			'max_requests_per_minute' => 60,
			'burst'                   => 10,
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
			'initializing' => InstanceStatus::PROVISIONING,
			'starting'     => InstanceStatus::STARTING,
			'running'      => InstanceStatus::ACTIVE,
			'stopping'     => InstanceStatus::STOPPING,
			'stopped'      => InstanceStatus::STOPPED,
			'migrating'    => InstanceStatus::REBUILDING,
			'resetting'    => InstanceStatus::REBUILDING,
			'rebooting'    => InstanceStatus::REBOOTING,
			default        => InstanceStatus::ERROR,
		};
	}

	// -------------------------------------------------------------------------
	// Private Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns standard headers for Hetzner API requests.
	 *
	 * @return array<string, string>
	 */
	private function get_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_token,
		);
	}

	/**
	 * Sends a power/reboot command to an instance.
	 *
	 * @param string $instance_id Provider instance ID.
	 * @param string $command Command name ('power_on', 'power_off', or 'reboot').
	 * @return ProviderResult<ActionResult>
	 *
	 * @throws \InvalidArgumentException If command is not one of the accepted constants.
	 */
	private function send_instance_command( string $instance_id, string $command ): ProviderResult {
		$endpoint = match ( $command ) {
			'power_on'  => '/servers/' . $instance_id . '/actions/power_on',
			'power_off' => '/servers/' . $instance_id . '/actions/power_off',
			'reboot'    => '/servers/' . $instance_id . '/actions/reboot',
			default     => throw new \InvalidArgumentException(
				'Unsupported instance command. Valid commands: power_on, power_off, reboot.'
			),
		};

		$response = $this->http_request(
			'POST',
			self::API_BASE . $endpoint,
			$this->get_headers(),
			array()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 201 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to send command to instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'hetzner-' . $command . '-' . $instance_id ) );
	}

	/**
	 * Extracts IPv4 from instance data.
	 *
	 * @param array<string, mixed> $instance Instance data.
	 * @return string|null
	 */
	private function extract_ipv4( array $instance ): ?string {
		if ( ! isset( $instance['public_net'] ) || ! is_array( $instance['public_net'] ) ) {
			return null;
		}

		$public_net = $instance['public_net'];
		if ( isset( $public_net['ipv4'] ) && is_array( $public_net['ipv4'] ) ) {
			$ipv4_obj = $public_net['ipv4'];
			if ( isset( $ipv4_obj['ip'] ) && ! empty( $ipv4_obj['ip'] ) ) {
				return $ipv4_obj['ip'];
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
		if ( ! isset( $instance['public_net'] ) || ! is_array( $instance['public_net'] ) ) {
			return null;
		}

		$public_net = $instance['public_net'];
		if ( isset( $public_net['ipv6'] ) && is_array( $public_net['ipv6'] ) ) {
			$ipv6_obj = $public_net['ipv6'];
			if ( isset( $ipv6_obj['ip'] ) && ! empty( $ipv6_obj['ip'] ) ) {
				return $ipv6_obj['ip'];
			}
		}

		return null;
	}

	/**
	 * Extracts error message from Hetzner API response.
	 *
	 * @param string $response_body Response body.
	 * @param string $fallback Fallback message.
	 * @return string
	 */
	private function extract_error_message( string $response_body, string $fallback ): string {
		$decoded = $this->decode_json( $response_body );
		if ( is_array( $decoded ) ) {
			if ( isset( $decoded['error'] ) && is_array( $decoded['error'] ) && isset( $decoded['error']['message'] ) ) {
				return (string) $decoded['error']['message'];
			}
			if ( isset( $decoded['message'] ) ) {
				return (string) $decoded['message'];
			}
		}

		return $fallback;
	}
}
