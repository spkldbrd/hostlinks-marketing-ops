<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Unauthorized' ); }

// ── Handle form saves ─────────────────────────────────────────────────────────

$notice = '';

// General settings
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
	$notice = '<div class="notice notice-success is-dismissible"><p>General settings saved.</p></div>';
}

// Marketer user mappings
if ( isset( $_POST['hmo_save_mappings'] ) ) {
	check_admin_referer( 'hmo_save_general' );

	$access_svc = new HMO_Access_Service();
	$mappings   = isset( $_POST['hmo_mapping'] ) && is_array( $_POST['hmo_mapping'] )
		? $_POST['hmo_mapping'] : array();

	// Build a lookup of all existing mapped user IDs so we can clear removed ones.
	$all_users = get_users( array( 'number' => -1, 'fields' => array( 'ID' ) ) );
	foreach ( $all_users as $u ) {
		$existing_mid = (int) get_user_meta( $u->ID, HMO_Access_Service::META_MARKETER_ID, true );
		if ( $existing_mid ) {
			// Will be overwritten below if still present; cleared if not.
			$access_svc->remove_user_marketer_mapping( (int) $u->ID );
		}
	}

	// Save new mappings: keyed by marketer_id => wp_user_id.
	$bridge    = new HMO_Hostlinks_Bridge();
	$marketers = $bridge->get_marketers();
	$mkt_index = array();
	foreach ( $marketers as $m ) {
		$mkt_index[ (int) $m->event_marketer_id ] = $m->event_marketer_name;
	}

	foreach ( $mappings as $marketer_id => $wp_user_id ) {
		$marketer_id = (int) $marketer_id;
		$wp_user_id  = (int) $wp_user_id;
		if ( $wp_user_id && isset( $mkt_index[ $marketer_id ] ) ) {
			$access_svc->set_user_marketer_mapping(
				$wp_user_id,
				$marketer_id,
				$mkt_index[ $marketer_id ]
			);
		}
	}

	$notice = '<div class="notice notice-success is-dismissible"><p>Marketer mappings saved.</p></div>';
}

// Page links
if ( isset( $_POST['hmo_save_page_urls'] ) ) {
	check_admin_referer( 'hmo_save_page_urls' );
	HMO_Page_URLs::save_overrides(
		sanitize_text_field( $_POST['hmo_url_dashboard']    ?? '' ),
		sanitize_text_field( $_POST['hmo_url_my_classes']   ?? '' ),
		sanitize_text_field( $_POST['hmo_url_event_detail'] ?? '' ),
		sanitize_text_field( $_POST['hmo_url_task_editor']  ?? '' )
	);
	$notice = '<div class="notice notice-success is-dismissible"><p>Page links saved.</p></div>';
}

// User access
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

// Clone from Hostlinks
if ( isset( $_POST['hmo_clone_viewers'] ) ) {
	check_admin_referer( 'hmo_user_access' );
	$access_svc = new HMO_Access_Service();
	$added      = $access_svc->clone_approved_viewers_from_hostlinks();
	$notice = '<div class="notice notice-success is-dismissible"><p>' . sprintf( 'Cloned approved viewers from Hostlinks. %d user(s) added.', $added ) . '</p></div>';
}

// Task editors save
if ( isset( $_POST['hmo_save_task_editors'] ) ) {
	check_admin_referer( 'hmo_user_access' );
	$access_svc  = new HMO_Access_Service();
	$raw_ids     = sanitize_text_field( $_POST['hmo_task_editor_ids'] ?? '' );
	$ids         = $raw_ids !== '' ? explode( ',', $raw_ids ) : array();
	$access_svc->save_task_editors( $ids );
	$notice = '<div class="notice notice-success is-dismissible"><p>Task editor settings saved.</p></div>';
}

// ── Current state ─────────────────────────────────────────────────────────────

$access_svc      = new HMO_Access_Service();
$approved_ids    = $access_svc->get_approved_viewers();
$task_editor_ids = $access_svc->get_task_editors();
$denial_message  = get_option( HMO_Access_Service::OPT_MESSAGE, HMO_Access_Service::DEFAULT_MESSAGE );
$page_status     = HMO_Page_URLs::detection_status();
$url_overrides   = HMO_Page_URLs::get_overrides();
$saved_modes     = get_option( HMO_Access_Service::OPT_MODES, array() );

