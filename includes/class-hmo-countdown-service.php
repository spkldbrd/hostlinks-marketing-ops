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
