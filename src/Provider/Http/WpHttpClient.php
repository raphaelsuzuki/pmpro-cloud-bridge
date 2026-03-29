<?php
/**
 * WpHttpClient — WordPress-backed HTTP adapter.
 *
 * @package CloudBridge\Provider\Http
 */

declare(strict_types=1);

namespace CloudBridge\Provider\Http;

/**
 * HTTP client implementation that delegates to wp_remote_request().
 */
final class WpHttpClient implements HttpClientInterface {
	/**
	 * Sends an HTTP request through WordPress HTTP API.
	 *
	 * @param string                $method  HTTP method.
	 * @param string                $url     Fully qualified URL.
	 * @param array<string, string> $headers Request headers.
	 * @param string|null           $body    Raw body payload.
	 * @param int                   $timeout Timeout in seconds.
	 *
	 * @throws \RuntimeException When transport is unavailable or fails.
	 *
	 * @inheritDoc
	 */
	public function request( string $method, string $url, array $headers = array(), ?string $body = null, int $timeout = 30 ): HttpResponse {
		if ( ! \function_exists( 'wp_remote_request' ) ) {
			throw new \RuntimeException( 'WordPress HTTP API is not available.' );
		}

		$args = array(
			'method'  => \strtoupper( $method ),
			'headers' => $headers,
			'timeout' => $timeout,
		);

		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = \wp_remote_request( $url, $args );
		if ( \is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \RuntimeException( $response->get_error_message() );
		}

		$status_code = (int) \wp_remote_retrieve_response_code( $response );
		$raw_headers = \wp_remote_retrieve_headers( $response );
		$body_text   = (string) \wp_remote_retrieve_body( $response );

		$headers_out = array();
		if ( \is_array( $raw_headers ) ) {
			foreach ( $raw_headers as $key => $value ) {
				if ( ! \is_string( $value ) ) {
					continue;
				}
				$headers_out[ (string) $key ] = $value;
			}
		}

		return new HttpResponse( $status_code, $body_text, $headers_out );
	}
}
