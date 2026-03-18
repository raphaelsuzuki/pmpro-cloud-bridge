<?php
/**
 * ProvisionFlowTest — integration test for the full provisioning flow.
 *
 * Full test suite delivered in the Integration Testing phase.
 *
 * @package CloudBridge\Tests\Integration
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Uses DummyDriver to simulate the complete flow:
 * payment confirmed → ProvisionIntent created → job dispatched →
 * provider call → ACTIVE status.
 */
class ProvisionFlowTest extends TestCase {
	// Full test suite delivered in Integration Testing phase.
}
