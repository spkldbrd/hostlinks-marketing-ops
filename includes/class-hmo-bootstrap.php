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
		$alert_svc      = new HMO_Alert_Service();

		// REST API.
		$rest = new HMO_REST( $checklist_svc, $dashboard_svc, $access );
		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );

		// AJAX (user/bucket access + task editor CRUD + bulk provision + goal reset).
		HMO_Access_Service::register_ajax();
		HMO_Checklist_Templates::register_ajax();
		HMO_Checklist_Service::register_ajax();
		HMO_Dashboard_Service::register_ajax();
		HMO_Page_Sync::register_ajax();

		// Front-end shortcodes.
		$shortcodes = new HMO_Shortcodes( $access, $dashboard_svc, $checklist_svc, $countdown_svc, $bridge, $alert_svc );
		add_action( 'init', array( $shortcodes, 'register' ) );

		// Assets.
		$assets = new HMO_Assets();
		add_action( 'admin_enqueue_scripts', array( $assets, 'enqueue_admin' ) );
		add_action( 'wp_enqueue_scripts',    array( $assets, 'enqueue_frontend' ) );

		// Admin menu — Settings only.
		$admin_menu = new HMO_Admin_Menu();
		add_action( 'admin_menu',            array( $admin_menu, 'register_menus' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $admin_menu, 'enqueue_media_on_settings' ) );

		// Auto-provision tasks when Hostlinks creates a new event.
		add_action( 'hostlinks_event_created', array( $checklist_svc, 'on_event_created' ), 10, 2 );

		// Create GWU marketing page when Hostlinks creates a new event (after checklist).
		$page_sync = new HMO_Page_Sync();
		add_action( 'hostlinks_event_created', array( $page_sync, 'on_event_created' ), 20, 2 );
	}

	public function notice_hostlinks_missing() {
		echo '<div class="notice notice-error"><p><strong>Hostlinks Marketing Ops</strong> requires the <strong>Hostlinks</strong> plugin to be installed and active.</p></div>';
	}
}
