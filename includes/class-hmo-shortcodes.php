<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Shortcodes {

	/** @var HMO_Access_Service */
	private $access;

	/** @var HMO_Dashboard_Service */
	private $dashboard;

	/** @var HMO_Checklist_Service */
	private $checklist;

	/** @var HMO_Countdown_Service */
	private $countdown;

	/** @var HMO_Hostlinks_Bridge */
	private $bridge;

	public function __construct(
		HMO_Access_Service $access,
		HMO_Dashboard_Service $dashboard,
		HMO_Checklist_Service $checklist,
		HMO_Countdown_Service $countdown,
		HMO_Hostlinks_Bridge $bridge
	) {
		$this->access    = $access;
		$this->dashboard = $dashboard;
		$this->checklist = $checklist;
		$this->countdown = $countdown;
		$this->bridge    = $bridge;
	}

	public function register(): void {
		add_shortcode( 'hmo_dashboard',    array( $this, 'render_dashboard' ) );
		add_shortcode( 'hmo_my_classes',   array( $this, 'render_my_classes' ) );
		add_shortcode( 'hmo_event_detail', array( $this, 'render_event_detail' ) );
		add_shortcode( 'hmo_task_editor',  array( $this, 'render_task_editor' ) );
	}

	// -------------------------------------------------------------------------
	// [hmo_dashboard] — all events (admins) or marketer-filtered (others)
	// -------------------------------------------------------------------------

	public function render_dashboard( $atts ): string {
		if ( ! $this->access->can_view_shortcode( 'hmo_dashboard' ) ) {
			return $this->access->get_denial_message_html();
		}

		$rows  = $this->dashboard->get_dashboard_rows();
		$cards = $this->dashboard->get_summary_cards();
		$access = $this->access;

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/dashboard.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hmo_my_classes] — always filtered to the current marketer's events
	// -------------------------------------------------------------------------

	public function render_my_classes( $atts ): string {
		if ( ! $this->access->can_view_shortcode( 'hmo_my_classes' ) ) {
			return $this->access->get_denial_message_html();
		}

		$marketer_id = $this->access->get_current_user_marketer_id();
		$filters     = $marketer_id ? array( 'marketer_id' => $marketer_id ) : array();
		$rows        = $this->dashboard->get_dashboard_rows( $filters );
		$cards       = $this->dashboard->get_summary_cards();
		$access      = $this->access;

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/dashboard.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hmo_task_editor] — manage task templates (for Task Editor users + admins)
	// -------------------------------------------------------------------------

	public function render_task_editor( $atts ): string {
		if ( ! HMO_Access_Service::current_user_can_edit_tasks() ) {
			return $this->access->get_denial_message_html();
		}

		$templates = new HMO_Checklist_Templates();
		$stages    = $templates->get_all_stages();
		$stage_tasks = array();
		foreach ( $stages as $stage ) {
			$tasks = $templates->get_tasks_for_stage( $stage['stage_key'] );
			foreach ( $tasks as $task ) {
				$task->subtasks = $templates->get_subtasks( (int) $task->id );
			}
			$stage_tasks[ $stage['stage_key'] ] = $tasks;
		}

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/task-editor.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hmo_event_detail] — single event, event_id from URL ?event_id=X
	// -------------------------------------------------------------------------

	public function render_event_detail( $atts ): string {
		if ( ! $this->access->can_view_shortcode( 'hmo_event_detail' ) ) {
			return $this->access->get_denial_message_html();
		}

		$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;

		if ( ! $event_id ) {
			return '<div class="hmo-notice">No event specified.</div>';
		}

		if ( ! $this->access->can_view_event( $event_id ) ) {
			return $this->access->get_denial_message_html();
		}

		$event     = $this->bridge->get_event( $event_id );
		$ops       = HMO_DB::get_event_ops( $event_id );
		$checklist = $this->checklist->get_event_checklist( $event_id );
		$countdown = $this->countdown;
		$days_left = $this->countdown->get_days_left( $event_id );
		$days_label = $this->countdown->format_days_left( $days_left );
		$reg_count = $this->bridge->get_event_registration_count( $event_id );
		$access    = $this->access;

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/event-detail.php';
		return ob_get_clean();
	}
}
