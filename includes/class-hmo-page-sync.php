<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles cross-site page creation/update on grantwritingusa.com when events
 * are created or regenerated in Hostlinks.  Credentials are stored as
 * wp-config.php constants so they are never written to the database.
 *
 * Required constants (add to hostlinks.grantwritingusa.com wp-config.php):
 *   define( 'GWU_PRIMARY_API',           'https://www.grantwritingusa.com/wp-json/wp/v2' );
 *   define( 'GWU_API_USER',              'event-automation' );
 *   define( 'GWU_API_PASS',              'xxxx xxxx xxxx xxxx xxxx xxxx' );
 *   define( 'GWU_EVENTS_PARENT_PAGE_ID',  0 ); // optional; 0 = top-level
 */
class HMO_Page_Sync {

	const CONST_API    = 'GWU_PRIMARY_API';
	const CONST_USER   = 'GWU_API_USER';
	const CONST_PASS   = 'GWU_API_PASS';
	const CONST_PARENT = 'GWU_EVENTS_PARENT_PAGE_ID';
	const CONST_STATUS = 'GWU_PAGE_STATUS';

	/** When false, Course Type HTML is omitted from synced body (use [event_course_type] on GWU). */
	const OPT_INCLUDE_COURSE_TYPE_BODY = 'hmo_gwu_page_include_course_type_body';

	public static function include_course_type_in_body(): bool {
		return (int) get_option( self::OPT_INCLUDE_COURSE_TYPE_BODY, 1 ) === 1;
	}

	/**
	 * Returns the post status used for newly created GWU pages.
	 * Honors GWU_PAGE_STATUS constant; defaults to 'publish'.
	 * Valid WP page statuses only: publish, draft, pending, private.
	 */
	public static function get_default_page_status(): string {
		$allowed = array( 'publish', 'draft', 'pending', 'private' );

		if ( defined( self::CONST_STATUS ) ) {
			$status = strtolower( (string) constant( self::CONST_STATUS ) );
			if ( in_array( $status, $allowed, true ) ) {
				return $status;
			}
		}
		return 'publish';
	}

	// -------------------------------------------------------------------------
	// Configuration helpers
	// -------------------------------------------------------------------------

	public static function is_configured(): bool {
		return defined( self::CONST_API )
			&& defined( self::CONST_USER )
			&& defined( self::CONST_PASS );
	}