$task_editor_users = array();
if ( ! empty( $task_editor_ids ) ) {
	$task_editor_users = get_users( array(
		'include' => $task_editor_ids,
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
		'orderby' => 'display_name',
	) );
}

$approved_users = array();
if ( ! empty( $approved_ids ) ) {
	$approved_users = get_users( array(
		'include' => $approved_ids,
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
		'orderby' => 'display_name',
	) );
}

$mode_labels = array(
	'public'           => 'Public — anyone',
	'logged_in'        => 'Logged-in Users',
	'approved_viewers' => 'Approved Viewers Only',
);

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'general';

$tabs = array(
	'general'     => 'General',
	'page-links'  => 'Page Links',
	'user-access' => 'User Access',
);
?>
<div class="wrap">
<h1>Hostlinks Marketing Ops — Settings</h1>

<?php if ( ! defined( 'HOSTLINKS_VERSION' ) ) : ?>
	<div class="notice notice-error"><p><strong>Hostlinks is not active.</strong> This plugin requires Hostlinks to function.</p></div>
<?php endif; ?>

<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<!-- Tab nav -->
<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
	<?php foreach ( $tabs as $slug => $label ) :
		$url     = add_query_arg( array( 'page' => 'hmo-settings', 'tab' => $slug ), admin_url( 'admin.php' ) );
		$classes = 'nav-tab' . ( $active_tab === $slug ? ' nav-tab-active' : '' );
	?>
	<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>

<!-- ======================================================================
     TAB: GENERAL
     ====================================================================== -->
<?php if ( $active_tab === 'general' ) : ?>
<form method="post" action="">
	<?php wp_nonce_field( 'hmo_save_general' ); ?>

	<h2>Registration</h2>
	<table class="form-table">
		<tr>
			<th><label for="hmo_default_goal">Default Registration Goal</label></th>
			<td>
				<input type="number" id="hmo_default_goal" name="hmo_default_goal" min="1" class="small-text"
					value="<?php echo (int) get_option( 'hmo_default_goal', 40 ); ?>">
				<p class="description">Used when no goal has been set on a specific event.</p>
			</td>
		</tr>
	</table>

	<h2>Risk Highlighting Thresholds</h2>
	<table class="form-table">
		<tr>
			<th>Red Risk</th>
			<td>
				Fewer than
				<input type="number" name="hmo_risk_red_days" min="1" class="small-text"
					value="<?php echo (int) get_option( 'hmo_risk_red_days', 30 ); ?>">
				days left AND more than
				<input type="number" name="hmo_risk_red_tasks" min="0" class="small-text"
					value="<?php echo (int) get_option( 'hmo_risk_red_tasks', 5 ); ?>">
				open tasks.
			</td>
		</tr>
		<tr>
			<th>Yellow Risk</th>
			<td>
				Fewer than
				<input type="number" name="hmo_risk_yellow_days" min="1" class="small-text"
					value="<?php echo (int) get_option( 'hmo_risk_yellow_days', 45 ); ?>">
				days left AND at least 1 open task.
			</td>
		</tr>
	</table>

	<h2>Access</h2>
	<table class="form-table">
		<tr>
			<th><label for="hmo_enable_marketer_filter">Enable Marketer Filtering</label></th>
			<td>
				<input type="checkbox" id="hmo_enable_marketer_filter" name="hmo_enable_marketer_filter" value="1"
					<?php checked( get_option( 'hmo_enable_marketer_filter', 1 ) ); ?>>
				<label for="hmo_enable_marketer_filter">Marketers only see their assigned classes.</label>
			</td>
		</tr>
	</table>

	<h2>Marketer User Mapping</h2>
	<p>
		Assign each Hostlinks marketer to a WordPress user account.
		Mapped users will only see their own assigned classes in the My Classes view.
		Administrators always see all classes regardless of mapping.
	</p>

	<?php
	$bridge_for_mapping = new HMO_Hostlinks_Bridge();
	$all_marketers      = $bridge_for_mapping->get_marketers();
	$all_wp_users       = get_users( array(
		'number'  => -1,
		'orderby' => 'display_name',
		'order'   => 'ASC',
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
	) );

	// Build reverse index: marketer_id => wp_user_id currently mapped.
	$mapping_by_marketer = array();
	foreach ( $all_wp_users as $u ) {
		$mid = (int) get_user_meta( $u->ID, HMO_Access_Service::META_MARKETER_ID, true );
		if ( $mid ) {
			$mapping_by_marketer[ $mid ] = (int) $u->ID;
		}
	}
	?>

	<?php if ( empty( $all_marketers ) ) : ?>
		<div class="notice notice-warning inline"><p>No active marketers found in Hostlinks. Add marketers in Hostlinks first.</p></div>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped" style="max-width:760px;">
		<thead>
			<tr>
				<th style="width:35%;">Hostlinks Marketer</th>
				<th>Mapped WordPress User</th>
				<th style="width:90px;text-align:center;">Status</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $all_marketers as $mkt ) :
			$mkt_id      = (int) $mkt->event_marketer_id;
			$mapped_uid  = $mapping_by_marketer[ $mkt_id ] ?? 0;
		?>
			<tr>
				<td>
					<strong><?php echo esc_html( $mkt->event_marketer_name ); ?></strong>
					<br><small style="color:#8c8f94;">ID: <?php echo (int) $mkt_id; ?></small>
				</td>
				<td>
					<select name="hmo_mapping[<?php echo (int) $mkt_id; ?>]"
						class="hmo-mapping-select"
						style="width:100%;max-width:340px;">
						<option value="0">— No mapping —</option>
						<?php foreach ( $all_wp_users as $u ) : ?>
							<option value="<?php echo (int) $u->ID; ?>" <?php selected( $mapped_uid, (int) $u->ID ); ?>>
								<?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_email ); ?>)
							</option>
						<?php endforeach; ?>
					</select>
				</td>
				<td style="text-align:center;">
					<?php if ( $mapped_uid ) : ?>
						<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:hsl(142 55% 90%);color:hsl(142 60% 28%);font-size:11px;font-weight:600;">Mapped</span>
					<?php else : ?>
						<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:#f0f0f1;color:#8c8f94;font-size:11px;font-weight:600;">None</span>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p style="margin-top:12px;">
		<button type="submit" name="hmo_save_mappings" class="button button-primary">Save Marketer Mappings</button>
	</p>
	<?php endif; ?>

	<hr style="margin:24px 0;">
	<?php submit_button( 'Save General Settings', 'primary', 'hmo_save_general' ); ?>

