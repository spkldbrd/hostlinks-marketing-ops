<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Countdown_Service {

	/** @var HMO_Hostlinks_Bridge */
	private $bridge;

	public function __construct( HMO_Hostlinks_Bridge $bridge ) {
		$this->bridge = $bridge;
	}

	/**
	 * Days from today until the event start date.
	 * Returns a negative number for past events.
	 *
	 * @param int $event_id  hostlinks_event_id
	 * @return int|null  null if event date is not available
	 */
	public function get_days_left( int $event_id ) {
		$date_str = $this->bridge->get_event_date( $event_id );
		return $this->get_days_left_from_date( $date_str );
	}

	/**
	 * Same calculation but accepts a date string directly — avoids a DB round-trip
	 * when the caller already has the event record in memory.
	 *
	 * @param string $date_str  Y-m-d string (empty = no date).
	 * @return int|null
	 */
	public function get_days_left_from_date( string $date_str ): ?int {
		if ( ! $date_str ) {
			return null;
		}
		$event_date = new DateTime( $date_str );
		$today      = new DateTime( wp_date( 'Y-m-d' ) );
		$interval   = $today->diff( $event_date );
		$days       = (int) $interval->days;
		return $event_date >= $today ? $days : -$days;
	}

	/**
	 * Risk level based on days left and open task count.
	 *
	 * red    — fewer than 30 days AND more than 5 open tasks
	 * yellow — fewer than 45 days AND at least 1 open task
	 * green  — otherwise
	 *
	 * @param int $days_left
	 * @param int $open_tasks
	 * @return string  'red' | 'yellow' | 'green'
	 */
	public function get_risk_level( int $days_left, int $open_tasks ): string {
		$red_days    = (int) get_option( 'hmo_risk_red_days',    30 );
		$red_tasks   = (int) get_option( 'hmo_risk_red_tasks',    5 );
		$yellow_days = (int) get_option( 'hmo_risk_yellow_days', 45 );

		if ( $days_left < $red_days && $open_tasks > $red_tasks ) {
			return 'red';
		}

		if ( $days_left < $yellow_days && $open_tasks > 0 ) {
			return 'yellow';
		}

		return 'green';
	}

	/**
	 * Determine whether an event is "behind schedule" based on stage vs days left.
	 *
	 * Rules:
	 *  - Still in Event Setup with < 60 days left
	 *  - Still in Data Send Prep with < 30 days left
	 *  - Not yet in Final Prep or Completion with < 14 days left
	 *
	 * @param int    $days_left  From get_days_left() — must be >= 0 (future event).
	 * @param string $stage      Workflow stage key.
	 * @return bool
	 */
	public function is_behind_schedule( int $days_left, string $stage ): bool {
		if ( $days_left < 0 ) {
			return false; // Past events don't count.
		}
		if ( $days_left < 60 && $stage === 'event_setup' ) {
			return true;
		}
		if ( $days_left < 30 && $stage === 'data_send_prep' ) {
			return true;
		}
		if ( $days_left < 14 && ! in_array( $stage, array( 'final_prep', 'completion' ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Human-readable label for days left.
	 *
	 * @param int|null $days_left
	 * @return string
	 */
	public function format_days_left( $days_left ): string {
		if ( $days_left === null ) {
			return '—';
		}
		if ( $days_left < 0 ) {
			return absint( $days_left ) . ' days ago';
		}
		if ( $days_left === 0 ) {
			return 'Today';
		}
		return $days_left . ' days';
	}
}
