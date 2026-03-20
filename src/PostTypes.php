<?php
/**
 * Custom Post Type Registration for Cloud Bridge
 *
 * @package CloudBridge
 * @since 1.0.0
 */

declare(strict_types=1);

namespace CloudBridge;

/**
 * Handles registration of custom post types and their metadata.
 *
 * Registers:
 * - cb_plan: Stores cloud provider plan configurations
 * - cb_instance: Stores provisioned cloud server instances
 *
 * @since 1.0.0
 */
final class PostTypes {

	/**
	 * Initialize CPT registration hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function init(): void {
		\add_action( 'init', [ self::class, 'register_cpts' ], 10 );
		\add_action( 'init', [ self::class, 'register_post_meta' ], 15 );
	}

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_cpts(): void {
		self::register_plan_cpt();
		self::register_instance_cpt();
	}

	/**
	 * Register cb_plan CPT for cloud provider plan configurations.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_plan_cpt(): void {
		$args = [
			'label'              => \__( 'Cloud Plans', 'cloud-bridge-for-pmpro' ),
			'description'        => \__( 'Cloud provider plan configurations', 'cloud-bridge-for-pmpro' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_in_rest'       => false,
			'exclude_from_search' => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => [ 'title', 'custom-fields' ],
		];

		\register_post_type( 'cb_plan', $args );
	}

	/**
	 * Register cb_instance CPT for provisioned server instances.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_instance_cpt(): void {
		$args = [
			'label'              => \__( 'Cloud Instances', 'cloud-bridge-for-pmpro' ),
			'description'        => \__( 'Provisioned cloud server instances', 'cloud-bridge-for-pmpro' ),
			'public'             => false,
			'publicly_queryable' => false,
			'show_in_rest'       => false,
			'exclude_from_search' => true,
			'show_ui'            => false,
			'show_in_menu'       => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => [ 'title', 'custom-fields' ],
		];

		\register_post_type( 'cb_instance', $args );
	}

	/**
	 * Register post meta for both CPTs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function register_post_meta(): void {
		self::register_plan_meta();
		self::register_instance_meta();
	}

	/**
	 * Register metadata fields for cb_plan CPT.
	 *
	 * Meta keys for billing plan configuration:
	 * - provider: Cloud provider identifier (e.g., 'linode', 'digitalocean')
	 * - plan_slug: Provider's plan identifier
	 * - billing_mode: 'hourly' or 'monthly'
	 * - max_instances: Maximum instances per subscription
	 * - base_price: Monthly price in USD
	 * - allowed_regions: JSON array of region identifiers
	 * - allowed_images: JSON array of OS image identifiers
	 * - grace_period_days: Grace period before suspension (days)
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_plan_meta(): void {
		$meta_keys = [
			'provider'         => [
				'type'              => 'string',
				'description'       => 'Cloud provider identifier',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'plan_slug'        => [
				'type'              => 'string',
				'description'       => "Provider's plan identifier",
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'billing_mode'     => [
				'type'              => 'string',
				'description'       => "Billing mode: 'hourly' or 'monthly'",
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_billing_mode' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'max_instances'    => [
				'type'              => 'integer',
				'description'       => 'Maximum instances per subscription',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'intval',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'base_price'       => [
				'type'              => 'number',
				'description'       => 'Monthly price in USD',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_price' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'allowed_regions'  => [
				'type'              => 'string',
				'description'       => 'JSON array of region identifiers',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_json_array' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'allowed_images'   => [
				'type'              => 'string',
				'description'       => 'JSON array of OS image identifiers',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_json_array' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'grace_period_days' => [
				'type'              => 'integer',
				'description'       => 'Grace period before suspension (days)',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'intval',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
		];

		foreach ( $meta_keys as $key => $args ) {
			\register_post_meta( 'cb_plan', $key, $args );
		}
	}

	/**
	 * Register metadata fields for cb_instance CPT.
	 *
	 * Meta keys for provisioned instances:
	 * - cbuser_id: Associated WordPress user ID
	 * - cbplan_id: Associated plan post ID
	 * - cbpmpro_order_id: Associated PMPro order ID
	 * - cbpmpro_level_id: Associated PMPro membership level ID
	 * - cbprovider: Cloud provider identifier
	 * - cbprovider_instance_id: Provider's instance identifier
	 * - cbinstance_status: Current instance status (provisioning, active, etc.)
	 * - cbbilling_status: Billing status (active, suspended, terminated)
	 * - cbidempotency_key: Unique key for idempotent operations
	 * - cbssh_keys: JSON array of SSH public keys
	 * - cbipv4: Instance IPv4 address
	 * - cbipv6: Instance IPv6 address
	 * - cbregion: Deployment region identifier
	 * - cbimage_id: OS image identifier
	 * - cbhostname: Instance hostname
	 * - cbcreated_utc: Creation timestamp (ISO 8601)
	 * - cbtermination_due_utc: Scheduled termination timestamp or null
	 * - cblast_synced_utc: Last provider sync timestamp
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private static function register_instance_meta(): void {
		$meta_keys = [
			'cbuser_id'              => [
				'type'              => 'integer',
				'description'       => 'Associated WordPress user ID',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'intval',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbplan_id'              => [
				'type'              => 'integer',
				'description'       => 'Associated plan post ID',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'intval',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbpmpro_order_id'       => [
				'type'              => 'integer',
				'description'       => 'Associated PMPro order ID',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'intval',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbpmpro_level_id'       => [
				'type'              => 'integer',
				'description'       => 'Associated PMPro membership level ID',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'intval',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbprovider'             => [
				'type'              => 'string',
				'description'       => 'Cloud provider identifier',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbprovider_instance_id' => [
				'type'              => 'string',
				'description'       => "Provider's instance identifier",
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbinstance_status'      => [
				'type'              => 'string',
				'description'       => 'Current instance status',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbbilling_status'       => [
				'type'              => 'string',
				'description'       => 'Billing status (active, suspended, terminated)',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbidempotency_key'      => [
				'type'              => 'string',
				'description'       => 'Unique key for idempotent operations',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbssh_keys'             => [
				'type'              => 'string',
				'description'       => 'JSON array of SSH public keys',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_json_array' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbipv4'                 => [
				'type'              => 'string',
				'description'       => 'Instance IPv4 address',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_ip_address' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbipv6'                 => [
				'type'              => 'string',
				'description'       => 'Instance IPv6 address',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_ip_address' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbregion'               => [
				'type'              => 'string',
				'description'       => 'Deployment region identifier',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbimage_id'             => [
				'type'              => 'string',
				'description'       => 'OS image identifier',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbhostname'             => [
				'type'              => 'string',
				'description'       => 'Instance hostname',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbcreated_utc'          => [
				'type'              => 'string',
				'description'       => 'Creation timestamp (ISO 8601)',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cbtermination_due_utc'  => [
				'type'              => 'string',
				'description'       => 'Scheduled termination timestamp or null',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => [ self::class, 'sanitize_nullable_iso8601' ],
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
			'cblast_synced_utc'      => [
				'type'              => 'string',
				'description'       => 'Last provider sync timestamp',
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => [ self::class, 'auth_callback' ],
			],
		];

		foreach ( $meta_keys as $key => $args ) {
			\register_post_meta( 'cb_instance', $key, $args );
		}
	}

	/**
	 * Authorization callback for post meta access.
	 *
	 * Restricts post meta access to users with manage_cloud_bridge capability.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $allowed Whether access is allowed.
	 * @param string $meta_key Meta key being accessed.
	 * @param int    $post_id Post ID.
	 * @param int    $user_id User ID.
	 * @param string $cap Current user's capability.
	 * @param array  $caps User's capabilities.
	 *
	 * @return bool True if user has manage_cloud_bridge capability.
	 */
	public static function auth_callback(
		bool $allowed,
		string $meta_key,
		int $post_id,
		int $user_id,
		string $cap,
		array $caps
	): bool {
		return \current_user_can( 'manage_cloud_bridge' );
	}

