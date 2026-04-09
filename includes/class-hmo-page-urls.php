<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves front-end page URLs for HMO shortcodes.
 *
 * Priority (highest → lowest):
 *   1. Manual override stored in 'hmo_page_urls' option.
 *   2. Auto-detect: scan published pages for the shortcode tag (cached 24 h).
 *   3. Empty string (no fallback — admin must set the page).
 */
class HMO_Page_URLs {

	const OPTION_KEY = 'hmo_page_urls';

	// ── Public getters ─────────────────────────────────────────────────────────

	public static function get_dashboard_selector(): string {
		return self::resolve( 'dashboard_selector', 'hmo_dashboard_selector', '' );
	}

	public static function get_dashboard(): string {
		return self::resolve( 'dashboard', 'hmo_dashboard', '' );
	}

	public static function get_my_classes(): string {
		return self::resolve( 'my_classes', 'hmo_my_classes', '' );
	}

	public static function get_event_detail(): string {
		return self::resolve( 'event_detail', 'hmo_event_detail', '' );
	}

	public static function get_task_editor(): string {
		return self::resolve( 'task_editor', 'hmo_task_editor', '' );
	}

	public static function get_event_report(): string {
		return self::resolve( 'event_report', 'hmo_event_report', '' );
	}

	public static function get_maps_tool(): string {
		return self::resolve( 'maps_tool', 'display_maps_tool', '' );
	}

	// ── Settings helpers ───────────────────────────────────────────────────────

	public static function get_overrides(): array {
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), array(
			'dashboard_selector' => '',
			'dashboard'          => '',
			'my_classes'         => '',
			'event_detail'       => '',
			'task_editor'        => '',
			'event_report'       => '',
			'maps_tool'          => '',
		) );
	}

	public static function save_overrides( string $dashboard_selector = '', string $dashboard = '', string $my_classes = '', string $event_detail = '', string $task_editor = '', string $event_report = '', string $maps_tool = '' ): void {
		update_option( self::OPTION_KEY, array(
			'dashboard_selector' => esc_url_raw( trim( $dashboard_selector ) ),
			'dashboard'          => esc_url_raw( trim( $dashboard ) ),
			'my_classes'         => esc_url_raw( trim( $my_classes ) ),
			'event_detail'       => esc_url_raw( trim( $event_detail ) ),
			'task_editor'        => esc_url_raw( trim( $task_editor ) ),
			'event_report'       => esc_url_raw( trim( $event_report ) ),
			'maps_tool'          => esc_url_raw( trim( $maps_tool ) ),
		) );
		self::clear_cache();
	}

	// ── Cache management ───────────────────────────────────────────────────────

	public static function clear_cache(): void {
		delete_transient( 'hmo_page_url_dashboard_selector' );
		delete_transient( 'hmo_page_url_dashboard' );
		delete_transient( 'hmo_page_url_my_classes' );
		delete_transient( 'hmo_page_url_event_detail' );
		delete_transient( 'hmo_page_url_task_editor' );
		delete_transient( 'hmo_page_url_event_report' );
		delete_transient( 'hmo_page_url_maps_tool' );
	}

	// ── Detection status (used by the settings UI) ─────────────────────────────

	/**
	 * @return array  [ key => [ 'url' => string, 'source' => 'override'|'auto'|'none' ] ]
	 */
	public static function detection_status(): array {
		$overrides = self::get_overrides();
		$map       = array(
			'dashboard_selector' => 'hmo_dashboard_selector',
			'dashboard'          => 'hmo_dashboard',
			'my_classes'         => 'hmo_my_classes',
			'event_detail'       => 'hmo_event_detail',
			'task_editor'        => 'hmo_task_editor',
			'event_report'       => 'hmo_event_report',
			'maps_tool'          => 'display_maps_tool',
		);

		$status = array();
		foreach ( $map as $key => $shortcode ) {
			if ( ! empty( $overrides[ $key ] ) ) {
				$status[ $key ] = array( 'url' => $overrides[ $key ], 'source' => 'override' );
				continue;
			}

			$cached = self::get_cached_or_scan( $key, $shortcode );
			$status[ $key ] = $cached
				? array( 'url' => $cached, 'source' => 'auto' )
				: array( 'url' => '',      'source' => 'none' );
		}

		return $status;
	}

	// ── Core resolver ──────────────────────────────────────────────────────────

	private static function resolve( string $key, string $shortcode, string $default_path ): string {
		$overrides = self::get_overrides();
		if ( ! empty( $overrides[ $key ] ) ) {
			return $overrides[ $key ];
		}

		$cached = self::get_cached_or_scan( $key, $shortcode );
		if ( $cached ) {
			return $cached;
		}

		return $default_path ? home_url( $default_path ) : '';
	}

	private static function get_cached_or_scan( string $key, string $shortcode ): string {
		$transient_key = 'hmo_page_url_' . $key;
		$cached        = get_transient( $transient_key );

		if ( $cached === false ) {
			global $wpdb;
			$rid = $wpdb->get_var( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_status = 'publish'
				   AND post_type   = 'page'
				   AND post_content LIKE %s
				 LIMIT 1",
				'%' . $wpdb->esc_like( '[' . $shortcode . ']' ) . '%'
			) );
			$cached = $rid ? (string) get_permalink( (int) $rid ) : '';
			set_transient( $transient_key, $cached, DAY_IN_SECONDS );
		}

		return $cached;
	}
}
