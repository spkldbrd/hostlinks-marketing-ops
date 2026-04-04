<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cross-site page creation on grantwritingusa.com when a new event is
 * created in Hostlinks.  Credentials are stored as wp-config.php constants so
 * they are never written to the database.
 *
 * Required constants (add to hostlinks.grantwritingusa.com wp-config.php):
 *   define( 'GWU_PRIMARY_API',           'https://grantwritingusa.com/wp-json/wp/v2' );
 *   define( 'GWU_API_USER',              'event-automation' );
 *   define( 'GWU_API_PASS',              'xxxx xxxx xxxx xxxx xxxx xxxx' );
 *   define( 'GWU_EVENTS_PARENT_PAGE_ID',  0 ); // set to Events parent page ID, or 0 for top-level
 */
class HMO_Page_Sync {

	const CONST_API    = 'GWU_PRIMARY_API';
	const CONST_USER   = 'GWU_API_USER';
	const CONST_PASS   = 'GWU_API_PASS';
	const CONST_PARENT = 'GWU_EVENTS_PARENT_PAGE_ID';

	// -------------------------------------------------------------------------
	// Configuration helpers
	// -------------------------------------------------------------------------

	public static function is_configured(): bool {
		return defined( self::CONST_API )
			&& defined( self::CONST_USER )
			&& defined( self::CONST_PASS );
	}

	public static function get_config_status(): array {
		$consts = array( self::CONST_API, self::CONST_USER, self::CONST_PASS, self::CONST_PARENT );
		$status = array();
		foreach ( $consts as $c ) {
			$status[ $c ] = defined( $c );
		}
		return $status;
	}

	// -------------------------------------------------------------------------
	// Hook: event created
	// -------------------------------------------------------------------------

