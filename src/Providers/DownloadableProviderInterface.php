<?php
/**
 * Downloadable (local database) provider contract.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * Implemented by local providers that maintain a downloadable database.
 */
interface DownloadableProviderInterface {

	/**
	 * Download / refresh the local database(s).
	 *
	 * @param array<string,mixed> $args    wp_remote_get()-style args (timeout, ...).
	 * @param array<string,mixed> $options Full settings snapshot (for existing
	 *                                     paths and the provider credential).
	 */
	public function download( array $args, array $options ): DownloadReport;

	/**
	 * Whether a local database is currently present and answerable.
	 *
	 * @param array<string,mixed> $settings
	 */
	public function databaseReady( array $settings ): bool;
}
