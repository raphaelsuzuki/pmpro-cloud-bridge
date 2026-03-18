<?php
/**
 * AbstractProvider — shared base for all provider drivers.
 *
 * Provides HTTP helper methods and contextual logging utilities that every driver
 * needs. Subclasses MUST NOT add WordPress function calls beyond what is
 * explicitly provided here; all WordPress coupling must remain in this class,
 * minimised and clearly documented.
 *
 * @package CloudBridge\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Provider;

use CloudBridge\Provider\Result\ProviderResult;

/**
 * Base class for cloud provider drivers.
 *
 * Concrete drivers extend this class and implement CloudProviderInterface.
 */
abstract class AbstractProvider implements CloudProviderInterface {

	/**
	 * Makes an HTTP request to the provider API.
	 *
	 * This is the only place wp_remote_*() calls are permitted in driver code.
	 * All request parameters, base URL, and auth headers are injected by the
	 * concrete driver.
	 *
	 * @param string               $method  HTTP method: GET, POST, DELETE, PATCH.
	 * @param string               $url     Fully qualified provider API URL.
	 * @param array<string, mixed> $headers Additional request headers (auth, idempotency, etc.).
	 * @param mixed                $body    Request body (will be JSON-encoded if non-null).
	 * @return array{code: int, body: string}|ProviderResult On HTTP transport failure returns ProviderResult::fail('http_error', ...).
	 */
	protected function http_request( string $method, string $url, array $headers = [], mixed $body = null ): array|ProviderResult {
		$args = [
			'method'  => strtoupper( $method ),
			'headers' => array_merge(
				[ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
				$headers
			),
			'timeout' => 30,
		];

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return ProviderResult::fail(
				'http_error',
				$response->get_error_message()
			);
		}

		return [
			'code' => wp_remote_retrieve_response_code( $response ),
			'body' => wp_remote_retrieve_body( $response ),
		];
	}

	/**
	 * Decodes a JSON response body.
	 *
	 * @param string $body Raw response body.
	 * @return array<mixed>|null Decoded array, or null on parse failure.
	 */
	protected function decode_json( string $body ): ?array {
		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		return $decoded;
	}
}
