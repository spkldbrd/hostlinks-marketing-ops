<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Checklist_Service {

	/** @var HMO_Checklist_Templates */
	private $templates;

	public function __construct( HMO_Checklist_Templates $templates ) {
		$this->templates = $templates;
	}

	// -------------------------------------------------------------------------
	// Task provisioning
	// -------------------------------------------------------------------------

	/**
	 * Creates task rows for an event from the active templates if they do not
	 * exist yet. Safe to call multiple times (idempotent).
	 *
	 * @param int $event_id  hostlinks_event_id
	 */
	public function ensure_event_tasks_exist( int $event_id ): void {
		global $wpdb;

		$existing_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}hmo_event_tasks WHERE hostlinks_event_id = %d",
				$event_id
			)
		);

		if ( $existing_count > 0 ) {
			return;
		}

		$all_tasks = $this->templates->get_all_active_tasks();

		foreach ( $all_tasks as $tmpl ) {
			$wpdb->insert(
				$wpdb->prefix . 'hmo_event_tasks',
				array(
					'hostlinks_event_id' => $event_id,
					'stage_key'          => $tmpl->stage_key,
					'task_key'           => $tmpl->task_key,
					'task_label'         => $tmpl->task_label,
					'task_description'   => $tmpl->task_description,
					'status'             => 'pending',
					'sort_order'         => (int) $tmpl->sort_order,
					'created_at'         => current_time( 'mysql' ),
					'updated_at'         => current_time( 'mysql' ),
				)
			);
		}

		// Ensure event_ops row exists; seed goal from the current setting so it
		// is never hard-coded. A stored value of 0 means "not yet set."
		HMO_DB::upsert_event_ops( $event_id, array(
			'registration_goal' => max( 1, (int) get_option( 'hmo_default_goal', 25 ) ),
		) );
		$this->recalculate_open_task_count( $event_id );
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	/**
	 * Returns tasks grouped by stage, in canonical stage order.
	 *
	 * @param int $event_id
	 * @return array  Keys are stage_key; values are arrays with 'stage_label' and 'tasks'.
	 */
	public function get_event_checklist( int $event_id ): array {
		global $wpdb;

		$this->ensure_event_tasks_exist( $event_id );

		$tasks = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_event_tasks
				 WHERE hostlinks_event_id = %d
				 ORDER BY sort_order ASC",
				$event_id
			)
		);

		$stage_labels = array();
		foreach ( $this->templates->get_all_stages() as $s ) {
			$stage_labels[ $s['stage_key'] ] = $s['stage_label'];
		}

		$grouped = array();
		foreach ( HMO_Checklist_Templates::get_stage_order() as $stage_key ) {
			$grouped[ $stage_key ] = array(
				'stage_key'   => $stage_key,
				'stage_label' => $stage_labels[ $stage_key ] ?? $stage_key,
				'tasks'       => array(),
			);
		}

		foreach ( $tasks as $task ) {
			if ( isset( $grouped[ $task->stage_key ] ) ) {
				$grouped[ $task->stage_key ]['tasks'][] = $task;
			}
		}

		// Attach template subtasks to each event task so the view can render them.
		$task_keys         = wp_list_pluck( $tasks, 'task_key' );
		$template_subtasks = $this->templates->get_subtasks_by_task_keys( $task_keys );

		foreach ( $grouped as $stage_key => &$stage_data ) {
			foreach ( $stage_data['tasks'] as &$task ) {
				$task->template_subtasks = $template_subtasks[ $task->task_key ] ?? array();
			}
			unset( $task );
		}
		unset( $stage_data );

		return $grouped;
	}

	public function get_task( int $task_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_event_tasks WHERE id = %d",
				$task_id
			)
		);
	}

	// -------------------------------------------------------------------------
	// Task completion
	// -------------------------------------------------------------------------

	/**
	 * Mark a task complete.
	 *
	 * @param int    $task_id
	 * @param int    $user_id
	 * @param string $note   Optional completion note.
	 * @return bool
	 */
	public function mark_task_complete( int $task_id, int $user_id, string $note = '' ): bool {
		global $wpdb;

		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			return false;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'hmo_event_tasks',
			array(
				'status'               => 'complete',
				'completed_by_user_id' => $user_id,
				'completed_at'         => current_time( 'mysql' ),
				'completion_note'      => sanitize_textarea_field( $note ),
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $task_id )
		);

		if ( $updated !== false ) {
			$this->recalculate_open_task_count( (int) $task->hostlinks_event_id );
			HMO_DB::log_activity(
				(int) $task->hostlinks_event_id,
				'task_complete',
				sprintf( 'Task completed: %s', $task->task_label ),
				array( 'task_id' => $task_id, 'note' => $note )
			);
		}

		return $updated !== false;
	}

	/**
	 * Mark a task incomplete (revert completion).
	 *
	 * @param int $task_id
	 * @return bool
	 */
	public function mark_task_incomplete( int $task_id ): bool {
		global $wpdb;

		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			return false;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'hmo_event_tasks',
			array(
				'status'               => 'pending',
				'completed_by_user_id' => 0,
				'completed_at'         => null,
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $task_id )
		);

		if ( $updated !== false ) {
			$this->recalculate_open_task_count( (int) $task->hostlinks_event_id );
			HMO_DB::log_activity(
				(int) $task->hostlinks_event_id,
				'task_incomplete',
				sprintf( 'Task reopened: %s', $task->task_label ),
				array( 'task_id' => $task_id )
			);
		}

		return $updated !== false;
	}

	/**
	 * Save or update a completion note without changing task status.
	 *
	 * @param int    $task_id
	 * @param string $note
	 * @return bool
	 */
	public function save_task_note( int $task_id, string $note ): bool {
		global $wpdb;

		$task = $this->get_task( $task_id );
		if ( ! $task ) {
			return false;
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'hmo_event_tasks',
			array(
				'completion_note' => sanitize_textarea_field( $note ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $task_id )
		);

		return $updated !== false;
	}

	// -------------------------------------------------------------------------
	// Open task count
	// -------------------------------------------------------------------------

	/**
	 * Recalculate and denormalize the open task count for an event into
	 * wp_hmo_event_ops. Also invalidates any dashboard transients.
	 *
	 * @param int $event_id  hostlinks_event_id
	 */
	public function recalculate_open_task_count( int $event_id ): void {
		global $wpdb;

		$open_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}hmo_event_tasks
				 WHERE hostlinks_event_id = %d AND status = 'pending'",
				$event_id
			)
		);

		HMO_DB::upsert_event_ops( $event_id, array( 'open_task_count' => $open_count ) );

		HMO_Dashboard_Service::flush_row_cache();
	}

	// -------------------------------------------------------------------------
	// Stage
	// -------------------------------------------------------------------------

	/**
	 * Update the workflow stage for an event.
	 *
	 * @param int    $event_id   hostlinks_event_id
	 * @param string $stage_key
	 * @return bool
	 */
	public function update_stage( int $event_id, string $stage_key ): bool {
		$valid_stages = HMO_Checklist_Templates::get_stage_order();
		if ( ! in_array( $stage_key, $valid_stages, true ) ) {
			return false;
		}

		HMO_DB::upsert_event_ops( $event_id, array(
			'workflow_stage'       => $stage_key,
			'last_status_change_at'=> current_time( 'mysql' ),
		) );

		HMO_Dashboard_Service::flush_row_cache();

		HMO_DB::log_activity( $event_id, 'stage_change', sprintf( 'Stage updated to: %s', $stage_key ) );

		return true;
	}

	// -------------------------------------------------------------------------
	// Bulk provision (admin)
	// -------------------------------------------------------------------------

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_bulk_provision',        array( __CLASS__, 'ajax_bulk_provision' ) );
		add_action( 'wp_ajax_hmo_bulk_complete_stages',  array( __CLASS__, 'ajax_bulk_complete_stages' ) );
	}

	/**
	 * Provision task rows for every future active event that doesn't have them yet.
	 * Runs synchronously in one AJAX request — safe for up to several hundred events.
	 */
	public static function ajax_bulk_provision(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_bulk_provision' );

		@set_time_limit( 120 );

		global $wpdb;

		// Fetch all active future events from Hostlinks.
		$today  = current_time( 'Y-m-d' );
		$events = $wpdb->get_col( $wpdb->prepare(
			"SELECT eve_id FROM {$wpdb->prefix}event_details_list
			 WHERE eve_status = 1 AND eve_start >= %s",
			$today
		) );

		if ( empty( $events ) ) {
			wp_send_json_success( array( 'provisioned' => 0, 'already_done' => 0, 'total' => 0 ) );
		}

		$templates     = new HMO_Checklist_Templates();
		$checklist_svc = new self( $templates );
		$provisioned   = 0;
		$already_done  = 0;

		foreach ( $events as $event_id ) {
			$event_id = (int) $event_id;

			$existing = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}hmo_event_tasks WHERE hostlinks_event_id = %d",
				$event_id
			) );

			if ( $existing > 0 ) {
				$already_done++;
				continue;
			}

			$checklist_svc->ensure_event_tasks_exist( $event_id );
			$provisioned++;
		}

		HMO_Dashboard_Service::flush_row_cache();

		wp_send_json_success( array(
			'provisioned'  => $provisioned,
			'already_done' => $already_done,
			'total'        => count( $events ),
		) );
	}

	/**
	 * Bulk-complete all tasks in specified stages for future events within a given days window.
	 *
	 * POST params:
	 *   stages[]   — stage_key values to complete (e.g. event_setup, data_send_prep)
	 *   days_out   — upper limit of days until event (inclusive, e.g. 50)
	 */
	public static function ajax_bulk_complete_stages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_bulk_complete_stages' );

		@set_time_limit( 180 );

		$raw_stages = isset( $_POST['stages'] ) && is_array( $_POST['stages'] )
			? array_map( 'sanitize_key', $_POST['stages'] )
			: array();
		$days_out   = isset( $_POST['days_out'] ) ? max( 1, (int) $_POST['days_out'] ) : 50;

		if ( empty( $raw_stages ) ) {
			wp_send_json_error( 'No stages specified.' );
		}

		global $wpdb;

		$today    = current_time( 'Y-m-d' );
		$end_date = date( 'Y-m-d', strtotime( "+{$days_out} days", strtotime( $today ) ) );

		// Fetch all qualifying future events.
		$events = $wpdb->get_col( $wpdb->prepare(
			"SELECT eve_id FROM {$wpdb->prefix}event_details_list
			 WHERE eve_status = 1 AND eve_start >= %s AND eve_start <= %s",
			$today,
			$end_date
		) );

		if ( empty( $events ) ) {
			wp_send_json_success( array( 'events' => 0, 'tasks_completed' => 0, 'stages' => $raw_stages ) );
		}

		$templates     = new HMO_Checklist_Templates();
		$checklist_svc = new self( $templates );
		$user_id       = get_current_user_id();
		$now           = current_time( 'mysql' );
		$tasks_done    = 0;
		$affected_events = 0;

		// Build a safe IN() clause for stage keys.
		$stage_placeholders = implode( ', ', array_fill( 0, count( $raw_stages ), '%s' ) );

		foreach ( $events as $event_id ) {
			$event_id = (int) $event_id;

			// Ensure task rows exist before completing them.
			$checklist_svc->ensure_event_tasks_exist( $event_id );

			// Build query args: event_id + each stage key.
			$query_args = array_merge( array( $event_id ), $raw_stages );

			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}hmo_event_tasks
					 SET status = 'complete',
					     completed_at = %s,
					     completed_by_user_id = %d
					 WHERE hostlinks_event_id = %d
					   AND stage_key IN ($stage_placeholders)
					   AND status = 'pending'",
					array_merge( array( $now, $user_id, $event_id ), $raw_stages )
				)
			);

			if ( $updated > 0 ) {
				$tasks_done += $updated;
				$affected_events++;
				$checklist_svc->recalculate_open_task_count( $event_id );

				// Log activity.
				$stage_label = implode( ' + ', array_map( fn( $s ) => ucwords( str_replace( '_', ' ', $s ) ), $raw_stages ) );
				HMO_DB::log_activity( $event_id, 'bulk_complete', sprintf( 'Bulk-completed all tasks in: %s', $stage_label ) );
			}
		}

		HMO_Dashboard_Service::flush_row_cache();

		wp_send_json_success( array(
			'events'          => count( $events ),
			'affected_events' => $affected_events,
			'tasks_completed' => $tasks_done,
			'stages'          => $raw_stages,
			'days_out'        => $days_out,
		) );
	}
}
