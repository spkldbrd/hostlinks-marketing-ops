<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the per-section page content templates used when auto-creating
 * GWU marketing pages.  Each section is stored as a WP option so admins
 * can edit the boilerplate text without touching PHP.
 *
 * Templates are scoped to four event-type contexts:
 *   default    — fallback for any event type that has no custom template
 *   writing    — Grant Writing events (eve_type = 1)
 *   management — Grant Management events (eve_type = 2)
 *   subaward   — Subaward events (eve_type = 3)
 *
 * Option key format:
 *   Default context : hmo_page_tmpl_{section}
 *   Type context    : hmo_page_tmpl_{type}_{section}   e.g. hmo_page_tmpl_writing_welcome
 *
 * At render time get_section_content() checks the type-specific option first,
 * then falls back to the default option, then to the hard-coded default string.
 *
 * Token reference — tokens replaced at render time with live event data:
 *   {{DATE_LONG}}   — Formatted date range, e.g. "April 30-May 1, 2026"
 *   {{MAP_URL}}     — Google Maps URL for the venue address
 *   {{HOST_LINE}}   — HTML fragment: "<br>Hosted by {Name}" — or empty
 *   {{ADDR_BLOCK}}  — HTML fragment: "<br>Street<br>City, ST ZIP" — or empty
 *   {{CITY_STATE}}  — "City, ST" extracted from the event location
 */
class HMO_Page_Template {

	const OPT_PREFIX   = 'hmo_page_tmpl_';
	const TYPE_DEFAULT = 'default';

	// -------------------------------------------------------------------------
	// Event type registry
	// -------------------------------------------------------------------------

	/**
	 * Returns all supported template context keys and their display labels.
	 */
	public static function get_event_types(): array {
		return array(
			'default'    => 'Default (All Types)',
			'writing'    => 'Writing',
			'management' => 'Management',
			'subaward'   => 'Subaward',
		);
	}

	/**
	 * Maps an eve_type integer to a template context key.
	 * Returns '' (treated as default) for unknown types.
	 */
	public static function event_type_key( int $eve_type ): string {
		$map = array(
			1 => 'writing',
			2 => 'management',
			3 => 'subaward',
		);
		return $map[ $eve_type ] ?? '';
	}

	// -------------------------------------------------------------------------
	// Section registry
	// -------------------------------------------------------------------------

	public static function get_sections(): array {
		return array(
			'welcome' => array(
				'label'       => 'Welcome',
				'description' => 'Opening paragraph shown to all visitors.',
				'tokens'      => array(),
			),
			'itinerary_inperson' => array(
				'label'       => 'Itinerary &amp; Location (In-Person)',
				'description' => 'Shown for in-person workshops only.',
				'tokens'      => array(
					'{{DATE_LONG}}'  => 'Formatted date range',
					'{{MAP_URL}}'    => 'Google Maps link for the venue',
					'{{HOST_LINE}}' => '"&lt;br&gt;Hosted by ..." — or empty',
					'{{ADDR_BLOCK}}' => '"&lt;br&gt;Street&lt;br&gt;City, ST ZIP" — or empty',
				),
			),
			'itinerary_zoom' => array(
				'label'       => 'Itinerary &amp; Date/Time (Zoom)',
				'description' => 'Shown for Zoom webinars only.',
				'tokens'      => array(
					'{{DATE_LONG}}' => 'Formatted date range',
				),
			),
			'format_inperson' => array(
				'label'       => 'Course Type (In-Person)',
				'description' => 'Grant writing vs. management selection shown for in-person events.',
				'tokens'      => array(),
			),
			'format_zoom' => array(
				'label'       => 'Course Type (Zoom)',
				'description' => 'Course format description for Zoom webinars.',
				'tokens'      => array(),
			),
			'tuition' => array(
				'label'       => 'Tuition',
				'description' => 'Tuition amount and inclusions.',
				'tokens'      => array(),
			),
			'covid' => array(
				'label'       => 'COVID Guidelines',
				'description' => 'Health and safety notice.',
				'tokens'      => array(),
			),
			'ceu' => array(
				'label'       => 'CEU Credits',
				'description' => 'Information about continuing education units.',
				'tokens'      => array(),
			),
			'payment' => array(
				'label'       => 'Payment Policy',
				'description' => 'Credit card and check payment instructions.',
				'tokens'      => array(),
			),
			'purchase_orders' => array(
				'label'       => 'Purchase Orders',
				'description' => 'Instructions for government agencies paying by PO.',
				'tokens'      => array(),
			),
			'cancel' => array(
				'label'       => 'Cancel Policy',
				'description' => 'Withdrawal and refund terms.',
				'tokens'      => array(),
			),
			'questions' => array(
				'label'       => 'Questions / Contact',
				'description' => 'How to reach the client services team.',
				'tokens'      => array(),
			),
		);
	}

