<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_REST {

	const NAMESPACE = 'hmo/v1';

	/** @var HMO_Checklist_Service */
	private $checklist;

	/** @var HMO_Dashboard_Service */
	private $dashboard;

	/** @var HMO_Access_Service */
	private $access;

	public function __construct(
		HMO_Checklist_Service $checklist,
		HMO_Dashboard_Service $dashboard,
		HMO_Access_Service $access
	) {
		$this->checklist = $checklist;
		$this->dashboard = $dashboard;
		$this->access    = $access;
	}

	public function register_routes(): void {
		$ns = self::NAMESPACE;

		// Dashboard data.
		register_rest_route( $ns, '/dashboard', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_dashboard' ),
			'permission_callback' => array( $this, 'require_logged_in' ),
		) );

		// Event checklist.
		register_rest_route( $ns, '/events/(?P<id>\d+)/checklist', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_checklist' ),
			'permission_callback' => array( $this, 'require_event_access' ),
		) );

		// Stage update.
		register_rest_route( $ns, '/events/(?P<id>\d+)/stage', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_stage' ),
			'permission_callback' => array( $this, 'require_event_access' ),
			'args'                => array(
				'stage' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		// List metadata update.
		register_rest_route( $ns, '/events/(?P<id>\d+)/lists', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_lists' ),
			'permission_callback' => array( $this, 'require_event_access' ),
		) );

		// Registration goal update (managers only, future events).
		register_rest_route( $ns, '/events/(?P<id>\d+)/goal', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_goal' ),
			'permission_callback' => array( $this, 'require_manager' ),
			'args'                => array(
				'registration_goal' => array(
					'required'          => true,
					'validate_callback' => fn( $v ) => is_numeric( $v ) && (int) $v >= 1,
					'sanitize_callback' => 'absint',
				),
			),
		) );

		// Bulk-complete all tasks in specified stages for a single event (used by Kanban DnD).
		register_rest_route( $ns, '/events/(?P<id>\d+)/complete-stages', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'complete_stages' ),
			'permission_callback' => array( $this, 'require_event_access' ),
			'args'                => array(
				'stage_keys' => array(
					'required'          => true,
					'validate_callback' => fn( $v ) => is_array( $v ) && ! empty( $v ),
				),
			),
		) );

		// Mark task complete.
		register_rest_route( $ns, '/tasks/(?P<id>\d+)/complete', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'task_complete' ),
			'permission_callback' => array( $this, 'require_task_access' ),
			'args'                => array(
				'note' => array( 'sanitize_callback' => 'sanitize_textarea_field' ),
			),
		) );

		// Mark task incomplete.
		register_rest_route( $ns, '/tasks/(?P<id>\d+)/incomplete', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'task_incomplete' ),
			'permission_callback' => array( $this, 'require_task_access' ),
		) );

		// Save task note.
		register_rest_route( $ns, '/tasks/(?P<id>\d+)/note', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'save_note' ),
			'permission_callback' => array( $this, 'require_task_access' ),
			'args'                => array(
				'note' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_textarea_field',
				),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	public function get_dashboard( WP_REST_Request $request ): WP_REST_Response {
		$rows  = $this->dashboard->get_dashboard_rows();
		$cards = $this->dashboard->get_summary_cards();
		return new WP_REST_Response( array( 'rows' => $rows, 'cards' => $cards ), 200 );
	}

	public function get_checklist( WP_REST_Request $request ): WP_REST_Response {
		$event_id = (int) $request->get_param( 'id' );
		$data     = $this->checklist->get_event_checklist( $event_id );
		return new WP_REST_Response( $data, 200 );
	}

	public function update_stage( WP_REST_Request $request ): WP_REST_Response {
		$event_id = (int) $request->get_param( 'id' );
		$stage    = $request->get_param( 'stage' );
		$success  = $this->checklist->update_stage( $event_id, $stage );

		if ( ! $success ) {
			return new WP_REST_Response( array( 'message' => 'Invalid stage key.' ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function update_goal( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$event_id = (int) $request->get_param( 'id' );
		$goal     = (int) $request->get_param( 'registration_goal' );

		// Refuse to change goals for past events.
		$eve_start = $wpdb->get_var( $wpdb->prepare(
			"SELECT eve_start FROM {$wpdb->prefix}event_details_list WHERE eve_id = %d",
			$event_id
		) );

		if ( $eve_start && strtotime( $eve_start ) < strtotime( current_time( 'Y-m-d' ) ) ) {
			return new WP_REST_Response( array( 'message' => 'Cannot change goal for a past event.' ), 403 );
		}

		HMO_DB::upsert_event_ops( $event_id, array( 'registration_goal' => $goal ) );
		HMO_DB::log_activity( $event_id, 'goal_update', sprintf( 'Registration goal set to %d.', $goal ) );
		HMO_Dashboard_Service::flush_row_cache();

		return new WP_REST_Response( array( 'success' => true, 'registration_goal' => $goal ), 200 );
	}

	public function update_lists( WP_REST_Request $request ): WP_REST_Response {
		$event_id = (int) $request->get_param( 'id' );
		$data     = $request->get_json_params();
		$success  = $this->dashboard->update_list_metadata( $event_id, $data );

		return new WP_REST_Response( array( 'success' => $success ), $success ? 200 : 400 );
	}

	public function complete_stages( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$event_id   = (int) $request->get_param( 'id' );
		$raw_stages = (array) $request->get_param( 'stage_keys' );
		$stage_keys = array_values( array_map( 'sanitize_key', $raw_stages ) );

		if ( empty( $stage_keys ) ) {
			return new WP_REST_Response( array( 'message' => 'No stage keys provided.' ), 400 );
		}

		$valid_stages = HMO_Checklist_Templates::get_stage_order();
		$stage_keys   = array_values( array_filter( $stage_keys, fn( $s ) => in_array( $s, $valid_stages, true ) ) );

		if ( empty( $stage_keys ) ) {
			return new WP_REST_Response( array( 'message' => 'No valid stage keys.' ), 400 );
		}

		$user_id     = get_current_user_id();
		$now         = current_time( 'mysql' );
		$placeholders = implode( ', ', array_fill( 0, count( $stage_keys ), '%s' ) );

		$updated = $wpdb->query(
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"UPDATE {$wpdb->prefix}hmo_event_tasks
				 SET status = 'complete',
				     completed_at = %s,
				     completed_by_user_id = %d,
				     updated_at = %s
				 WHERE hostlinks_event_id = %d
				   AND stage_key IN ($placeholders)
				   AND status = 'pending'",
				array_merge( array( $now, $user_id, $now, $event_id ), $stage_keys )
			)
		);

		if ( $updated > 0 ) {
			$this->checklist->recalculate_open_task_count( $event_id );
			$stage_label = implode( ' + ', array_map( fn( $s ) => ucwords( str_replace( '_', ' ', $s ) ), $stage_keys ) );
			HMO_DB::log_activity( $event_id, 'stage_bulk_complete', sprintf( 'Kanban: bulk-completed tasks in: %s', $stage_label ) );
			HMO_Dashboard_Service::flush_row_cache();
		}

		return new WP_REST_Response( array( 'success' => true, 'tasks_completed' => (int) $updated ), 200 );
	}

	public function task_complete( WP_REST_Request $request ): WP_REST_Response {
		$task_id = (int) $request->get_param( 'id' );
		$note    = (string) $request->get_param( 'note' );
		$success = $this->checklist->mark_task_complete( $task_id, get_current_user_id(), $note );

		return new WP_REST_Response( array( 'success' => $success ), $success ? 200 : 400 );
	}

	public function task_incomplete( WP_REST_Request $request ): WP_REST_Response {
		$task_id = (int) $request->get_param( 'id' );
		$success = $this->checklist->mark_task_incomplete( $task_id );

		return new WP_REST_Response( array( 'success' => $success ), $success ? 200 : 400 );
	}

	public function save_note( WP_REST_Request $request ): WP_REST_Response {
		$task_id = (int) $request->get_param( 'id' );
		$note    = (string) $request->get_param( 'note' );
		$success = $this->checklist->save_task_note( $task_id, $note );

		return new WP_REST_Response( array( 'success' => $success ), $success ? 200 : 400 );
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	public function require_logged_in( WP_REST_Request $request ): bool {
		return is_user_logged_in();
	}

	public function require_manager( WP_REST_Request $request ): bool {
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	public function require_event_access( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$event_id = (int) $request->get_param( 'id' );
		return $this->access->can_view_event( $event_id );
	}

	public function require_task_access( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$task_id = (int) $request->get_param( 'id' );
		$task    = $this->checklist->get_task( $task_id );

		if ( ! $task ) {
			return false;
		}

		return $this->access->can_view_event( (int) $task->hostlinks_event_id );
	}
}