	/**
	 * Sanitize billing mode to 'hourly' or 'monthly'.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Billing mode value.
	 *
	 * @return string Sanitized billing mode.
	 */
	public static function sanitize_billing_mode( string $value ): string {
		return \in_array( $value, [ 'hourly', 'monthly' ], true ) ? $value : 'monthly';
	}

	/**
	 * Sanitize price to float.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Price value.
	 *
	 * @return float Sanitized price.
	 */
	public static function sanitize_price( $value ): float {
		return (float) $value;
	}

	/**
	 * Sanitize JSON array strings.
	 *
	 * Validates and cleans JSON array input.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value JSON string.
	 *
	 * @return string Sanitized JSON or empty JSON array.
	 */
	public static function sanitize_json_array( string $value ): string {
		if ( empty( $value ) ) {
			return '[]';
		}

		$decoded = \json_decode( $value, true );
		if ( ! \is_array( $decoded ) ) {
			return '[]';
		}

		// Re-encode to ensure valid JSON format.
		return \json_encode( $decoded, JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Sanitize IP address (IPv4 or IPv6).
	 *
	 * @since 1.0.0
	 *
	 * @param string $value IP address.
	 *
	 * @return string Sanitized IP address or empty string.
	 */
	public static function sanitize_ip_address( string $value ): string {
		$sanitized = \sanitize_text_field( $value );

		// Basic validation for IPv4 or IPv6.
		if ( \filter_var( $sanitized, FILTER_VALIDATE_IP ) ) {
			return $sanitized;
		}

		return '';
	}

	/**
	 * Sanitize nullable ISO 8601 timestamp.
	 *
	 * Accepts ISO 8601 format or empty string (null representation).
	 *
	 * @since 1.0.0
	 *
	 * @param string $value Timestamp value.
	 *
	 * @return string Sanitized timestamp or empty string.
	 */
	public static function sanitize_nullable_iso8601( string $value ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// Very basic ISO 8601 validation.
		if ( \preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value ) ) {
			return \sanitize_text_field( $value );
		}

		return '';
	}
}
