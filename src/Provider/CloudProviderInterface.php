<?php

/**
 * Provider contract.
 *
 * Every cloud provider driver must implement this interface.
 * Zero WordPress coupling is permitted inside any implementation — all WordPress
 * dependencies must be injected via the constructor.
 *
 * @package CloudBridge\Provider
 */

declare(strict_types=1);

namespace CloudBridge\Provider;

use CloudBridge\Provider\DTO\ActionResult;
use CloudBridge\Provider\DTO\InstanceStatus as InstanceStatusDTO;
use CloudBridge\Provider\DTO\ProvisionRequest;
use CloudBridge\Provider\DTO\ProvisionResult;
use CloudBridge\Provider\Result\ProviderResult;

/**
 * Interface CloudProviderInterface
 *
 * All provider drivers must implement this interface. No WordPress functions are
 * permitted inside driver classes. Configuration is injected via the constructor.
 * Drivers return ProviderResult — they never throw for expected API failures.
 */
interface CloudProviderInterface
{
    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    /** Returns the driver's unique slug, e.g. 'vultr'. */
    public function get_id(): string;

    /** Returns the human-readable label, e.g. 'Vultr'. */
    public function get_label(): string;

    /** Returns the provider API version used by this driver, e.g. 'v2'. */
    public function get_api_version(): string;

    // -------------------------------------------------------------------------
    // Credential validation
    // -------------------------------------------------------------------------

    /**
     * Tests that stored credentials are valid.
     *
     * Called on settings save and from the daily health-check job.
     *
     * @return ProviderResult<bool>
     */
    public function validate_credentials(): ProviderResult;

    // -------------------------------------------------------------------------
    // Capabilities declaration
    // -------------------------------------------------------------------------

    /**
     * Returns a map of optional operation slugs to availability booleans.
     *
     * Example: ['rebuild' => true, 'console' => false, 'resize' => false]
     *
     * @return array<string, bool>
     */
    public function get_capabilities(): array;

    /**
     * Returns action definitions for the member dashboard.
     *
     * Each item: ['type' => 'button|text|view', 'label' => ..., 'action' => ..., 'confirm' => bool]
     *
     * @param string               $provider_instance_id Provider-side instance ID.
     * @param array<string, mixed> $settings             Driver-specific settings map.
     * @return array<int, array<string, mixed>>
     */
    public function get_actions(string $provider_instance_id, array $settings): array;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Provisions a new server instance.
     *
     * @param ProvisionRequest $request Provisioning parameters.
     * @return ProviderResult<ProvisionResult>
     */
    public function provision(ProvisionRequest $request): ProviderResult;

    /**
     * Destroys a server instance.
     *
     * MUST only be called when instance status is TERMINATION_QUEUED.
     * The job layer enforces this with a \LogicException.
     *
     * @param string $provider_instance_id Provider-side instance ID.
     * @return ProviderResult<ActionResult>
     */
    public function destroy(string $provider_instance_id): ProviderResult;

    // -------------------------------------------------------------------------
    // Power operations
    // -------------------------------------------------------------------------

    /**
     * Powers on a stopped instance.
     *
     * @param string $provider_instance_id Provider-side instance ID.
     * @return ProviderResult<ActionResult>
     */
    public function power_on(string $provider_instance_id): ProviderResult;

    /**
     * Powers off a running instance.
     *
     * @param string $provider_instance_id Provider-side instance ID.
     * @return ProviderResult<ActionResult>
     */
    public function power_off(string $provider_instance_id): ProviderResult;

    /**
     * Reboots a running instance.
     *
     * @param string $provider_instance_id Provider-side instance ID.
     * @return ProviderResult<ActionResult>
     */
    public function reboot(string $provider_instance_id): ProviderResult;

    // -------------------------------------------------------------------------
    // Rebuild (reinstall OS — destructive, all data lost)
    // -------------------------------------------------------------------------

    /**
     * Reinstalls the server OS from the specified image.
     *
     * @param string $provider_instance_id Provider-side instance ID.
     * @param string $image_id             OS image ID to install.
     * @return ProviderResult<ActionResult>
     */
    public function rebuild(string $provider_instance_id, string $image_id): ProviderResult;

    // -------------------------------------------------------------------------
    // Status polling
    // -------------------------------------------------------------------------

    /**
     * Returns the current provider-side status.
     *
     * @param string $provider_instance_id Provider-side instance ID.
     * @return ProviderResult<InstanceStatusDTO>
     */
    public function get_instance_status(string $provider_instance_id): ProviderResult;

    // -------------------------------------------------------------------------
    // Catalogue (used by admin plan config UI)
    // -------------------------------------------------------------------------

    /**
     * Lists available plan/size slugs, optionally filtered by region.
     *
     * @param string|null $region_slug Optional region to filter by.
     * @return ProviderResult<array<int, array<string, mixed>>>
     */
    public function get_available_plans(?string $region_slug = null): ProviderResult;

    /**
     * Lists available regions for this provider.
     *
     * @return ProviderResult<array<int, array<string, mixed>>>
     */
    public function get_available_regions(): ProviderResult;

    /**
     * Lists available OS images for this provider.
     *
     * @return ProviderResult<array<int, array<string, mixed>>>
     */
    public function get_available_images(): ProviderResult;

    // -------------------------------------------------------------------------
    // Rate limits & state normalisation
    // -------------------------------------------------------------------------

    /**
     * Declares this driver's provider API rate limits.
     *
     * Used by the global token-bucket rate limiter shared across all jobs.
     *
     * @return array{max_requests_per_minute: int, burst: int}
     */
    public function get_rate_limits(): array;

    /**
     * Maps a provider-native status string to an InstanceStatus constant.
     *
     * Unknown states MUST return InstanceStatus::ERROR, never null or empty string.
     * No raw provider status string may reach the state machine directly.
     *
     * @param string $provider_state Raw status string from the provider API.
     */
    public function normalise_state(string $provider_state): string;
}
