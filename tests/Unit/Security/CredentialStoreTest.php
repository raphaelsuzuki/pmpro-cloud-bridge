<?php
/**
 * Unit tests for CredentialStore encryption and decryption behavior.
 *
 * @package CloudBridge\Tests\Unit\Security
 */

declare(strict_types=1);

namespace {
	/** @var array<string, string> */
	$GLOBALS['cb_test_options'] = array();

	if ( ! \function_exists( 'update_option' ) ) {
		/**
		 * Test double for update_option().
		 */
		function update_option( string $option, string $value, bool $autoload = false ): bool {
			unset( $autoload );
			$GLOBALS['cb_test_options'][ $option ] = $value;
			return true;
		}
	}

	if ( ! \function_exists( 'get_option' ) ) {
		/**
		 * Test double for get_option().
		 *
		 * @param string       $option  Option key.
		 * @param string|false $default Default return.
		 *
		 * @return string|false
		 */
		function get_option( string $option, string|false $default = false ): string|false {
			return $GLOBALS['cb_test_options'][ $option ] ?? $default;
		}
	}

	if ( ! \function_exists( 'delete_option' ) ) {
		/**
		 * Test double for delete_option().
		 */
		function delete_option( string $option ): bool {
			if ( ! isset( $GLOBALS['cb_test_options'][ $option ] ) ) {
				return false;
			}

			unset( $GLOBALS['cb_test_options'][ $option ] );
			return true;
		}
	}

	if ( ! \function_exists( 'site_url' ) ) {
		/**
		 * Test double for site_url().
		 */
		function site_url(): string {
			return 'https://example.test';
		}
	}

	if ( ! \function_exists( 'add_action' ) ) {
		/**
		 * Test double for add_action().
		 */
		function add_action( string $hook, callable $callback ): void {
			unset( $hook, $callback );
		}
	}

	if ( ! \function_exists( 'current_user_can' ) ) {
		/**
		 * Test double for current_user_can().
		 */
		function current_user_can( string $capability ): bool {
			unset( $capability );
			return true;
		}
	}

	if ( ! \function_exists( 'esc_html__' ) ) {
		/**
		 * Test double for esc_html__().
		 */
		function esc_html__( string $text, string $domain ): string {
			unset( $domain );
			return $text;
		}
	}
}

namespace CloudBridge\Tests\Unit\Security {

use CloudBridge\Security\CredentialStore;
use PHPUnit\Framework\TestCase;

/**
 * Verifies encryption round-trip and failure modes for CredentialStore.
 */
class CredentialStoreTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['cb_test_options'] = array();
	}

	public function test_round_trip_encrypts_and_decrypts_credential(): void {
		$store = new CredentialStore( 'test-key-material' );

		$this->assertTrue( $store->store_provider_credential( 'vultr', 'super-secret-api-key' ) );
		$this->assertSame( 'super-secret-api-key', $store->get_provider_credential( 'vultr' ) );
	}

	public function test_stored_value_is_not_plaintext(): void {
		$store = new CredentialStore( 'test-key-material' );
		$store->store_provider_credential( 'vultr', 'super-secret-api-key' );

		$stored = $GLOBALS['cb_test_options']['cb_credential_vultr'] ?? '';
		$this->assertNotSame( 'super-secret-api-key', $stored );
		$this->assertStringNotContainsString( 'super-secret-api-key', $stored );
	}

	public function test_decryption_with_wrong_key_fails_gracefully(): void {
		$writer = new CredentialStore( 'key-material-a' );
		$reader = new CredentialStore( 'key-material-b' );

		$writer->store_provider_credential( 'hetzner', 'another-secret' );

		$this->assertNull( $reader->get_provider_credential( 'hetzner' ) );
	}

	public function test_malformed_payload_returns_null_instead_of_throwing(): void {
		$GLOBALS['cb_test_options']['cb_credential_digitalocean'] = 'not-a-valid-ciphertext';
		$store                                                     = new CredentialStore( 'key-material-a' );

		$this->assertNull( $store->get_provider_credential( 'digitalocean' ) );
	}

	public function test_delete_provider_credential_removes_stored_value(): void {
		$store = new CredentialStore( 'test-key-material' );
		$store->store_provider_credential( 'linode', 'linode-secret' );

		$this->assertTrue( $store->delete_provider_credential( 'linode' ) );
		$this->assertNull( $store->get_provider_credential( 'linode' ) );
	}

	public function test_store_throws_when_fallback_key_material_is_weak(): void {
		$store = new CredentialStore();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'fallback key material is too weak' );

		$store->store_provider_credential( 'vultr', 'secret' );
	}
}
}
