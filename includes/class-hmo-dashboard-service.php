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
	 * Returns dashboard row objects, access-filtered and sorted.
	 * Quick filters ($filters keys: view, hmo_stage, hmo_risk, hmo_bucket,
	 * hmo_trouble, hmo_missing, hmo_buckets[], hmo_trouble_only, hmo_next30)
	 * are applied post-sort and not cached — they're cheap array operations.
	 *
	 * @param array $filters
	 * @param bool  $force_refresh
	 * @return array
	 */
	public function get_dashboard_rows( array $filters = array(), bool $force_refresh = false ): array {
		// Build a stable cache key from just the access-level filters.
		$cache_filters = array_diff_key( $filters, array_flip( array(
			'view', 'hmo_stage', 'hmo_risk', 'hmo_bucket', 'hmo_trouble',
			'hmo_missing', 'hmo_buckets', 'hmo_trouble_only', 'hmo_next30',
		) ) );
		$cache_key = self::TRANSIENT_ROWS . '_' . md5( serialize( $cache_filters ) . get_current_user_id() );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $this->apply_post_cache_filters( $cached, $filters );
			}
		}

		// Restrict to user's buckets (marketer_ids) — null means admin (no restriction).
		$bucket_ids = $this->access->get_allowed_bucket_ids();
		if ( $bucket_ids !== null ) {
			if ( empty( $bucket_ids ) ) {
				return array();
			}
			$cache_filters['marketer_ids'] = $bucket_ids;
		}

		$events = $this->bridge->get_events( $cache_filters );

		if ( empty( $events ) ) {
			return array();
		}

		// Bulk-fetch all event_ops rows indexed by event id.
		$event_ids = array_column( $events, 'eve_id' );
		$ops_by_id = $this->get_event_ops_bulk( $event_ids );

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

			$stage       = $ops ? $ops->workflow_stage : 'event_setup';
			$days_left   = $this->countdown->get_days_left( $event_id );
			$open_tasks  = $ops ? (int) $ops->open_task_count : 0;
			$risk        = $days_left !== null
				? $this->countdown->get_risk_level( $days_left, $open_tasks )
				: 'green';
			$behind      = ( $days_left !== null && $days_left >= 0 )
				? $this->countdown->is_behind_schedule( $days_left, $stage )
				: false;

			$reg_count = $this->bridge->get_event_registration_count( $event_id );
			$reg_goal  = $ops ? (int) $ops->registration_goal : (int) get_option( 'hmo_default_goal', 40 );

			$rows[] = (object) array(
				'event_id'           => $event_id,
				'event_name'         => $event->cvent_event_title ?: $event->eve_location,
				'location'           => $event->eve_location,
				'marketer_id'        => (int) $event->eve_marketer,
				'marketer_name'      => $event->event_marketer_name ?? ( $ops ? $ops->assigned_marketer_name : '' ),
				'built_date'         => $ops ? $ops->class_built_date : '',
				'event_date'         => $event->eve_start,
				'event_end_date'     => $event->eve_end,
				'days_left'          => $days_left,
				'days_left_label'    => $this->countdown->format_days_left( $days_left ),
				'stage'              => $stage,
				'open_task_count'    => $open_tasks,
				'registration_count' => $reg_count,
				'registration_goal'  => $reg_goal,
				'risk_level'         => $risk,
				'behind_schedule'    => $behind,
				'data_list_status'   => $ops ? $ops->data_list_status : '',
				'call_list_status'   => $ops ? $ops->call_list_status : '',
				'data_list_url'      => $ops ? $ops->data_list_url : '',
				'call_list_url'      => $ops ? $ops->call_list_url : '',
			);
		}

		/*
		 * Sort priority:
		 *   1. Future events (days_left >= 0) — ascending, soonest first.
		 *   2. No-date events (days_left === null) — middle.
		 *   3. Past events (days_left < 0) — descending, most-recent past first.
		 */
		usort( $rows, function ( $a, $b ) {
			$da = $a->days_left;
			$db = $b->days_left;

			$groupA = $da === null ? 1 : ( $da >= 0 ? 0 : 2 );
			$groupB = $db === null ? 1 : ( $db >= 0 ? 0 : 2 );

			if ( $groupA !== $groupB ) { return $groupA <=> $groupB; }
			if ( $groupA === 0 ) { return $da <=> $db; }
			if ( $groupA === 2 ) { return $db <=> $da; }
			return 0;
		} );

		set_transient( $cache_key, $rows, self::CACHE_TTL );

		return $this->apply_post_cache_filters( $rows, $filters );
	}

	// -------------------------------------------------------------------------
	// Summary cards
	// -------------------------------------------------------------------------

	public function get_summary_cards( bool $force_refresh = false ): array {
		$user_id   = get_current_user_id();
		$cache_key = self::TRANSIENT_CARDS . '_' . $user_id;

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		// Cards are always based on ALL upcoming rows (no quick filters).
		$rows = $this->get_dashboard_rows( array( 'view' => 'upcoming' ), $force_refresh );

		$today     = wp_date( 'Y-m-d' );
		$in_30days = date( 'Y-m-d', strtotime( '+30 days' ) );

		$cards = array(
			'my_classes'        => 0,
			'red_risk'          => 0,
			'behind_schedule'   => 0,
			'next_30_days'      => 0,
			'missing_data_list' => 0,
			'missing_call_list' => 0,
		);

		foreach ( $rows as $row ) {
			$cards['my_classes']++;

			if ( $row->risk_level === 'red' ) {
				$cards['red_risk']++;
			}

			if ( $row->behind_schedule ) {
				$cards['behind_schedule']++;
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

		self::flush_row_cache();
		delete_transient( self::TRANSIENT_CARDS . '_' . get_current_user_id() );

		return true;
	}

	// -------------------------------------------------------------------------
	// Post-cache filters (view + quick filters)
	// -------------------------------------------------------------------------

	private function apply_post_cache_filters( array $rows, array $filters ): array {
		// Upcoming / past view filter.
		$view = $filters['view'] ?? 'upcoming';
		if ( $view === 'past' ) {
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->days_left !== null && $r->days_left < 0 ) );
		} else {
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->days_left === null || $r->days_left >= 0 ) );
		}

		// Dashboard: stage filter.
		if ( ! empty( $filters['hmo_stage'] ) ) {
			$s = sanitize_key( $filters['hmo_stage'] );
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->stage === $s ) );
		}

		// Dashboard: risk filter.
		if ( ! empty( $filters['hmo_risk'] ) && in_array( $filters['hmo_risk'], array( 'red', 'yellow', 'green' ), true ) ) {
			$risk = $filters['hmo_risk'];
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->risk_level === $risk ) );
		}

		// Dashboard: single bucket filter.
		if ( ! empty( $filters['hmo_bucket'] ) ) {
			$bid  = (int) $filters['hmo_bucket'];
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->marketer_id === $bid ) );
		}

		// Dashboard: trouble filter (at risk OR behind schedule).
		if ( ! empty( $filters['hmo_trouble'] ) ) {
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->risk_level === 'red' || $r->behind_schedule ) );
		}

		// Dashboard: missing list filter.
		if ( ! empty( $filters['hmo_missing'] ) ) {
			$m    = $filters['hmo_missing'];
			$rows = array_values( array_filter( $rows, function ( $r ) use ( $m ) {
				if ( $m === 'data' ) { return empty( $r->data_list_url ); }
				if ( $m === 'call' ) { return empty( $r->call_list_url ); }
				if ( $m === 'both' ) { return empty( $r->data_list_url ) || empty( $r->call_list_url ); }
				return false;
			} ) );
		}

		// My Classes: multi-bucket pill filter.
		if ( ! empty( $filters['hmo_buckets'] ) && is_array( $filters['hmo_buckets'] ) ) {
			$bids = array_map( 'intval', $filters['hmo_buckets'] );
			$rows = array_values( array_filter( $rows, fn( $r ) => in_array( $r->marketer_id, $bids, true ) ) );
		}

		// My Classes: trouble only.
		if ( ! empty( $filters['hmo_trouble_only'] ) ) {
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->risk_level === 'red' || $r->behind_schedule ) );
		}

		// My Classes: next 30 days.
		if ( ! empty( $filters['hmo_next30'] ) ) {
			$rows = array_values( array_filter( $rows, fn( $r ) => $r->days_left !== null && $r->days_left >= 0 && $r->days_left <= 30 ) );
		}

		return $rows;
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	public static function flush_row_cache(): void {
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_' . self::TRANSIENT_ROWS . '_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
	}

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
