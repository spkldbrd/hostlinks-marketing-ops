<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Assets {

	/** @var string[] Admin page slugs that should load HMO admin assets */
	private static $hmo_admin_pages = array(
		'hostlinks_page_hmo-settings',
	);

	// ── Admin assets ──────────────────────────────────────────────────────────

	public function enqueue_admin( string $hook ): void {
		if ( ! $this->is_hmo_admin_page( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'hmo-admin',
			HMO_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			HMO_VERSION
		);

		wp_enqueue_script(
			'hmo-admin',
			HMO_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			HMO_VERSION,
			true
		);

		wp_localize_script( 'hmo-admin', 'hmoData', $this->get_script_data() );
	}

	private function is_hmo_admin_page( string $hook ): bool {
		return in_array( $hook, self::$hmo_admin_pages, true )
			|| strpos( $hook, 'hmo-' ) !== false;
	}

	// ── Front-end assets ──────────────────────────────────────────────────────

	/**
	 * Called on wp_enqueue_scripts. Only loads assets if the current page
	 * contains an HMO shortcode.
	 */
	public function enqueue_frontend(): void {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		$shortcodes = array_keys( HMO_Access_Service::SHORTCODES );
		$has_hmo    = false;

		foreach ( $shortcodes as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				$has_hmo = true;
				break;
			}
		}

		if ( ! $has_hmo ) {
			return;
		}

		// Inherit the Hostlinks front-end stylesheet if already loaded.
		$deps_css = array();
		if ( wp_style_is( 'hostlinks-calendar', 'registered' ) ) {
			$deps_css[] = 'hostlinks-calendar';
		}

		wp_enqueue_style(
			'hmo-frontend',
			HMO_PLUGIN_URL . 'assets/css/frontend.css',
			$deps_css,
			HMO_VERSION
		);

		wp_enqueue_script(
			'hmo-frontend',
			HMO_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			HMO_VERSION,
			true
		);

		wp_localize_script( 'hmo-frontend', 'hmoData', $this->get_script_data() );
	}

	// ── Shared script data ────────────────────────────────────────────────────

	private function get_script_data(): array {
		return array(
			'restBase' => esc_url_raw( rest_url( 'hmo/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'teNonce'  => wp_create_nonce( 'hmo_task_editor' ),
			'strings'  => array(
				'saving'         => __( 'Saving…', 'hmo' ),
				'saved'          => __( 'Saved', 'hmo' ),
				'error'          => __( 'Error — please try again', 'hmo' ),
				'markComplete'   => __( 'Mark complete', 'hmo' ),
				'markIncomplete' => __( 'Mark incomplete', 'hmo' ),
				'confirmDelete'  => __( 'Delete this item? This cannot be undone.', 'hmo' ),
			),
		);
	}
}
