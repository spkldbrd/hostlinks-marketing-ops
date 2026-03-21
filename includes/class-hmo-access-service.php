<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HMO access control — two layers:
 *
 *  1. Shortcode gate (public / logged_in / approved_viewers) — mirrors Hostlinks_Access.
 *     Controls whether a user can see the front-end page at all.
 *
 *  2. Marketer filtering — controls which events a marketer-mapped user sees.
 *     Applied after the shortcode gate passes.
 */
class HMO_Access_Service {

	// ── Shortcode registry ────────────────────────────────────────────────────

	const SHORTCODES = array(
		'hmo_dashboard'    => 'Marketing Ops Dashboard',
		'hmo_my_classes'   => 'My Classes',
		'hmo_event_detail' => 'Event Detail',
		'hmo_task_editor'  => 'Task Template Editor',
	);

	const MODES = array( 'public', 'logged_in', 'approved_viewers' );

	// ── wp_options keys ───────────────────────────────────────────────────────

	const OPT_MODES        = 'hmo_shortcode_access_modes';
	const OPT_VIEWERS      = 'hmo_approved_viewers';
	const OPT_MESSAGE      = 'hmo_denial_message';
	const OPT_TASK_EDITORS = 'hmo_task_editors';

	// ── Marketer mapping user meta keys ───────────────────────────────────────

	const META_MARKETER_ID   = 'hmo_marketer_id';
	const META_MARKETER_NAME = 'hmo_marketer_name';

