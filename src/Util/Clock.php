<?php
/**
 * Clock — testable wrapper for time().
 *
 * @package CloudBridge\Util
 */

declare(strict_types=1);

namespace CloudBridge\Util;

/**
 * Wraps time() so that tests can inject a deterministic clock.
 *
 * All production code should call Clock::now() rather than time() directly.
 * In tests, pass a mock Clock to inject a fixed timestamp.
 */
final class Clock {

	/**
	 * Returns the current UTC Unix timestamp.
	 *
	 * Production usage: Clock::now()
	 * Test usage: inject a mock that returns a fixed value.
	 */
	public static function now(): int {
		return time();
	}
}
