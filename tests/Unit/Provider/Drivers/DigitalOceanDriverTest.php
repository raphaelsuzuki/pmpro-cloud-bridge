<?php

// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName

/**
 * DigitalOceanDriverTest — comprehensive unit tests for DigitalOceanDriver.
 *
 * Tests driver identity, capabilities, rate limits, and state normalization.
 *
 * @package CloudBridge\Tests\Unit\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Tests\Unit\Provider\Drivers;

use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\Drivers\DigitalOceanDriver;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DigitalOceanDriver implementation.
 *
 * Focus on core logic and state management. Full integration tests
 * with mocked HTTP are delivered in later phases.
 */
class DigitalOceanDriverTest extends TestCase
{
    /**
     * System under test.
     */
    private DigitalOceanDriver $driver;

    /**
     * Sets up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new DigitalOceanDriver(api_token: 'test-api-token-123');
    }

    /**
     * Tests identity methods.
     */
    public function test_identity_returns_correct_values(): void
    {
        $this->assertSame('digitalocean', $this->driver->get_id());
        $this->assertSame('DigitalOcean', $this->driver->get_label());
        $this->assertSame('v2', $this->driver->get_api_version());
    }

    /**
     * Tests rate limits match DigitalOcean API specification.
     */
    public function test_rate_limits(): void
    {
        $limits = $this->driver->get_rate_limits();

        $this->assertSame(250, $limits['max_requests_per_minute']);
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
     * Tests state normalization from DigitalOcean provider states to standard InstanceStatus.
     */
    public function test_normalise_state_new(): void
    {
        $this->assertSame(InstanceStatus::PROVISIONING, $this->driver->normalise_state('new'));
    }

    public function test_normalise_state_active(): void
    {
        $this->assertSame(InstanceStatus::ACTIVE, $this->driver->normalise_state('active'));
    }

    public function test_normalise_state_archive(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('archive'));
    }

    public function test_normalise_state_off(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('off'));
    }

    public function test_normalise_state_power_off(): void
    {
        $this->assertSame(InstanceStatus::STOPPED, $this->driver->normalise_state('power_off'));
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
