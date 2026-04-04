<?php
/**
 * Plugin Name: Hostlinks Marketing Ops
 * Plugin URI:  https://digitalsolution.com
 * Description: Companion plugin for Hostlinks that adds marketer dashboards, per-event checklist workflow, countdowns, and marketer-only access to assigned events.
 * Version:     1.7.2
 * Author:      Digital Solution
 * Author URI:  https://digitalsolution.com
 * License:     GPL2
 * Requires Plugins: hostlinks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HMO_VERSION',    '1.7.2' );
define( 'HMO_DB_VERSION', '1.3.4' );
define( 'HMO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HMO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HMO_PLUGIN_FILE', __FILE__ );

require_once HMO_PLUGIN_DIR . 'includes/class-hmo-db.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-hostlinks-bridge.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-page-urls.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-checklist-templates.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-checklist-service.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-countdown-service.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-access-service.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-alert-service.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-dashboard-service.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-shortcodes.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-rest.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-assets.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-updater.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-activator.php';
require_once HMO_PLUGIN_DIR . 'includes/class-hmo-bootstrap.php';
require_once HMO_PLUGIN_DIR . 'admin/class-hmo-admin-menu.php';

HMO_Updater::init( __FILE__, 'spkldbrd', 'hostlinks-marketing-ops' );

register_activation_hook( __FILE__, array( 'HMO_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HMO_Activator', 'deactivate' ) );

add_action( 'plugins_loaded', function() {
	HMO_DB::maybe_upgrade();
	HMO_DB::register_migration_hooks();
	add_action( 'admin_notices', array( 'HMO_DB', 'render_migration_result_notice' ) );
	$bootstrap = new HMO_Bootstrap();
	$bootstrap->init();
}, 20 );