	// -------------------------------------------------------------------------
	// Defaults
	// -------------------------------------------------------------------------

	public static function get_default( string $key ): string {
		$defaults = array(

			'welcome' =>
				'<p>If you\'re ready to learn how to find funding sources and write winning grant proposals, you\'ve come to the right place. Beginning and experienced grant writers from city, county and state agencies as well as healthcare organizations, nonprofits, K-12, colleges and universities are encouraged to attend. You <em>do not</em> need to work in the same profession as the host agency.</p>',

			'itinerary_inperson' =>
				'<p><strong>Itinerary and Location:</strong> This workshop is {{DATE_LONG}}, 9-4 both days with lunch on your own from noon to 1:20. View a <a href="{{MAP_URL}}" target="_blank">map of the workshop location</a> and review the <a href="https://www.grantwritingusa.com/grant-writing-course-content/">learning objectives</a> for this course.{{HOST_LINE}}{{ADDR_BLOCK}}</p>',

			'itinerary_zoom' =>
				'<p><strong>Date and Time:</strong> This webinar is {{DATE_LONG}}, 9:30&ndash;4:30 ET / 8:00&ndash;3:00 MT / 7:00&ndash;2:00 PT. A Zoom link will be emailed to all registered participants prior to the event. You do not need to download any software; participation requires only a computer, tablet, or smartphone with internet access.</p>',

			'format_inperson' =>
				'<p>This is a:</p>' . "\n" .
				'<p>&radic;&nbsp;<strong>grant writing class</strong><br>' . "\n" .
				'&nbsp;&nbsp;&nbsp;grant management class<br>' . "\n" .
				'<em>what\'s the <a href="https://www.grantwritingusa.com/difference/">difference</a>?</em></p>',

			'format_zoom' =>
				'<p>This is a <strong>Zoom webinar</strong>. Instruction is identical to our in-person workshops &mdash; just delivered online. Please have a reliable internet connection and a device with audio.</p>',

			'tuition' =>
				'<p>Tuition is $525 and includes everything: two days of terrific instruction, workbook, and lifetime access to our Alumni Resource Center that\'s packed full of helpful resources and sample grant proposals.</p>',

			'covid' =>
				'<p>Local health and safety guidelines will be followed. If online learning is more comfortable for you, please visit our <a href="https://www.grantwritingusa.com/events.html">complete calendar of events</a> for a list of our monthly Zoom classes.</p>',

			'ceu' =>
				'<p>Various CEUs and university credit are available for this class. For complete details click <a href="https://www.grantwritingusa.com/ceu-credits/">here</a>.</p>',

			'payment' =>
				'<p>Payment by credit card at the time of enrollment is preferred, however, you may pay later by check. Our registration system will auto-generate a personalized invoice/receipt for you immediately after you enroll. If you choose to pay by check, it is your responsibility to print the online invoice and guide it through your purchasing channels. We do not mail invoices. Payment by check or card is required by the workshop date unless other arrangements are made.</p>',

			'purchase_orders' =>
				'<p>If you work for a government agency and want to pay by purchase order, when you register online choose the &ldquo;pay by check&rdquo; option. The web site will auto-generate a printable invoice. Print the invoice, give it and your purchase order to your purchasing department and they\'ll send the check. That\'s it!</p>',

			'cancel' =>
				'<p>Tuition is set regardless of method of instruction and will not be refunded if instruction occurs remotely at another time. Withdrawals are allowed up to one week prior to the workshop. Tuition refunds &mdash; less a $30 admin charge &mdash; are made by check and mailed within 5 working days of receiving your cancellation. If you cancel within one week of the workshop or if you\'re registered for a workshop and fail to show up, you are obliged to submit your tuition in full and are then prepaid for and welcome to attend any future workshop we offer within one year of the workshop you cancelled. If you register within 10 days of the class, you may cancel your registration up to 5 days after by notifying us via email at <a href="mailto:cs@grantwritingusa.com">cs@grantwritingusa.com</a>. Tuition refunds &mdash; less a $30 admin charge &mdash; are made within 5 working days of receiving your cancellation notice.</p>',

			'questions' =>
				'<p><a href="mailto:cs@grantwritingusa.com">Email</a> or call The Client Services Team at Grant Writing USA, at 800.814.8191, 8:00 am to 4:00 pm (PT).</p>',
		);

		return $defaults[ $key ] ?? '';
	}

