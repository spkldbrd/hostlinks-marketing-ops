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

		// Ensure event_ops row exists.
		HMO_DB::upsert_event_ops( $event_id, array() );
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

		// Attach sub-items if any exist.
		$task_ids = wp_list_pluck( $tasks, 'id' );
		if ( ! empty( $task_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $task_ids ), '%d' ) );
			$sub_items    = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}hmo_event_task_items WHERE event_task_id IN ($placeholders) ORDER BY sort_order ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$task_ids
				)
			);
			$items_by_task = array();
			foreach ( $sub_items as $item ) {
				$items_by_task[ $item->event_task_id ][] = $item;
			}
			foreach ( $grouped as $stage_key => &$stage_data ) {
				foreach ( $stage_data['tasks'] as &$task ) {
					$task->sub_items = $items_by_task[ $task->id ] ?? array();
				}
				unset( $task );
			}
			unset( $stage_data );
		}

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

		delete_transient( 'hmo_dashboard_rows' );
		delete_transient( 'hmo_summary_cards' );
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

		delete_transient( 'hmo_dashboard_rows' );
		delete_transient( 'hmo_summary_cards' );

		HMO_DB::log_activity( $event_id, 'stage_change', sprintf( 'Stage updated to: %s', $stage_key ) );

		return true;
	}
}
