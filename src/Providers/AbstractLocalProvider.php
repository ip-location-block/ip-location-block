<?php
/**
 * Base class for local (bundled-database) geolocation providers.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

use IPLocationBlock\Geolocation\LocationResult;
use IPLocationBlock\Support\Util;

/**
 * Shared plumbing for the two local providers. Concrete providers own their
 * lookup(), download() and the `ip-location-block-<provider>-*` filters.
 */
abstract class AbstractLocalProvider implements ProviderInterface, DownloadableProviderInterface {

	public function isLocal(): bool {
		return true;
	}

	public function legacySupports(): array {
		return Capability::toLegacyList( $this->capabilities() );
	}

	public function lookupCountry( string $ip, LookupContext $context ): LocationResult {
		return $this->lookup( $ip, $context );
	}

	/**
	 * Local providers never carry a request allowance.
	 */
	public function requestQuota(): ?array {
		return array( 'total' => -1, 'term' => '' );
	}

	/**
	 * Base databases-storage directory for this provider.
	 */
	protected function storageDir(): string {
		return Util::get_databases_storage_dir( $this->id() );
	}
}