<script>
(function() {
	var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'hmo_save_mapping' ) ); ?>;

	// Collect marketer names from the table rows for the AJAX call.
	var marketerNames = {};
	<?php foreach ( $all_marketers as $mkt ) : ?>
	marketerNames[<?php echo (int) $mkt->event_marketer_id; ?>] = <?php echo wp_json_encode( $mkt->event_marketer_name ); ?>;
	<?php endforeach; ?>

	document.querySelectorAll('.hmo-mapping-select').forEach(function(sel) {
		sel.addEventListener('change', function() {
			var row        = sel.closest('tr');
			var statusCell = row.querySelector('td:last-child');
			var marketerId = parseInt(sel.name.match(/\[(\d+)\]/)[1], 10);
			var wpUserId   = parseInt(sel.value, 10);
			var mktName    = marketerNames[marketerId] || '';

			statusCell.innerHTML = '<span style="color:#888;font-size:11px;">Saving…</span>';

			var data = new FormData();
			data.append('action',        'hmo_save_single_mapping');
			data.append('_ajax_nonce',   nonce);
			data.append('marketer_id',   marketerId);
			data.append('wp_user_id',    wpUserId);
			data.append('marketer_name', mktName);

			fetch(ajaxUrl, { method: 'POST', body: data })
				.then(function(r) { return r.json(); })
				.then(function(resp) {
					if (resp.success) {
						if (resp.data.status === 'mapped') {
							statusCell.innerHTML = '<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:hsl(142 55% 90%);color:hsl(142 60% 28%);font-size:11px;font-weight:600;">Mapped</span>';
						} else {
							statusCell.innerHTML = '<span style="display:inline-block;padding:2px 8px;border-radius:3px;background:#f0f0f1;color:#8c8f94;font-size:11px;font-weight:600;">None</span>';
						}
					} else {
						statusCell.innerHTML = '<span style="color:#d63638;font-size:11px;">Error</span>';
					}
				})
				.catch(function() {
					statusCell.innerHTML = '<span style="color:#d63638;font-size:11px;">Error</span>';
				});
		});
	});
})();
</script>
</form>

<!-- ======================================================================
     TAB: PAGE LINKS
     ====================================================================== -->
