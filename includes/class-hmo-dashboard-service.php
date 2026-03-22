<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Dashboard_Service {

	const TRANSIENT_ROWS  = 'hmo_dashboard_rows';
	const TRANSIENT_CARDS = 'hmo_summary_cards';
	const CACHE_TTL       = 300; // 5 minutes

	/** @var HMO_Hostlinks_Bridge */
	private $bridge;

	/** @var HMO_Access_Service */
	private $access;

	/** @var HMO_Checklist_Service */
	private $checklist;

	/** @var HMO_Countdown_Service */
	private $countdown;

	public function __construct(
		HMO_Hostlinks_Bridge $bridge,
		HMO_Access_Service $access,
		HMO_Checklist_Service $checklist,
		HMO_Countdown_Service $countdown
	) {
		$this->bridge    = $bridge;
		$this->access    = $access;
		$this->checklist = $checklist;
		$this->countdown = $countdown;
	}

	// -------------------------------------------------------------------------
	// Dashboard rows
	// -------------------------------------------------------------------------

	/**
	 * Returns an array of dashboard row objects, filtered by access and optional
	 * additional $filters (same keys as HMO_Hostlinks_Bridge::get_events).
	 *
	 * @param array $filters
	 * @param bool  $force_refresh  Skip transient cache.
	 * @return array
	 */
	public function get_dashboard_rows( array $filters = array(), bool $force_refresh = false ): array {
		$cache_key = self::TRANSIENT_ROWS . '_' . md5( serialize( $filters ) . get_current_user_id() );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// Restrict to allowed events for current user.
		$allowed_ids = $this->access->get_allowed_event_ids();
		if ( is_array( $allowed_ids ) ) {
			if ( empty( $allowed_ids ) ) {
				return array();
			}
			$filters['event_ids'] = $allowed_ids;
		}

		$events = $this->bridge->get_events( $filters );

		if ( empty( $events ) ) {
			return array();
		}

		// Bulk-fetch all event_ops rows indexed by event id.
		$event_ids   = array_column( $events, 'eve_id' );
		$ops_by_id   = $this->get_event_ops_bulk( $event_ids );

		$rows = array();
		foreach ( $events as $event ) {
			$event_id = (int) $event->eve_id;
			$ops      = $ops_by_id[ $event_id ] ?? null;

			// Ensure event_ops row exists.
			if ( ! $ops ) {
				HMO_DB::upsert_event_ops( $event_id, array(
					'assigned_marketer_id'   => (int) $event->eve_marketer,
					'assigned_marketer_name' => $event->event_marketer_name ?? '',
				) );
				$ops = HMO_DB::get_event_ops( $event_id );
			}

			$days_left   = $this->countdown->get_days_left( $event_id );
			$open_tasks  = $ops ? (int) $ops->open_task_count : 0;
			$risk        = $days_left !== null
				? $this->countdown->get_risk_level( $days_left, $open_tasks )
				: 'green';

			$reg_count = $this->bridge->get_event_registration_count( $event_id );

			$rows[] = (object) array(
				'event_id'          => $event_id,
				'event_name'        => $event->cvent_event_title ?: $event->eve_location,
				'location'          => $event->eve_location,
				'marketer_id'       => (int) $event->eve_marketer,
				'marketer_name'     => $event->event_marketer_name ?? ( $ops ? $ops->assigned_marketer_name : '' ),
				'built_date'        => $ops ? $ops->class_built_date : '',
				'event_date'        => $event->eve_start,
				'event_end_date'    => $event->eve_end,
				'days_left'         => $days_left,
				'days_left_label'   => $this->countdown->format_days_left( $days_left ),
				'stage'             => $ops ? $ops->workflow_stage : 'event_setup',
				'open_task_count'   => $open_tasks,
				'registration_count'=> $reg_count,
				'registration_goal' => $ops ? (int) $ops->registration_goal : (int) get_option( 'hmo_default_goal', 40 ),
				'risk_level'        => $risk,
				'data_list_status'  => $ops ? $ops->data_list_status : '',
				'call_list_status'  => $ops ? $ops->call_list_status : '',
				'data_list_url'     => $ops ? $ops->data_list_url : '',
				'call_list_url'     => $ops ? $ops->call_list_url : '',
			);
		}

		// Sort: fewest days left first; null (no date set) floats to the bottom.
		usort( $rows, function ( $a, $b ) {
			$da = $a->days_left;
			$db = $b->days_left;
			if ( $da === null && $db === null ) { return 0; }
			if ( $da === null ) { return 1; }
			if ( $db === null ) { return -1; }
			return $da <=> $db;
		} );

		set_transient( $cache_key, $rows, self::CACHE_TTL );

		return $rows;
	}

	// -------------------------------------------------------------------------
	// Summary cards
	// -------------------------------------------------------------------------

	/**
	 * Returns counts for the summary card widgets.
	 *
	 * @param bool $force_refresh
	 * @return array
	 */
	public function get_summary_cards( bool $force_refresh = false ): array {
		$user_id   = get_current_user_id();
		$cache_key = self::TRANSIENT_CARDS . '_' . $user_id;

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$rows = $this->get_dashboard_rows( array(), $force_refresh );

		$today     = wp_date( 'Y-m-d' );
		$in_30days = date( 'Y-m-d', strtotime( '+30 days' ) );

		$cards = array(
			'my_classes'           => 0,
			'red_risk'             => 0,
			'next_30_days'         => 0,
			'missing_data_list'    => 0,
			'missing_call_list'    => 0,
		);

		foreach ( $rows as $row ) {
			$cards['my_classes']++;

			if ( $row->risk_level === 'red' ) {
				$cards['red_risk']++;
			}

			if ( $row->event_date >= $today && $row->event_date <= $in_30days ) {
				$cards['next_30_days']++;
			}

			if ( empty( $row->data_list_url ) ) {
				$cards['missing_data_list']++;
			}

			if ( empty( $row->call_list_url ) ) {
				$cards['missing_call_list']++;
			}
		}

		set_transient( $cache_key, $cards, self::CACHE_TTL );

		return $cards;
	}

	// -------------------------------------------------------------------------
	// List metadata update
	// -------------------------------------------------------------------------

	/**
	 * Update data list and call list metadata for an event.
	 *
	 * @param int   $event_id
	 * @param array $data  Keys: data_list_status, data_list_url, call_list_status, call_list_url
	 * @return bool
	 */
	public function update_list_metadata( int $event_id, array $data ): bool {
		$allowed = array( 'data_list_status', 'data_list_url', 'call_list_status', 'call_list_url' );
		$clean   = array();

		foreach ( $allowed as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( $data[ $key ] );
			}
		}

		if ( empty( $clean ) ) {
			return false;
		}

		HMO_DB::upsert_event_ops( $event_id, $clean );

		HMO_DB::log_activity( $event_id, 'list_update', 'List metadata updated.', $clean );

		delete_transient( self::TRANSIENT_ROWS . '_*' );
		delete_transient( self::TRANSIENT_CARDS . '_' . get_current_user_id() );

		return true;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Bulk-fetch event_ops rows indexed by hostlinks_event_id.
	 *
	 * @param int[] $event_ids
	 * @return array  Keyed by hostlinks_event_id.
	 */
	private function get_event_ops_bulk( array $event_ids ): array {
		if ( empty( $event_ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $event_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_event_ops WHERE hostlinks_event_id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$event_ids
			)
		);

		$indexed = array();
		foreach ( $rows as $row ) {
			$indexed[ (int) $row->hostlinks_event_id ] = $row;
		}
		return $indexed;
	}
}
