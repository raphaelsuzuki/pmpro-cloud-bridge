<?php
/**
 * CredentialStore — encrypted storage of provider API keys.
 *
 * Full implementation delivered in the Security phase.
 *
 * @package CloudBridge\Security
 */

declare(strict_types=1);

namespace CloudBridge\Security;

/**
 * Stores and retrieves provider API credentials encrypted at rest.
 *
 * Uses sodium_crypto_secretbox() with the key from the PCM_ENCRYPTION_KEY
 * constant (defined in wp-config.php or the environment). The key is NEVER
 * stored in the database.
 */
final class CredentialStore {
	// Full implementation delivered in Security phase.
}
