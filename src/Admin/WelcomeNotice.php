<?php
/**
 * Welcome notice campaign state and screen eligibility.
 *
 * @package IP_Location_Block
 */

namespace IPLocationBlock\Admin;

use IPLocationBlock\Core\Validator;

/**
 * Keep the welcome notice dismissal site-wide and independent from exported
 * blocking settings. Changing CAMPAIGN is the deliberate way to show a new
 * onboarding notice after an earlier campaign was dismissed.
 */
final class WelcomeNotice {

	const CAMPAIGN        = 'welcome-1.4-native-accuracy';
	const LEGACY_CAMPAIGN = 'welcome-legacy-dismissal';
	const OPTION          = 'ip_location_block_welcome_notice';

	/**
	 * Whether this request is on a WordPress core screen or this plugin's screen.
	 * Plugin-created screens normally contain `_page_` in their hook suffix or a
	 * `page` query argument. Our own screen is the explicit exception.
	 *
	 * @param string      $hook_suffix Current admin hook suffix.
	 * @param string|null $page        Sanitized page query value, or null to read it.
	 * @return bool
	 */
	public static function is_eligible_screen( $hook_suffix, $page = null ) {
		if ( null === $page ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		}

		if ( 'ip-location-block' === $page ) {
			return true;
		}

		if ( '' !== $page ) {
			return false;
		}

		return false === strpos( (string) $hook_suffix, '_page_' );
	}

	/**
	 * Whether the current campaign has been dismissed on this site.
	 *
	 * The old boolean setting is migrated lazily as a previous campaign. This
	 * allows a materially different campaign to appear once without losing the
	 * administrator's earlier dismissal history.
	 *
	 * @return bool
	 */
	public static function is_dismissed() {
		$state = get_option( self::OPTION, null );

		if ( is_array( $state ) && isset( $state['campaign'] ) ) {
			return self::CAMPAIGN === (string) $state['campaign'];
		}

		$settings = Validator::get_option();
		if ( ! empty( $settings['welcome'] ) ) {
			self::store_campaign( self::LEGACY_CAMPAIGN );
			return false;
		}

		return false;
	}

	/**
	 * Dismiss the current campaign for every administrator on this site.
	 *
	 * @return bool
	 */
	public static function dismiss() {
		self::store_campaign();

		// Keep the legacy flag synchronized so downgrading does not resurrect the
		// old welcome notice. The campaign option remains the source of truth.
		$settings = Validator::get_option();
		if ( empty( $settings['welcome'] ) ) {
			$settings['welcome'] = true;
			Validator::update_option( $settings );
		}

		return true;
	}

	/**
	 * Persist the current campaign without autoloading this admin-only state.
	 *
	 * @return void
	 */
	private static function store_campaign( $campaign = self::CAMPAIGN ) {
		update_option(
			self::OPTION,
			array(
				'campaign'     => (string) $campaign,
				'dismissed_at' => time(),
			),
			false
		);
	}
}
