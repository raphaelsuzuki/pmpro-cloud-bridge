<?php

/**
 * ProviderResult — result monad returned by every driver method.
 *
 * Callers MUST check is_ok() before calling unwrap(). Drivers must never throw
 * PHP exceptions for expected API failure conditions; they return
 * ProviderResult::fail() instead.
 *
 * @package CloudBridge\Provider\Result
 */

declare(strict_types=1);

namespace CloudBridge\Provider\Result;

/**
 * Immutable result wrapper for all provider driver responses.
 *
 * @template T
 */
final class ProviderResult
{
    /**
     * Valid error code constants.
     *
     * One of: auth_failed, not_found, rate_limited, quota_exceeded, invalid_plan, api_error, http_error, unsupported.
     *
     * @var string[]
     */
    public const ERROR_CODES = array(
        'auth_failed',
        'not_found',
        'rate_limited',
        'quota_exceeded',
        'invalid_plan',
        'api_error',
        'http_error',
        'unsupported',
    );

    /**
     * Constructor sets operation state and result data.
     *
     * @param bool   $ok            Whether the operation succeeded.
     * @param mixed  $data          The unwrapped value on success (null on failure).
     * @param string $error_code    One of ERROR_CODES on failure; empty string on success.
     * @param string $error_message Human-readable failure description.
     */
    private function __construct(
        private readonly bool $ok,
        private readonly mixed $data,
        private readonly string $error_code,
        private readonly string $error_message,
    ) {
    }

    /**
     * Creates a successful result.
     *
     * @template U
     * @param U $data The value to wrap.
     * @return self<U>
     */
    public static function ok(mixed $data = null): self
    {
        return new self(true, $data, '', '');
    }

    /**
     * Creates a failure result.
     *
     * @param string $error_code    Must be one of self::ERROR_CODES.
     * @param string $error_message Human-readable description of the failure.
     * @return self<null>
     *
     * @throws \InvalidArgumentException When $error_code is not in self::ERROR_CODES.
     */
    public static function fail(string $error_code, string $error_message): self
    {
        if (! in_array($error_code, self::ERROR_CODES, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid provider error code "%s". Allowed codes: %s.',
                    $error_code,
                    implode(', ', self::ERROR_CODES)
                )
            );
        }

        return new self(false, null, $error_code, $error_message);
    }

    /** Whether the operation succeeded. */
    public function is_ok(): bool
    {
        return $this->ok;
    }

    /**
     * Returns the wrapped value.
     *
     * @throws \LogicException If called on a failure result.
     * @return mixed
     */
    public function unwrap(): mixed
    {
        if (! $this->ok) {
            $msg = sprintf('Called unwrap() on a failed ProviderResult: [%s] %s', $this->error_code, $this->error_message);
            // @codingStandardsIgnoreLine
            throw new \LogicException($msg);
        }

        return $this->data;
    }

    /** Returns the error code, or empty string on success. */
    public function get_error_code(): string
    {
        return $this->error_code;
    }

    /** Returns the error message, or empty string on success. */
    public function get_error_message(): string
    {
        return $this->error_message;
    }
}
