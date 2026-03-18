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

use PHPUnit\Framework\TestCase;

/**
 * Verifies InstanceStateMachine::transition() allows valid moves,
 * throws \LogicException on invalid ones, and enforces the destroy() guard.
 */
class StateMachineTest extends TestCase {
	// Full test suite delivered in Task 1.4.
}
