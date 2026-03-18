<?php
/**
 * MagicLevelsAdapter — implements BillingLevelManagerInterface via PMPro Magic Levels.
 *
 * Full implementation delivered in the Billing phase.
 *
 * @package CloudBridge\Billing
 */

declare(strict_types=1);

namespace CloudBridge\Billing;

/**
 * Bridges Cloud Bridge billing calls to PMPro Magic Levels.
 *
 * This is the ONLY class permitted to call pmpro_magic_levels_process().
 */
final class MagicLevelsAdapter implements BillingLevelManagerInterface {
	// Full implementation delivered in Billing phase.
}
