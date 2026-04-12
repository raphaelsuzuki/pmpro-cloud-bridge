<?php

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName

/**
 * LinodeDriverTest — comprehensive unit tests for LinodeDriver.
 *
 * Tests driver identity, capabilities, rate limits, and state normalization.
 *
 * @package CloudBridge\Tests\Unit\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider\Drivers;

use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\Drivers\LinodeDriver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LinodeDriver implementation.
 *
 * Focus on core logic and state management. Full integration tests
 * with mocked HTTP are delivered in later phases.
 */
class LinodeDriverTest extends TestCase
{
    /**
     * System under test.
     */
    private LinodeDriver $driver;

    /**
     * Sets up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new LinodeDriver(api_token: 'test-api-token-123');
    }

    /**
     * Tests identity methods.
     */
    public function test_identity_returns_correct_values(): void
    {
        $this->assertSame('linode', $this->driver->get_id());
        $this->assertSame('Linode', $this->driver->get_label());
        $this->assertSame('v4', $this->driver->get_api_version());
    }

    /**
     * Tests rate limits match Linode API specification.
     */
    public function test_rate_limits(): void
    {
        $limits = $this->driver->get_rate_limits();

        $this->assertSame(240, $limits['max_requests_per_minute']);
        $this->assertSame(60, $limits['burst']);
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
     * Tests state normalization from Linode provider states to standard InstanceStatus.
     */
    public function test_normalise_state_provisioning(): void
    {
        $this->assertSame(InstanceStatus::PROVISIONING, $this->driver->normalise_state('provisioning'));
    }

    public function test_normalise_state_running(): void
    {
        $this->assertSame(InstanceStatus::ACTIVE, $this->driver->normalise_state('running'));
    }

    public function test_normalise_state_stopped(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('stopped'));
    }

    public function test_normalise_state_offline(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('offline'));
    }

    public function test_normalise_state_booting(): void
    {
        $this->assertSame(InstanceStatus::STARTING, $this->driver->normalise_state('booting'));
    }

    public function test_normalise_state_shutting_down(): void
    {
        $this->assertSame(InstanceStatus::STOPPING, $this->driver->normalise_state('shutting_down'));
    }

    public function test_normalise_state_rebooting(): void
    {
        $this->assertSame(InstanceStatus::REBOOTING, $this->driver->normalise_state('rebooting'));
    }

    public function test_normalise_state_rebuilding(): void
    {
        $this->assertSame(InstanceStatus::REBUILDING, $this->driver->normalise_state('rebuilding'));
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
