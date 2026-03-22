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

	/** @var HMO_Alert_Service */
	private $alerts;

	public function __construct(
		HMO_Access_Service $access,
		HMO_Dashboard_Service $dashboard,
		HMO_Checklist_Service $checklist,
		HMO_Countdown_Service $countdown,
		HMO_Hostlinks_Bridge $bridge,
		HMO_Alert_Service $alerts
	) {
		$this->access    = $access;
		$this->dashboard = $dashboard;
		$this->checklist = $checklist;
		$this->countdown = $countdown;
		$this->bridge    = $bridge;
		$this->alerts    = $alerts;
	}

	public function register(): void {
		add_shortcode( 'hmo_dashboard_selector', array( $this, 'render_dashboard_selector' ) );
		add_shortcode( 'hmo_dashboard',          array( $this, 'render_dashboard' ) );
		add_shortcode( 'hmo_my_classes',         array( $this, 'render_my_classes' ) );
		add_shortcode( 'hmo_event_detail',       array( $this, 'render_event_detail' ) );
		add_shortcode( 'hmo_task_editor',        array( $this, 'render_task_editor' ) );
		add_shortcode( 'hmo_event_report',       array( $this, 'render_event_report' ) );
	}

	// -------------------------------------------------------------------------
	// [hmo_dashboard_selector] — routes to Dashboard or My Classes based on role
	// -------------------------------------------------------------------------

	public function render_dashboard_selector( $atts ): string {
		if ( ! $this->access->can_view_shortcode( 'hmo_dashboard_selector' ) ) {
			return $this->access->get_denial_message_html();
		}

		if ( HMO_Access_Service::current_user_is_marketing_admin() ) {
			return $this->render_dashboard( $atts );
		}

		return $this->render_my_classes( $atts );
	}

	// -------------------------------------------------------------------------
	// [hmo_dashboard] — manager view, all accessible events + quick filters
	// -------------------------------------------------------------------------

	public function render_dashboard( $atts ): string {
		if ( ! $this->access->can_view_shortcode( 'hmo_dashboard' ) ) {
			return $this->access->get_denial_message_html();
		}

		$view    = $this->get_view_param();
		$filters = array_merge( $this->get_dashboard_filter_params(), array( 'view' => $view ) );

		// Full unfiltered set for kanban (uses all upcoming rows, ignoring quick filters).
		$all_rows_unfiltered = $this->dashboard->get_dashboard_rows( array( 'view' => $view ) );
		$all_rows            = $this->dashboard->get_dashboard_rows( $filters );

		$cards       = $this->dashboard->get_summary_cards();
		$alert_data  = $this->alerts->get_alerts( $all_rows_unfiltered );
		$access      = $this->access;
		$detail_base = HMO_Page_URLs::get_event_detail();

		// Available buckets for bucket quick-filter dropdown (admin sees all).
		$buckets = $this->bridge->get_marketers();

		// All stages for stage filter dropdown.
		$stage_order = HMO_Checklist_Templates::get_stage_order();
		$stage_labels = array_column( HMO_Checklist_Templates::get_stages_option(), 'label', 'key' );

		list( $rows, $pagination ) = $this->paginate_rows( $all_rows );

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/dashboard.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hmo_my_classes] — execution view, filtered to user's buckets
	// -------------------------------------------------------------------------

	public function render_my_classes( $atts ): string {
		if ( ! $this->access->can_view_shortcode( 'hmo_my_classes' ) ) {
			return $this->access->get_denial_message_html();
		}

		$view = $this->get_view_param();

		// User's assigned buckets — for pill UI and access control.
		$is_admin       = $this->access->current_user_can_see_all_events();
		$user_buckets   = $is_admin ? array() : $this->access->get_current_user_buckets();
		$has_multi_buckets = count( $user_buckets ) > 1;

		// Read selected bucket pills from GET (default = all user buckets).
		$selected_buckets = array();
		if ( $has_multi_buckets && ! empty( $_GET['hmo_buckets'] ) && is_array( $_GET['hmo_buckets'] ) ) {
			$selected_buckets = array_map( 'intval', $_GET['hmo_buckets'] );
		} elseif ( $has_multi_buckets ) {
			$selected_buckets = array_column( $user_buckets, 'id' );
		}

		$filters = array( 'view' => $view );
		if ( $has_multi_buckets && ! empty( $selected_buckets ) ) {
			$filters['hmo_buckets'] = $selected_buckets;
		}

		// Carry trouble_only and next30 filters.
		if ( ! empty( $_GET['hmo_trouble_only'] ) ) {
			$filters['hmo_trouble_only'] = 1;
		}
		if ( ! empty( $_GET['hmo_next30'] ) ) {
			$filters['hmo_next30'] = 1;
		}

		$all_rows    = $this->dashboard->get_dashboard_rows( $filters );
		$cards       = $this->dashboard->get_summary_cards();
		$alert_data  = $this->alerts->get_alerts( $all_rows );
		$access      = $this->access;
		$detail_base = HMO_Page_URLs::get_event_detail();

		list( $rows, $pagination ) = $this->paginate_rows( $all_rows );

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/my-classes.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hmo_task_editor]
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
	// [hmo_event_detail]
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

		$event          = $this->bridge->get_event( $event_id );
		$ops            = HMO_DB::get_event_ops( $event_id );
		$checklist      = $this->checklist->get_event_checklist( $event_id );
		$countdown      = $this->countdown;
		$days_left      = $this->countdown->get_days_left( $event_id );
		$days_label     = $this->countdown->format_days_left( $days_left );
		$reg_count      = $this->bridge->get_event_registration_count( $event_id );
		$marketer_name  = $this->bridge->get_event_marketer_name( $event_id );
		$access         = $this->access;

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/event-detail.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// [hmo_event_report] — journey report for report viewers
	// -------------------------------------------------------------------------

	public function render_event_report( $atts ): string {
		if ( ! HMO_Access_Service::current_user_can_view_reports() ) {
			return $this->access->get_denial_message_html();
		}

		global $wpdb;

		$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;

		// Build event list for the selector dropdown.
		// Admins see all active events; non-admins see only their bucket events.
		$is_admin = $this->access->current_user_can_see_all_events();
		if ( $is_admin ) {
			$events = $wpdb->get_results(
				"SELECT eve_id AS id, eve_name AS name, eve_start AS event_date
				 FROM {$wpdb->prefix}event_details_list
				 WHERE eve_status = 1
				 ORDER BY eve_start DESC"
			);
		} else {
			$allowed = $this->access->get_allowed_event_ids();
			if ( empty( $allowed ) ) {
				$events = array();
			} else {
				$ph     = implode( ',', array_fill( 0, count( $allowed ), '%d' ) );
				$events = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT eve_id AS id, eve_name AS name, eve_start AS event_date
					 FROM {$wpdb->prefix}event_details_list
					 WHERE eve_status = 1 AND eve_id IN ($ph)
					 ORDER BY eve_start DESC",
					$allowed
				) );
			}
		}

		// If an event is selected, verify access and load report data.
		$report_event   = null;
		$report_stages  = array();
		$activity_log   = array();
		$user_cache     = array();

		if ( $event_id ) {
			if ( ! $is_admin && ! $this->access->can_view_event( $event_id ) ) {
				return $this->access->get_denial_message_html();
			}

			// Fetch event info.
			$report_event = $this->bridge->get_event( $event_id );

			// Load tasks grouped by stage.
			$tasks = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_event_tasks
				 WHERE hostlinks_event_id = %d
				 ORDER BY sort_order ASC",
				$event_id
			) );

			// Get all stage definitions in order.
			$stage_defs = HMO_Checklist_Templates::get_stages_option();
			foreach ( $stage_defs as $s ) {
				$report_stages[ $s['key'] ] = array(
					'label' => $s['label'],
					'tasks' => array(),
				);
			}

			// Collect user IDs for name lookup.
			$user_ids = array();
			foreach ( $tasks as $task ) {
				if ( (int) $task->completed_by_user_id > 0 ) {
					$user_ids[] = (int) $task->completed_by_user_id;
				}
				$sk = $task->stage_key;
				if ( ! isset( $report_stages[ $sk ] ) ) {
					$report_stages[ $sk ] = array( 'label' => ucwords( str_replace( '_', ' ', $sk ) ), 'tasks' => array() );
				}
				$report_stages[ $sk ]['tasks'][] = $task;
			}

			// Activity log.
			$activity_log = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_event_activity
				 WHERE hostlinks_event_id = %d
				 ORDER BY created_at ASC",
				$event_id
			) );

			foreach ( $activity_log as $entry ) {
				if ( (int) $entry->user_id > 0 ) {
					$user_ids[] = (int) $entry->user_id;
				}
			}

			// Bulk-load user display names.
			$user_ids = array_unique( array_filter( $user_ids ) );
			if ( ! empty( $user_ids ) ) {
				$user_objects = get_users( array( 'include' => $user_ids, 'fields' => array( 'ID', 'display_name' ) ) );
				foreach ( $user_objects as $u ) {
					$user_cache[ (int) $u->ID ] = $u->display_name;
				}
			}
		}

		ob_start();
		include HMO_PLUGIN_DIR . 'shortcode/views/event-report.php';
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_view_param(): string {
		$v = $_GET['hmo_view'] ?? 'upcoming';
		return $v === 'past' ? 'past' : 'upcoming';
	}

	private function get_dashboard_filter_params(): array {
		$out = array();
		$passthrough = array( 'hmo_stage', 'hmo_risk', 'hmo_bucket', 'hmo_trouble', 'hmo_missing', 'hmo_next30' );
		foreach ( $passthrough as $key ) {
			if ( ! empty( $_GET[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $_GET[ $key ] );
			}
		}
		return $out;
	}

	private function paginate_rows( array $all_rows ): array {
		$per_page    = 30;
		$total       = count( $all_rows );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = max( 1, min( $total_pages, (int) ( $_GET['hmo_page'] ?? 1 ) ) );
		$offset      = ( $page - 1 ) * $per_page;
		$rows        = array_slice( $all_rows, $offset, $per_page );

		// Base URL preserves hmo_view and any active filters but resets hmo_page.
		$base = remove_query_arg( 'hmo_page' );

		$pagination = array(
			'page'        => $page,
			'total_pages' => $total_pages,
			'total'       => $total,
			'per_page'    => $per_page,
			'from'        => $total ? $offset + 1 : 0,
			'to'          => min( $offset + $per_page, $total ),
			'prev_url'    => $page > 1            ? add_query_arg( 'hmo_page', $page - 1, $base ) : '',
			'next_url'    => $page < $total_pages ? add_query_arg( 'hmo_page', $page + 1, $base ) : '',
			'page_urls'   => array(),
		);

		$window = 2;
		for ( $p = 1; $p <= $total_pages; $p++ ) {
			if ( $p === 1 || $p === $total_pages || ( $p >= $page - $window && $p <= $page + $window ) ) {
				$pagination['page_urls'][ $p ] = add_query_arg( 'hmo_page', $p, $base );
			}
		}

		return array( $rows, $pagination );
	}
}
