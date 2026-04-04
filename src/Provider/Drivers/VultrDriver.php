<?php

/**
 * VultrDriver — Vultr Cloud Compute (API v2).
 *
 * Implements CloudProviderInterface for Vultr Cloud Compute.
 *
 * Authentication: Authorization Bearer token header.
 * Idempotency: X-Request-ID header on POST /instances.
 * Rate limit: 30 req/s (1800 req/min).
 * API docs: https://www.vultr.com/api/#server
 *
 * @package CloudBridge\Provider\Drivers
 */

declare(strict_types=1);

namespace CloudBridge\Provider\Drivers;

use CloudBridge\Provider\AbstractProvider;
use CloudBridge\Provider\DTO\ActionResult;
use CloudBridge\Provider\DTO\InstanceStatus;
use CloudBridge\Provider\DTO\ProvisionRequest;
use CloudBridge\Provider\DTO\ProvisionResult;
use CloudBridge\Provider\Result\ProviderResult;

/**
 * Vultr Cloud Compute driver (API v2).
 *
 * Zero WordPress coupling. Configuration injected via constructor.
 */
final class VultrDriver extends AbstractProvider
{
    private const API_BASE = 'https://api.vultr.com/v2';

    /**
     * Constructor.
     *
     * @param string $api_key Vultr API key.
     */
    public function __construct(
        private readonly string $api_key,
    ) {
    }

    // -------------------------------------------------------------------------
    // Identity
    // -------------------------------------------------------------------------

    public function get_id(): string
    {
        return 'vultr';
    }

    public function get_label(): string
    {
        return 'Vultr';
    }

    public function get_api_version(): string
    {
        return 'v2';
    }

    // -------------------------------------------------------------------------
    // Credential Validation
    // -------------------------------------------------------------------------

