<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all plugin tables and options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'hmo_event_ops',
	$wpdb->prefix . 'hmo_checklist_templates',
	$wpdb->prefix . 'hmo_event_tasks',
	$wpdb->prefix . 'hmo_event_task_items',
	$wpdb->prefix . 'hmo_event_activity',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

$options = array(
	'hmo_version',
	'hmo_db_version',
	'hmo_default_goal',
	'hmo_risk_red_days',
	'hmo_risk_red_tasks',
	'hmo_risk_yellow_days',
	'hmo_enable_marketer_filter',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Clean up transients.
delete_transient( 'hmo_dashboard_rows' );
delete_transient( 'hmo_summary_cards' );
