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
    public readonly int $status_code;

    public readonly string $body;

    /**
     * @var array<string, string|array<int, string>>
     */
    public readonly array $headers;

    /**
     * Constructor.
     *
     * @param int                                        $status_code HTTP response status code.
     * @param string                                     $body        Raw response body.
     * @param array<string, string|array<int, string>>  $headers     Response headers.
     *
     * @throws \InvalidArgumentException When any argument is invalid.
     */
    public function __construct(int $status_code, string $body, array $headers = array())
    {
        if ($status_code < 100 || $status_code > 599) {
            throw new \InvalidArgumentException(
                sprintf('Invalid HTTP status code "%d". Expected a value between 100 and 599.', $status_code)
            );
        }

        foreach ($headers as $header_name => $header_value) {
            if (! is_string($header_name) || '' === $header_name || is_numeric($header_name)) {
                throw new \InvalidArgumentException('HTTP response headers must use non-numeric string keys.');
            }

            if (is_string($header_value)) {
                continue;
            }

            if (! is_array($header_value)) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid header value for "%s". Expected string or string-array.', $header_name)
                );
            }

            foreach ($header_value as $item) {
                if (! is_string($item)) {
                    throw new \InvalidArgumentException(
                        sprintf('Invalid multi-value header entry for "%s". Expected string items only.', $header_name)
                    );
                }
            }
        }

        $this->status_code = $status_code;
        $this->body        = $body;
        $this->headers     = $headers;
    }
}
