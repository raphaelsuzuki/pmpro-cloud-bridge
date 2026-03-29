<?php

/**
 * HttpResponse — normalized HTTP response for provider requests.
 *
 * @package CloudBridge\Provider\Http
 */

declare(strict_types=1);

namespace CloudBridge\Provider\Http;

/**
 * Immutable HTTP response value object.
 */
final class HttpResponse
{
    /**
     * Constructor.
     *
     * @param int                                      $status_code HTTP response status code.
     * @param string                                   $body        Raw response body.
     * @param array<string, string|array<int, string>> $headers Response headers.
     */
    public function __construct(
        public readonly int $status_code,
        public readonly string $body,
        public readonly array $headers = array(),
    ) {
    }
}
