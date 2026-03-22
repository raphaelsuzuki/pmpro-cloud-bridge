<?php
/**
 * StateMachineTest — covers every valid transition and representative invalid ones.
 *
 * Full test suite delivered in Task 1.4.
 *
 * @package CloudBridge\Tests\Unit\Model
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Model;

use CloudBridge\Model\InstanceStateMachine;
use CloudBridge\Model\InstanceStatus;
use PHPUnit\Framework\TestCase;

/**
 * Verifies InstanceStateMachine::transition() allows valid moves,
 * throws \LogicException on invalid ones, and enforces the destroy() guard.
 */
class StateMachineTest extends TestCase {
	/**
	 * @dataProvider provide_valid_transitions
	 */
	public function test_transition_allows_valid_transitions( string $from, string $to ): void {
		$this->assertSame( $to, InstanceStateMachine::transition( $from, $to ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provide_valid_transitions(): array {
		return [
			'queued to provisioning'          => [ InstanceStatus::PROVISIONING_QUEUED, InstanceStatus::PROVISIONING ],
			'pending to provisioning'         => [ InstanceStatus::PROVISIONING_PENDING, InstanceStatus::PROVISIONING ],
			'pending to cancel requested'     => [ InstanceStatus::PROVISIONING_PENDING, InstanceStatus::CANCEL_REQUESTED ],
			'provisioning to active'          => [ InstanceStatus::PROVISIONING, InstanceStatus::ACTIVE ],
			'provisioning to failed'          => [ InstanceStatus::PROVISIONING, InstanceStatus::PROVISION_FAILED ],
			'provisioning to cancel requested'=> [ InstanceStatus::PROVISIONING, InstanceStatus::CANCEL_REQUESTED ],
			'active to stopping'              => [ InstanceStatus::ACTIVE, InstanceStatus::STOPPING ],
			'active to rebuilding'            => [ InstanceStatus::ACTIVE, InstanceStatus::REBUILDING ],
			'active to rebooting'             => [ InstanceStatus::ACTIVE, InstanceStatus::REBOOTING ],
			'active to suspended'             => [ InstanceStatus::ACTIVE, InstanceStatus::SUSPENDED ],
			'active to cancel requested'      => [ InstanceStatus::ACTIVE, InstanceStatus::CANCEL_REQUESTED ],
			'suspended to active'             => [ InstanceStatus::SUSPENDED, InstanceStatus::ACTIVE ],
			'suspended to termination queued' => [ InstanceStatus::SUSPENDED, InstanceStatus::TERMINATION_QUEUED ],
			'stopped to starting'             => [ InstanceStatus::STOPPED, InstanceStatus::STARTING ],
			'starting to active'              => [ InstanceStatus::STARTING, InstanceStatus::ACTIVE ],
			'stopping to stopped'             => [ InstanceStatus::STOPPING, InstanceStatus::STOPPED ],
			'rebooting to active'             => [ InstanceStatus::REBOOTING, InstanceStatus::ACTIVE ],
			'rebuilding to active'            => [ InstanceStatus::REBUILDING, InstanceStatus::ACTIVE ],
			'rebuilding to failed'            => [ InstanceStatus::REBUILDING, InstanceStatus::PROVISION_FAILED ],
			'failed to pending retry'         => [ InstanceStatus::PROVISION_FAILED, InstanceStatus::PROVISIONING_PENDING ],
			'failed to cancel requested'      => [ InstanceStatus::PROVISION_FAILED, InstanceStatus::CANCEL_REQUESTED ],
			'cancel requested to cancelling'  => [ InstanceStatus::CANCEL_REQUESTED, InstanceStatus::CANCELLING ],
			'cancel requested to termination queued' => [ InstanceStatus::CANCEL_REQUESTED, InstanceStatus::TERMINATION_QUEUED ],
			'cancelling to cancelled'         => [ InstanceStatus::CANCELLING, InstanceStatus::CANCELLED ],
			'cancelling to error'             => [ InstanceStatus::CANCELLING, InstanceStatus::ERROR ],
			'cancelled to terminated'         => [ InstanceStatus::CANCELLED, InstanceStatus::TERMINATED ],
			'termination queued to terminated'=> [ InstanceStatus::TERMINATION_QUEUED, InstanceStatus::TERMINATED ],
		];
	}

	public function test_transition_rejects_invalid_transition(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Invalid status transition' );

		InstanceStateMachine::transition( InstanceStatus::ACTIVE, InstanceStatus::PROVISIONING_QUEUED );
	}

	public function test_destroy_guard_precondition_is_enforced_by_transition_rules(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Invalid status transition' );

		// A job attempting destroy() from ACTIVE must fail before provider call.
		InstanceStateMachine::transition( InstanceStatus::ACTIVE, InstanceStatus::TERMINATED );
	}

	public function test_terminal_status_has_no_outbound_transitions(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Invalid status transition' );

		InstanceStateMachine::transition( InstanceStatus::TERMINATED, InstanceStatus::ACTIVE );
	}

	public function test_transition_rejects_unknown_current_status(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Unknown current status' );

		InstanceStateMachine::transition( 'INVALID', InstanceStatus::ACTIVE );
	}

	public function test_transition_rejects_unknown_target_status(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Unknown target status' );

		InstanceStateMachine::transition( InstanceStatus::ACTIVE, 'INVALID' );
	}

	public function test_transition_is_idempotent_for_same_status(): void {
		$this->assertSame(
			InstanceStatus::ACTIVE,
			InstanceStateMachine::transition( InstanceStatus::ACTIVE, InstanceStatus::ACTIVE )
		);
	}

	public function test_allowed_targets_returns_expected_map_entry(): void {
		$this->assertSame(
			[ InstanceStatus::TERMINATED ],
			InstanceStateMachine::allowed_targets( InstanceStatus::CANCELLED )
		);
	}

	public function test_can_transition_rejects_unknown_current_status(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Unknown current status' );

		InstanceStateMachine::can_transition( 'INVALID', InstanceStatus::ACTIVE );
	}

	public function test_can_transition_rejects_unknown_target_status(): void {
		$this->expectException( \LogicException::class );
		$this->expectExceptionMessage( 'Unknown target status' );

		InstanceStateMachine::can_transition( InstanceStatus::ACTIVE, 'INVALID' );
	}
}
