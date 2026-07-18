<?php
/**
 * Legacy class aliases.
 *
 * Maps each old procedural class name onto its 1:1 namespaced port so that
 * existing integrations, the frozen classic admin, the deployed mu-plugin
 * copies and third-party code keep resolving the historic names. Every alias
 * is guarded so a real (not-yet-ported) legacy class of the same name always
 * wins — this phase only aliases the seven "support leaf" classes.
 *
 * class_alias() autoloads its target (the namespaced class), so requiring this
 * file eagerly loads the ports and runs their top-level side effects (e.g. the
 * IP_LOCATION_BLOCK_* constants defined in Logs).
 *
 * @package IP_Location_Block
 * @since   1.4.0
 */

defined( 'WPINC' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || die;

$ip_location_block_aliases = array(
	// legacy name                => namespaced port
	'IP_Location_Block_Util'   => 'IPLocationBlock\\Support\\Util',
	'IP_Location_Block_FS'     => 'IPLocationBlock\\Support\\FileSystem',
	'IP_Location_Block_Lkup'   => 'IPLocationBlock\\Support\\Dns',
	'IP_Location_Block_Cron'   => 'IPLocationBlock\\Cron\\Scheduler',
	'IP_Location_Block_Opts'   => 'IPLocationBlock\\Settings\\Options',
	'IP_Location_Block_Logs'   => 'IPLocationBlock\\Logging\\Logs',
	'IP_Location_Block_Loader' => 'IPLocationBlock\\Core\\HookLoader',
);

foreach ( $ip_location_block_aliases as $ip_location_block_legacy => $ip_location_block_target ) {
	if ( ! class_exists( $ip_location_block_legacy, false ) ) {
		class_alias( $ip_location_block_target, $ip_location_block_legacy );
	}
}

unset( $ip_location_block_aliases, $ip_location_block_legacy, $ip_location_block_target );
