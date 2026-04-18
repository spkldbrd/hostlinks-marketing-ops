<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes Marketing Ops settings POST actions (admin Settings screen).
 */
class HMO_Settings_Form_Handler {

	/**
	 * @return string Admin notice HTML, or empty string.
	 */
	public static function process(): string {
		$notice = '';

		if ( isset( $_POST['hmo_save_general'] ) ) {
			check_admin_referer( 'hmo_save_general' );
			$fields = array(
				'hmo_default_goal'           => 'intval',
				'hmo_risk_red_days'          => 'intval',
				'hmo_risk_red_tasks'         => 'intval',
				'hmo_risk_yellow_days'       => 'intval',
				'hmo_enable_marketer_filter' => 'boolval',
			);
			foreach ( $fields as $key => $san ) {
				if ( isset( $_POST[ $key ] ) ) {
					update_option( $key, $san( $_POST[ $key ] ) );
				}
			}
			update_option( 'hmo_hide_list_links', isset( $_POST['hmo_hide_list_links'] ) ? 1 : 0 );
			update_option( 'hmo_goal_edit_marketing_admin', isset( $_POST['hmo_goal_edit_marketing_admin'] ) ? 1 : 0 );
			update_option( 'hmo_goal_edit_hostlinks_user', isset( $_POST['hmo_goal_edit_hostlinks_user'] ) ? 1 : 0 );
			$notice = '<div class="notice notice-success is-dismissible"><p>General settings saved.</p></div>';
		}

		if ( isset( $_POST['hmo_save_page_urls'] ) ) {
			check_admin_referer( 'hmo_save_page_urls' );
			HMO_Page_URLs::save_overrides(
				sanitize_text_field( $_POST['hmo_url_dashboard_selector'] ?? '' ),
				sanitize_text_field( $_POST['hmo_url_dashboard'] ?? '' ),
				sanitize_text_field( $_POST['hmo_url_my_classes'] ?? '' ),
				sanitize_text_field( $_POST['hmo_url_event_detail'] ?? '' ),
				sanitize_text_field( $_POST['hmo_url_task_editor'] ?? '' ),
				sanitize_text_field( $_POST['hmo_url_event_report'] ?? '' ),
				sanitize_text_field( $_POST['hmo_url_maps_tool'] ?? '' )
			);
			$notice = '<div class="notice notice-success is-dismissible"><p>Page links saved.</p></div>';
		}

		if ( isset( $_POST['hmo_save_user_access'] ) ) {
			check_admin_referer( 'hmo_user_access' );

			$access_svc = new HMO_Access_Service();

			$raw_modes = isset( $_POST['hmo_access_mode'] ) && is_array( $_POST['hmo_access_mode'] )
				? $_POST['hmo_access_mode'] : array();
			$access_svc->save_access_modes( $raw_modes );

			$raw_ids = sanitize_text_field( $_POST['hmo_approved_viewer_ids'] ?? '' );
			$ids     = $raw_ids !== '' ? explode( ',', $raw_ids ) : array();
			$access_svc->save_approved_viewers( $ids );

			$msg = sanitize_textarea_field( $_POST['hmo_denial_message'] ?? '' );
			update_option( HMO_Access_Service::OPT_MESSAGE, $msg );

			$notice = '<div class="notice notice-success is-dismissible"><p>User access settings saved.</p></div>';
		}

		if ( isset( $_POST['hmo_clone_viewers'] ) ) {
			check_admin_referer( 'hmo_user_access' );
			$access_svc = new HMO_Access_Service();
			$added  = $access_svc->clone_approved_viewers_from_hostlinks();
			$notice = '<div class="notice notice-success is-dismissible"><p>' . sprintf( 'Cloned approved viewers from Hostlinks. %d user(s) added.', $added ) . '</p></div>';
		}

		if ( isset( $_POST['hmo_save_task_editors'] ) ) {
			check_admin_referer( 'hmo_user_access' );
			$access_svc = new HMO_Access_Service();
			$raw_ids     = sanitize_text_field( $_POST['hmo_task_editor_ids'] ?? '' );
			$ids         = $raw_ids !== '' ? explode( ',', $raw_ids ) : array();
			$access_svc->save_task_editors( $ids );
			$notice = '<div class="notice notice-success is-dismissible"><p>Task editor settings saved.</p></div>';
		}

		if ( isset( $_POST['hmo_save_report_viewers'] ) ) {
			check_admin_referer( 'hmo_user_access' );
			$access_svc = new HMO_Access_Service();
			$raw_ids     = sanitize_text_field( $_POST['hmo_report_viewer_ids'] ?? '' );
			$ids         = $raw_ids !== '' ? explode( ',', $raw_ids ) : array();
			$access_svc->save_report_viewers( $ids );
			$notice = '<div class="notice notice-success is-dismissible"><p>Report viewer settings saved.</p></div>';
		}

		if ( isset( $_POST['hmo_save_marketing_admins'] ) ) {
			check_admin_referer( 'hmo_user_access' );
			$access_svc = new HMO_Access_Service();
			$raw_ids     = sanitize_text_field( $_POST['hmo_marketing_admin_ids'] ?? '' );
			$ids         = $raw_ids !== '' ? explode( ',', $raw_ids ) : array();
			$access_svc->save_marketing_admins( $ids );
			$notice = '<div class="notice notice-success is-dismissible"><p>Marketing admin settings saved.</p></div>';
		}

		if ( isset( $_POST['hmo_save_tools'] ) ) {
			check_admin_referer( 'hmo_save_tools' );
			$raw_names = isset( $_POST['hmo_tool_name'] ) && is_array( $_POST['hmo_tool_name'] )
				? $_POST['hmo_tool_name'] : array();
			$raw_urls  = isset( $_POST['hmo_tool_url'] ) && is_array( $_POST['hmo_tool_url'] )
				? $_POST['hmo_tool_url'] : array();
			$raw_icons = isset( $_POST['hmo_tool_icon'] ) && is_array( $_POST['hmo_tool_icon'] )
				? $_POST['hmo_tool_icon'] : array();

			$tools = array();
			foreach ( $raw_names as $i => $name ) {
				$name = sanitize_text_field( $name );
				$url  = esc_url_raw( $raw_urls[ $i ] ?? '' );
				$icon = sanitize_text_field( $raw_icons[ $i ] ?? '' );
				if ( $name && $url ) {
					$tools[] = array( 'name' => $name, 'url' => $url, 'icon' => $icon );
				}
			}
			update_option( 'hmo_tools_links', $tools, false );
			$notice = '<div class="notice notice-success is-dismissible"><p>Tools links saved.</p></div>';
		}

		if ( isset( $_POST['hmo_save_maps'] ) ) {
			check_admin_referer( 'hmo_save_maps' );
			update_option( 'hmo_maps_census_api_key', sanitize_text_field( $_POST['hmo_maps_census_api_key'] ?? '' ) );
			update_option( 'hmo_maps_google_api_key', sanitize_text_field( $_POST['hmo_maps_google_api_key'] ?? '' ) );
			update_option( 'hmo_maps_sync_frequency', sanitize_key( $_POST['hmo_maps_sync_frequency'] ?? 'monthly' ) );
			update_option( 'hmo_maps_page_heading', sanitize_text_field( $_POST['hmo_maps_page_heading'] ?? '' ) );
			update_option(
				'hmo_maps_centroid_source',
				in_array( $_POST['hmo_maps_centroid_source'] ?? '', array( 'geographic', 'population_weighted' ), true )
					? $_POST['hmo_maps_centroid_source']
					: 'geographic'
			);
			$notice = '<div class="notice notice-success is-dismissible"><p>Maps settings saved.</p></div>';
		}

		if ( isset( $_POST['hmo_save_page_template'] ) ) {
			check_admin_referer( 'hmo_page_template', 'hmo_page_template_nonce' );

			$sections    = HMO_Page_Template::get_sections();
			$all_types   = HMO_Page_Template::get_event_types();
			$active_type = sanitize_key( $_POST['hmo_tmpl_type'] ?? 'default' );
			if ( ! array_key_exists( $active_type, $all_types ) ) {
				$active_type = 'default';
			}

			$type_key_for_save = ( $active_type === 'default' ) ? '' : $active_type;

			$posted_tmpl = isset( $_POST['hmo_tmpl'] ) && is_array( $_POST['hmo_tmpl'] )
				? $_POST['hmo_tmpl'] : array();

			foreach ( array_keys( $sections ) as $key ) {
				if ( isset( $posted_tmpl[ $key ] ) ) {
					HMO_Page_Template::save_section( $key, wp_unslash( $posted_tmpl[ $key ] ), $type_key_for_save );
				}
			}

			$notice = '<div class="notice notice-success is-dismissible"><p>'
				. esc_html( $all_types[ $active_type ] ) . ' template sections saved.</p></div>';
		}

		return $notice;
	}
}
