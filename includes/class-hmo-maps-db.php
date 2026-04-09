<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Maps_DB {

	/**
	 * Create the two Maps tables via dbDelta. Safe to call on every activation
	 * and upgrade — dbDelta is idempotent.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ── County centroids (lat/lng per FIPS from Gazetteer) ────────────────
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_maps_county_centroids (
			id          bigint(20)    NOT NULL AUTO_INCREMENT,
			fips        char(5)       NOT NULL,
			state_abbr  char(2)       NOT NULL DEFAULT '',
			county_name varchar(255)  NOT NULL DEFAULT '',
			lat         decimal(10,7) NOT NULL DEFAULT 0,
			lng         decimal(10,7) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY fips (fips)
		) $charset_collate;";
		dbDelta( $sql );

		// ── County population + migration stats (from Census PEP file) ────────
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_maps_county_stats (
			id           bigint(20)   NOT NULL AUTO_INCREMENT,
			fips         char(5)      NOT NULL,
			state_name   varchar(100) NOT NULL DEFAULT '',
			county_name  varchar(255) NOT NULL DEFAULT '',
			pop_2025     bigint(20)   NOT NULL DEFAULT 0,
			netmig_2025  int(11)      NOT NULL DEFAULT 0,
			synced_at    datetime     DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY fips (fips)
		) $charset_collate;";
		dbDelta( $sql );
	}

	/** Row count for the centroids table (used in admin status display). */
	public static function centroids_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hmo_maps_county_centroids" );
	}

	/** Row count for the stats table (used in admin status display). */
	public static function stats_count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hmo_maps_county_stats" );
	}
}