    public function validate_credentials(): ProviderResult
    {
        $response = $this->http_request(
            'GET',
            self::API_BASE . '/account',
            $this->get_headers()
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        if (200 !== $response['code']) {
            if (in_array($response['code'], array(401, 403), true)) {
                return ProviderResult::fail('auth_failed', 'Invalid or expired Vultr API key.');
            }

            $error_msg = $this->extract_error_message($response['body'], 'Failed to validate Vultr credentials.');
            return ProviderResult::fail('api_error', $error_msg);
        }

        return ProviderResult::ok(true);
    }

    // -------------------------------------------------------------------------
    // Capabilities
    // -------------------------------------------------------------------------

    public function get_capabilities(): array
    {
        return array(
            'rebuild' => true,
            'console' => false,
            'resize'  => false,
        );
    }

    public function get_actions(string $provider_instance_id, array $settings): array
    {
        return array();
    }

    // -------------------------------------------------------------------------
    // Provisioning
    // -------------------------------------------------------------------------

    public function provision(ProvisionRequest $request): ProviderResult
    {
        $body = array(
            'region'        => $request->region_slug,
            'plan'          => $request->plan_slug,
            'os_id'         => (int) $request->image_id,
            'hostname'      => $request->hostname,
            'label'         => $request->hostname,
            'enable_ipv6'   => true,
            'backups'       => 'disabled',
        );

        // Add SSH keys if provided.
        if (! empty($request->ssh_key_ids)) {
            $body['sshkey_id'] = array_values($request->ssh_key_ids);
        }

        $headers = array_merge(
            $this->get_headers(),
            array(
                'X-Request-ID' => 'cb_' . $request->idempotency_key,
            )
        );

        $response = $this->http_request(
            'POST',
            self::API_BASE . '/instances',
            $headers,
            $body
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        if (201 !== $response['code']) {
            $error_msg = $this->extract_error_message($response['body'], 'Failed to provision instance');
            return ProviderResult::fail('api_error', $error_msg);
        }

        $decoded = $this->decode_json($response['body']);
        if (null === $decoded || ! isset($decoded['instance'])) {
            return ProviderResult::fail('api_error', 'Invalid response structure from Vultr API.');
        }

        $instance = $decoded['instance'];

        return ProviderResult::ok(
            new ProvisionResult(
                provider_instance_id: (string) $instance['id'],
                provider_status: $instance['status'] ?? 'pending',
                ipv4: $instance['main_ip'] ?? null,
                ipv6: $this->extract_ipv6($instance) ?? null
            )
        );
    }

    // -------------------------------------------------------------------------
    // Instance Destruction
    // -------------------------------------------------------------------------

    public function destroy(string $provider_instance_id): ProviderResult
    {
        $response = $this->http_request(
            'DELETE',
            self::API_BASE . '/instances/' . $provider_instance_id,
            $this->get_headers()
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        // Vultr returns 204 No Content on successful deletion.
        if (204 !== $response['code']) {
            $error_msg = $this->extract_error_message($response['body'], 'Failed to destroy instance');
            return ProviderResult::fail('api_error', $error_msg);
        }

        return ProviderResult::ok(new ActionResult(provider_action_id: 'vultr-destroy-' . $provider_instance_id));
    }

    // -------------------------------------------------------------------------
    // Power Operations
    // -------------------------------------------------------------------------

    public function power_on(string $provider_instance_id): ProviderResult
    {
        return $this->send_instance_command($provider_instance_id, 'power_on');
    }

    public function power_off(string $provider_instance_id): ProviderResult
    {
        return $this->send_instance_command($provider_instance_id, 'power_off');
    }

    public function reboot(string $provider_instance_id): ProviderResult
    {
        return $this->send_instance_command($provider_instance_id, 'reboot');
    }

    // -------------------------------------------------------------------------
    // Rebuild
    // -------------------------------------------------------------------------

    public function rebuild(string $provider_instance_id, string $image_id): ProviderResult
    {
        $body = array(
            'os_id' => (int) $image_id,
        );

        $response = $this->http_request(
            'PATCH',
            self::API_BASE . '/instances/' . $provider_instance_id,
            $this->get_headers(),
            $body
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        if (202 !== $response['code']) {
            $error_msg = $this->extract_error_message($response['body'], 'Failed to rebuild instance');
            return ProviderResult::fail('api_error', $error_msg);
        }

        return ProviderResult::ok(new ActionResult(provider_action_id: 'vultr-rebuild-' . $provider_instance_id));
    }

    // -------------------------------------------------------------------------
    // Status Polling
    // -------------------------------------------------------------------------

    public function get_instance_status(string $provider_instance_id): ProviderResult
    {
        $response = $this->http_request(
            'GET',
            self::API_BASE . '/instances/' . $provider_instance_id,
            $this->get_headers()
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        if (200 !== $response['code']) {
            // Not found is expected if instance was deleted.
            if (404 === $response['code']) {
                return ProviderResult::fail('not_found', 'Instance not found on Vultr.');
            }

            $error_msg = $this->extract_error_message($response['body'], 'Failed to get instance status');
            return ProviderResult::fail('api_error', $error_msg);
        }

        $decoded = $this->decode_json($response['body']);
        if (null === $decoded || ! isset($decoded['instance'])) {
            return ProviderResult::fail('api_error', 'Invalid response structure from Vultr API.');
        }

        $provider_status = (string) ($decoded['instance']['status'] ?? 'unknown');
        $normalized_status = $this->normalise_state($provider_status);

        return ProviderResult::ok($normalized_status);
    }

    // -------------------------------------------------------------------------
    // Catalogue (Plans, Regions, Images)
    // -------------------------------------------------------------------------

    public function get_available_plans(?string $region_slug = null): ProviderResult
    {
        $url = self::API_BASE . '/plans';
        if (null !== $region_slug) {
            $url .= '?type=cloud&region=' . urlencode($region_slug);
        } else {
            $url .= '?type=cloud';
        }

        $response = $this->http_request('GET', $url, $this->get_headers());

        if ($response instanceof ProviderResult) {
            return $response;
        }

        if (200 !== $response['code']) {
            $error_msg = $this->extract_error_message($response['body'], 'Failed to fetch plans');
            return ProviderResult::fail('api_error', $error_msg);
        }

        $decoded = $this->decode_json($response['body']);
        if (null === $decoded || ! isset($decoded['plans'])) {
            return ProviderResult::fail('api_error', 'Invalid response structure from Vultr API.');
        }

        $plans = array();
        foreach ($decoded['plans'] as $plan) {
            $monthly_cost = $plan['monthly_cost'] ?? 0;
            $plans[] = array(
                'slug'        => $plan['id'],
                'name'        => $plan['name'] ?? '',
                'cores'       => $plan['vcpu_count'] ?? 0,
                'memory_gb'   => ($plan['ram'] ?? 0) / 1024, // RAM in MB.
                'disk_gb'     => $plan['disk'] ?? 0,
                'bandwidth_gb' => $plan['bandwidth'] ?? null,
                'price_monthly' => $monthly_cost,
                'price_hourly' => $monthly_cost > 0 ? ($monthly_cost / 730) : 0,
            );
        }

        return ProviderResult::ok($plans);
    }

    public function get_available_regions(): ProviderResult
    {
        $response = $this->http_request(
            'GET',
            self::API_BASE . '/regions',
            $this->get_headers()
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        if (200 !== $response['code']) {
            $error_msg = $this->extract_error_message($response['body'], 'Failed to fetch regions');
            return ProviderResult::fail('api_error', $error_msg);
        }

        $decoded = $this->decode_json($response['body']);
        if (null === $decoded || ! isset($decoded['regions'])) {
            return ProviderResult::fail('api_error', 'Invalid response structure from Vultr API.');
        }

        $regions = array();
        foreach ($decoded['regions'] as $region) {
            // Only include regions that support cloud compute.
            if (! isset($region['capabilities']) || ! in_array('cloud_compute', $region['capabilities'], true)) {
                continue;
            }

            $regions[] = array(
                'slug'      => $region['id'],
                'name'      => $region['city'] . ', ' . $region['country'],
                'continent' => $region['continent'] ?? '',
            );
        }

        return ProviderResult::ok($regions);
    }

    public function get_available_images(): ProviderResult
    {
        $response = $this->http_request(
            'GET',
            self::API_BASE . '/os?type=all',
            $this->get_headers()
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        if (200 !== $response['code']) {
            $error_msg = $this->extract_error_message($response['body'], 'Failed to fetch images');
            return ProviderResult::fail('api_error', $error_msg);
        }

        $decoded = $this->decode_json($response['body']);
        if (null === $decoded || ! isset($decoded['os'])) {
            return ProviderResult::fail('api_error', 'Invalid response structure from Vultr API.');
        }

        $images = array();
        foreach ($decoded['os'] as $os) {
            $images[] = array(
                'id'   => (string) $os['id'],
                'name' => $os['name'] ?? '',
                'arch' => $os['arch'] ?? 'x64',
            );
        }

        return ProviderResult::ok($images);
    }

    // -------------------------------------------------------------------------
    // Rate Limits & State Normalization
    // -------------------------------------------------------------------------

    public function get_rate_limits(): array
    {
        return array(
            'max_requests_per_minute' => 1800,
            'burst'                   => 30,
        );
    }

    public function normalise_state(string $provider_state): string
    {
        return match ($provider_state) {
            'pending'       => InstanceStatus::PROVISIONING,
            'active'        => InstanceStatus::ACTIVE,
            'stopped'       => InstanceStatus::STOPPED,
            'suspended'     => InstanceStatus::SUSPENDED,
            'resizing'      => InstanceStatus::REBUILDING,
            'reboot'        => InstanceStatus::REBOOTING,
            'reinstalling'  => InstanceStatus::REBUILDING,
            default         => InstanceStatus::ERROR,
        };
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns standard headers for Vultr API requests.
     *
     * @return array<string, string>
     */
    private function get_headers(): array
    {
        return array(
            'Authorization' => 'Bearer ' . $this->api_key,
        );
    }

    /**
     * Sends a power/reboot command to an instance.
     *
     * Maps command name to correct Vultr endpoint path. Only accepts valid
     * command constants from CloudProviderInterface: 'power_on', 'power_off', 'reboot'.
     *
     * @param string $instance_id Provider instance ID.
     * @param string $command Command name ('power_on', 'power_off', or 'reboot').
     * @return ProviderResult<ActionResult>
     *
     * @throws \InvalidArgumentException If command is not one of the accepted constants.
     */
    private function send_instance_command(string $instance_id, string $command): ProviderResult
    {
        // Map command to endpoint path — Vultr uses separate endpoints, no command body.
        // Only valid commands proceed; unknown commands reject rather than silently halt.
        $endpoint = match ($command) {
            'power_on' => '/instances/' . $instance_id . '/start',
            'power_off' => '/instances/' . $instance_id . '/halt',
            'reboot'   => '/instances/' . $instance_id . '/reboot',
            default    => throw new \InvalidArgumentException(
                \sprintf('Unknown instance command: "%s". Valid commands: power_on, power_off, reboot.', $command)
            ),
        };

        $response = $this->http_request(
            'POST',
            self::API_BASE . $endpoint,
            $this->get_headers(),
            null
        );

        if ($response instanceof ProviderResult) {
            return $response;
        }

        // For power operations, Vultr returns 202 Accepted or 204 No Content.
        if (! in_array($response['code'], array(202, 204), true)) {
            $error_msg = $this->extract_error_message($response['body'], 'Failed to send command to instance');
            return ProviderResult::fail('api_error', $error_msg);
        }

        return ProviderResult::ok(new ActionResult(provider_action_id: 'vultr-' . $command . '-' . $instance_id));
    }

    /**
     * Extracts IPv6 from instance data.
     *
     * @param array<string, mixed> $instance Instance data.
     * @return string|null
     */
    private function extract_ipv6(array $instance): ?string
    {
        if (! isset($instance['v6_networks']) || ! is_array($instance['v6_networks'])) {
            return null;
        }

        foreach ($instance['v6_networks'] as $network) {
            if (isset($network['ip']) && ! empty($network['ip'])) {
                return $network['ip'];
            }
        }

        return null;
    }

    /**
     * Extracts error message from Vultr API response.
     *
     * @param string $response_body Response body.
     * @param string $fallback Fallback message.
     * @return string
     */
    private function extract_error_message(string $response_body, string $fallback): string
    {
        $decoded = $this->decode_json($response_body);
        if (is_array($decoded) && isset($decoded['error'])) {
            return (string) $decoded['error'];
        }

        return $fallback;
    }
}
