<?php
/**
 * @deprecated 1.4.0 Ported to IPLocationBlock\Settings\Options.
 *
 * This file is a tombstone. The class now lives in src/ (PSR-4) and its legacy
 * name is restored via class_alias in compat/legacy-aliases.php. The require
 * below boots that backward-compatibility layer for any code path that still
 * includes this path directly (frozen classic admin, deployed mu-plugin copies,
 * uninstall.php, third-party integrations).
 *
 * @package IP_Location_Block
 */

defined( 'WPINC' ) || defined( 'WP_UNINSTALL_PLUGIN' ) || die;

require_once dirname( __DIR__ ) . '/compat/bootstrap.php';
