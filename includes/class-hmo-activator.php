<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Activator {

	public static function activate() {
		if ( ! defined( 'HOSTLINKS_VERSION' ) ) {
			deactivate_plugins( plugin_basename( HMO_PLUGIN_FILE ) );
			wp_die(
				'Hostlinks Marketing Ops requires the Hostlinks plugin to be installed and active.',
				'Activation Error',
				array( 'back_link' => true )
			);
		}

		HMO_DB::create_tables();

		$templates = new HMO_Checklist_Templates();
		$templates->seed_templates();
		$templates->seed_stages();

		update_option( 'hmo_db_version', HMO_DB_VERSION );
		update_option( 'hmo_version', HMO_VERSION );
	}

	public static function deactivate() {
		// intentionally light — tables and data are preserved on deactivation
	}
}