	/**
	 * Called by hostlinks_event_created action (priority 20, after checklist at 10).
	 */
	public function on_event_created( int $event_id, string $eve_start ): void {
		if ( ! self::is_configured() ) {
			return;
		}

		// Skip past events.
		if ( $eve_start < current_time( 'Y-m-d' ) ) {
			return;
		}

		global $wpdb;
		$ev = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}event_details_list WHERE eve_id = %d",
				$event_id
			),
			ARRAY_A
		);

		if ( empty( $ev ) ) {
			return;
		}

		$page_url = $this->create_gwu_page( $ev );

		if ( $page_url ) {
			$this->save_web_url( $event_id, $page_url );
			HMO_DB::log_activity( $event_id, 'page_sync', 'GWU marketing page created: ' . $page_url );
		} else {
			error_log( 'HMO Page Sync: failed to create GWU page for event ID ' . $event_id );
		}
	}

	// -------------------------------------------------------------------------
	// Cross-site REST call
	// -------------------------------------------------------------------------

	/**
	 * POST to grantwritingusa.com's WP REST API to create the marketing page.
	 * Returns the new page URL, or an empty string on failure.
	 */
	public function create_gwu_page( array $ev ): string {
		$api_base = rtrim( constant( self::CONST_API ), '/' );
		$user     = constant( self::CONST_USER );
		$pass     = constant( self::CONST_PASS );
		$parent   = defined( self::CONST_PARENT ) ? (int) constant( self::CONST_PARENT ) : 0;

		$body = array(
			'title'    => $this->build_page_title( $ev ),
			'slug'     => $this->build_page_slug( $ev ),
			'content'  => $this->build_page_content( $ev ),
			'status'   => 'publish',
			'template' => 'gwu-event-pages/templates/page-event-marketing.php',
			'meta'     => array( '_gwu_event_id' => (int) $ev['eve_id'] ),
		);

		if ( $parent > 0 ) {
			$body['parent'] = $parent;
		}

		$response = wp_remote_post( $api_base . '/pages', array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'HMO Page Sync: wp_remote_post error — ' . $response->get_error_message() );
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 201 || empty( $data['link'] ) ) {
			error_log( 'HMO Page Sync: unexpected response ' . $code . ' — ' . wp_remote_retrieve_body( $response ) );
			return '';
		}

		return esc_url_raw( $data['link'] );
	}

	/**
	 * Write the marketing page URL back to eve_web_url on the event record.
	 */
	public function save_web_url( int $event_id, string $url ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'event_details_list',
			array( 'eve_web_url' => $url ),
			array( 'eve_id'      => $event_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	// -------------------------------------------------------------------------
	// Title / slug builders
	// -------------------------------------------------------------------------

	/**
	 * "Wellston, MO, April 30-May 1, 2026 | Grant Writing Class"
	 */
	public function build_page_title( array $ev ): string {
		$city  = trim( $ev['city']  ?? '' );
		$state = trim( $ev['state'] ?? '' );

		if ( $city && $state ) {
			$location_str = $city . ', ' . $state;
		} else {
			$location_str = $this->extract_city_state( $ev['eve_location'] ?? '' );
		}

		$date_str   = $this->format_date_range( $ev['eve_start'] ?? '', $ev['eve_end'] ?? '' );
		$type_label = ( ( $ev['eve_zoom'] ?? '' ) === 'yes' ) ? 'Zoom Webinar' : 'Grant Writing Class';

		return trim( $location_str . ', ' . $date_str . ' | ' . $type_label );
	}

	/**
	 * URL-safe slug: "wellston-mo-april-2026"
	 */
	public function build_page_slug( array $ev ): string {
		$city  = trim( $ev['city']  ?? '' );
		$state = trim( $ev['state'] ?? '' );
		$start = $ev['eve_start'] ?? '';

		if ( ! $city ) {
			$parsed = $this->extract_city_state( $ev['eve_location'] ?? '' );
			$parts  = explode( ',', $parsed, 2 );
			$city   = trim( $parts[0] ?? '' );
			$state  = trim( $parts[1] ?? $state );
		}

		$month_year = $start ? date( 'F-Y', strtotime( $start ) ) : '';
		$slug_parts = array_filter( array( $city, $state, $month_year ) );
		return sanitize_title( implode( '-', $slug_parts ) );
	}

	// -------------------------------------------------------------------------
	// Page content builder
	// -------------------------------------------------------------------------

	/**
	 * Build the full HTML body for the marketing page.
	 * WordPress renders the page title separately via the_title(), so no H1 here.
	 */
	public function build_page_content( array $ev ): string {
		$city      = trim( $ev['city']             ?? '' );
		$state     = trim( $ev['state']            ?? '' );
		$zip       = trim( $ev['zip_code']         ?? '' );
		$addr1     = trim( $ev['street_address_1'] ?? '' );
		$addr2     = trim( $ev['street_address_2'] ?? '' );
		$addr3     = trim( $ev['street_address_3'] ?? '' );
		$venue     = trim( $ev['location_name']    ?? '' );
		$host      = trim( $ev['host_name']        ?? '' );
		$start     = $ev['eve_start']              ?? '';
		$end       = $ev['eve_end']                ?? '';
		$reg_url   = trim( $ev['eve_trainer_url']  ?? '' );
		$is_zoom   = ( ( $ev['eve_zoom'] ?? '' ) === 'yes' );
		$hotels    = trim( $ev['hotels']           ?? '' );
		$special   = trim( $ev['special_instructions'] ?? '' );

		$date_long  = $this->format_date_range( $start, $end );
		$hosted_by  = $host ?: $venue;

		// Address block.
		$city_state_zip = implode( ', ', array_filter( array( $city, $state ) ) );
		if ( $zip ) {
			$city_state_zip .= ' ' . $zip;
		}
		$address_lines = array_filter( array( $addr1, $addr2, $addr3, $city_state_zip ) );
		$address_html  = implode( '<br>', array_map( 'esc_html', $address_lines ) );

		// Google Maps URL.
		$map_query = urlencode( implode( ', ', array_filter( array( $addr1, $city, $state, $zip ) ) ) );
		$map_url   = 'https://maps.google.com/?q=' . $map_query;

		// Registration button HTML.
		$reg_button = '';
		if ( $reg_url ) {
			$reg_button = '<p style="margin:12px 0;"><a href="' . esc_url( $reg_url ) . '" class="gwu-reg-btn" style="display:inline-block;padding:10px 24px;background:#00509e;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;">Click here to register!</a></p>' . "\n";
		}

		// Itinerary block (differs for zoom vs in-person).
		if ( $is_zoom ) {
			$itinerary_html = '<p><strong>Date and Time:</strong> This webinar is ' . esc_html( $date_long ) . ', 9:30&ndash;4:30 ET / 8:00&ndash;3:00 MT / 7:00&ndash;2:00 PT. A Zoom link will be emailed to all registered participants prior to the event. You do not need to download any software; participation requires only a computer, tablet, or smartphone with internet access.</p>' . "\n";
			$format_html    = '<p>This is a <strong>Zoom webinar</strong>. Instruction is identical to our in-person workshops &mdash; just delivered online. Please have a reliable internet connection and a device with audio.</p>' . "\n";
		} else {
			$host_line = $hosted_by
				? '<br>Hosted by ' . esc_html( $hosted_by )
				: '';
			$addr_line = $address_html
				? '<br>' . $address_html
				: '';
			$itinerary_html = '<p><strong>Itinerary and Location:</strong> This workshop is ' . esc_html( $date_long ) . ', 9-4 both days with lunch on your own from noon to 1:20. View a <a href="' . esc_url( $map_url ) . '" target="_blank">map of the workshop location</a> and review the <a href="https://www.grantwritingusa.com/grant-writing-course-content/">learning objectives</a> for this course.' . $host_line . $addr_line . '</p>' . "\n";
			$format_html    = '<p>This is a:</p>
<p>&radic;&nbsp;<strong>grant writing class</strong><br>
&nbsp;&nbsp;&nbsp;grant management class<br>
<em>what\'s the <a href="https://www.grantwritingusa.com/difference/">difference</a>?</em></p>' . "\n";
		}

		// Hotels section.
		$hotels_html = '';
		if ( $hotels ) {
			$hotels_html = '<h2>Traveling and need lodging?</h2>' . "\n"
				. '<p>These hotels are near the training location.</p>' . "\n"
				. wp_kses_post( $hotels ) . "\n";
		}

		// Special instructions.
		$special_html = $special
			? '<p>' . wp_kses_post( $special ) . '</p>' . "\n"
			: '';

		// Assemble.
		$c  = '';
		$c .= $reg_button;
		$c .= '<h2>Welcome!</h2>' . "\n";
		$c .= '<p>If you\'re ready to learn how to find funding sources and write winning grant proposals, you\'ve come to the right place. Beginning and experienced grant writers from city, county and state agencies as well as healthcare organizations, nonprofits, K-12, colleges and universities are encouraged to attend. You <em>do not</em> need to work in the same profession as the host agency.</p>' . "\n";
		$c .= $itinerary_html;
		$c .= $format_html;
		$c .= $special_html;

		$c .= '<h2>Tuition</h2>' . "\n";
		$c .= '<p>Tuition is $525 and includes everything: two days of terrific instruction, workbook, and lifetime access to our Alumni Resource Center that\'s packed full of helpful resources and sample grant proposals.</p>' . "\n";

		$c .= '<h2>COVID Guidelines</h2>' . "\n";
		$c .= '<p>Local health and safety guidelines will be followed. If online learning is more comfortable for you, please visit our <a href="https://www.grantwritingusa.com/events.html">complete calendar of events</a> for a list of our monthly Zoom classes.</p>' . "\n";

		$c .= '<h2>CEU Credits</h2>' . "\n";
		$c .= '<p>Various CEUs and university credit are available for this class. For complete details click <a href="https://www.grantwritingusa.com/ceu-credits/">here</a>.</p>' . "\n";

		$c .= '<h2>Payment Policy</h2>' . "\n";
		$c .= '<p>Payment by credit card at the time of enrollment is preferred, however, you may pay later by check. Our registration system will auto-generate a personalized invoice/receipt for you immediately after you enroll. If you choose to pay by check, it is your responsibility to print the online invoice and guide it through your purchasing channels. We do not mail invoices. Payment by check or card is required by the workshop date unless other arrangements are made.</p>' . "\n";

		$c .= '<h2>Purchase Orders</h2>' . "\n";
		$c .= '<p>If you work for a government agency and want to pay by purchase order, when you register online choose the &ldquo;pay by check&rdquo; option. The web site will auto-generate a printable invoice. Print the invoice, give it and your purchase order to your purchasing department and they\'ll send the check. That\'s it!</p>' . "\n";

		$c .= '<h2>Cancel Policy</h2>' . "\n";
		$c .= '<p>Tuition is set regardless of method of instruction and will not be refunded if instruction occurs remotely at another time. Withdrawals are allowed up to one week prior to the workshop. Tuition refunds &mdash; less a $30 admin charge &mdash; are made by check and mailed within 5 working days of receiving your cancellation. If you cancel within one week of the workshop or if you\'re registered for a workshop and fail to show up, you are obliged to submit your tuition in full and are then prepaid for and welcome to attend any future workshop we offer within one year of the workshop you cancelled. If you register within 10 days of the class, you may cancel your registration up to 5 days after by notifying us via email at <a href="mailto:cs@grantwritingusa.com">cs@grantwritingusa.com</a>. Tuition refunds &mdash; less a $30 admin charge &mdash; are made within 5 working days of receiving your cancellation notice.</p>' . "\n";

		$c .= '<h2>Questions?</h2>' . "\n";
		$c .= '<p><a href="mailto:cs@grantwritingusa.com">Email</a> or call The Client Services Team at Grant Writing USA, at 800.814.8191, 8:00 am to 4:00 pm (PT).</p>' . "\n";

		$c .= '<h2>Ready to enroll?</h2>' . "\n";
		$c .= '<p>Great &mdash; it\'s easy!</p>' . "\n";
		$c .= $reg_button;

		$c .= $hotels_html;

		return $c;
	}

	// -------------------------------------------------------------------------
	// Private helpers (mirrors Hostlinks public-event-list.php helpers)
	// -------------------------------------------------------------------------

	private function extract_city_state( string $location ): string {
		$location = trim( $location );
		if ( preg_match( '/^([A-Za-z][A-Za-z0-9\s\/\-\.]+,\s*[A-Z]{2})\b/u', $location, $m ) ) {
			return trim( $m[1] );
		}
		return $location;
	}

	private function format_date_range( string $start, string $end ): string {
		if ( empty( $start ) ) {
			return '';
		}
		$s = date_create( $start );
		if ( ! $s ) {
			return '';
		}
		$e  = $end ? date_create( $end ) : null;
		$sm = $s->format( 'F' );
		$sd = (int) $s->format( 'j' );
		$sy = $s->format( 'Y' );

		if ( ! $e || $start === $end ) {
			return $sm . ' ' . $sd . ', ' . $sy;
		}
		$em = $e->format( 'F' );
		$ed = (int) $e->format( 'j' );
		$ey = $e->format( 'Y' );

		if ( $sm === $em && $sy === $ey ) {
			return $sm . ' ' . $sd . '-' . $ed . ', ' . $sy;
		}
		return $sm . ' ' . $sd . '-' . $em . ' ' . $ed . ', ' . $sy;
	}

	// -------------------------------------------------------------------------
	// Admin AJAX: test connection
	// -------------------------------------------------------------------------

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_test_page_sync', array( __CLASS__, 'ajax_test_connection' ) );
	}

	public static function ajax_test_connection(): void {
		check_ajax_referer( 'hmo_page_sync_test' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! self::is_configured() ) {
			wp_send_json_error( 'wp-config.php constants are not defined. See the instructions below.' );
		}

		$api_base = rtrim( constant( self::CONST_API ), '/' );
		$user     = constant( self::CONST_USER );
		$pass     = constant( self::CONST_PASS );

		// Verify credentials by calling /users/me on the primary domain.
		$response = wp_remote_get( $api_base . '/users/me', array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ),
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 200 && ! empty( $data['name'] ) ) {
			wp_send_json_success( array(
				'message' => 'Connected! Authenticated as: ' . esc_html( $data['name'] ),
			) );
		} else {
			wp_send_json_error( 'HTTP ' . $code . '. Check your Application Password and username.' );
		}
	}
}
