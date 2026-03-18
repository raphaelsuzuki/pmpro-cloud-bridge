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
final class ProviderResult {

	/**
	 * Valid error code constants.
	 *
	 * @var string[]
	 */
	public const ERROR_CODES = [
		'auth_failed',
		'not_found',
		'rate_limited',
		'quota_exceeded',
		'invalid_plan',
		'api_error',
		'http_error',
		'unsupported',
	];

	/**
	 * @param bool        $ok            Whether the operation succeeded.
	 * @param mixed       $data          The unwrapped value on success (null on failure).
	 * @param string      $error_code    One of ERROR_CODES on failure; empty string on success.
	 * @param string      $error_message Human-readable failure description.
	 */
	private function __construct(
		private readonly bool   $ok,
		private readonly mixed  $data,
		private readonly string $error_code,
		private readonly string $error_message,
	) {}

	/**
	 * Creates a successful result.
	 *
	 * @template U
	 * @param mixed $data The value to wrap.
	 * @return self<U>
	 */
	public static function ok( mixed $data = null ): self {
		return new self( true, $data, '', '' );
	}

	/**
	 * Creates a failure result.
	 *
	 * @param string $error_code    Must be one of self::ERROR_CODES.
	 * @param string $error_message Human-readable description of the failure.
	 * @return self<null>
	 */
	public static function fail( string $error_code, string $error_message ): self {
		return new self( false, null, $error_code, $error_message );
	}

	/** Whether the operation succeeded. */
	public function is_ok(): bool {
		return $this->ok;
	}

	/**
	 * Returns the wrapped value.
	 *
	 * @throws \LogicException If called on a failure result.
	 * @return mixed
	 */
	public function unwrap(): mixed {
		if ( ! $this->ok ) {
			throw new \LogicException(
				sprintf( 'Called unwrap() on a failed ProviderResult: [%s] %s', $this->error_code, $this->error_message )
			);
		}

		return $this->data;
	}

	/** Returns the error code, or empty string on success. */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/** Returns the error message, or empty string on success. */
	public function get_error_message(): string {
		return $this->error_message;
	}
}
