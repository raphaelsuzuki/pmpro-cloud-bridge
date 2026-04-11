<?php

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName

/**
 * HetznerDriverTest — comprehensive unit tests for HetznerDriver.
 *
 * Tests driver identity, capabilities, rate limits, and state normalization.
 *
 * @package CloudBridge\Tests\Unit\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider\Drivers;

use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\Drivers\HetznerDriver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HetznerDriver implementation.
 *
 * Focus on core logic and state management. Full integration tests
 * with mocked HTTP are delivered in later phases.
 */
class HetznerDriverTest extends TestCase
{
    /**
     * System under test.
     */
    private HetznerDriver $driver;

    /**
     * Sets up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new HetznerDriver(api_token: 'test-api-token-123');
    }

    /**
     * Tests identity methods.
     */
    public function test_identity_returns_correct_values(): void
    {
        $this->assertSame('hetzner', $this->driver->get_id());
        $this->assertSame('Hetzner Cloud', $this->driver->get_label());
        $this->assertSame('v1', $this->driver->get_api_version());
    }

    /**
     * Tests rate limits match Hetzner Cloud API specification.
     */
    public function test_rate_limits(): void
    {
        $limits = $this->driver->get_rate_limits();

        $this->assertSame(60, $limits['max_requests_per_minute']);
        $this->assertSame(10, $limits['burst']);
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
     * Tests state normalization from Hetzner Cloud provider states to standard InstanceStatus.
     */
    public function test_normalise_state_initializing(): void
    {
        $this->assertSame(InstanceStatus::PROVISIONING, $this->driver->normalise_state('initializing'));
    }

    public function test_normalise_state_starting(): void
    {
        $this->assertSame(InstanceStatus::STARTING, $this->driver->normalise_state('starting'));
    }

    public function test_normalise_state_running(): void
    {
        $this->assertSame(InstanceStatus::ACTIVE, $this->driver->normalise_state('running'));
    }

    public function test_normalise_state_stopping(): void
    {
        $this->assertSame(InstanceStatus::STOPPING, $this->driver->normalise_state('stopping'));
    }

    public function test_normalise_state_stopped(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('stopped'));
    }

    public function test_normalise_state_migrating(): void
    {
        $this->assertSame(InstanceStatus::REBUILDING, $this->driver->normalise_state('migrating'));
    }

    public function test_normalise_state_rebooting(): void
    {
        $this->assertSame(InstanceStatus::REBOOTING, $this->driver->normalise_state('rebooting'));
    }

    public function test_normalise_state_resetting(): void
    {
        $this->assertSame(InstanceStatus::REBUILDING, $this->driver->normalise_state('resetting'));
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
