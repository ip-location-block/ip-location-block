<?php
/**
 * Legacy local-provider adapter.
 *
 * Extends the remote adapter with the download() / add_settings_field() /
 * get_attribution() surface the frozen classic admin and the cron scheduler
 * discover via method_exists(). Only LOCAL providers get this adapter, so
 * remote adapters correctly fail those method_exists() checks.
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

namespace IPLocationBlock\Compat;

use IPLocationBlock\Providers\DownloadableProviderInterface;

class LegacyLocalProviderAdapter extends LegacyProviderAdapter {

	/**
	 * Settings write-back from the most recent download() (the cron bug fix).
	 *
	 * @var array
	 */
	protected $lastFragment = array();

	/**
	 * Legacy download(): returns the per-file result arrays. Also stashes the
	 * settings fragment so the scheduler can persist the fresh *_path / *_last.
	 *
	 * @param mixed $args
	 *
	 * @return array
	 */
	public function download( $args ) {
		if ( ! $this->provider instanceof DownloadableProviderInterface ) {
			return array();
		}

		$report             = $this->provider->download( is_array( $args ) ? $args : array(), $this->options );
		$this->lastFragment = $report->settingsFragment();

		return $report->files();
	}

	/**
	 * The settings[<provider>] fragment to merge after the last download().
	 *
	 * @return array
	 */
	public function settings_fragment() {
		return $this->lastFragment;
	}

	/**
	 * Delegate to the provider's verbatim settings-field body.
	 *
	 * @param mixed $callback
	 */
	public function add_settings_field( $field, $section, $option_slug, $option_name, $options, $callback, $str_path, $str_last ) {
		if ( method_exists( $this->provider, 'addSettingsField' ) ) {
			$this->provider->addSettingsField(
				(string) $field,
				(string) $section,
				(string) $option_slug,
				(string) $option_name,
				is_array( $options ) ? $options : array(),
				$callback,
				(string) $str_path,
				(string) $str_last
			);
		}
	}

	/**
	 * Attribution HTML.
	 *
	 * @return string
	 */
	public function get_attribution() {
		return method_exists( $this->provider, 'getAttribution' ) ? $this->provider->getAttribution() : '';
	}
}
