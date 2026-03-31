<?php

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName

/**
 * VultrDriverTest — comprehensive unit tests for VultrDriver.
 *
 * Tests mocked HTTP responses where applicable.
 *
 * @package CloudBridge\Tests\Unit\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider\Drivers;

use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\Drivers\VultrDriver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VultrDriver implementation.
 *
 * Focus on core logic and state management. Full integration tests
 * with mocked HTTP are delivered in later phases.
 */
class VultrDriverTest extends TestCase
{
    /**
     * System under test.
     */
    private VultrDriver $driver;

    /**
     * Sets up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new VultrDriver(api_key: 'test-api-key-123');
    }

    /**
     * Tests identity methods.
     */
    public function test_identity_returns_correct_values(): void
    {
        $this->assertSame('vultr', $this->driver->get_id());
        $this->assertSame('Vultr', $this->driver->get_label());
        $this->assertSame('v2', $this->driver->get_api_version());
    }

    /**
     * Tests rate limits match Vultr API specification.
     */
    public function test_rate_limits(): void
    {
        $limits = $this->driver->get_rate_limits();

        $this->assertSame(1800, $limits['max_requests_per_minute']);
        $this->assertSame(30, $limits['burst']);
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
     * Tests state normalization from Vultr provider states to standard InstanceStatus.
     */
    public function test_normalise_state_pending(): void
    {
        $this->assertSame(InstanceStatus::PROVISIONING, $this->driver->normalise_state('pending'));
    }

    /**
     * Tests state normalization for active status.
     */
    public function test_normalise_state_active(): void
    {
        $this->assertSame(InstanceStatus::ACTIVE, $this->driver->normalise_state('active'));
    }

    /**
     * Tests state normalization for stopped status.
     */
    public function test_normalise_state_stopped(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('stopped'));
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('suspended'));
    }

    /**
     * Tests state normalization for resizing/rebuilding.
     */
    public function test_normalise_state_rebuilding(): void
    {
        $this->assertSame(InstanceStatus::REBUILDING, $this->driver->normalise_state('resizing'));
        $this->assertSame(InstanceStatus::REBUILDING, $this->driver->normalise_state('reinstalling'));
    }

    /**
     * Tests state normalization for rebooting.
     */
    public function test_normalise_state_rebooting(): void
    {
        $this->assertSame(InstanceStatus::REBOOTING, $this->driver->normalise_state('reboot'));
    }

    /**
     * Tests state normalization for unknown states.
     */
    public function test_normalise_state_unknown(): void
    {
        $this->assertSame(InstanceStatus::ERROR, $this->driver->normalise_state('unknown_state'));
        $this->assertSame(InstanceStatus::ERROR, $this->driver->normalise_state('invalid_status'));
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

