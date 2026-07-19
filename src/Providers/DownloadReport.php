<?php
/**
 * Database download report.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

declare(strict_types=1);

namespace IPLocationBlock\Providers;

/**
 * Immutable result of a local provider database download.
 *
 * Carries the legacy per-file result arrays (consumed by the classic admin
 * display) and the settings fragment that must be persisted back into
 * settings[<provider>], so cron rescheduling and the saved option don't end
 * up with stale *_path / *_last values.
 */
final class DownloadReport {

	/**
	 * @param array<string,mixed> $files            Legacy per-file result arrays.
	 * @param array<string,mixed> $settingsFragment Write-back for settings[<provider>].
	 */
	public function __construct(
		private readonly array $files,
		private readonly array $settingsFragment = array(),
	) {}

	/**
	 * Legacy download() return shape (e.g. `[ 'ipv4' => [...], 'ipv6' => [...] ]`
	 * or `[ 'ip' => [...], 'asn' => [...] ]`).
	 *
	 * @return array<string,mixed>
	 */
	public function files(): array {
		return $this->files;
	}

	/**
	 * The subset to merge into settings[<provider>] after downloading.
	 *
	 * @return array<string,mixed>
	 */
	public function settingsFragment(): array {
		return $this->settingsFragment;
	}
}
