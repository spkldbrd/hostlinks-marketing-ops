<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all plugin tables, options, template options, and HMO transients.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-hmo-uninstall.php';
HMO_Uninstall::run();
