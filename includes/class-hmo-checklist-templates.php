<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Checklist_Templates {

	/** @var array Canonical stage order */
	private static $stage_order = array(
		'event_setup',
		'data_send_prep',
		'marketing_60_day',
		'marketing_30_day',
		'final_prep',
		'completion',
	);

	/** @var array Stage label map */
	private static $stage_labels = array(
		'event_setup'       => 'Event Setup',
		'data_send_prep'    => 'Data Send Prep',
		'marketing_60_day'  => '60-Day Marketing',
		'marketing_30_day'  => '30-Day Marketing',
		'final_prep'        => 'Final Prep',
		'completion'        => 'Completion',
	);

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
		return array_map( function( $key ) {
			return array(
				'stage_key'   => $key,
				'stage_label' => self::$stage_labels[ $key ],
			);
		}, self::$stage_order );
	}

	public function get_tasks_for_stage( string $stage_key ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}hmo_checklist_templates
				 WHERE stage_key = %s AND is_active = 1
				 ORDER BY sort_order ASC",
				$stage_key
			)
		);
	}

	public function get_all_active_tasks(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}hmo_checklist_templates
			 WHERE is_active = 1
			 ORDER BY sort_order ASC"
		);
	}

	public function get_stage_sort_index( string $stage_key ): int {
		$index = array_search( $stage_key, self::$stage_order, true );
		return $index !== false ? (int) $index : 999;
	}

	public static function get_stage_order(): array {
		return self::$stage_order;
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
}
