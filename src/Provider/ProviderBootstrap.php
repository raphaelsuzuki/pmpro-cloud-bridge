<?php
/**
 * ProviderBootstrap — registers all cloud provider drivers.
 *
 * Hooked to the cloud_bridge_providers filter to inject all provider implementations.
 * Drivers are registered once per request, on first access to ProviderRegistry.
 *
 * @package CloudBridge\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Provider;

use CloudBridge\Provider\Drivers\VultrDriver;
use CloudBridge\Provider\Drivers\HetznerDriver;
use CloudBridge\Provider\Drivers\DigitalOceanDriver;
use CloudBridge\Provider\Drivers\LinodeDriver;
use CloudBridge\Provider\Drivers\ContaboDriver;
use CloudBridge\Provider\Drivers\LeasewebDriver;
use CloudBridge\Security\CredentialStore;

/**
 * Bootstrap provider drivers.
 *
 * Instantiates all registered cloud provider drivers and returns them
 * for registration in ProviderRegistry.
 */
final class ProviderBootstrap {

	/**
	 * Registers all provider drivers via the cloud_bridge_providers filter.
	 *
	 * Called once on init to inject all driver instances.
	 *
	 * @return void
	 */
	public static function init(): void {
		\add_filter(
			'cloud_bridge_providers',
			array( self::class, 'register_providers' ),
			10,
			1
		);
	}

	/**
	 * Callback for the cloud_bridge_providers filter.
	 *
	 * Instantiates all providers with credentials from CredentialStore
	 * and returns them for registry validation and storage.
	 *
	 * @param array<string, CloudProviderInterface> $providers Existing providers (if any).
	 * @return array<string, CloudProviderInterface> All registered providers.
	 */
	public static function register_providers( array $providers ): array {
		$credential_store = new CredentialStore();

		// Vultr (API v2).
		$vultr_key = $credential_store->get_provider_credential( 'vultr' );
		if ( $vultr_key ) {
			$providers['vultr'] = new VultrDriver( api_key: $vultr_key );
		}

		// Hetzner Cloud (API v1).
		$hetzner_token = $credential_store->get_provider_credential( 'hetzner' );
		if ( $hetzner_token ) {
			$providers['hetzner'] = new HetznerDriver( api_token: $hetzner_token );
		}

		// DigitalOcean (API v2).
		$do_token = $credential_store->get_provider_credential( 'digitalocean' );
		if ( $do_token ) {
			$providers['digitalocean'] = new DigitalOceanDriver( api_token: $do_token );
		}

		// Linode (API v4).
		$linode_token = $credential_store->get_provider_credential( 'linode' );
		if ( $linode_token ) {
			$providers['linode'] = new LinodeDriver( api_token: $linode_token );
		}

		// Contabo (API v1).
		$contabo_token = $credential_store->get_provider_credential( 'contabo' );
		if ( $contabo_token ) {
			$providers['contabo'] = new ContaboDriver( api_token: $contabo_token );
		}

		// Leaseweb (API v2).
		$leaseweb_key = $credential_store->get_provider_credential( 'leaseweb' );
		if ( $leaseweb_key ) {
			$providers['leaseweb'] = new LeasewebDriver( api_key: $leaseweb_key );
		}

		return $providers;
	}
}
