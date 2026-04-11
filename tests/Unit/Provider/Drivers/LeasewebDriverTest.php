<?php

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName

/**
 * LeasewebDriverTest — comprehensive unit tests for LeasewebDriver.
 *
 * Tests driver identity, capabilities, rate limits, and state normalization.
 *
 * @package CloudBridge\Tests\Unit\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider\Drivers;

use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\Drivers\LeasewebDriver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LeasewebDriver implementation.
 *
 * Focus on core logic and state management. Full integration tests
 * with mocked HTTP are delivered in later phases.
 */
class LeasewebDriverTest extends TestCase
{
    /**
     * System under test.
     */
    private LeasewebDriver $driver;

    /**
     * Sets up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new LeasewebDriver(api_key: 'test-api-key-123');
    }

    /**
     * Tests identity methods.
     */
    public function test_identity_returns_correct_values(): void
    {
        $this->assertSame('leaseweb', $this->driver->get_id());
        $this->assertSame('Leaseweb', $this->driver->get_label());
        $this->assertSame('v2', $this->driver->get_api_version());
    }

    /**
     * Tests rate limits match Leaseweb API specification.
     */
    public function test_rate_limits(): void
    {
        $limits = $this->driver->get_rate_limits();

        $this->assertSame(100, $limits['max_requests_per_minute']);
        $this->assertSame(20, $limits['burst']);
    }

    /**
     * Tests capabilities declaration.
     */
    public function test_capabilities(): void
    {
        $caps = $this->driver->get_capabilities();

        $this->assertTrue($caps['rebuild']);
        $this->assertFalse($caps['console']);
        $this->assertFalse($caps['resize']);
    }

    /**
     * Tests state normalization from Leaseweb provider states to standard InstanceStatus.
     */
    public function test_normalise_state_running(): void
    {
        $this->assertSame(InstanceStatus::ACTIVE, $this->driver->normalise_state('running'));
    }

    public function test_normalise_state_stopped(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('stopped'));
    }

    public function test_normalise_state_provisioning(): void
    {
        $this->assertSame(InstanceStatus::PROVISIONING, $this->driver->normalise_state('provisioning'));
    }

    public function test_normalise_state_unknown_state(): void
    {
        $this->assertSame(InstanceStatus::ERROR, $this->driver->normalise_state('unknown_state'));
    }


    /**
     * Tests get_actions returns empty array.
     */
    public function test_get_actions_returns_empty(): void
    {
        $actions = $this->driver->get_actions('instance-id', array('setting' => 'value'));
        $this->assertIsArray($actions);
        $this->assertEmpty($actions);
    }
}
