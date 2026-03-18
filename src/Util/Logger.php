<?php
/**
 * Logger — contextual logging wrapper.
 *
 * Full implementation delivered in the Util phase.
 *
 * @package CloudBridge\Util
 */

declare(strict_types=1);

namespace CloudBridge\Util;

/**
 * Wraps WP_Error and error_log with structured context for Cloud Bridge events.
 * Writes to WP debug log when WP_DEBUG_LOG is true.
 * Critical events are also logged to the cb_events table.
 */
final class Logger {
	// Full implementation delivered in Util phase.
}
