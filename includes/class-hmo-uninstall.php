<?php
/**
 * Uninstall data removal — single source of truth for tables, options, and transients.
 *
 * @package HostlinksMarketingOps
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

class HMO_Uninstall {

	/** All plugin-owned tables (prefix applied at runtime). */
	private static function table_names(): array {
		return array(
			'hmo_event_ops',
			'hmo_checklist_templates',
			'hmo_event_tasks',
			'hmo_event_task_items',
			'hmo_bucket_access',
			'hmo_event_activity',
			'hmo_maps_county_centroids',
			'hmo_maps_county_stats',
		);
	}

	/**
	 * All plugin-owned options (string keys only — do not delete hostlinks_*).
	 */
	private static function option_names(): array {
		return array(
			'hmo_version',
			'hmo_db_version',
			'hmo_default_goal',
			'hmo_risk_red_days',
			'hmo_risk_red_tasks',
			'hmo_risk_yellow_days',
			'hmo_enable_marketer_filter',
			'hmo_hide_list_links',
			'hmo_goal_edit_marketing_admin',
			'hmo_goal_edit_hostlinks_user',
			'hmo_tools_links',
			'hmo_denial_message',
			'hmo_shortcode_access_modes',
			'hmo_approved_viewers',
			'hmo_task_editors',
			'hmo_report_viewers',
			'hmo_marketing_admins',
			'hmo_maps_census_api_key',
			'hmo_maps_google_api_key',
			'hmo_maps_sync_frequency',
			'hmo_maps_page_heading',
			'hmo_maps_centroid_source',
			'hmo_maps_centroids_initialized',
			'hmo_maps_last_sync',
			'hmo_page_urls',
			'hmo_stages',
		);
	}

	public static function run(): void {
		global $wpdb;

		foreach ( self::table_names() as $suffix ) {
			$table = $wpdb->prefix . $suffix;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}

		foreach ( self::option_names() as $name ) {
			delete_option( $name );
		}

		// Page template sections (dynamic option keys).
		$like = $wpdb->esc_like( 'hmo_page_tmpl_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		// All HMO transients (dashboard caches, page URL caches, GitHub updater, REST rate buckets, etc.).
		$like_t  = $wpdb->esc_like( '_transient_hmo_' ) . '%';
		$like_tt = $wpdb->esc_like( '_transient_timeout_hmo_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_t, $like_tt ) );
	}
}
