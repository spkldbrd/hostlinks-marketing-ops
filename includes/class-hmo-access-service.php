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
 *  2. Bucket-based event filtering — controls which events a user sees.
 *     A user may be assigned to multiple event buckets (marketers).
 *     A bucket may be assigned to multiple users (many-to-many via hmo_bucket_access).
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

	// ── Legacy marketer meta (read-only for backward compat) ─────────────────

	const META_MARKETER_ID   = 'hmo_marketer_id';
	const META_MARKETER_NAME = 'hmo_marketer_name';

	const DEFAULT_MESSAGE = "You don't have access to this page. Please contact your site administrator if you believe this is an error.";

	// ── Bootstrap ─────────────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_hmo_search_users',          array( __CLASS__, 'ajax_search_users' ) );
		add_action( 'wp_ajax_nopriv_hmo_search_users',   array( __CLASS__, 'ajax_search_users_denied' ) );
		add_action( 'wp_ajax_hmo_add_bucket_access',     array( __CLASS__, 'ajax_add_bucket_access' ) );
		add_action( 'wp_ajax_hmo_remove_bucket_access',  array( __CLASS__, 'ajax_remove_bucket_access' ) );
		// Legacy — kept so old admin JS still works during transition.
		add_action( 'wp_ajax_hmo_save_single_mapping',   array( __CLASS__, 'ajax_save_single_mapping' ) );
	}

	// =========================================================================
	// 1. Shortcode access gate
	// =========================================================================

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
	// 2. Bucket-based event filtering (many-to-many)
	// =========================================================================

	public function current_user_can_see_all_events(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Returns the marketer_ids (bucket IDs) the current user has access to.
	 * Returns null for admins (no restriction).
	 *
	 * @return int[]|null
	 */
	public function get_allowed_bucket_ids(): ?array {
		if ( $this->current_user_can_see_all_events() ) {
			return null;
		}
		return HMO_DB::get_bucket_ids_for_user( get_current_user_id() );
	}

	/**
	 * Returns bucket info for the current user: array of ['id'=>int,'name'=>string].
	 */
	public function get_current_user_buckets(): array {
		global $wpdb;
		$uid  = get_current_user_id();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT marketer_id AS id, bucket_name AS name
			 FROM {$wpdb->prefix}hmo_bucket_access
			 WHERE wp_user_id = %d
			 ORDER BY bucket_name ASC",
			$uid
		) );
		return array_map( fn( $r ) => array( 'id' => (int) $r->id, 'name' => $r->name ), $rows );
	}

	/**
	 * Returns null for admins (no restriction), or an array of allowed
	 * hostlinks_event_id values. Reads from bucket_access table.
	 *
	 * Used only for per-event can_view_event() checks. Dashboard rows use
	 * the marketer_ids filter directly for efficiency.
	 *
	 * @return int[]|null
	 */
	public function get_allowed_event_ids(): ?array {
		if ( $this->current_user_can_see_all_events() ) {
			return null;
		}

		$bucket_ids = $this->get_allowed_bucket_ids();
		if ( empty( $bucket_ids ) ) {
			return array();
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $bucket_ids ), '%d' ) );
		$ids          = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT DISTINCT eve_id FROM {$wpdb->prefix}event_details_list
			 WHERE eve_marketer IN ($placeholders) AND eve_status = 1",
			$bucket_ids
		) );

		return array_map( 'intval', $ids );
	}

	public function can_view_event( int $event_id ): bool {
		if ( $this->current_user_can_see_all_events() ) {
			return true;
		}

		$allowed = $this->get_allowed_event_ids();
		return is_array( $allowed ) && in_array( $event_id, $allowed, true );
	}

	// ── Legacy marketer meta (read-only compat) ───────────────────────────────

	public function current_user_is_marketer(): bool {
		return ! $this->current_user_can_see_all_events()
			&& ! empty( $this->get_allowed_bucket_ids() );
	}

	/** @deprecated Use get_allowed_bucket_ids() */
	public function get_current_user_marketer_id(): int {
		return (int) get_user_meta( get_current_user_id(), self::META_MARKETER_ID, true );
	}

	/** @deprecated Use get_current_user_buckets() */
	public function get_current_user_marketer_name(): string {
		return (string) get_user_meta( get_current_user_id(), self::META_MARKETER_NAME, true );
	}

	/** Writes legacy user-meta AND inserts into bucket_access table. */
	public function set_user_marketer_mapping( int $wp_user_id, int $marketer_id, string $marketer_name ): void {
		update_user_meta( $wp_user_id, self::META_MARKETER_ID,   $marketer_id );
		update_user_meta( $wp_user_id, self::META_MARKETER_NAME, $marketer_name );
		HMO_DB::add_bucket_access( $marketer_id, $marketer_name, $wp_user_id );
	}

	/** Removes legacy user-meta AND removes from bucket_access table (all buckets for user). */
	public function remove_user_marketer_mapping( int $wp_user_id ): void {
		delete_user_meta( $wp_user_id, self::META_MARKETER_ID );
		delete_user_meta( $wp_user_id, self::META_MARKETER_NAME );
		// Remove all bucket access for this user.
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'hmo_bucket_access', array( 'wp_user_id' => $wp_user_id ) );
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

	/** Add a user → bucket mapping. POST: marketer_id, bucket_name, wp_user_id, _ajax_nonce */
	public static function ajax_add_bucket_access(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_bucket_access' );

		$marketer_id  = (int) ( $_POST['marketer_id']  ?? 0 );
		$wp_user_id   = (int) ( $_POST['wp_user_id']   ?? 0 );
		$bucket_name  = sanitize_text_field( $_POST['bucket_name'] ?? '' );

		if ( ! $marketer_id || ! $wp_user_id ) {
			wp_send_json_error( 'Invalid parameters', 400 );
		}

		HMO_DB::add_bucket_access( $marketer_id, $bucket_name, $wp_user_id );
		HMO_Dashboard_Service::flush_row_cache();

		$user = get_userdata( $wp_user_id );
		wp_send_json_success( array(
			'user_id' => $wp_user_id,
			'name'    => $user ? $user->display_name : '',
			'email'   => $user ? $user->user_email : '',
		) );
	}

	/** Remove a user → bucket mapping. POST: marketer_id, wp_user_id, _ajax_nonce */
	public static function ajax_remove_bucket_access(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		check_ajax_referer( 'hmo_bucket_access' );

		$marketer_id = (int) ( $_POST['marketer_id'] ?? 0 );
		$wp_user_id  = (int) ( $_POST['wp_user_id']  ?? 0 );

		if ( ! $marketer_id || ! $wp_user_id ) {
			wp_send_json_error( 'Invalid parameters', 400 );
		}

		HMO_DB::remove_bucket_access( $marketer_id, $wp_user_id );
		HMO_Dashboard_Service::flush_row_cache();

		wp_send_json_success( array( 'removed' => true ) );
	}

	/** Legacy: kept for backward compat with older admin JS. */
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
		if ( $wp_user_id ) {
			$self->set_user_marketer_mapping( $wp_user_id, $marketer_id, $marketer_name );
			wp_send_json_success( array( 'status' => 'mapped', 'user_id' => $wp_user_id ) );
		} else {
			wp_send_json_success( array( 'status' => 'cleared' ) );
		}
	}
}
