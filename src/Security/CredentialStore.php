<?php
/**
 * CredentialStore — encrypted storage of provider API keys.
 *
 * Full implementation delivered in the Security phase.
 *
 * @package CloudBridge\Security
 */

declare(strict_types=1);

namespace CloudBridge\Security;

/**
 * Stores and retrieves provider API credentials encrypted at rest.
 *
 * Uses sodium_crypto_secretbox() with key material from CB_ENCRYPTION_KEY.
 * If unavailable, it falls back to AUTH_KEY + SECURE_AUTH_KEY + site_url()
 * and raises an admin warning because that fallback should be temporary.
 */
final class CredentialStore {
	private const OPTION_PREFIX = 'cb_credential_';
	private const MIN_FALLBACK_KEY_MATERIAL_LENGTH = 64;

	/**
	 * Ensures fallback warning hook is only registered once.
	 *
	 * @var bool
	 */
	private static bool $fallback_notice_registered = false;

	/**
	 * Optional override used for deterministic tests.
	 *
	 * @var string|null
	 */
	private readonly ?string $key_override;

	/**
	 * Constructor.
	 *
	 * @param string|null $key_override Optional raw key material override.
	 */
	public function __construct( ?string $key_override = null ) {
		$this->key_override = $key_override;
	}

	/**
	 * Encrypts and stores a provider credential.
	 *
	 * @param string $provider_id Provider slug (e.g. vultr, hetzner).
	 * @param string $credential  Plaintext credential.
	 *
	 * @return bool True when the option update succeeded.
	 *
	 * @throws \InvalidArgumentException When provider ID is empty after sanitisation.
	 */
	public function store_provider_credential( string $provider_id, string $credential ): bool {
		$option_name = $this->build_option_name( $provider_id );
		$key         = $this->resolve_encryption_key();

		$nonce  = \random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = \sodium_crypto_secretbox( $credential, $nonce, $key );

		$payload = \sodium_bin2base64( $nonce . $cipher, SODIUM_BASE64_VARIANT_ORIGINAL );

		return \update_option( $option_name, $payload, false );
	}

	/**
	 * Retrieves and decrypts a provider credential.
	 *
	 * Returns null when the credential does not exist or when decryption fails
	 * (e.g. wrong key, tampered payload, malformed data).
	 *
	 * @param string $provider_id Provider slug.
	 *
	 * @return string|null
	 */
	public function get_provider_credential( string $provider_id ): ?string {
		$option_name = $this->build_option_name( $provider_id );
		$stored      = \get_option( $option_name, '' );

		if ( ! \is_string( $stored ) || '' === $stored ) {
			return null;
		}

		try {
			$decoded = \sodium_base642bin( $stored, SODIUM_BASE64_VARIANT_ORIGINAL, '' );
		} catch ( \SodiumException ) {
			return null;
		}

		if ( \strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}

		$nonce  = \substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = \substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$key       = $this->resolve_encryption_key();
		$plaintext = \sodium_crypto_secretbox_open( $cipher, $nonce, $key );

		if ( false === $plaintext ) {
			return null;
		}

		return $plaintext;
	}

	/**
	 * Deletes an existing provider credential.
	 *
	 * @param string $provider_id Provider slug.
	 *
	 * @return bool True when deleted.
	 *
	 * @throws \InvalidArgumentException When provider ID is empty after sanitisation.
	 */
	public function delete_provider_credential( string $provider_id ): bool {
		return \delete_option( $this->build_option_name( $provider_id ) );
	}

	/**
	 * Builds a deterministic option key using the cb_ prefix convention.
	 *
	 * @param string $provider_id Provider slug.
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException When provider ID is empty after sanitisation.
	 */
	private function build_option_name( string $provider_id ): string {
		$normalised = \strtolower( \preg_replace( '/[^a-z0-9_]+/i', '_', $provider_id ) ?? '' );

		if ( '' === $normalised ) {
			throw new \InvalidArgumentException( 'Provider identifier cannot be empty.' );
		}

		return self::OPTION_PREFIX . $normalised;
	}

	/**
	 * Returns a binary secretbox key of exactly 32 bytes.
	 *
	 * @return string
	 *
	 * @throws \RuntimeException When fallback key material is too weak.
	 */
	private function resolve_encryption_key(): string {
		$key_material  = $this->key_override;
		$used_fallback = false;

		if ( null === $key_material || '' === $key_material ) {
			if ( \defined( 'CB_ENCRYPTION_KEY' ) && \is_string( CB_ENCRYPTION_KEY ) && '' !== CB_ENCRYPTION_KEY ) {
				$key_material = CB_ENCRYPTION_KEY;
			} else {
				$auth_key        = \defined( 'AUTH_KEY' ) && \is_string( AUTH_KEY ) ? AUTH_KEY : '';
				$secure_auth_key = \defined( 'SECURE_AUTH_KEY' ) && \is_string( SECURE_AUTH_KEY ) ? SECURE_AUTH_KEY : '';
				$site_url        = \function_exists( 'site_url' ) ? (string) \site_url() : '';
				$key_material    = $auth_key . $secure_auth_key . $site_url;

				if ( \strlen( $key_material ) < self::MIN_FALLBACK_KEY_MATERIAL_LENGTH ) {
					throw new \RuntimeException( 'Credential encryption fallback key material is too weak. Define CB_ENCRYPTION_KEY in wp-config.php.' );
				}

				$used_fallback = true;
			}
		}

		if ( $used_fallback ) {
			$this->register_fallback_notice();
		}

		return \sodium_crypto_generichash(
			$key_material,
			'',
			SODIUM_CRYPTO_SECRETBOX_KEYBYTES
		);
	}

	/**
	 * Warns admins to set a dedicated encryption key constant.
	 */
	private function register_fallback_notice(): void {
		if ( self::$fallback_notice_registered ) {
			return;
		}

		if ( ! \function_exists( 'add_action' ) ) {
			return;
		}

		\add_action(
			'admin_notices',
			static function (): void {
				if ( \function_exists( 'current_user_can' ) && ! \current_user_can( 'manage_options' ) ) {
					return;
				}

				\printf(
					'<div class="notice notice-warning"><p>%s</p></div>',
					\esc_html__(
						'Cloud Bridge is using a derived fallback encryption key. Define CB_ENCRYPTION_KEY in wp-config.php for stable credential encryption.',
						'cloud-bridge-for-pmpro'
					)
				);
			}
		);

		self::$fallback_notice_registered = true;
	}
}