	public static function get_config_status(): array {
		$consts = array( self::CONST_API, self::CONST_USER, self::CONST_PASS, self::CONST_PARENT, self::CONST_STATUS );
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
	 *
	 * "Today" uses this site's WordPress timezone (`current_time`). If Hostlinks and
	 * grantwritingusa.com use different timezone settings, the cutoff for "future"
	 * can disagree by up to one calendar day at boundaries.
	 */
	public function on_event_created( int $event_id, string $eve_start ): void {
		if ( ! self::is_configured() ) {
			return;
		}

		// Compare calendar dates only (eve_start may be Y-m-d or include time).
		$today = current_time( 'Y-m-d' );
		$start = strlen( $eve_start ) >= 10 ? substr( $eve_start, 0, 10 ) : $eve_start;
		if ( $start < $today ) {
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

		$result = $this->create_gwu_page( $ev );

		if ( $result ) {
			// Pass false so a manually-entered WEB URL is never overwritten on auto-creation.
			$this->save_web_url( $event_id, $result['url'], false );
			HMO_DB::upsert_event_ops( $event_id, array( 'gwu_page_id' => $result['page_id'] ) );
			HMO_DB::log_activity( $event_id, 'page_sync', 'GWU marketing page created: ' . $result['url'] );
		} else {
			error_log( 'HMO Page Sync: failed to create GWU page for event ID ' . $event_id );
		}
	}

	// -------------------------------------------------------------------------
	// Cross-site REST calls
	// -------------------------------------------------------------------------

	/**
	 * POST to grantwritingusa.com's WP REST API to create a new marketing page.
	 *
	 * @return array{url:string,page_id:int}|null  Null on failure.
	 */
	public function create_gwu_page( array $ev ): ?array {
		$api_base = rtrim( constant( self::CONST_API ), '/' );
		$user     = constant( self::CONST_USER );
		$pass     = constant( self::CONST_PASS );
		$parent   = defined( self::CONST_PARENT ) ? (int) constant( self::CONST_PARENT ) : 0;

		$body = array(
			'title'    => $this->build_page_title( $ev ),
			'slug'     => $this->build_page_slug( $ev ),
			'content'  => $this->build_page_content( $ev ),
			'status'   => self::get_default_page_status(),
			'template' => 'gwu-event-pages/templates/page-event-marketing.php',
			'meta'     => $this->build_gwu_page_meta( $ev ),
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
			error_log( 'HMO Page Sync: create — ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 201 || empty( $data['link'] ) ) {
			error_log( 'HMO Page Sync: create returned HTTP ' . $code . ' — ' . wp_remote_retrieve_body( $response ) );
			return null;
		}

		return array(
			'url'     => esc_url_raw( $data['link'] ),
			'page_id' => (int) ( $data['id'] ?? 0 ),
		);
	}

	/**
	 * POST to the existing page on grantwritingusa.com to update its content.
	 *
	 * @param int   $gwu_page_id  WP page ID on grantwritingusa.com.
	 * @param array $ev           Event row from event_details_list.
	 * @return array{url:string,page_id:int}|null  Null on failure.
	 */
	public function update_gwu_page( int $gwu_page_id, array $ev ): ?array {
		$api_base = rtrim( constant( self::CONST_API ), '/' );
		$user     = constant( self::CONST_USER );
		$pass     = constant( self::CONST_PASS );

		$body = array(
			'title'   => $this->build_page_title( $ev ),
			'content' => $this->build_page_content( $ev ),
			'meta'    => $this->build_gwu_page_meta( $ev ),
		);

		$response = wp_remote_post( $api_base . '/pages/' . $gwu_page_id, array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ),
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( 'HMO Page Sync: update ' . $gwu_page_id . ' — ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $data['link'] ) ) {
			error_log( 'HMO Page Sync: update returned HTTP ' . $code . ' for page ' . $gwu_page_id );
			return null;
		}

		return array(
			'url'     => esc_url_raw( $data['link'] ),
			'page_id' => $gwu_page_id,
		);
	}

	// -------------------------------------------------------------------------
	// DB helpers
	// -------------------------------------------------------------------------

	/**
	 * Write the marketing page URL back to eve_web_url on the event record.
	 *
	 * @param bool $overwrite When false, skips the update if eve_web_url is already populated.
	 *                        Always true for explicit admin actions (Create / Regenerate buttons).
	 *                        Pass false for automatic creation so a manually-entered URL is preserved.
	 */
	public function save_web_url( int $event_id, string $url, bool $overwrite = true ): void {
		global $wpdb;
		if ( ! $overwrite ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT eve_web_url FROM {$wpdb->prefix}event_details_list WHERE eve_id = %d",
					$event_id
				)
			);
			if ( ! empty( $existing ) ) {
				return;
			}
		}
		$wpdb->update(
			$wpdb->prefix . 'event_details_list',
			array( 'eve_web_url' => $url ),
			array( 'eve_id'      => $event_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	// -------------------------------------------------------------------------
	// GWU page meta (for [event_register_button] on grantwritingusa.com)
	// -------------------------------------------------------------------------

	/**
	 * Meta fields written to each marketing page via the WP REST API.
	 *
	 * @param array $ev Event row from event_details_list.
	 * @return array<string, mixed>
	 */
	public function build_gwu_page_meta( array $ev ): array {
		return array(
			'_gwu_event_id'         => (int) ( $ev['eve_id'] ?? 0 ),
			'_gwu_reg_url'          => esc_url_raw( trim( (string) ( $ev['eve_trainer_url'] ?? '' ) ) ),
			'_gwu_course_type_html' => $this->build_format_html( $ev ),
		);
	}

	// -------------------------------------------------------------------------
	// Title / slug builders
	// -------------------------------------------------------------------------

	public function build_page_title( array $ev ): string {
		$city  = trim( $ev['city']  ?? '' );
		$state = trim( $ev['state'] ?? '' );

		if ( $city && $state ) {
			$location_str = $city . ', ' . $state;
		} else {
			$location_str = $this->extract_city_state( $ev['eve_location'] ?? '' );
		}

		$date_str = $this->format_date_range( $ev['eve_start'] ?? '', $ev['eve_end'] ?? '' );

		// Build type label from the event's actual type (writing / management /
		// subaward) plus the delivery mode (zoom vs. in-person).
		$type_key = HMO_Page_Template::event_type_key( (int) ( $ev['eve_type'] ?? 0 ) );
		$is_zoom  = ( ( $ev['eve_zoom'] ?? '' ) === 'yes' );

		switch ( $type_key ) {
			case 'writing':
				$type_label = $is_zoom ? 'Grant Writing Zoom Webinar' : 'Grant Writing Class';
				break;
			case 'management':
				$type_label = $is_zoom ? 'Grant Management Zoom Webinar' : 'Grant Management Class';
				break;
			case 'subaward':
				$type_label = $is_zoom ? 'Managing Subawards Zoom Webinar' : 'Managing Subawards Class';
				break;
			default:
				$type_label = $is_zoom ? 'Zoom Webinar' : 'Class';
		}

		return trim( $location_str . ', ' . $date_str . ' | ' . $type_label );
	}

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

		// Include day-of-month so two events in the same city and month
		// produce distinct slugs instead of colliding and triggering WP's
		// auto-increment suffix (-2, -3, …) which makes admin listings
		// visually indistinguishable.
		$date_part = $start ? date( 'F-j-Y', strtotime( $start ) ) : '';

		$slug_parts = array_filter( array( $city, $state, $date_part ) );
		return sanitize_title( implode( '-', $slug_parts ) );
	}

	// -------------------------------------------------------------------------
	// Page content builder — uses HMO_Page_Template for editable sections
	// -------------------------------------------------------------------------

	public function build_page_content( array $ev ): string {
		$city    = trim( $ev['city']             ?? '' );
		$state   = trim( $ev['state']            ?? '' );
		$zip     = trim( $ev['zip_code']         ?? '' );
		$addr1   = trim( $ev['street_address_1'] ?? '' );
		$start   = $ev['eve_start']              ?? '';
		$end     = $ev['eve_end']                ?? '';
		$is_zoom = ( ( $ev['eve_zoom'] ?? '' ) === 'yes' );
		$hotels  = trim( $ev['hotels']           ?? '' );

		// Resolve event-type template context (writing / management / subaward / '').
		$type_key = HMO_Page_Template::event_type_key( (int) ( $ev['eve_type'] ?? 0 ) );

		$date_long = $this->format_date_range( $start, $end );

		// Google Maps URL.
		$map_query = urlencode( implode( ', ', array_filter( array( $addr1, $city, $state, $zip ) ) ) );
		$map_url   = 'https://maps.google.com/?q=' . $map_query;

		// Legacy tokens stripped from saved templates that still reference host/address in itinerary body.
		$legacy_itinerary_tokens = array(
			'{{HOST_LINE}}'  => '',
			'{{ADDR_BLOCK}}' => '',
		);

		$format_key  = $is_zoom ? 'format_zoom' : 'format_inperson';
		$format_html = HMO_Page_Template::is_section_visible( $format_key )
			? $this->build_format_html( $ev )
			: '';

		// Hotels section (dynamic — not template-editable).
		$hotels_html = $this->render_hotels_html( $hotels );

		$special_html = $this->build_special_instructions_html( $ev );

		// Assemble using template sections for all static boilerplate.
		// Register buttons: use [event_register_button] in the DIVI layout (GWU Event Pages 1.2.21+).
		$c  = '';
		$c .= $this->build_host_venue_html( $ev );
		$c .= $this->append_template_heading_section( 'Welcome!', 'welcome', array(), $type_key );

		if ( $is_zoom ) {
			$c .= $this->append_template_heading_section(
				'Date and Time',
				'itinerary_zoom',
				array_merge( array( '{{DATE_LONG}}' => esc_html( $date_long ) ), $legacy_itinerary_tokens ),
				$type_key
			);
		} else {
			$c .= $this->append_template_heading_section(
				'Itinerary and Location',
				'itinerary_inperson',
				array_merge(
					array(
						'{{DATE_LONG}}' => esc_html( $date_long ),
						'{{MAP_URL}}'   => esc_url( $map_url ),
					),
					$legacy_itinerary_tokens
				),
				$type_key
			);
		}

		if ( self::include_course_type_in_body() ) {
			$c .= $this->append_template_section( $format_html );
		}
		$c .= $special_html;

		$c .= $this->append_template_heading_section( 'Tuition', 'tuition', array(), $type_key );
		$c .= $this->append_template_heading_section( 'COVID Guidelines', 'covid', array(), $type_key );
		$c .= $this->append_template_heading_section( 'CEU Credits', 'ceu', array(), $type_key );
		$c .= $this->append_template_heading_section( 'Payment Policy', 'payment', array(), $type_key );
		$c .= $this->append_template_heading_section( 'Purchase Orders', 'purchase_orders', array(), $type_key );
		$c .= $this->append_template_heading_section( 'Cancel Policy', 'cancel', array(), $type_key );
		$c .= $this->append_template_heading_section( 'Questions?', 'questions', array(), $type_key );

		$c .= '<h2>Ready to enroll?</h2>' . "\n";
		$c .= '<p>Great &mdash; it\'s easy!</p>' . "\n";

		$c .= $hotels_html;

		return $c;
	}

	/**
	 * Outputs H2 + template section, or nothing when the section is hidden in settings.
	 */
	private function append_template_heading_section( string $heading, string $section_key, array $tokens, string $type_key ): string {
		if ( ! HMO_Page_Template::is_section_visible( $section_key ) ) {
			return '';
		}
		$body = HMO_Page_Template::render_section( $section_key, $tokens, $type_key );
		return '<h2>' . esc_html( $heading ) . '</h2>' . "\n" . $body;
	}

	private function append_template_section( string $html ): string {
		return $html;
	}

	/**
	 * Course Type block (Grant Writing / Management / Subaward, Zoom or in-person).
	 *
	 * @param array $ev Event row from event_details_list.
	 */
	public function build_format_html( array $ev ): string {
		$type_key = HMO_Page_Template::event_type_key( (int) ( $ev['eve_type'] ?? 0 ) );
		$is_zoom  = ( ( $ev['eve_zoom'] ?? '' ) === 'yes' );

		if ( $is_zoom ) {
			return HMO_Page_Template::render_section( 'format_zoom', array(), $type_key );
		}

		return HMO_Page_Template::render_section( 'format_inperson', array(), $type_key );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Host &amp; venue block above Welcome (in-person only), from Hostlinks Edit Event fields.
	 *
	 * @param array $ev Event row from event_details_list.
	 */
	private function build_host_venue_html( array $ev ): string {
		if ( ( $ev['eve_zoom'] ?? '' ) === 'yes' ) {
			return '';
		}

		$displayed = trim( (string) ( $ev['displayed_as'] ?? '' ) );
		$host_name = trim( (string) ( $ev['host_name'] ?? '' ) );
		$location  = trim( (string) ( $ev['location_name'] ?? '' ) );

		$addr_lines = array_filter( array(
			trim( (string) ( $ev['street_address_1'] ?? '' ) ),
			trim( (string) ( $ev['street_address_2'] ?? '' ) ),
			trim( (string) ( $ev['street_address_3'] ?? '' ) ),
		) );

		$city_line = trim(
			trim( (string) ( $ev['city'] ?? '' ) ) . ', ' .
			trim( (string) ( $ev['state'] ?? '' ) ) . ' ' .
			trim( (string) ( $ev['zip_code'] ?? '' ) ),
			', '
		);

		$lines = array();
		if ( $displayed !== '' ) {
			$lines[] = esc_html( $displayed );
		} elseif ( $host_name !== '' ) {
			$lines[] = 'Hosted by ' . esc_html( $host_name );
		}
		if ( $location !== '' ) {
			$lines[] = esc_html( $location );
		}
		foreach ( $addr_lines as $line ) {
			$lines[] = esc_html( $line );
		}
		if ( $city_line !== '' ) {
			$lines[] = esc_html( $city_line );
		}

		if ( $lines === array() ) {
			return '';
		}

		return '<p>' . implode( '<br>', $lines ) . '</p>' . "\n";
	}

	/**
	 * Special instructions and optional parking file link from Hostlinks Additional Details.
	 *
	 * @param array $ev Event row from event_details_list.
	 */
	private function build_special_instructions_html( array $ev ): string {
		$special = trim( (string) ( $ev['special_instructions'] ?? '' ) );
		$parking = esc_url( trim( (string) ( $ev['parking_file_url'] ?? '' ) ) );

		if ( $special === '' && $parking === '' ) {
			return '';
		}

		$html = '';
		if ( $special !== '' ) {
			$html .= '<p>' . wp_kses_post( $special ) . '</p>' . "\n";
		}
		if ( $parking !== '' ) {
			$html .= '<p><a href="' . esc_url( $parking ) . '" target="_blank" rel="noopener">Parking / instructions (PDF)</a></p>' . "\n";
		}
		return $html;
	}

	/**
	 * Renders the hotels JSON column as readable HTML for GWU marketing pages.
	 *
	 * @param string $hotels_raw JSON array from event_details_list.hotels.
	 */
	private function render_hotels_html( string $hotels_raw ): string {
		$hotels_raw = trim( $hotels_raw );
		if ( $hotels_raw === '' ) {
			return '';
		}

		$hotels = json_decode( $hotels_raw, true );
		if ( ! is_array( $hotels ) || $hotels === array() ) {
			// Fallback: already HTML from a legacy import.
			if ( str_contains( $hotels_raw, '<' ) ) {
				return '<h2>Traveling and need lodging?</h2>' . "\n"
					. '<p>These hotels are near the training location.</p>' . "\n"
					. wp_kses_post( $hotels_raw ) . "\n";
			}
			return '';
		}

		$blocks = '';
		foreach ( $hotels as $h ) {
			if ( ! is_array( $h ) ) {
				continue;
			}
			$name    = trim( (string) ( $h['name'] ?? '' ) );
			$phone   = trim( (string) ( $h['phone'] ?? '' ) );
			$address = trim( (string) ( $h['address'] ?? '' ) );
			$url     = trim( (string) ( $h['url'] ?? '' ) );

			if ( $name === '' && $address === '' && $phone === '' ) {
				continue;
			}

			$blocks .= '<div class="gwu-hotel">' . "\n";
			if ( $name !== '' ) {
				if ( $url !== '' ) {
					$blocks .= '<p class="gwu-hotel-name"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">'
						. esc_html( $name ) . '</a></p>' . "\n";
				} else {
					$blocks .= '<p class="gwu-hotel-name"><strong>' . esc_html( $name ) . '</strong></p>' . "\n";
				}
			}
			if ( $phone !== '' ) {
				$blocks .= '<p class="gwu-hotel-phone">' . esc_html( $phone ) . '</p>' . "\n";
			}
			if ( $address !== '' ) {
				$blocks .= '<p class="gwu-hotel-address">' . esc_html( $address ) . '</p>' . "\n";
			}
			$blocks .= '</div>' . "\n";
		}

		if ( $blocks === '' ) {
			return '';
		}

		return '<h2>Traveling and need lodging?</h2>' . "\n"
			. '<p>These hotels are near the training location.</p>' . "\n"
			. '<div class="gwu-hotels">' . "\n"
			. $blocks
			. '</div>' . "\n";
	}

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

	/**
	 * Update or create the GWU marketing page for an event row; persist WEB URL and gwu_page_id in ops when needed.
	 *
	 * @param int   $event_id             Hostlinks event ID.
	 * @param array $ev                   Row from event_details_list.
	 * @param bool  $force_upsert_page_id When true, always write gwu_page_id (admin single-event regenerate). When false, only upsert gwu_page_id after a create (bulk batch).
	 * @return array{url:string,page_id:int}|null Null on failure.
	 */
	private function sync_event_row_to_gwu( int $event_id, array $ev, bool $force_upsert_page_id, bool $overwrite_web_url = true ): ?array {
		$page_id = HMO_DB::get_event_gwu_page_id( $event_id );
		$result  = $page_id > 0
			? $this->update_gwu_page( $page_id, $ev )
			: $this->create_gwu_page( $ev );

		if ( ! $result ) {
			return null;
		}

		$this->save_web_url( $event_id, $result['url'], $overwrite_web_url );

		if ( $force_upsert_page_id || $page_id === 0 ) {
			HMO_DB::upsert_event_ops( $event_id, array( 'gwu_page_id' => $result['page_id'] ) );
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Admin AJAX
	// -------------------------------------------------------------------------

	/** Number of events processed per batch AJAX request in bulk regeneration. */
	const BULK_BATCH_SIZE = 3;

	const BULK_REGEN_FUTURE = 'future';
	const BULK_REGEN_PAST   = 'past';

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_test_page_sync',        array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_hmo_regenerate_event_page', array( __CLASS__, 'ajax_regenerate_event_page' ) );
		add_action( 'wp_ajax_hmo_bulk_regen_init',       array( __CLASS__, 'ajax_bulk_regen_init' ) );
		add_action( 'wp_ajax_hmo_bulk_regen_batch',      array( __CLASS__, 'ajax_bulk_regen_batch' ) );
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

		// Add a cache-busting param and no-cache headers so caching plugins on the
		// primary domain don't serve a stale unauthenticated response.
		$response = wp_remote_get( $api_base . '/users/me?_=' . time(), array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $user . ':' . $pass ),
				'Cache-Control' => 'no-cache',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code === 200 && ! empty( $data['slug'] ) ) {
			wp_send_json_success( array(
				'message' => 'Connected! Authenticated as: ' . esc_html( $data['name'] ?? $data['slug'] ),
			) );
		} elseif ( $code === 401 ) {
			wp_send_json_error( 'HTTP 401 — Authentication failed. Verify GWU_API_USER matches the WordPress username whose profile has the Application Password, and that GWU_API_PASS is the generated password value (not the password name).' );
		} elseif ( $code === 200 ) {
			// 200 but no user object — likely a caching layer or redirect served HTML.
			$snippet = mb_substr( wp_strip_all_tags( $body ), 0, 120 );
			wp_send_json_error( 'HTTP 200 but no user data returned — a caching layer may be intercepting the request. Response preview: ' . esc_html( $snippet ) );
		} else {
			wp_send_json_error( 'HTTP ' . $code . ' — unexpected response. Check GWU_PRIMARY_API URL and that the primary domain REST API is reachable.' );
		}
	}

	/**
	 * Regenerate (or create) the GWU marketing page for a single event.
	 * Triggered from the Event Detail admin page.
	 */
	public static function ajax_regenerate_event_page(): void {
		check_ajax_referer( 'hmo_regenerate_page' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! self::is_configured() ) {
			wp_send_json_error( 'GWU API constants are not configured. See Settings → GWU Page Sync.' );
		}

		$event_id = (int) ( $_POST['event_id'] ?? 0 );
		if ( ! $event_id ) {
			wp_send_json_error( 'Invalid event ID.' );
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
			wp_send_json_error( 'Event not found.' );
		}

		$instance = new self();
		$page_id  = HMO_DB::get_event_gwu_page_id( $event_id );
		$result   = $instance->sync_event_row_to_gwu( $event_id, $ev, true );

		if ( ! $result ) {
			wp_send_json_error( 'Failed to sync page. Check the server error log for details.' );
		}

		HMO_DB::log_activity( $event_id, 'page_sync', 'GWU page regenerated: ' . $result['url'] );

		wp_send_json_success( array(
			'url'     => $result['url'],
			'page_id' => $result['page_id'],
			'message' => $page_id > 0 ? 'Page updated successfully.' : 'New page created successfully.',
		) );
	}

	/**
	 * Validates bulk regen scope from AJAX (future vs past linked pages).
	 */
	/** Whether bulk regen should overwrite eve_web_url (checkbox; default false). */
	private static function bulk_regen_overwrite_web_url_from_request(): bool {
		return ! empty( $_POST['overwrite_web_url'] );
	}

	private static function sanitize_bulk_regen_scope( string $raw ): string {
		$scope = sanitize_key( $raw );
		if ( ! in_array( $scope, array( self::BULK_REGEN_FUTURE, self::BULK_REGEN_PAST ), true ) ) {
			return self::BULK_REGEN_FUTURE;
		}
		return $scope;
	}

	/**
	 * Event IDs eligible for bulk GWU page regeneration.
	 *
	 * Future: eve_start on or after today. Past: eve_start before today.
	 * Both require gwu_page_id > 0 (only pages already linked in Marketing Ops).
	 *
	 * @return int[]
	 */
	public static function get_bulk_regen_event_ids( string $scope ): array {
		global $wpdb;

		$today = current_time( 'Y-m-d' );
		$is_past = ( $scope === self::BULK_REGEN_PAST );

		$sql = "SELECT ops.hostlinks_event_id
		 FROM {$wpdb->prefix}hmo_event_ops ops
		 INNER JOIN {$wpdb->prefix}event_details_list ev
		     ON ops.hostlinks_event_id = ev.eve_id
		 WHERE ops.gwu_page_id > 0
		   AND ev.eve_start " . ( $is_past ? '<' : '>=' ) . ' %s
		 ORDER BY ev.eve_start ' . ( $is_past ? 'DESC' : 'ASC' );

		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $today ) );

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Step 1 of bulk regeneration — return the list of candidate event IDs.
	 *
	 * POST regen_scope: `future` (default) or `past`. Only events with gwu_page_id > 0.
	 * Future batches may create a page if the stored ID is missing (drift); past batches update only.
	 */
	public static function ajax_bulk_regen_init(): void {
		check_ajax_referer( 'hmo_bulk_regen' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! self::is_configured() ) {
			wp_send_json_error( 'GWU API constants are not configured.' );
		}

		$scope     = self::sanitize_bulk_regen_scope( (string) ( $_POST['regen_scope'] ?? self::BULK_REGEN_FUTURE ) );
		$event_ids = self::get_bulk_regen_event_ids( $scope );

		wp_send_json_success( array(
			'event_ids'  => $event_ids,
			'total'      => count( $event_ids ),
			'batch_size' => self::BULK_BATCH_SIZE,
			'scope'      => $scope,
		) );
	}

	/**
	 * Step 2 of bulk regeneration — process a single batch of event IDs.
	 *
	 * The client posts an array of event_ids; we process at most
	 * BULK_BATCH_SIZE per call and return a per-event result summary so the
	 * UI can update a progress bar and display any errors.
	 */
	public static function ajax_bulk_regen_batch(): void {
		check_ajax_referer( 'hmo_bulk_regen' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! self::is_configured() ) {
			wp_send_json_error( 'GWU API constants are not configured.' );
		}

		// Best-effort: raise the per-request time budget so a slow remote
		// page update doesn't kill the batch mid-loop.  Some hosts disable
		// set_time_limit — suppress failures silently.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 );
		}

		$scope            = self::sanitize_bulk_regen_scope( (string) ( $_POST['regen_scope'] ?? self::BULK_REGEN_FUTURE ) );
		$overwrite_web_url = self::bulk_regen_overwrite_web_url_from_request();

		$raw = $_POST['event_ids'] ?? array();
		if ( ! is_array( $raw ) ) {
			wp_send_json_error( 'Invalid event_ids payload.' );
		}

		$event_ids = array_values( array_filter(
			array_map( 'intval', $raw ),
			function ( $id ) { return $id > 0; }
		) );
		$event_ids = array_slice( $event_ids, 0, self::BULK_BATCH_SIZE );

		if ( empty( $event_ids ) ) {
			wp_send_json_success( array(
				'processed' => 0,
				'updated'   => 0,
				'failed'    => 0,
				'errors'    => array(),
			) );
		}

		global $wpdb;
		$instance = new self();
		$updated  = 0;
		$failed   = 0;
		$errors   = array();

		foreach ( $event_ids as $event_id ) {
			$ev = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}event_details_list WHERE eve_id = %d",
					$event_id
				),
				ARRAY_A
			);

