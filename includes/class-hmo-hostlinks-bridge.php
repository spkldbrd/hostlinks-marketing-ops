<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All access to Hostlinks core tables is isolated here.
 * No other HMO class should query event_details_list or event_marketer directly.
 */
class HMO_Hostlinks_Bridge {

	/** @var string */
	private $events_table;

	/** @var string */
	private $marketer_table;

	public function __construct() {
		global $wpdb;
		$this->events_table   = $wpdb->prefix . 'event_details_list';
		$this->marketer_table = $wpdb->prefix . 'event_marketer';
	}

	// -------------------------------------------------------------------------
	// Status check
	// -------------------------------------------------------------------------

	public function is_hostlinks_active(): bool {
		return defined( 'HOSTLINKS_VERSION' );
	}

	// -------------------------------------------------------------------------
	// Event queries
	// -------------------------------------------------------------------------

	/**
	 * Fetch a single event record by eve_id.
	 *
	 * @param int $event_id
	 * @return object|null
	 */
	public function get_event( int $event_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->events_table} WHERE eve_id = %d",
				$event_id
			)
		);
	}

	/**
	 * Fetch multiple events with optional filters.
	 *
	 * Supported $filters keys:
	 *   status      int   1 = active (default), 0 = inactive
	 *   marketer_id int   filter by eve_marketer FK
	 *   date_from   string Y-m-d  eve_start >= date_from
	 *   date_to     string Y-m-d  eve_start <= date_to
	 *   event_ids   array list of eve_id values to include
	 *
	 * @param array $filters
	 * @return array
	 */
	public function get_events( array $filters = array() ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		$status = isset( $filters['status'] ) ? (int) $filters['status'] : 1;
		$where[]  = 'e.eve_status = %d';
		$params[] = $status;

		if ( ! empty( $filters['marketer_id'] ) ) {
			$where[]  = 'e.eve_marketer = %d';
			$params[] = (int) $filters['marketer_id'];
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[]  = 'e.eve_start >= %s';
			$params[] = $filters['date_from'];
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[]  = 'e.eve_start <= %s';
			$params[] = $filters['date_to'];
		}

		if ( ! empty( $filters['event_ids'] ) && is_array( $filters['event_ids'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $filters['event_ids'] ), '%d' ) );
			$where[]      = "e.eve_id IN ($placeholders)";
			$params       = array_merge( $params, array_map( 'intval', $filters['event_ids'] ) );
		}

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT e.*, m.event_marketer_name
				FROM {$this->events_table} e
				LEFT JOIN {$this->marketer_table} m ON e.eve_marketer = m.event_marketer_id
				WHERE $where_sql
				ORDER BY e.eve_start ASC";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	// -------------------------------------------------------------------------
	// Marketer queries
	// -------------------------------------------------------------------------

	/**
	 * Fetch all active marketers.
	 *
	 * @return array
	 */
	public function get_marketers(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT event_marketer_id, event_marketer_name
			 FROM {$this->marketer_table}
			 WHERE event_marketer_status = 1
			 ORDER BY event_marketer_name ASC"
		);
	}

	// -------------------------------------------------------------------------
	// Per-event convenience accessors
	// -------------------------------------------------------------------------

	public function get_event_date( int $event_id ): string {
		$event = $this->get_event( $event_id );
		return $event ? (string) $event->eve_start : '';
	}

	public function get_event_end_date( int $event_id ): string {
		$event = $this->get_event( $event_id );
		return $event ? (string) $event->eve_end : '';
	}

	public function get_event_location( int $event_id ): string {
		$event = $this->get_event( $event_id );
		return $event ? (string) $event->eve_location : '';
	}

	public function get_event_marketer_id( int $event_id ): int {
		$event = $this->get_event( $event_id );
		return $event ? (int) $event->eve_marketer : 0;
	}

	public function get_event_marketer_name( int $event_id ): string {
		global $wpdb;
		$event = $this->get_event( $event_id );
		if ( ! $event || ! $event->eve_marketer ) {
			return '';
		}
		$name = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT event_marketer_name FROM {$this->marketer_table} WHERE event_marketer_id = %d",
				$event->eve_marketer
			)
		);
		return $name ? (string) $name : '';
	}

	/**
	 * Total registration count (paid + free).
	 *
	 * @param int $event_id
	 * @return int
	 */
	public function get_event_registration_count( int $event_id ): int {
		$event = $this->get_event( $event_id );
		if ( ! $event ) {
			return 0;
		}
		return (int) $event->eve_paid + (int) $event->eve_free;
	}

	public function get_event_cvent_id( int $event_id ): string {
		$event = $this->get_event( $event_id );
		return $event ? (string) $event->cvent_event_id : '';
	}
}
