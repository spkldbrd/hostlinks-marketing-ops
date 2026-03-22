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
			registration_goal int(11) NOT NULL DEFAULT 0,
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
			parent_id bigint(20) NOT NULL DEFAULT 0,
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
			KEY stage_key (stage_key),
			KEY parent_id (parent_id)
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
		// wp_hmo_bucket_access — many-to-many: event bucket (marketer) ↔ WP user
		// -------------------------------------------------------------------------
		$sql = "CREATE TABLE {$wpdb->prefix}hmo_bucket_access (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			marketer_id int(11) NOT NULL,
			bucket_name varchar(100) NOT NULL DEFAULT '',
			wp_user_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY bucket_user (marketer_id, wp_user_id),
			KEY marketer_id (marketer_id),
			KEY wp_user_id (wp_user_id)
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
		global $wpdb;
		$installed = get_option( 'hmo_db_version', '0' );

		if ( version_compare( $installed, HMO_DB_VERSION, '<' ) ) {
			self::create_tables();
			self::migrate_marketer_meta_to_buckets();

			// v1.3: add task-completion tracking columns that dbDelta cannot add to existing tables.
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}hmo_event_tasks", 0 );
			if ( ! in_array( 'completed_by_user_id', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$wpdb->prefix}hmo_event_tasks ADD COLUMN completed_by_user_id bigint(20) NOT NULL DEFAULT 0" );
			}
			if ( ! in_array( 'completed_at', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$wpdb->prefix}hmo_event_tasks ADD COLUMN completed_at datetime DEFAULT NULL" );
			}
			if ( ! in_array( 'completion_note', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$wpdb->prefix}hmo_event_tasks ADD COLUMN completion_note text NOT NULL DEFAULT ''" );
			}

			update_option( 'hmo_db_version', HMO_DB_VERSION );
		}

		// Seed stages option for existing installs that pre-date stage management.
		if ( ! get_option( HMO_Checklist_Templates::OPT_STAGES ) ) {
			( new HMO_Checklist_Templates() )->seed_stages();
		}
	}

	/**
	 * One-time migration: import any existing hmo_marketer_id user-meta rows into
	 * the new hmo_bucket_access table (preserves all previously saved mappings).
	 */
	public static function migrate_marketer_meta_to_buckets(): void {
		global $wpdb;
		$meta_rows = $wpdb->get_results(
			"SELECT user_id, meta_value AS marketer_id
			 FROM {$wpdb->usermeta}
			 WHERE meta_key = 'hmo_marketer_id'
			   AND meta_value != '' AND meta_value != '0'"
		);
		if ( empty( $meta_rows ) ) {
			return;
		}
		foreach ( $meta_rows as $r ) {
			$uid  = (int) $r->user_id;
			$mid  = (int) $r->marketer_id;
			$name = (string) get_user_meta( $uid, 'hmo_marketer_name', true );
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}hmo_bucket_access
				 (marketer_id, bucket_name, wp_user_id)
				 VALUES (%d, %s, %d)",
				$mid, $name, $uid
			) );
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

	// -------------------------------------------------------------------------
	// Bucket access helpers
	// -------------------------------------------------------------------------

	public static function add_bucket_access( int $marketer_id, string $bucket_name, int $wp_user_id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->prefix}hmo_bucket_access (marketer_id, bucket_name, wp_user_id)
			 VALUES (%d, %s, %d)",
			$marketer_id, $bucket_name, $wp_user_id
		) );
		// Keep bucket_name fresh.
		$wpdb->update(
			$wpdb->prefix . 'hmo_bucket_access',
			array( 'bucket_name' => $bucket_name ),
			array( 'marketer_id' => $marketer_id, 'wp_user_id' => $wp_user_id )
		);
	}

	public static function remove_bucket_access( int $marketer_id, int $wp_user_id ): void {
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'hmo_bucket_access',
			array( 'marketer_id' => $marketer_id, 'wp_user_id' => $wp_user_id )
		);
	}

	/** Returns array of marketer_ids accessible by a WP user (empty = no access). */
	public static function get_bucket_ids_for_user( int $wp_user_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT marketer_id FROM {$wpdb->prefix}hmo_bucket_access WHERE wp_user_id = %d",
			$wp_user_id
		) );
		return array_map( 'intval', $ids );
	}

	/** Returns array of WP user IDs that have access to a bucket (marketer_id). */
	public static function get_users_for_bucket( int $marketer_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT wp_user_id FROM {$wpdb->prefix}hmo_bucket_access WHERE marketer_id = %d",
			$marketer_id
		) );
	}

	/** Returns all buckets with their assigned users, keyed by marketer_id. */
	public static function get_all_bucket_access(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT marketer_id, bucket_name, wp_user_id
			 FROM {$wpdb->prefix}hmo_bucket_access
			 ORDER BY marketer_id ASC, wp_user_id ASC"
		);
		$map = array();
		foreach ( $rows as $r ) {
			$mid = (int) $r->marketer_id;
			if ( ! isset( $map[ $mid ] ) ) {
				$map[ $mid ] = array( 'bucket_name' => $r->bucket_name, 'users' => array() );
			}
			$map[ $mid ]['users'][] = (int) $r->wp_user_id;
		}
		return $map;
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
