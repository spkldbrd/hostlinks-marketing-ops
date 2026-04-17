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
 *   define( 'GWU_PRIMARY_API',           'https://grantwritingusa.com/wp-json/wp/v2' );
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
	 */
	public function on_event_created( int $event_id, string $eve_start ): void {
		if ( ! self::is_configured() ) {
			return;
		}

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

		$month_year = $start ? date( 'F-Y', strtotime( $start ) ) : '';
		$slug_parts = array_filter( array( $city, $state, $month_year ) );
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
		$addr2   = trim( $ev['street_address_2'] ?? '' );
		$addr3   = trim( $ev['street_address_3'] ?? '' );
		$venue   = trim( $ev['location_name']    ?? '' );
		$host    = trim( $ev['host_name']        ?? '' );
		$start   = $ev['eve_start']              ?? '';
		$end     = $ev['eve_end']                ?? '';
		$reg_url = trim( $ev['eve_trainer_url']  ?? '' );
		$is_zoom = ( ( $ev['eve_zoom'] ?? '' ) === 'yes' );
		$hotels  = trim( $ev['hotels']           ?? '' );
		$special = trim( $ev['special_instructions'] ?? '' );

		// Resolve event-type template context (writing / management / subaward / '').
		$type_key = HMO_Page_Template::event_type_key( (int) ( $ev['eve_type'] ?? 0 ) );

		$date_long = $this->format_date_range( $start, $end );
		$hosted_by = $host ?: $venue;

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

		// Token values for itinerary sections.
		$host_line  = $hosted_by ? '<br>Hosted by ' . esc_html( $hosted_by ) : '';
		$addr_block = $address_html ? '<br>' . $address_html : '';

		// Dynamic (token-substituted) and static (template-driven) sections.
		if ( $is_zoom ) {
			$itinerary_html = HMO_Page_Template::render_section( 'itinerary_zoom', array(
				'{{DATE_LONG}}' => esc_html( $date_long ),
			), $type_key );
			$format_html = HMO_Page_Template::render_section( 'format_zoom', array(), $type_key );
		} else {
			$itinerary_html = HMO_Page_Template::render_section( 'itinerary_inperson', array(
				'{{DATE_LONG}}'  => esc_html( $date_long ),
				'{{MAP_URL}}'    => esc_url( $map_url ),
				'{{HOST_LINE}}' => $host_line,
				'{{ADDR_BLOCK}}' => $addr_block,
			), $type_key );
			$format_html = HMO_Page_Template::render_section( 'format_inperson', array(), $type_key );
		}

		// Hotels section (dynamic — not template-editable).
		$hotels_html = '';
		if ( $hotels ) {
			$hotels_html = '<h2>Traveling and need lodging?</h2>' . "\n"
				. '<p>These hotels are near the training location.</p>' . "\n"
				. wp_kses_post( $hotels ) . "\n";
		}

		// Special instructions (dynamic).
		$special_html = $special
			? '<p>' . wp_kses_post( $special ) . '</p>' . "\n"
			: '';

		// Assemble using template sections for all static boilerplate.
		$c  = '';
		$c .= $reg_button;
		$c .= '<h2>Welcome!</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'welcome', array(), $type_key );
		$c .= $itinerary_html;
		$c .= $format_html;
		$c .= $special_html;

		$c .= '<h2>Tuition</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'tuition', array(), $type_key );

		$c .= '<h2>COVID Guidelines</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'covid', array(), $type_key );

		$c .= '<h2>CEU Credits</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'ceu', array(), $type_key );

		$c .= '<h2>Payment Policy</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'payment', array(), $type_key );

		$c .= '<h2>Purchase Orders</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'purchase_orders', array(), $type_key );

		$c .= '<h2>Cancel Policy</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'cancel', array(), $type_key );

		$c .= '<h2>Questions?</h2>' . "\n";
		$c .= HMO_Page_Template::render_section( 'questions', array(), $type_key );

		$c .= '<h2>Ready to enroll?</h2>' . "\n";
		$c .= '<p>Great &mdash; it\'s easy!</p>' . "\n";
		$c .= $reg_button;

		$c .= $hotels_html;

		return $c;
	}

	// -------------------------------------------------------------------------
	// Private helpers
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
	// Admin AJAX
	// -------------------------------------------------------------------------

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_test_page_sync',       array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_hmo_regenerate_event_page', array( __CLASS__, 'ajax_regenerate_event_page' ) );
		add_action( 'wp_ajax_hmo_bulk_regenerate_pages', array( __CLASS__, 'ajax_bulk_regenerate_pages' ) );
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
		$ops      = HMO_DB::get_event_ops( $event_id );
		$page_id  = (int) ( $ops->gwu_page_id ?? 0 );

		if ( $page_id > 0 ) {
			$result = $instance->update_gwu_page( $page_id, $ev );
		} else {
			$result = $instance->create_gwu_page( $ev );
		}

		if ( ! $result ) {
			wp_send_json_error( 'Failed to sync page. Check the server error log for details.' );
		}

		$instance->save_web_url( $event_id, $result['url'] );
		HMO_DB::upsert_event_ops( $event_id, array( 'gwu_page_id' => $result['page_id'] ) );
		HMO_DB::log_activity( $event_id, 'page_sync', 'GWU page regenerated: ' . $result['url'] );

		wp_send_json_success( array(
			'url'     => $result['url'],
			'page_id' => $result['page_id'],
			'message' => $page_id > 0 ? 'Page updated successfully.' : 'New page created successfully.',
		) );
	}

	/**
	 * Regenerate GWU marketing pages for all future events that already have
	 * a gwu_page_id.  Triggered from the Page Template admin tab.
	 */
	public static function ajax_bulk_regenerate_pages(): void {
		check_ajax_referer( 'hmo_bulk_regen' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! self::is_configured() ) {
			wp_send_json_error( 'GWU API constants are not configured.' );
		}

		global $wpdb;

		// All future events that have a gwu_page_id set.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ops.hostlinks_event_id, ops.gwu_page_id
				 FROM {$wpdb->prefix}hmo_event_ops ops
				 INNER JOIN {$wpdb->prefix}event_details_list ev
				     ON ops.hostlinks_event_id = ev.eve_id
				 WHERE ops.gwu_page_id > 0
				   AND ev.eve_start >= %s",
				current_time( 'Y-m-d' )
			)
		);

		if ( empty( $rows ) ) {
			wp_send_json_success( array( 'message' => 'No future event pages found to regenerate.' ) );
		}

		$instance = new self();
		$updated  = 0;
		$failed   = 0;

		foreach ( $rows as $row ) {
			$ev = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}event_details_list WHERE eve_id = %d",
					(int) $row->hostlinks_event_id
				),
				ARRAY_A
			);

			if ( empty( $ev ) ) {
				$failed++;
				continue;
			}

			$result = $instance->update_gwu_page( (int) $row->gwu_page_id, $ev );

			if ( $result ) {
				$instance->save_web_url( (int) $row->hostlinks_event_id, $result['url'] );
				HMO_DB::log_activity( (int) $row->hostlinks_event_id, 'page_sync', 'GWU page bulk regenerated.' );
				$updated++;
			} else {
				$failed++;
			}
		}

		$msg = sprintf( '%d page(s) regenerated.', $updated );
		if ( $failed > 0 ) {
			$msg .= sprintf( ' %d failed — check error log.', $failed );
		}

		wp_send_json_success( array( 'message' => $msg ) );
	}
}
