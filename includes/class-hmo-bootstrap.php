<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Bootstrap {

	public function init() {
		if ( ! defined( 'HOSTLINKS_VERSION' ) ) {
			add_action( 'admin_notices', array( $this, 'notice_hostlinks_missing' ) );
			return;
		}

		// Core services.
		$bridge         = new HMO_Hostlinks_Bridge();
		$access         = new HMO_Access_Service();
		$checklist_tmpl = new HMO_Checklist_Templates();
		$checklist_svc  = new HMO_Checklist_Service( $checklist_tmpl );
		$countdown_svc  = new HMO_Countdown_Service( $bridge );
		$dashboard_svc  = new HMO_Dashboard_Service( $bridge, $access, $checklist_svc, $countdown_svc );

		// REST API.
		$rest = new HMO_REST( $checklist_svc, $dashboard_svc, $access );
		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );

		// AJAX (user search for settings page).
		HMO_Access_Service::register_ajax();

		// Front-end shortcodes.
		$shortcodes = new HMO_Shortcodes( $access, $dashboard_svc, $checklist_svc, $countdown_svc, $bridge );
		add_action( 'init', array( $shortcodes, 'register' ) );

		// Assets.
		$assets = new HMO_Assets();
		add_action( 'admin_enqueue_scripts', array( $assets, 'enqueue_admin' ) );
		add_action( 'wp_enqueue_scripts',    array( $assets, 'enqueue_frontend' ) );

		// Admin menu — Settings only.
		$admin_menu = new HMO_Admin_Menu();
		add_action( 'admin_menu', array( $admin_menu, 'register_menus' ), 20 );
	}

	public function notice_hostlinks_missing() {
		echo '<div class="notice notice-error"><p><strong>Hostlinks Marketing Ops</strong> requires the <strong>Hostlinks</strong> plugin to be installed and active.</p></div>';
	}
}