<?php elseif ( $active_tab === 'page-links' ) : ?>
<form method="post" action="">
	<?php wp_nonce_field( 'hmo_save_page_urls' ); ?>

	<p>
		Set the WordPress page URL where each HMO shortcode lives.
		If left blank, the plugin auto-detects the page by scanning for the shortcode tag.
	</p>

	<table class="form-table">
		<?php
		$page_defs = array(
			'dashboard'    => array( 'label' => 'Dashboard',          'shortcode' => '[hmo_dashboard]',    'field' => 'hmo_url_dashboard' ),
			'my_classes'   => array( 'label' => 'My Classes',         'shortcode' => '[hmo_my_classes]',   'field' => 'hmo_url_my_classes' ),
			'event_detail' => array( 'label' => 'Event Detail',       'shortcode' => '[hmo_event_detail]', 'field' => 'hmo_url_event_detail' ),
			'task_editor'  => array( 'label' => 'Task Template Editor','shortcode' => '[hmo_task_editor]',  'field' => 'hmo_url_task_editor' ),
		);
		$source_labels = array(
			'override' => '<span style="color:#007017;">&#10003; Manual override</span>',
			'auto'     => '<span style="color:#007017;">&#10003; Auto-detected</span>',
			'none'     => '<span style="color:#d63638;">&#10007; Not found</span>',
		);
		foreach ( $page_defs as $key => $def ) :
			$status = $page_status[ $key ] ?? array( 'url' => '', 'source' => 'none' );
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $def['field'] ); ?>"><?php echo esc_html( $def['label'] ); ?></label>
				<p class="description"><code><?php echo esc_html( $def['shortcode'] ); ?></code></p>
			</th>
			<td>
				<input type="url" id="<?php echo esc_attr( $def['field'] ); ?>"
					name="<?php echo esc_attr( $def['field'] ); ?>"
					value="<?php echo esc_url( $url_overrides[ $key ] ?? '' ); ?>"
					class="regular-text"
					placeholder="https://">
				<p class="description">
					<?php echo $source_labels[ $status['source'] ]; // phpcs:ignore ?>
					<?php if ( $status['url'] ) : ?>
						— <a href="<?php echo esc_url( $status['url'] ); ?>" target="_blank"><?php echo esc_html( $status['url'] ); ?></a>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>

	<?php submit_button( 'Save Page Links', 'primary', 'hmo_save_page_urls' ); ?>
</form>

<!-- ======================================================================
     TAB: USER ACCESS
     ====================================================================== -->
<?php elseif ( $active_tab === 'user-access' ) :
	$ajax_nonce = wp_create_nonce( 'hmo_user_access' );
?>
<p>Control who can view each HMO front-end shortcode. Administrators always have access.</p>

