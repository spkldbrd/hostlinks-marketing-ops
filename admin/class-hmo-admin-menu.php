<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HMO_Admin_Menu {

	public function enqueue_media_on_settings( string $hook ): void {
		if ( strpos( $hook, 'hmo-settings' ) !== false ) {
			wp_enqueue_media();
		}
	}

	public function register_menus(): void {
		$parent_slug = $this->get_hostlinks_menu_slug();

		if ( $parent_slug ) {
			add_submenu_page(
				$parent_slug,
				'Marketing Ops Settings',
				'Marketing Ops',
				'manage_options',
				'hmo-settings',
				array( $this, 'render_settings' )
			);
		} else {
			add_menu_page(
				'Marketing Ops Settings',
				'Marketing Ops',
				'manage_options',
				'hmo-settings',
				array( $this, 'render_settings' ),
				'dashicons-clipboard',
				30
			);
		}
	}

	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have permission to manage Marketing Ops settings.' );
		}
		include HMO_PLUGIN_DIR . 'admin/views/settings.php';
	}

	private function get_hostlinks_menu_slug(): string {
		global $menu;
		if ( ! is_array( $menu ) ) {
			return '';
		}
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && $item[2] === 'booking-menu' ) {
				return 'booking-menu';
			}
		}
		return '';
	}
}
