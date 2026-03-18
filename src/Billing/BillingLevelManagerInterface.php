<?php
/**
 * BillingLevelManagerInterface — abstraction contract for the billing identity layer.
 *
 * Full implementation delivered in the Billing phase.
 * Cloud Bridge MUST NOT depend on Magic Levels internals. All interactions go
 * through this interface, implemented by MagicLevelsAdapter.
 *
 * @package CloudBridge\Billing
 */

declare(strict_types=1);

namespace CloudBridge\Billing;

/**
 * Abstraction over PMPro Magic Levels.
 *
 * Direct calls to pmpro_magic_levels_process() outside of MagicLevelsAdapter
 * are CI failures.
 */
interface BillingLevelManagerInterface {
	// Full interface definition delivered in Billing phase.
}