<form method="post" action="" id="hmo-user-access-form">
	<?php wp_nonce_field( 'hmo_user_access' ); ?>

	<input type="hidden" id="hmo_approved_viewer_ids" name="hmo_approved_viewer_ids"
		value="<?php echo esc_attr( implode( ',', $approved_ids ) ); ?>">

	<!-- Shortcode access modes -->
	<h2>Shortcode Access Modes</h2>
	<table class="widefat striped" style="max-width:720px;margin-bottom:24px;">
		<thead>
			<tr>
				<th style="width:220px;">Shortcode</th>
				<th style="width:180px;">Page</th>
				<th>Access Mode</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( HMO_Access_Service::SHORTCODES as $key => $label ) :
			$current_mode = $saved_modes[ $key ] ?? 'approved_viewers';
		?>
			<tr>
				<td><code>[<?php echo esc_html( $key ); ?>]</code></td>
				<td><?php echo esc_html( $label ); ?></td>
				<td>
					<select name="hmo_access_mode[<?php echo esc_attr( $key ); ?>]" style="width:220px;">
						<?php foreach ( $mode_labels as $mode => $mode_label ) : ?>
							<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $current_mode, $mode ); ?>>
								<?php echo esc_html( $mode_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Approved viewers -->
	<h2>Approved Viewers</h2>
	<p>Users added here can view shortcodes set to <strong>Approved Viewers Only</strong>.</p>

	<!-- Clone from Hostlinks -->
	<?php if ( defined( 'HOSTLINKS_VERSION' ) ) :
		$hl_count = count( (array) get_option( 'hostlinks_approved_viewers', array() ) );
	?>
	<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px 16px;max-width:560px;margin-bottom:16px;">
		<strong>Clone from Hostlinks</strong>
		<p style="margin:4px 0 8px;">
			Hostlinks has <strong><?php echo (int) $hl_count; ?></strong> approved viewer(s).
			Click below to copy them into HMO's approved viewer list (merges, does not overwrite).
		</p>
		<button type="submit" name="hmo_clone_viewers" class="button">
			&#8635; Copy Hostlinks Approved Viewers to HMO
		</button>
	</div>
	<?php endif; ?>

	<!-- User search / add -->
	<div style="max-width:560px;margin-bottom:8px;">
		<label for="hmo-user-search" style="display:block;font-weight:600;margin-bottom:6px;">Search users to add</label>
		<div style="display:flex;gap:8px;">
			<input type="text" id="hmo-user-search" placeholder="Type a name or email…"
				class="regular-text" autocomplete="off" style="flex:1;">
			<span id="hmo-search-spinner" style="display:none;line-height:30px;color:#888;">Searching…</span>
		</div>
		<ul id="hmo-search-results" style="
			list-style:none;margin:0;padding:0;max-width:560px;
			border:1px solid #ddd;border-top:none;display:none;
			background:#fff;position:relative;z-index:100;"></ul>
	</div>

	<table class="widefat striped" style="max-width:560px;margin-bottom:24px;" id="hmo-viewers-table">
		<thead>
			<tr><th>Name</th><th>Email</th><th style="width:80px;"></th></tr>
		</thead>
		<tbody id="hmo-viewers-tbody">
		<?php if ( empty( $approved_users ) ) : ?>
			<tr id="hmo-viewers-empty"><td colspan="3" style="color:#888;font-style:italic;">No approved viewers yet.</td></tr>
		<?php else : ?>
			<?php foreach ( $approved_users as $u ) : ?>
			<tr id="hmo-viewer-row-<?php echo (int) $u->ID; ?>">
				<td><?php echo esc_html( $u->display_name ); ?></td>
				<td><?php echo esc_html( $u->user_email ); ?></td>
				<td>
					<button type="button" class="button button-small hmo-remove-viewer"
						data-id="<?php echo (int) $u->ID; ?>">Remove</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<!-- Denial message -->
	<h2>Access Denied Message</h2>
	<p>Shown when a user does not have access to a restricted shortcode.</p>
	<textarea id="hmo_denial_message" name="hmo_denial_message" rows="4"
		class="large-text" style="max-width:720px;"><?php echo esc_textarea( $denial_message ); ?></textarea>
	<p class="description"><a href="#" id="hmo-reset-message">Reset to default</a></p>

	<p class="submit" style="margin-top:16px;">
		<button type="submit" name="hmo_save_user_access" class="button button-primary">Save User Access Settings</button>
	</p>
</form>

<!-- ── Task Editor Users ─────────────────────────────────────────────────── -->
<hr style="margin:32px 0;">
<h2>Task Template Editor — Allowed Users</h2>
<p>
	Users listed here can access the <code>[hmo_task_editor]</code> shortcode to add, edit, and remove checklist tasks.
	Administrators always have access regardless of this list.
</p>

<form method="post" action="" id="hmo-te-users-form">
	<?php wp_nonce_field( 'hmo_user_access' ); ?>
	<input type="hidden" id="hmo_task_editor_ids" name="hmo_task_editor_ids"
		value="<?php echo esc_attr( implode( ',', $task_editor_ids ) ); ?>">

	<!-- User search / add -->
	<div style="max-width:560px;margin-bottom:8px;">
		<label for="hmo-te-user-search" style="display:block;font-weight:600;margin-bottom:6px;">Search users to add</label>
		<div style="display:flex;gap:8px;">
			<input type="text" id="hmo-te-user-search" placeholder="Type a name or email…"
				class="regular-text" autocomplete="off" style="flex:1;">
			<span id="hmo-te-search-spinner" style="display:none;line-height:30px;color:#888;">Searching…</span>
		</div>
		<ul id="hmo-te-search-results" style="
			list-style:none;margin:0;padding:0;max-width:560px;
			border:1px solid #ddd;border-top:none;display:none;
			background:#fff;position:relative;z-index:100;"></ul>
	</div>

	<table class="widefat striped" style="max-width:560px;margin-bottom:24px;" id="hmo-te-users-table">
		<thead>
			<tr><th>Name</th><th>Email</th><th style="width:80px;"></th></tr>
		</thead>
		<tbody id="hmo-te-users-tbody">
		<?php if ( empty( $task_editor_users ) ) : ?>
			<tr id="hmo-te-users-empty"><td colspan="3" style="color:#888;font-style:italic;">No task editor users yet.</td></tr>
		<?php else : ?>
			<?php foreach ( $task_editor_users as $u ) : ?>
			<tr id="hmo-te-user-row-<?php echo (int) $u->ID; ?>">
				<td><?php echo esc_html( $u->display_name ); ?></td>
				<td><?php echo esc_html( $u->user_email ); ?></td>
				<td>
					<button type="button" class="button button-small hmo-te-remove-user"
						data-id="<?php echo (int) $u->ID; ?>">Remove</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<p class="submit">
		<button type="submit" name="hmo_save_task_editors" class="button button-primary">Save Task Editor Users</button>
	</p>
</form>

<script>
(function() {
	var ajaxUrl   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce     = <?php echo wp_json_encode( wp_create_nonce( 'hmo_user_access' ) ); ?>;
	var teUserIds = <?php echo wp_json_encode( array_map( 'intval', $task_editor_ids ) ); ?>;

	function syncTeIdsField() {
		document.getElementById('hmo_task_editor_ids').value = teUserIds.join(',');
	}

	// Search
	var teTimer;
	document.getElementById('hmo-te-user-search').addEventListener('input', function() {
		clearTimeout( teTimer );
		var q = this.value.trim();
		var $results = document.getElementById('hmo-te-search-results');
		if ( q.length < 2 ) { $results.style.display = 'none'; return; }
		document.getElementById('hmo-te-search-spinner').style.display = 'inline';
		teTimer = setTimeout( function() {
			var fd = new FormData();
			fd.append('action','hmo_search_users');
			fd.append('q', q);
			fd.append('_ajax_nonce', nonce);
			fetch(ajaxUrl, { method:'POST', body: fd })
				.then(function(r){ return r.json(); })
				.then(function(res) {
					document.getElementById('hmo-te-search-spinner').style.display = 'none';
					$results.innerHTML = '';
					if ( ! res.success || ! res.data.length ) { $results.style.display = 'none'; return; }
					res.data.forEach(function(u) {
						if ( teUserIds.indexOf(u.id) !== -1 ) { return; }
						var li = document.createElement('li');
						li.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;';
						li.textContent = u.name + ' (' + u.email + ')';
						li.addEventListener('click', function() {
							teUserIds.push(u.id);
							syncTeIdsField();
							var emptyRow = document.getElementById('hmo-te-users-empty');
							if (emptyRow) emptyRow.remove();
							var tbody = document.getElementById('hmo-te-users-tbody');
							var tr = document.createElement('tr');
							tr.id = 'hmo-te-user-row-' + u.id;
							tr.innerHTML = '<td>' + u.name + '</td><td>' + u.email + '</td>' +
								'<td><button type="button" class="button button-small hmo-te-remove-user" data-id="' + u.id + '">Remove</button></td>';
							tbody.appendChild(tr);
							$results.style.display = 'none';
							document.getElementById('hmo-te-user-search').value = '';
						});
						$results.appendChild(li);
					});
					$results.style.display = $results.children.length ? 'block' : 'none';
				});
		}, 300 );
	});

	// Remove user
	document.addEventListener('click', function(e) {
		if ( ! e.target.classList.contains('hmo-te-remove-user') ) { return; }
		var id = parseInt( e.target.getAttribute('data-id'), 10 );
		teUserIds = teUserIds.filter(function(i){ return i !== id; });
		syncTeIdsField();
		var row = document.getElementById('hmo-te-user-row-' + id);
		if (row) row.remove();
		if ( ! document.getElementById('hmo-te-users-tbody').children.length ) {
			var tr = document.createElement('tr');
			tr.id = 'hmo-te-users-empty';
			tr.innerHTML = '<td colspan="3" style="color:#888;font-style:italic;">No task editor users yet.</td>';
			document.getElementById('hmo-te-users-tbody').appendChild(tr);
		}
	});
})();
</script>

<script>
(function() {
	var ajaxUrl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce      = <?php echo wp_json_encode( $ajax_nonce ); ?>;
	var defaultMsg = <?php echo wp_json_encode( HMO_Access_Service::DEFAULT_MESSAGE ); ?>;
	var approvedIds = <?php echo wp_json_encode( array_map( 'intval', $approved_ids ) ); ?>;

	function syncIdsField() {
		document.getElementById('hmo_approved_viewer_ids').value = approvedIds.join(',');
	}

	function removeViewer(id) {
		approvedIds = approvedIds.filter(function(x){ return x !== id; });
		syncIdsField();
		var row = document.getElementById('hmo-viewer-row-' + id);
		if (row) row.remove();
		if (!document.querySelector('#hmo-viewers-tbody tr:not(#hmo-viewers-empty)')) {
			showEmptyRow();
		}
	}

	function showEmptyRow() {
		var tbody = document.getElementById('hmo-viewers-tbody');
		if (!document.getElementById('hmo-viewers-empty')) {
			var tr = document.createElement('tr');
			tr.id = 'hmo-viewers-empty';
			tr.innerHTML = '<td colspan="3" style="color:#888;font-style:italic;">No approved viewers yet.</td>';
			tbody.appendChild(tr);
		}
	}

	function addViewer(user) {
		if (approvedIds.indexOf(user.id) !== -1) return;
		approvedIds.push(user.id);
		syncIdsField();
		var empty = document.getElementById('hmo-viewers-empty');
		if (empty) empty.remove();
		var tbody = document.getElementById('hmo-viewers-tbody');
		var tr = document.createElement('tr');
		tr.id = 'hmo-viewer-row-' + user.id;
		tr.innerHTML =
			'<td>' + escHtml(user.name) + '</td>' +
			'<td>' + escHtml(user.email) + '</td>' +
			'<td><button type="button" class="button button-small hmo-remove-viewer" data-id="' + user.id + '">Remove</button></td>';
		tbody.appendChild(tr);
	}

	document.getElementById('hmo-viewers-tbody').addEventListener('click', function(e) {
		var btn = e.target.closest('.hmo-remove-viewer');
		if (btn) removeViewer(parseInt(btn.dataset.id, 10));
	});

	var searchInput   = document.getElementById('hmo-user-search');
	var resultsBox    = document.getElementById('hmo-search-results');
	var spinnerEl     = document.getElementById('hmo-search-spinner');
	var searchTimeout = null;

	searchInput.addEventListener('input', function() {
		clearTimeout(searchTimeout);
		var q = this.value.trim();
		resultsBox.style.display = 'none';
		resultsBox.innerHTML = '';
		if (q.length < 2) return;

		searchTimeout = setTimeout(function() {
			spinnerEl.style.display = 'inline';
			var xhr = new XMLHttpRequest();
			xhr.open('GET', ajaxUrl + '?action=hmo_search_users&q=' + encodeURIComponent(q) + '&_ajax_nonce=' + encodeURIComponent(nonce));
			xhr.onload = function() {
				spinnerEl.style.display = 'none';
				try {
					var resp = JSON.parse(xhr.responseText);
					if (!resp.success || !resp.data.length) {
						resultsBox.innerHTML = '<li style="padding:8px 12px;color:#888;">No users found.</li>';
					} else {
						resultsBox.innerHTML = '';
						resp.data.forEach(function(u) {
							var li = document.createElement('li');
							li.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;';
							li.textContent = u.name + ' (' + u.email + ')';
							li.addEventListener('mousedown', function(e) {
								e.preventDefault();
								addViewer(u);
								searchInput.value = '';
								resultsBox.style.display = 'none';
							});
							li.addEventListener('mouseover', function(){ this.style.background='#f0f0f0'; });
							li.addEventListener('mouseout',  function(){ this.style.background=''; });
							resultsBox.appendChild(li);
						});
					}
					resultsBox.style.display = 'block';
				} catch(err) {}
			};
			xhr.onerror = function(){ spinnerEl.style.display = 'none'; };
			xhr.send();
		}, 300);
	});

	document.addEventListener('click', function(e) {
		if (e.target !== searchInput) resultsBox.style.display = 'none';
	});
	searchInput.addEventListener('blur', function(){
		setTimeout(function(){ resultsBox.style.display = 'none'; }, 200);
	});
	searchInput.addEventListener('focus', function(){
		if (resultsBox.children.length) resultsBox.style.display = 'block';
	});

	document.getElementById('hmo-reset-message').addEventListener('click', function(e){
		e.preventDefault();
		document.getElementById('hmo_denial_message').value = defaultMsg;
	});

	function escHtml(str) {
		return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}
})();
</script>
<?php endif; ?>
</div>