	const DEFAULT_MESSAGE = "You don't have access to this page. Please contact your site administrator if you believe this is an error.";

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_search_users',       array( __CLASS__, 'ajax_search_users' ) );
		add_action( 'wp_ajax_nopriv_hmo_search_users',array( __CLASS__, 'ajax_search_users_denied' ) );
		add_action( 'wp_ajax_hmo_save_single_mapping',array( __CLASS__, 'ajax_save_single_mapping' ) );
	}

	// =========================================================================
	// 1. Shortcode access gate
	// =========================================================================

	/**
	 * Main gate: can the current user view an HMO shortcode?
	 * Administrators (manage_options) always pass.
	 *
	 * @param string $key  One of the keys in self::SHORTCODES.
	 * @return bool
	 */
	public function can_view_shortcode( string $key ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$mode = $this->get_shortcode_access_mode( $key );

		switch ( $mode ) {
			case 'public':
				return true;
			case 'logged_in':
				return is_user_logged_in();
			case 'approved_viewers':
				return is_user_logged_in() && $this->current_user_is_approved_viewer();
			default:
				return false;
		}
	}

	public function get_shortcode_access_mode( string $key ): string {
		$modes = get_option( self::OPT_MODES, array() );
		$mode  = $modes[ $key ] ?? 'approved_viewers';
		return in_array( $mode, self::MODES, true ) ? $mode : 'approved_viewers';
	}

	public function current_user_is_approved_viewer(): bool {
		$uid      = get_current_user_id();
		$approved = $this->get_approved_viewers();
		return $uid > 0 && in_array( $uid, $approved, true );
	}

	public function get_denial_message_html(): string {
		$msg = get_option( self::OPT_MESSAGE, '' );
		$msg = $msg !== '' ? $msg : self::DEFAULT_MESSAGE;
		return '<div class="hmo-access-denied hostlinks-access-denied"><p>'
			. wp_kses_post( $msg )
			. '</p></div>';
	}

	// ── Option getters / setters ──────────────────────────────────────────────

	public function get_approved_viewers(): array {
		$raw = get_option( self::OPT_VIEWERS, array() );
		return array_map( 'intval', (array) $raw );
	}

	public function save_approved_viewers( array $ids ): void {
		$clean = array_values( array_unique( array_filter(
			array_map( 'intval', $ids ),
			fn( $id ) => $id > 0
		) ) );
		update_option( self::OPT_VIEWERS, $clean );
	}

	public function save_access_modes( array $modes ): void {
		$clean = array();
		foreach ( array_keys( self::SHORTCODES ) as $key ) {
			$m           = $modes[ $key ] ?? 'approved_viewers';
			$clean[$key] = in_array( $m, self::MODES, true ) ? $m : 'approved_viewers';
		}
		update_option( self::OPT_MODES, $clean );
	}

	/**
	 * One-time clone: copies Hostlinks approved viewers into HMO approved viewers.
	 * Only copies users that exist in WordPress. Does not overwrite — merges.
	 *
	 * @return int  Number of users added.
	 */
	public function clone_approved_viewers_from_hostlinks(): int {
		$hl_viewers  = array_map( 'intval', (array) get_option( 'hostlinks_approved_viewers', array() ) );
		$hmo_viewers = $this->get_approved_viewers();
		$merged      = array_values( array_unique( array_merge( $hmo_viewers, $hl_viewers ) ) );
		$merged      = array_filter( $merged, fn( $id ) => $id > 0 );

		$added = count( $merged ) - count( $hmo_viewers );
		$this->save_approved_viewers( array_values( $merged ) );
		return max( 0, $added );
	}

	// ── Task editor access ────────────────────────────────────────────────────

	public static function current_user_can_edit_tasks(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$uid     = get_current_user_id();
		$editors = array_map( 'intval', (array) get_option( self::OPT_TASK_EDITORS, array() ) );
		return $uid > 0 && in_array( $uid, $editors, true );
	}

	public function get_task_editors(): array {
		$raw = get_option( self::OPT_TASK_EDITORS, array() );
		return array_map( 'intval', (array) $raw );
	}

	public function save_task_editors( array $ids ): void {
		$clean = array_values( array_unique( array_filter(
			array_map( 'intval', $ids ),
			fn( $id ) => $id > 0
		) ) );
		update_option( self::OPT_TASK_EDITORS, $clean );
	}

	// =========================================================================
	// 2. Marketer filtering
	// =========================================================================

	public function current_user_can_see_all_events(): bool {
		return current_user_can( 'manage_options' );
	}

	public function current_user_is_marketer(): bool {
		return ! $this->current_user_can_see_all_events()
			&& (bool) $this->get_current_user_marketer_id();
	}

	public function get_current_user_marketer_id(): int {
		return (int) get_user_meta( get_current_user_id(), self::META_MARKETER_ID, true );
	}

	public function get_current_user_marketer_name(): string {
		return (string) get_user_meta( get_current_user_id(), self::META_MARKETER_NAME, true );
	}

	public function set_user_marketer_mapping( int $wp_user_id, int $marketer_id, string $marketer_name ): void {
		update_user_meta( $wp_user_id, self::META_MARKETER_ID,   $marketer_id );
		update_user_meta( $wp_user_id, self::META_MARKETER_NAME, $marketer_name );
	}

	public function remove_user_marketer_mapping( int $wp_user_id ): void {
		delete_user_meta( $wp_user_id, self::META_MARKETER_ID );
		delete_user_meta( $wp_user_id, self::META_MARKETER_NAME );
	}

	/**
	 * Returns null for admins (no restriction), or an array of allowed
	 * hostlinks_event_id values for marketers.
	 *
	 * @return int[]|null
	 */
	public function get_allowed_event_ids() {
		if ( $this->current_user_can_see_all_events() ) {
			return null;
		}

		$marketer_id = $this->get_current_user_marketer_id();
		if ( ! $marketer_id ) {
			return array();
		}

		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT hostlinks_event_id FROM {$wpdb->prefix}hmo_event_ops
				 WHERE assigned_marketer_id = %d",
				$marketer_id
			)
		);

		return array_map( 'intval', $ids );
	}

	public function can_view_event( int $event_id ): bool {
		if ( $this->current_user_can_see_all_events() ) {
			return true;
		}

		$allowed = $this->get_allowed_event_ids();
		return is_array( $allowed ) && in_array( $event_id, $allowed, true );
	}

	// =========================================================================
	// AJAX
	// =========================================================================

	public static function ajax_search_users(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_user_access' );

		$q = sanitize_text_field( $_REQUEST['q'] ?? '' );
		if ( strlen( $q ) < 2 ) {
			wp_send_json_success( array() );
		}

		$users = new WP_User_Query( array(
			'search'         => '*' . $q . '*',
			'search_columns' => array( 'display_name', 'user_email', 'user_login' ),
			'number'         => 10,
			'fields'         => array( 'ID', 'display_name', 'user_email' ),
		) );

		$results = array();
		foreach ( $users->get_results() as $u ) {
			$results[] = array(
				'id'    => (int) $u->ID,
				'name'  => $u->display_name,
				'email' => $u->user_email,
			);
		}
		wp_send_json_success( $results );
	}

	public static function ajax_search_users_denied(): void {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	/**
	 * Save a single marketer → WP user mapping via AJAX.
	 * Expects POST: marketer_id (int), wp_user_id (int), marketer_name (string), _ajax_nonce
	 */
	public static function ajax_save_single_mapping(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_save_mapping' );

		$marketer_id   = (int) ( $_POST['marketer_id']   ?? 0 );
		$wp_user_id    = (int) ( $_POST['wp_user_id']    ?? 0 );
		$marketer_name = sanitize_text_field( $_POST['marketer_name'] ?? '' );

		if ( ! $marketer_id ) {
			wp_send_json_error( 'Invalid marketer ID', 400 );
		}

		$self = new self();

		// If a different user was previously mapped to this marketer, clear them first.
		global $wpdb;
		$old_user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s",
				self::META_MARKETER_ID,
				$marketer_id
			)
		);
		foreach ( $old_user_ids as $old_uid ) {
			$self->remove_user_marketer_mapping( (int) $old_uid );
		}

		if ( $wp_user_id ) {
			$self->set_user_marketer_mapping( $wp_user_id, $marketer_id, $marketer_name );
			wp_send_json_success( array( 'status' => 'mapped', 'user_id' => $wp_user_id ) );
		} else {
			wp_send_json_success( array( 'status' => 'cleared' ) );
		}
	}
}
