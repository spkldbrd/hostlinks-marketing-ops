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

	/**
	 * Opens the public-events endpoint to unauthenticated requests.
	 *
	 * Two complementary hooks are used so the route stays reachable regardless
	 * of which security/login plugin is restricting the REST API:
	 *
	 *  1. v_forcelogin_bypass — Force Login plugin's own whitelist filter.
	 *  2. rest_authentication_errors @ priority 99 — runs after every other
	 *     plugin and explicitly clears any 401 error for this route only,
	 *     leaving all other routes untouched.
	 */
	public static function register_force_login_bypass(): void {
		// Force Login whitelist filter.
		add_filter( 'v_forcelogin_bypass', function ( $bypass, $url ) {
			if ( strpos( $url, '/wp-json/hmo/v1/public-events' ) !== false ) {
				return true;
			}
			return $bypass;
		}, 10, 2 );

		// Catch-all: override any authentication error injected by any plugin
		// for the public-events route.  Priority 99 ensures this runs last.
		add_filter( 'rest_authentication_errors', function ( $result ) {
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
			$route = isset( $GLOBALS['wp']->query_vars['rest_route'] )
				? (string) $GLOBALS['wp']->query_vars['rest_route']
				: '';
			if ( strpos( $route, '/hmo/v1/public-events' ) === 0 ) {
				return null; // Clear the error; WP will use the route's own permission_callback.
			}
			return $result;
		}, 99 );
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

		// Save event-level note.
		register_rest_route( $ns, '/events/(?P<id>\d+)/event-note', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'save_event_note' ),
			'permission_callback' => array( $this, 'require_event_access' ),
			'args'                => array(
				'note' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_textarea_field',
				),
			),
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

		// Public event list — no authentication required.
		register_rest_route( $ns, '/public-events', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_public_events' ),
			'permission_callback' => '__return_true',
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

	public function save_event_note( WP_REST_Request $request ): WP_REST_Response {
		$event_id = (int) $request->get_param( 'id' );
		$note     = (string) $request->get_param( 'note' );
		$success  = HMO_DB::upsert_event_ops( $event_id, array( 'event_note' => $note ) );

		return new WP_REST_Response( array( 'success' => $success ), $success ? 200 : 500 );
	}

	/**
	 * GET /hmo/v1/public-events
	 *
	 * Returns upcoming public events as JSON for consumption by the
	 * gwu-event-pages plugin on grantwritingusa.com.  No authentication required.
	 * Mirrors the query in Hostlinks' public-event-list.php shortcode and adds
	 * a `column` field ('left'|'right'|'') computed from the same saved options.
	 */
	public function get_public_events( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$today = current_time( 'Y-m-d' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, t.event_type_name
				 FROM {$wpdb->prefix}event_details_list e
				 LEFT JOIN {$wpdb->prefix}event_type t ON t.event_type_id = e.eve_type
				 WHERE e.eve_status = 1
				   AND e.eve_public_hide = 0
				   AND e.eve_start >= %s
				   AND e.eve_location NOT LIKE %s
				   AND e.eve_location NOT LIKE %s
				   AND e.eve_location NOT LIKE %s
				 ORDER BY e.eve_start ASC",
				$today,
				'%|PRIVATE%',
				'%| PRIVATE%',
				'%|private%'
			),
			ARRAY_A
		);

		// Column assignment uses the same Hostlinks options as the shortcode.
		$left_types  = array_map( 'intval', (array) get_option( 'hostlinks_pel_left_types',  array() ) );
		$right_types = array_map( 'intval', (array) get_option( 'hostlinks_pel_right_types', array() ) );

		// Column heading / description options forwarded so the remote shortcode
		// can render identically without needing its own configuration.
		$meta = array(
			'left_heading'      => get_option( 'hostlinks_pel_left_heading',      'Grant Writing Workshops' ),
			'left_heading_tag'  => get_option( 'hostlinks_pel_left_heading_tag',  'h2' ),
			'left_desc'         => get_option( 'hostlinks_pel_left_desc',         '' ),
			'left_desc_tag'     => get_option( 'hostlinks_pel_left_desc_tag',     'p' ),
			'right_heading'     => get_option( 'hostlinks_pel_right_heading',     'Grant Management Workshops' ),
			'right_heading_tag' => get_option( 'hostlinks_pel_right_heading_tag', 'h2' ),
			'right_desc'        => get_option( 'hostlinks_pel_right_desc',        '' ),
			'right_desc_tag'    => get_option( 'hostlinks_pel_right_desc_tag',    'p' ),
			'zoom_east'         => get_option( 'hostlinks_pel_zoom_time_east',    '9:30-4:30 EST' ),
			'zoom_west'         => get_option( 'hostlinks_pel_zoom_time_west',    '8:00-3:00 PST' ),
			'zoom_default'      => get_option( 'hostlinks_pel_zoom_time_default', '9:30-4:30 EST' ),
		);

		$events = array();
		foreach ( (array) $rows as $ev ) {
			$type_id = (int) $ev['eve_type'];
			if ( in_array( $type_id, $left_types, true ) ) {
				$column = 'left';
			} elseif ( in_array( $type_id, $right_types, true ) ) {
				$column = 'right';
			} else {
				$column = '';
			}

			$events[] = array(
				'id'          => (int) $ev['eve_id'],
				'location'    => $ev['eve_location'],
				'city'        => $ev['city'],
				'state'       => $ev['state'],
				'start'       => $ev['eve_start'],
				'end'         => $ev['eve_end'],
				'type_id'     => $type_id,
				'type_name'   => $ev['event_type_name'],
				'zoom'        => $ev['eve_zoom'],
				'zoom_time'   => $ev['eve_zoom_time'],
				'cvent_title' => $ev['cvent_event_title'],
				'web_url'     => $ev['eve_web_url'],
				'reg_url'     => $ev['eve_trainer_url'],
				'column'      => $column,
			);
		}

		return new WP_REST_Response( array( 'events' => $events, 'meta' => $meta ), 200 );
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
