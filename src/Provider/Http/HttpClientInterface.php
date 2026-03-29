<?php
/**
 * HttpClientInterface — abstraction for provider HTTP calls.
 *
 * @package CloudBridge\Provider\Http
 */

declare(strict_types=1);

namespace CloudBridge\Provider\Http;

/**
 * Minimal HTTP client abstraction for provider drivers.
 */
interface HttpClientInterface {
	/**
	 * Sends an HTTP request.
	 *
	 * @param string               $method  HTTP method.
	 * @param string               $url     Fully-qualified URL.
	 * @param array<string,string> $headers Request headers.
	 * @param string|null          $body    Raw request body.
	 * @param int                  $timeout Timeout in seconds.
	 *
	 * @throws \RuntimeException On transport failures.
	 */
	public function request( string $method, string $url, array $headers = array(), ?string $body = null, int $timeout = 30 ): HttpResponse;
}
