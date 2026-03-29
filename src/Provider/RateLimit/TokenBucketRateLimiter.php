<?php
/**
 * TokenBucketRateLimiter — provider-scoped transient-backed throttling.
 *
 * @package CloudBridge\Provider\RateLimit
 */

declare(strict_types=1);

namespace CloudBridge\Provider\RateLimit;

/**
 * Shared token-bucket limiter used before provider API calls.
 */
final class TokenBucketRateLimiter {
	private const STATE_PREFIX = 'cb_rate_limit_';

	/**
	 * Sleep callback used when bucket is empty.
	 *
	 * @var callable
	 */
	private $sleep_callback;

	/**
	 * Clock callback returning current timestamp in seconds.
	 *
	 * @var callable
	 */
	private $clock_callback;

	/**
	 * Constructor.
	 *
	 * @param TransientStoreInterface|null $store          State backend.
	 * @param callable|null                $sleep_callback Receives microseconds to sleep.
	 * @param callable|null                $clock_callback Returns current timestamp float.
	 */
	public function __construct(
		private readonly ?TransientStoreInterface $store = null,
		?callable $sleep_callback = null,
		?callable $clock_callback = null,
	) {
		$this->sleep_callback = $sleep_callback ?? static function ( int $microseconds ): void {
			\usleep( $microseconds );
		};
		$this->clock_callback = $clock_callback ?? static fn (): float => \microtime( true );
	}

	/**
	 * Acquires a token, blocking with sleep when the bucket is empty.
	 *
	 * @param string $provider_id              Provider slug.
	 * @param int    $max_requests_per_minute  Refill budget per minute.
	 * @param int    $burst                    Maximum token capacity.
	 */
	public function acquire( string $provider_id, int $max_requests_per_minute, int $burst ): void {
		$capacity          = \max( 1, $burst );
		$refill_per_second = \max( 1, $max_requests_per_minute ) / 60;
		$state_key         = self::STATE_PREFIX . $provider_id;
		$ttl_seconds       = \max( 60, (int) \ceil( $capacity / $refill_per_second ) * 2 );

		while ( true ) {
			$now   = $this->now();
			$state = $this->get_store()->get( $state_key, null );

			$tokens     = (float) $capacity;
			$updated_at = $now;
			if ( \is_array( $state ) && isset( $state['tokens'], $state['updated_at'] ) ) {
				$tokens     = (float) $state['tokens'];
				$updated_at = (float) $state['updated_at'];
			}

			$elapsed = \max( 0.0, $now - $updated_at );
			$tokens  = \min( (float) $capacity, $tokens + ( $elapsed * $refill_per_second ) );

			if ( $tokens >= 1.0 ) {
				$this->get_store()->set(
					$state_key,
					array(
						'tokens'     => $tokens - 1.0,
						'updated_at' => $now,
					),
					$ttl_seconds
				);
				return;
			}

			$seconds_until_next_token = ( 1.0 - $tokens ) / $refill_per_second;
			$microseconds             = (int) \ceil( $seconds_until_next_token * 1000000 );
			$microseconds             = \max( 10000, $microseconds );

			$this->sleep( $microseconds );
		}
	}

	/**
	 * Returns the active limiter state backend.
	 *
	 * @return TransientStoreInterface
	 */
	private function get_store(): TransientStoreInterface {
		return $this->store ?? new WpTransientStore();
	}

	/**
	 * Reads the current timestamp from the injected clock.
	 */
	private function now(): float {
		$clock = $this->clock_callback;
		return (float) $clock();
	}

	/**
	 * Sleeps for the provided microseconds via injected callback.
	 *
	 * @param int $microseconds Sleep duration.
	 */
	private function sleep( int $microseconds ): void {
		$sleeper = $this->sleep_callback;
		$sleeper( $microseconds );
	}
}
