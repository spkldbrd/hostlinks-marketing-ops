<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers Marketing Ops as a top-level WP admin menu with Dashboard,
 * Settings, and a hidden event-detail route under the same page slug.
 */
class HMO_Admin_Menu {

	/** @var HMO_Dashboard_Service */
	private $dashboard_svc;

	/** @var HMO_Hostlinks_Bridge */
	private $bridge;

	/** @var HMO_Checklist_Service */
	private $checklist;

	/** @var HMO_Countdown_Service */
	private $countdown;

	public function __construct(
		HMO_Dashboard_Service $dashboard_svc,
		HMO_Hostlinks_Bridge $bridge,
		HMO_Checklist_Service $checklist,
		HMO_Countdown_Service $countdown
	) {
		$this->dashboard_svc = $dashboard_svc;
		$this->bridge        = $bridge;
		$this->checklist     = $checklist;
		$this->countdown     = $countdown;
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public function register_menus(): void {
		// Top-level Marketing Ops menu — landing page is the Dashboard.
		add_menu_page(
			'Marketing Ops',
			'Marketing Ops',
			'manage_options',
			'hmo-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-clipboard',
			58
		);

		// Rename the auto-created first submenu item from "Marketing Ops" → "Dashboard".
		add_submenu_page(
			'hmo-dashboard',
			'Marketing Ops — Dashboard',
			'Dashboard',
			'manage_options',
			'hmo-dashboard',
			array( $this, 'render_dashboard' )
		);

		// Settings page.
		add_submenu_page(
			'hmo-dashboard',
			'Marketing Ops — Settings',
			'Settings',
			'manage_options',
			'hmo-settings',
			array( $this, 'render_settings' )
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/**
	 * Dashboard page — handles both the event list (default) and the event
	 * detail view (?view=event&event_id=N).
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'hmo' ) );
		}

		$view     = sanitize_key( $_GET['view']     ?? '' );
		$event_id = (int)           ( $_GET['event_id'] ?? 0 );

		if ( $view === 'event' && $event_id > 0 ) {
			$this->render_event_detail( $event_id );
			return;
		}

		// Dashboard list view.
		$rows  = $this->dashboard_svc->get_dashboard_rows();
		$cards = $this->dashboard_svc->get_summary_cards();
		include HMO_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Loads event data and renders the admin event detail view.
	 */
	private function render_event_detail( int $event_id ): void {
		$event = $this->bridge->get_event( $event_id );

		if ( ! $event ) {
			wp_die( 'Event not found.' );
		}

		$ops       = HMO_DB::get_event_ops( $event_id );
		$checklist = $this->checklist->get_event_checklist( $event_id );
		$countdown = $this->countdown;
		$days_left = $this->countdown->get_days_left( $event_id );
		$reg_count = $this->bridge->get_event_registration_count( $event_id );

		include HMO_PLUGIN_DIR . 'admin/views/event-detail.php';
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage Marketing Ops settings.', 'hmo' ) );
		}
		include HMO_PLUGIN_DIR . 'admin/views/settings.php';
	}

	// -------------------------------------------------------------------------
	// Asset helpers
	// -------------------------------------------------------------------------

	public function enqueue_media_on_settings( string $hook ): void {
		if ( strpos( $hook, 'hmo-settings' ) !== false ) {
			wp_enqueue_media();
		}
	}
}
