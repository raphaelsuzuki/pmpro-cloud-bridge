<?php
/**
 * DigitalOceanDriver — DigitalOcean (API v2).
 *
 * Implements CloudProviderInterface for DigitalOcean.
 *
 * Authentication: Authorization Bearer token header.
 * Rate limit: 250 req/min (burst 30).
 * API docs: https://docs.digitalocean.com/reference/api/
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
 * DigitalOcean driver (API v2).
 *
 * Zero WordPress coupling. Configuration injected via constructor.
 */
final class DigitalOceanDriver extends AbstractProvider {

	private const API_BASE = 'https://api.digitalocean.com/v2';

	/**
	 * Constructor.
	 *
	 * @param string $api_token DigitalOcean API token.
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
		return 'digitalocean';
	}

	/**
	 * Gets the provider label.
	 *
	 * @return string Human-readable provider label.
	 */
	public function get_label(): string {
		return 'DigitalOcean';
	}

	/**
	 * Gets the provider API version.
	 *
	 * @return string Provider API version.
	 */
	public function get_api_version(): string {
		return 'v2';
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
				return ProviderResult::fail( 'auth_failed', 'Invalid or expired DigitalOcean API token.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to validate DigitalOcean credentials.' );
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
	 * Provisions a new DigitalOcean instance.
	 *
	 * @param ProvisionRequest $request Provisioning request payload.
	 * @return ProviderResult Provisioning result.
	 */
	public function provision( ProvisionRequest $request ): ProviderResult {
		$body = array(
			'name'        => $request->hostname,
			'region'      => $request->region_slug,
			'size'        => $request->plan_slug,
			'image'       => $request->image_id,
			'enable_ipv6' => true,
		);

		// Add SSH keys if provided.
		if ( ! empty( $request->ssh_key_ids ) ) {
			$body['ssh_keys'] = array_map( 'intval', $request->ssh_key_ids );
		}

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/droplets',
			$this->get_headers(),
			$body
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 202 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to provision instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['droplet'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from DigitalOcean API.' );
		}

		$instance = $decoded['droplet'];

		$ipv4 = $this->extract_ipv4( $instance );
		$ipv6 = $this->extract_ipv6( $instance );

		return ProviderResult::ok(
			new ProvisionResult(
				provider_instance_id: (string) $instance['id'],
				provider_status: $instance['status'] ?? 'new',
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
			self::API_BASE . '/droplets/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		// DigitalOcean returns 204 No Content on successful deletion.
		if ( 204 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to destroy instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'do-destroy-' . $provider_instance_id ) );
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
		return $this->send_instance_action( $provider_instance_id, 'power_on' );
	}

	/**
	 * Powers off an instance.
	 *
	 * @param string $provider_instance_id Provider instance identifier.
	 * @return ProviderResult Power-off result.
	 */
	public function power_off( string $provider_instance_id ): ProviderResult {
		return $this->send_instance_action( $provider_instance_id, 'power_off' );
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
			'type'  => 'rebuild',
			'image' => $image_id,
		);

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/droplets/' . $provider_instance_id . '/actions',
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

		return ProviderResult::ok( new ActionResult( provider_action_id: 'do-rebuild-' . $provider_instance_id ) );
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
			self::API_BASE . '/droplets/' . $provider_instance_id,
			$this->get_headers()
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			// Not found is expected if instance was deleted.
			if ( 404 === $response['code'] ) {
				return ProviderResult::fail( 'not_found', 'Instance not found on DigitalOcean.' );
			}

			$error_msg = $this->extract_error_message( $response['body'], 'Failed to get instance status' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['droplet'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from DigitalOcean API.' );
		}

		$provider_status   = (string) ( $decoded['droplet']['status'] ?? 'unknown' );
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
		$url = self::API_BASE . '/sizes?per_page=250';

		$response = $this->http_request( 'GET', $url, $this->get_headers() );

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 200 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to fetch plans' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		$decoded = $this->decode_json( $response['body'] );
		if ( null === $decoded || ! isset( $decoded['sizes'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from DigitalOcean API.' );
		}

		$plans = array();
		foreach ( $decoded['sizes'] as $plan ) {
			// Filter: must be available.
			if ( ! isset( $plan['available'] ) || ! $plan['available'] ) {
				continue;
			}

			// Filter by region if requested.
			if ( null !== $region_slug ) {
				if ( ! isset( $plan['regions'] ) || ! in_array( $region_slug, $plan['regions'], true ) ) {
					continue;
				}
			}

			$plans[] = array(
				'slug'          => $plan['slug'],
				'name'          => $plan['description'] ?? '',
				'cores'         => $plan['vcpus'] ?? 0,
				'memory_gb'     => ( $plan['memory'] ?? 0 ) / 1024,
				'disk_gb'       => $plan['disk'] ?? 0,
				'price_monthly' => (float) ( $plan['price_monthly'] ?? 0 ),
				'price_hourly'  => (float) ( $plan['price_hourly'] ?? 0 ),
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
			self::API_BASE . '/regions?per_page=250',
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
		if ( null === $decoded || ! isset( $decoded['regions'] ) ) {
			return ProviderResult::fail( 'api_error', 'Invalid response structure from DigitalOcean API.' );
		}

		$regions = array();
		foreach ( $decoded['regions'] as $region ) {
			// Filter: must be available.
			if ( ! isset( $region['available'] ) || ! $region['available'] ) {
				continue;
			}

			$regions[] = array(
				'slug'      => $region['slug'],
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
			self::API_BASE . '/images?type=distribution&per_page=250',
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
			return ProviderResult::fail( 'api_error', 'Invalid response structure from DigitalOcean API.' );
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
			'max_requests_per_minute' => 250,
			'burst'                   => 30,
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
			'new'      => InstanceStatus::PROVISIONING,
			'active'   => InstanceStatus::ACTIVE,
			'off'      => InstanceStatus::STOPPED,
			'archived' => InstanceStatus::STOPPED,
			'power_off' => InstanceStatus::STOPPED,
			default    => InstanceStatus::ERROR,
		};
	}

	// -------------------------------------------------------------------------
	// Private Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns standard headers for DigitalOcean API requests.
	 *
	 * @return array<string, string>
	 */
	private function get_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_token,
		);
	}

	/**
	 * Sends an action command to a droplet.
	 *
	 * @param string $instance_id Provider instance ID.
	 * @param string $action Action name ('power_on', 'power_off', or 'reboot').
	 * @return ProviderResult<ActionResult>
	 *
	 * @throws \InvalidArgumentException If action is not one of the accepted constants.
	 */
	private function send_instance_action( string $instance_id, string $action ): ProviderResult {
		$action_map = array(
			'power_on'  => 'power_on',
			'power_off' => 'power_off',
			'reboot'    => 'reboot',
		);

		if ( ! isset( $action_map[ $action ] ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Unknown action: "%s". Valid actions: power_on, power_off, reboot.', esc_attr( $action ) )
			);
		}

		$body = array(
			'type' => $action_map[ $action ],
		);

		$response = $this->http_request(
			'POST',
			self::API_BASE . '/droplets/' . $instance_id . '/actions',
			$this->get_headers(),
			$body
		);

		if ( $response instanceof ProviderResult ) {
			return $response;
		}

		if ( 201 !== $response['code'] ) {
			$error_msg = $this->extract_error_message( $response['body'], 'Failed to send action to instance' );
			return ProviderResult::fail( 'api_error', $error_msg );
		}

		return ProviderResult::ok( new ActionResult( provider_action_id: 'do-' . $action . '-' . $instance_id ) );
	}

	/**
	 * Extracts IPv4 from droplet networks.
	 *
	 * @param array<string, mixed> $instance Instance data.
	 * @return string|null
	 */
	private function extract_ipv4( array $instance ): ?string {
		if ( ! isset( $instance['networks'] ) || ! is_array( $instance['networks'] ) ) {
			return null;
		}

		$networks = $instance['networks'];
		if ( isset( $networks['v4'] ) && is_array( $networks['v4'] ) && count( $networks['v4'] ) > 0 ) {
			$ipv4_obj = $networks['v4'][0];
			if ( isset( $ipv4_obj['ip_address'] ) && ! empty( $ipv4_obj['ip_address'] ) ) {
				return $ipv4_obj['ip_address'];
			}
		}

		return null;
	}

	/**
	 * Extracts IPv6 from droplet networks.
	 *
	 * @param array<string, mixed> $instance Instance data.
	 * @return string|null
	 */
	private function extract_ipv6( array $instance ): ?string {
		if ( ! isset( $instance['networks'] ) || ! is_array( $instance['networks'] ) ) {
			return null;
		}

		$networks = $instance['networks'];
		if ( isset( $networks['v6'] ) && is_array( $networks['v6'] ) && count( $networks['v6'] ) > 0 ) {
			$ipv6_obj = $networks['v6'][0];
			if ( isset( $ipv6_obj['ip_address'] ) && ! empty( $ipv6_obj['ip_address'] ) ) {
				// Return without prefix length.
				return explode( '/', $ipv6_obj['ip_address'] )[0];
			}
		}

		return null;
	}

	/**
	 * Extracts error message from DigitalOcean API response.
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
			if ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) && count( $decoded['errors'] ) > 0 ) {
				$first_error = $decoded['errors'][0];
				if ( is_array( $first_error ) && isset( $first_error['message'] ) ) {
					return (string) $first_error['message'];
				}
			}
		}

		return $fallback;
	}
}
