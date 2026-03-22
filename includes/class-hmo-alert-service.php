<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared alert system for Dashboard and My Classes.
 * Converts a flat array of dashboard row objects into typed alert buckets.
 */
class HMO_Alert_Service {

	const TYPE_AT_RISK           = 'at_risk';
	const TYPE_BEHIND_SCHEDULE   = 'behind_schedule';
	const TYPE_MISSING_DATA_LIST = 'missing_data_list';
	const TYPE_MISSING_CALL_LIST = 'missing_call_list';
	const TYPE_UNDER_GOAL        = 'under_goal';

	const LABELS = array(
		self::TYPE_AT_RISK           => 'At Risk',
		self::TYPE_BEHIND_SCHEDULE   => 'Behind Schedule',
		self::TYPE_MISSING_DATA_LIST => 'Missing Data List',
		self::TYPE_MISSING_CALL_LIST => 'Missing Call List',
		self::TYPE_UNDER_GOAL        => 'Under Goal',
	);

	const COLORS = array(
		self::TYPE_AT_RISK           => 'red',
		self::TYPE_BEHIND_SCHEDULE   => 'orange',
		self::TYPE_MISSING_DATA_LIST => 'yellow',
		self::TYPE_MISSING_CALL_LIST => 'yellow',
		self::TYPE_UNDER_GOAL        => 'blue',
	);

	/**
	 * Group rows into alert buckets.
	 *
	 * @param array $rows  Dashboard row objects (stdClass).
	 * @return array  Keyed by alert type; each value is an array of row objects.
	 */
	public function get_alerts( array $rows ): array {
		$alerts = array_fill_keys( array_keys( self::LABELS ), array() );

		foreach ( $rows as $row ) {
			if ( $row->risk_level === 'red' ) {
				$alerts[ self::TYPE_AT_RISK ][] = $row;
			}
			if ( $row->behind_schedule ) {
				$alerts[ self::TYPE_BEHIND_SCHEDULE ][] = $row;
			}
			if ( empty( $row->data_list_url ) ) {
				$alerts[ self::TYPE_MISSING_DATA_LIST ][] = $row;
			}
			if ( empty( $row->call_list_url ) ) {
				$alerts[ self::TYPE_MISSING_CALL_LIST ][] = $row;
			}
			if ( $row->registration_count < $row->registration_goal ) {
				$alerts[ self::TYPE_UNDER_GOAL ][] = $row;
			}
		}

		return $alerts;
	}

	/**
	 * Returns just the counts per alert type.
	 *
	 * @param array $rows
	 * @return array  Keyed by alert type => int count.
	 */
	public function get_alert_counts( array $rows ): array {
		return array_map( 'count', $this->get_alerts( $rows ) );
	}

	/**
	 * Returns true if any alert type has at least one item.
	 *
	 * @param array $rows
	 * @return bool
	 */
	public function has_alerts( array $rows ): bool {
		foreach ( $this->get_alert_counts( $rows ) as $count ) {
			if ( $count > 0 ) {
				return true;
			}
		}
		return false;
	}
}