	// -------------------------------------------------------------------------
	// Option key helper
	// -------------------------------------------------------------------------

	/**
	 * Returns the WP option name for a section, respecting the type context.
	 *
	 * @param string $section_key Section key (e.g. 'welcome').
	 * @param string $type_key    Context key ('writing', 'management', 'subaward', or '' / 'default').
	 */
	public static function get_option_key( string $section_key, string $type_key = '' ): string {
		if ( $type_key && $type_key !== self::TYPE_DEFAULT ) {
			return self::OPT_PREFIX . sanitize_key( $type_key ) . '_' . $section_key;
		}
		return self::OPT_PREFIX . $section_key;
	}

	// -------------------------------------------------------------------------
	// Read / write
	// -------------------------------------------------------------------------

	/**
	 * Returns the active content for a section:
	 *   1. Type-specific saved option (if type_key given and option exists).
	 *   2. Default saved option.
	 *   3. Hard-coded default string.
	 *
	 * @param string $key      Section key.
	 * @param string $type_key Event type context key, or '' for default.
	 */
	public static function get_section_content( string $key, string $type_key = '' ): string {
		if ( $type_key && $type_key !== self::TYPE_DEFAULT ) {
			$saved = get_option( self::get_option_key( $key, $type_key ), null );
			if ( $saved !== null && $saved !== '' ) {
				return $saved;
			}
		}
		$saved = get_option( self::OPT_PREFIX . $key, null );
		if ( $saved !== null && $saved !== '' ) {
			return $saved;
		}
		return self::get_default( $key );
	}

	/**
	 * Returns the raw saved value for a specific type context with no fallback.
	 * Used to populate the admin editor (empty = no override, inherits default).
	 */
	public static function get_type_raw( string $key, string $type_key ): string {
		return get_option( self::get_option_key( $key, $type_key ), '' );
	}

	/**
	 * Saves a section's content for a given type context.
	 */
	public static function save_section( string $key, string $content, string $type_key = '' ): void {
		$sections = self::get_sections();
		if ( ! isset( $sections[ $key ] ) ) {
			return;
		}
		update_option( self::get_option_key( $key, $type_key ), wp_kses_post( $content ), false );
	}

	/**
	 * Resets a section to its default by deleting the saved option for the given type context.
	 */
	public static function reset_section( string $key, string $type_key = '' ): void {
		delete_option( self::get_option_key( $key, $type_key ) );
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/**
	 * Returns a section's content with {{TOKEN}} placeholders replaced.
	 * Falls back through type → default → hard-coded default.
	 *
	 * @param string $key      Section key.
	 * @param array  $tokens   '{{TOKEN}}' => 'replacement' map.
	 * @param string $type_key Event type context key.
	 */
	public static function render_section( string $key, array $tokens = array(), string $type_key = '' ): string {
		$content = self::get_section_content( $key, $type_key );
		if ( ! empty( $tokens ) ) {
			$content = str_replace( array_keys( $tokens ), array_values( $tokens ), $content );
		}
		return $content . "\n";
	}

	// -------------------------------------------------------------------------
	// Admin AJAX
	// -------------------------------------------------------------------------

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_reset_template_section', array( __CLASS__, 'ajax_reset_section' ) );
	}

	public static function ajax_reset_section(): void {
		check_ajax_referer( 'hmo_page_template' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$key      = sanitize_key( $_POST['section_key'] ?? '' );
		$type_key = sanitize_key( $_POST['type_key']    ?? '' );
		$sections = self::get_sections();

		if ( ! isset( $sections[ $key ] ) ) {
			wp_send_json_error( 'Invalid section key.' );
		}

		$valid_types = self::get_event_types();
		if ( $type_key && ! array_key_exists( $type_key, $valid_types ) ) {
			wp_send_json_error( 'Invalid type key.' );
		}

		self::reset_section( $key, $type_key === self::TYPE_DEFAULT ? '' : $type_key );

		wp_send_json_success( array(
			'message' => $type_key && $type_key !== self::TYPE_DEFAULT
				? 'Type override cleared — section will now use the Default template.'
				: 'Section reset to default.',
		) );
	}
}
