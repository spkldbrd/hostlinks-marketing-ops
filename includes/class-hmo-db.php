<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_DB {

	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// -------------------------------------------------------------------------
		// wp_hmo_event_ops — one row per Hostlinks event; workflow + list metadata
		// -------------------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_event_ops (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			hostlinks_event_id bigint(20) NOT NULL,
			class_built_date date DEFAULT NULL,
			assigned_marketer_id int(11) NOT NULL DEFAULT 0,
			assigned_marketer_name varchar(255) NOT NULL DEFAULT '',
			workflow_stage varchar(50) NOT NULL DEFAULT 'event_setup',
			registration_goal int(11) NOT NULL DEFAULT 40,
			open_task_count int(11) NOT NULL DEFAULT 0,
			data_list_status varchar(50) NOT NULL DEFAULT '',
			call_list_status varchar(50) NOT NULL DEFAULT '',
			data_list_url text NOT NULL DEFAULT '',
			call_list_url text NOT NULL DEFAULT '',
			ship_to_name varchar(255) NOT NULL DEFAULT '',
			ship_to_address text NOT NULL DEFAULT '',
			host_contact_json longtext NOT NULL DEFAULT '',
			last_status_change_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY hostlinks_event_id (hostlinks_event_id)
		) $charset_collate;";
		dbDelta( $sql );

		// -------------------------------------------------------------------------
		// wp_hmo_checklist_templates — master stage/task definitions
		// -------------------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_checklist_templates (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			stage_key varchar(50) NOT NULL DEFAULT '',
			stage_label varchar(255) NOT NULL DEFAULT '',
			task_key varchar(100) NOT NULL DEFAULT '',
			task_label varchar(255) NOT NULL DEFAULT '',
			task_description text NOT NULL DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			timing_anchor varchar(50) NOT NULL DEFAULT '',
			timing_offset_days int(11) NOT NULL DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY stage_key (stage_key)
		) $charset_collate;";
		dbDelta( $sql );

		// -------------------------------------------------------------------------
		// wp_hmo_event_tasks — per-event task instances
		// -------------------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_event_tasks (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			hostlinks_event_id bigint(20) NOT NULL,
			stage_key varchar(50) NOT NULL DEFAULT '',
			task_key varchar(100) NOT NULL DEFAULT '',
			task_label varchar(255) NOT NULL DEFAULT '',
			task_description text NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			completed_by_user_id bigint(20) NOT NULL DEFAULT 0,
			completed_at datetime DEFAULT NULL,
			completion_note text NOT NULL DEFAULT '',
			due_date date DEFAULT NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY hostlinks_event_id (hostlinks_event_id),
			KEY stage_key (stage_key),
			KEY status (status)
		) $charset_collate;";
		dbDelta( $sql );

		// -------------------------------------------------------------------------
		// wp_hmo_event_task_items — optional sub-checklist items under a task
		// -------------------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_event_task_items (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_task_id bigint(20) NOT NULL,
			item_label varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			completed_at datetime DEFAULT NULL,
			completed_by_user_id bigint(20) NOT NULL DEFAULT 0,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY event_task_id (event_task_id)
		) $charset_collate;";
		dbDelta( $sql );

		// -------------------------------------------------------------------------
		// wp_hmo_event_activity — light audit log for task and metadata changes
		// -------------------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_event_activity (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			hostlinks_event_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL DEFAULT 0,
			activity_type varchar(50) NOT NULL DEFAULT '',
			activity_summary varchar(500) NOT NULL DEFAULT '',
			meta_json longtext NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY hostlinks_event_id (hostlinks_event_id),
			KEY activity_type (activity_type)
		) $charset_collate;";
		dbDelta( $sql );
	}

	public static function maybe_upgrade() {
		$installed = get_option( 'hmo_db_version', '0' );

		if ( version_compare( $installed, HMO_DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'hmo_db_version', HMO_DB_VERSION );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers used by service classes
	// -------------------------------------------------------------------------

	public static function get_event_ops( int $event_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_event_ops WHERE hostlinks_event_id = %d",
				$event_id
			)
		);
	}

	public static function upsert_event_ops( int $event_id, array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'hmo_event_ops';

		$existing = self::get_event_ops( $event_id );

		if ( $existing ) {
			$wpdb->update( $table, $data, array( 'hostlinks_event_id' => $event_id ) );
		} else {
			$data['hostlinks_event_id'] = $event_id;
			$wpdb->insert( $table, $data );
		}
	}

	public static function log_activity( int $event_id, string $type, string $summary, array $meta = array() ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'hmo_event_activity',
			array(
				'hostlinks_event_id' => $event_id,
				'user_id'            => get_current_user_id(),
				'activity_type'      => $type,
				'activity_summary'   => $summary,
				'meta_json'          => ! empty( $meta ) ? wp_json_encode( $meta ) : '',
				'created_at'         => current_time( 'mysql' ),
			)
		);
	}
}