			if ( empty( $ev ) ) {
				$failed++;
				$errors[] = array(
					'event_id' => $event_id,
					'error'    => 'Event not found',
				);
				continue;
			}

			if ( $scope === self::BULK_REGEN_PAST ) {
				$gwu_page_id = HMO_DB::get_event_gwu_page_id( $event_id );
				if ( $gwu_page_id <= 0 ) {
					$failed++;
					$errors[] = array(
						'event_id' => $event_id,
						'error'    => 'No linked GWU page',
					);
					continue;
				}
				$result = $instance->update_gwu_page( $gwu_page_id, $ev );
				if ( $result ) {
					$instance->save_web_url( $event_id, $result['url'], $overwrite_web_url );
				}
			} else {
				$result = $instance->sync_event_row_to_gwu( $event_id, $ev, false, $overwrite_web_url );
			}

			if ( $result ) {
				$log_msg = $scope === self::BULK_REGEN_PAST
					? 'GWU page bulk regenerated (past).'
					: 'GWU page bulk regenerated.';
				HMO_DB::log_activity( $event_id, 'page_sync', $log_msg );
				$updated++;
			} else {
				$failed++;
				$errors[] = array(
					'event_id' => $event_id,
					'error'    => 'Sync failed (see server error log)',
				);
			}
		}

		wp_send_json_success( array(
			'processed' => count( $event_ids ),
			'updated'   => $updated,
			'failed'    => $failed,
			'errors'    => $errors,
		) );
	}
}
