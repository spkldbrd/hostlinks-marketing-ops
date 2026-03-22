<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Checklist_Templates {

	const OPT_STAGES = 'hmo_stages';

	/** @var array Default stage definitions — used as seed only, not the live source */
	private static $default_stages = array(
		array( 'key' => 'event_setup',      'label' => 'Event Setup' ),
		array( 'key' => 'data_send_prep',   'label' => 'Data Send Prep' ),
		array( 'key' => 'marketing_60_day', 'label' => '60-Day Marketing' ),
		array( 'key' => 'marketing_30_day', 'label' => '30-Day Marketing' ),
		array( 'key' => 'final_prep',       'label' => 'Final Prep' ),
		array( 'key' => 'completion',       'label' => 'Completion' ),
	);

	// -------------------------------------------------------------------------
	// Stage option helpers
	// -------------------------------------------------------------------------

	public static function get_stages_option(): array {
		$stored = get_option( self::OPT_STAGES );
		if ( ! empty( $stored ) && is_array( $stored ) ) {
			return $stored;
		}
		return self::$default_stages;
	}

	public static function save_stages( array $stages ): void {
		update_option( self::OPT_STAGES, $stages );
	}

	public function seed_stages(): void {
		if ( ! get_option( self::OPT_STAGES ) ) {
			update_option( self::OPT_STAGES, self::$default_stages );
		}
	}

	// -------------------------------------------------------------------------
	// Seed
	// -------------------------------------------------------------------------

	public function seed_templates() {
		global $wpdb;
		$table = $wpdb->prefix . 'hmo_checklist_templates';

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return;
		}

		foreach ( $this->get_template_data() as $row ) {
			$wpdb->insert( $table, $row );
		}
	}

	// -------------------------------------------------------------------------
	// Read
	// -------------------------------------------------------------------------

	public function get_all_stages(): array {
		return array_map( function( $s ) {
			return array(
				'stage_key'   => $s['key'],
				'stage_label' => $s['label'],
			);
		}, self::get_stages_option() );
	}

	public function get_tasks_for_stage( string $stage_key ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_checklist_templates
				 WHERE stage_key = %s AND parent_id = 0 AND is_active = 1
				 ORDER BY sort_order ASC",
				$stage_key
			)
		);
	}

	public function get_subtasks( int $parent_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_checklist_templates
				 WHERE parent_id = %d AND is_active = 1
				 ORDER BY sort_order ASC",
				$parent_id
			)
		);
	}

	public function get_all_active_tasks(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}hmo_checklist_templates
			 WHERE parent_id = 0 AND is_active = 1
			 ORDER BY sort_order ASC"
		);
	}

	public function get_task( int $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_checklist_templates WHERE id = %d",
				$id
			)
		);
	}

	// -------------------------------------------------------------------------
	// Write (CRUD for the template editor)
	// -------------------------------------------------------------------------

	public function create_task( string $stage_key, string $label, string $description, int $parent_id = 0 ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'hmo_checklist_templates';

		// Sort order = max + 10 within same stage/parent bucket.
		$max = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(sort_order) FROM {$table} WHERE stage_key = %s AND parent_id = %d",
			$stage_key, $parent_id
		) );

		$stage_label = self::$stage_labels[ $stage_key ] ?? '';
		$task_key    = sanitize_key( $label ) . '_' . time();

		$wpdb->insert( $table, array(
			'parent_id'        => $parent_id,
			'stage_key'        => $stage_key,
			'stage_label'      => $stage_label,
			'task_key'         => $task_key,
			'task_label'       => sanitize_text_field( $label ),
			'task_description' => sanitize_textarea_field( $description ),
			'sort_order'       => $max + 10,
			'is_active'        => 1,
		) );

		return (int) $wpdb->insert_id;
	}

	public function update_task( int $id, string $label, string $description ): bool {
		global $wpdb;
		$rows = $wpdb->update(
			$wpdb->prefix . 'hmo_checklist_templates',
			array(
				'task_label'       => sanitize_text_field( $label ),
				'task_description' => sanitize_textarea_field( $description ),
			),
			array( 'id' => $id )
		);
		return $rows !== false;
	}

	public function delete_task( int $id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'hmo_checklist_templates';
		// Hard delete: also remove sub-tasks.
		$wpdb->delete( $table, array( 'parent_id' => $id ) );
		$wpdb->delete( $table, array( 'id'        => $id ) );
	}

	public function reorder_tasks( array $ordered_ids ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'hmo_checklist_templates';
		foreach ( $ordered_ids as $pos => $id ) {
			$wpdb->update( $table, array( 'sort_order' => (int) $pos * 10 ), array( 'id' => (int) $id ) );
		}
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_te_add_task',      array( __CLASS__, 'ajax_add_task' ) );
		add_action( 'wp_ajax_hmo_te_update_task',   array( __CLASS__, 'ajax_update_task' ) );
		add_action( 'wp_ajax_hmo_te_delete_task',   array( __CLASS__, 'ajax_delete_task' ) );
		add_action( 'wp_ajax_hmo_te_reorder',       array( __CLASS__, 'ajax_reorder' ) );
		add_action( 'wp_ajax_hmo_te_add_stage',     array( __CLASS__, 'ajax_add_stage' ) );
		add_action( 'wp_ajax_hmo_te_update_stage',  array( __CLASS__, 'ajax_update_stage' ) );
		add_action( 'wp_ajax_hmo_te_delete_stage',  array( __CLASS__, 'ajax_delete_stage' ) );
	}

	private static function check_task_editor_cap(): void {
		if ( ! HMO_Access_Service::current_user_can_edit_tasks() ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
		}
	}

	public static function ajax_add_task(): void {
		check_ajax_referer( 'hmo_task_editor', 'nonce' );
		self::check_task_editor_cap();

		$stage_key   = sanitize_key( $_POST['stage_key']   ?? '' );
		$label       = sanitize_text_field( $_POST['label'] ?? '' );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );
		$parent_id   = (int) ( $_POST['parent_id'] ?? 0 );

		if ( ! $stage_key || ! $label ) {
			wp_send_json_error( array( 'message' => 'Stage and label are required.' ) );
		}

		$tmpl = new self();
		$id   = $tmpl->create_task( $stage_key, $label, $description, $parent_id );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'Could not create task.' ) );
		}

		$row = $tmpl->get_task( $id );
		wp_send_json_success( array( 'task' => $row ) );
	}

	public static function ajax_update_task(): void {
		check_ajax_referer( 'hmo_task_editor', 'nonce' );
		self::check_task_editor_cap();

		$id          = (int) ( $_POST['id'] ?? 0 );
		$label       = sanitize_text_field( $_POST['label'] ?? '' );
		$description = sanitize_textarea_field( $_POST['description'] ?? '' );

		if ( ! $id || ! $label ) {
			wp_send_json_error( array( 'message' => 'ID and label are required.' ) );
		}

		$tmpl = new self();
		$ok   = $tmpl->update_task( $id, $label, $description );

		if ( $ok ) {
			wp_send_json_success( array( 'task' => $tmpl->get_task( $id ) ) );
		} else {
			wp_send_json_error( array( 'message' => 'Update failed.' ) );
		}
	}

	public static function ajax_delete_task(): void {
		check_ajax_referer( 'hmo_task_editor', 'nonce' );
		self::check_task_editor_cap();

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => 'ID required.' ) );
		}

		( new self() )->delete_task( $id );
		wp_send_json_success();
	}

	public static function ajax_reorder(): void {
		check_ajax_referer( 'hmo_task_editor', 'nonce' );
		self::check_task_editor_cap();

		$ids = isset( $_POST['ids'] ) && is_array( $_POST['ids'] )
			? array_map( 'intval', $_POST['ids'] ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => 'No IDs provided.' ) );
		}

		( new self() )->reorder_tasks( $ids );
		wp_send_json_success();
	}

	public function get_stage_sort_index( string $stage_key ): int {
		$index = array_search( $stage_key, self::$stage_order, true );
		return $index !== false ? (int) $index : 999;
	}

	public static function get_stage_order(): array {
		return array_column( self::get_stages_option(), 'key' );
	}

	// -------------------------------------------------------------------------
	// Template data — 6 stages, 30 tasks
	// -------------------------------------------------------------------------

	private function get_template_data(): array {
		$rows  = array();
		$order = 0;

		$stages = array(

			'event_setup' => array(
				'label' => 'Event Setup',
				'tasks' => array(
					array( 'review_hostlinks_accuracy',  'Review Hostlinks Accuracy',  'Verify all event details in Hostlinks are correct — location, date, type, and instructor.' ),
					array( 'review_website_accuracy',   'Review Website Accuracy',    'Confirm event is displaying correctly on the public website.' ),
					array( 'review_interspire_accuracy','Review Interspire Accuracy', 'Check that email lists and templates in Interspire match this event.' ),
					array( 'host_contact_intro',        'Host Contact Intro',         'Reach out to the host contact to introduce yourself and confirm event details.' ),
					array( 'review_with_host',          'Review Event With Host',     'Walk the host through event logistics and answer any questions.' ),
					array( 'confirm_room_capacity',     'Confirm Room Capacity',      'Verify the venue room capacity meets registration goal requirements.' ),
					array( 'confirm_shipping_info',     'Confirm Shipping Info',      'Get and record the ship-to name and address for books and materials.' ),
				),
			),

			'data_send_prep' => array(
				'label' => 'Data Send Prep',
				'tasks' => array(
					array( 'send_schedule_sent',            'Send Schedule Sent',            'Confirm the event schedule has been sent to appropriate parties.' ),
					array( 'confirm_data_send_scheduled',   'Confirm Data Send Scheduled',   'Verify the data send is scheduled and on track.' ),
					array( 'data_list_rebuild_check',       'Data List Rebuild Check',       'Review data list and rebuild if needed before the send.' ),
					array( 'phone_list_rebuild_check',      'Phone List Rebuild Check',      'Review phone/call list and rebuild if needed.' ),
					array( 'schedule_fyis',                 'Schedule FYIs',                 'Schedule FYI communications for appropriate contacts.' ),
					array( 'confirm_phone_list_complete',   'Confirm Phone List Complete',   'Verify the phone list is complete and ready for outreach.' ),
				),
			),

			'marketing_60_day' => array(
				'label' => '60-Day Marketing',
				'tasks' => array(
					array( 'host_60_day_checkin',          '60-Day Host Check-In',           'Check in with the host to confirm promotion is underway.' ),
					array( 'alumni_outreach',              'Alumni Outreach',                'Reach out to alumni from prior events in this region.' ),
					array( 'prior_interest_outreach',      'Prior Interest Outreach',        'Contact people who expressed interest but did not attend previously.' ),
					array( 'prior_hosts_outreach',         'Prior Hosts Outreach',           'Reach out to prior event hosts who may attend or refer attendees.' ),
					array( 'state_associations_outreach',  'State Associations Outreach',    'Contact relevant state associations to promote the event.' ),
					array( 'top_of_list_calls',            'Top of List Calls',              'Call top prospects from the call list.' ),
					array( 'host_county_outreach',         'Host County Outreach',           'Target outreach to contacts in the host county.' ),
					array( 'host_city_outreach',           'Host City Outreach',             'Target outreach to contacts in the host city.' ),
					array( 'surrounding_counties_outreach','Surrounding Counties Outreach',  'Extend outreach to contacts in surrounding counties.' ),
					array( 'surrounding_cities_outreach',  'Surrounding Cities Outreach',    'Extend outreach to contacts in surrounding cities.' ),
				),
			),

			'marketing_30_day' => array(
				'label' => '30-Day Marketing',
				'tasks' => array(
					array( 'host_30_day_checkin',        '30-Day Host Check-In',         'Check in with the host at the 30-day mark.' ),
					array( 'ask_host_share_again',       'Ask Host to Share Again',      'Request the host promote the event to their network once more.' ),
					array( 'follow_up_prior_calls',      'Follow Up Prior Calls',        'Follow up on all previous outreach calls that had interest.' ),
					array( 'identify_missing_agencies',  'Identify Missing Agencies',    'Identify any agencies in the area that have not been contacted.' ),
					array( 'group_discount_push',        'Group Discount Push',          'Promote group discount pricing to organizations and agencies.' ),
					array( 'parking_request',            'Parking Request',              'Request parking information from the venue if not already obtained.' ),
					array( 'comps_check',                'Comps Check',                  'Confirm complimentary registrations are allocated and tracked.' ),
					array( 'shipping_address_reconfirm', 'Reconfirm Shipping Address',   'Reconfirm the ship-to address is still correct before materials ship.' ),
				),
			),

			'final_prep' => array(
				'label' => 'Final Prep',
				'tasks' => array(
					array( 'confirm_room_equipment',            'Confirm Room & Equipment',          'Verify the room setup and all required equipment will be available.' ),
					array( 'books_arrived_or_on_way',           'Books Arrived or On the Way',       'Confirm course materials have shipped or arrived at the venue.' ),
					array( 'send_roster_to_instructor',         'Send Roster to Instructor',         'Email the current attendee roster to the instructor.' ),
					array( 'send_parking_to_instructor',        'Send Parking Info to Instructor',   'Email parking details and directions to the instructor.' ),
					array( 'resend_updated_roster_if_needed',   'Resend Updated Roster if Needed',   'If registration changes, resend an updated roster to the instructor.' ),
					array( 'resend_host_contact',               'Resend Host Contact Info',          'Resend host contact details to the instructor before the event.' ),
					array( 'send_attendee_parking_3_days_before','Send Parking to Attendees (3 days)','Email parking and logistics details to registered attendees 3 days before.' ),
				),
			),

			'completion' => array(
				'label' => 'Completion',
				'tasks' => array(
					array( 'final_host_checkin',  'Final Host Check-In',    'Check in with the host after the event to gather feedback.' ),
					array( 'attendee_info_sent',  'Attendee Info Sent',     'Confirm post-event attendee information has been sent as required.' ),
					array( 'host_thank_you',      'Host Thank-You',         'Send a thank-you message to the host.' ),
					array( 'lists_returned',      'Lists Returned',         'Confirm data and call lists have been returned and filed.' ),
				),
			),

		);

		foreach ( $stages as $stage_key => $stage ) {
			$task_sort = 0;
			foreach ( $stage['tasks'] as $task ) {
				$rows[] = array(
					'stage_key'          => $stage_key,
					'stage_label'        => $stage['label'],
					'task_key'           => $task[0],
					'task_label'         => $task[1],
					'task_description'   => $task[2],
					'sort_order'         => $order++,
					'timing_anchor'      => '',
					'timing_offset_days' => 0,
					'is_active'          => 1,
				);
				$task_sort++;
			}
		}

		return $rows;
	}

	// -------------------------------------------------------------------------
	// Stage AJAX handlers
	// -------------------------------------------------------------------------

	public static function ajax_add_stage(): void {
		check_ajax_referer( 'hmo_task_editor', 'nonce' );
		self::check_task_editor_cap();

		$label = sanitize_text_field( $_POST['label'] ?? '' );
		if ( ! $label ) {
			wp_send_json_error( array( 'message' => 'Stage name is required.' ) );
		}

		$key    = sanitize_key( $label ) . '_' . time();
		$stages = self::get_stages_option();
		$stages[] = array( 'key' => $key, 'label' => $label );
		self::save_stages( $stages );

		wp_send_json_success( array(
			'stage' => array( 'key' => $key, 'label' => $label ),
			'total' => count( $stages ),
		) );
	}

	public static function ajax_update_stage(): void {
		check_ajax_referer( 'hmo_task_editor', 'nonce' );
		self::check_task_editor_cap();

		$key   = sanitize_key( $_POST['stage_key'] ?? '' );
		$label = sanitize_text_field( $_POST['label'] ?? '' );

		if ( ! $key || ! $label ) {
			wp_send_json_error( array( 'message' => 'Stage key and label are required.' ) );
		}

		// Update the option.
		$stages = self::get_stages_option();
		$found  = false;
		foreach ( $stages as &$s ) {
			if ( $s['key'] === $key ) {
				$s['label'] = $label;
				$found = true;
				break;
			}
		}
		unset( $s );

		if ( ! $found ) {
			wp_send_json_error( array( 'message' => 'Stage not found.' ) );
		}

		self::save_stages( $stages );

		// Update the cached stage_label on all template rows.
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'hmo_checklist_templates',
			array( 'stage_label' => $label ),
			array( 'stage_key'   => $key )
		);

		wp_send_json_success( array( 'stage_key' => $key, 'label' => $label ) );
	}

	public static function ajax_delete_stage(): void {
		check_ajax_referer( 'hmo_task_editor', 'nonce' );
		self::check_task_editor_cap();

		$key = sanitize_key( $_POST['stage_key'] ?? '' );
		if ( ! $key ) {
			wp_send_json_error( array( 'message' => 'Stage key is required.' ) );
		}

		// Remove from option.
		$stages  = self::get_stages_option();
		$stages  = array_values( array_filter( $stages, fn( $s ) => $s['key'] !== $key ) );
		self::save_stages( $stages );

		// Hard-delete all tasks (and sub-tasks via same stage_key) for this stage.
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'hmo_checklist_templates',
			array( 'stage_key' => $key )
		);

		wp_send_json_success( array( 'total' => count( $stages ) ) );
	}
}
