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
	// Checkbox — unchecked inputs are absent from POST, so we always write it.
	update_option( 'hmo_hide_list_links', isset( $_POST['hmo_hide_list_links'] ) ? 1 : 0 );
	$notice = '<div class="notice notice-success is-dismissible"><p>General settings saved.</p></div>';
}

// (Marketer bulk-save removed — bucket access is now managed via AJAX on the Bucket Access tab)

// Page links
if ( isset( $_POST['hmo_save_page_urls'] ) ) {
	check_admin_referer( 'hmo_save_page_urls' );
	HMO_Page_URLs::save_overrides(
		sanitize_text_field( $_POST['hmo_url_dashboard_selector'] ?? '' ),
		sanitize_text_field( $_POST['hmo_url_dashboard']          ?? '' ),
		sanitize_text_field( $_POST['hmo_url_my_classes']         ?? '' ),
		sanitize_text_field( $_POST['hmo_url_event_detail']       ?? '' ),
		sanitize_text_field( $_POST['hmo_url_task_editor']        ?? '' ),
		sanitize_text_field( $_POST['hmo_url_event_report']       ?? '' )
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

// Report viewers save
if ( isset( $_POST['hmo_save_report_viewers'] ) ) {
	check_admin_referer( 'hmo_user_access' );
	$access_svc  = new HMO_Access_Service();
	$raw_ids     = sanitize_text_field( $_POST['hmo_report_viewer_ids'] ?? '' );
	$ids         = $raw_ids !== '' ? explode( ',', $raw_ids ) : array();
	$access_svc->save_report_viewers( $ids );
	$notice = '<div class="notice notice-success is-dismissible"><p>Report viewer settings saved.</p></div>';
}

// Marketing admins save
if ( isset( $_POST['hmo_save_marketing_admins'] ) ) {
	check_admin_referer( 'hmo_user_access' );
	$access_svc  = new HMO_Access_Service();
	$raw_ids     = sanitize_text_field( $_POST['hmo_marketing_admin_ids'] ?? '' );
	$ids         = $raw_ids !== '' ? explode( ',', $raw_ids ) : array();
	$access_svc->save_marketing_admins( $ids );
	$notice = '<div class="notice notice-success is-dismissible"><p>Marketing admin settings saved.</p></div>';
}

// Tools links save
if ( isset( $_POST['hmo_save_tools'] ) ) {
	check_admin_referer( 'hmo_save_tools' );
	$raw_names = isset( $_POST['hmo_tool_name'] ) && is_array( $_POST['hmo_tool_name'] )
		? $_POST['hmo_tool_name'] : array();
	$raw_urls  = isset( $_POST['hmo_tool_url'] )  && is_array( $_POST['hmo_tool_url'] )
		? $_POST['hmo_tool_url']  : array();
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

// ── Current state ─────────────────────────────────────────────────────────────

$access_svc           = new HMO_Access_Service();
$approved_ids         = $access_svc->get_approved_viewers();
$task_editor_ids      = $access_svc->get_task_editors();
$report_viewer_ids    = $access_svc->get_report_viewers();
$marketing_admin_ids  = $access_svc->get_marketing_admins();
$denial_message    = get_option( HMO_Access_Service::OPT_MESSAGE, HMO_Access_Service::DEFAULT_MESSAGE );
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

$report_viewer_users = array();
if ( ! empty( $report_viewer_ids ) ) {
	$report_viewer_users = get_users( array(
		'include' => $report_viewer_ids,
		'fields'  => array( 'ID', 'display_name', 'user_email' ),
		'orderby' => 'display_name',
	) );
}

$marketing_admin_users = array();
if ( ! empty( $marketing_admin_ids ) ) {
	$marketing_admin_users = get_users( array(
		'include' => $marketing_admin_ids,
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
	'general'       => 'General',
	'bucket-access' => 'Bucket Access',
	'page-links'    => 'Page Links',
	'user-access'   => 'User Access',
	'tools'         => 'Tools Links',
	'page-sync'     => 'GWU Page Sync',
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

	<hr style="margin:24px 0;">

	<h2>Task Provisioning</h2>
	<p>
		Task checklists are created per-event the first time you open an event detail page.
		Use this button to bulk-provision tasks for <strong>all future events</strong> at once,
		so open-task counts are accurate on the dashboard immediately.
	</p>
	<p>
		<button type="button" class="button button-secondary" id="hmo-bulk-provision-btn">
			&#9654; Provision Tasks for Future Events
		</button>
		<span id="hmo-bulk-provision-status" style="margin-left:12px;font-size:13px;"></span>
	</p>

	<h2 style="margin-top:24px;">Recount Open Tasks</h2>
	<p>
		Use this to recalculate the open-task counter shown on the dashboard for every already-provisioned future event.
		This is safe to run at any time — completed tasks are never affected; only the stored count is updated.
		Run this after editing the task template to ensure counts reflect the current task list.
	</p>
	<p>
		<button type="button" class="button button-secondary" id="hmo-recount-all-btn">
			&#9654; Recount Open Tasks for Future Events
		</button>
		<span id="hmo-recount-all-status" style="margin-left:12px;font-size:13px;"></span>
	</p>

	<script>
	(function() {
		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'hmo_recount_all_tasks' ) ); ?>;
		var btn     = document.getElementById('hmo-recount-all-btn');
		var status  = document.getElementById('hmo-recount-all-status');

		btn.addEventListener('click', function() {
			btn.disabled = true;
			btn.textContent = '⏳ Recounting…';
			status.style.color = '#888';
			status.textContent = 'Running…';

			var fd = new FormData();
			fd.append('action',      'hmo_recount_all_tasks');
			fd.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					btn.disabled = false;
					btn.textContent = '✓ Recount Open Tasks for Future Events';
					if (res.success) {
						status.style.color = '#007017';
						status.textContent = 'Done! ' + res.data.updated + ' future event(s) recounted.';
					} else {
						status.style.color = '#d63638';
						status.textContent = 'Error: ' + (res.data || 'Unknown error.');
					}
				})
				.catch(function() {
					btn.disabled = false;
					btn.textContent = '✓ Recount Open Tasks for Future Events';
					status.style.color = '#d63638';
					status.textContent = 'Request failed. Please try again.';
				});
		});
	})();
	</script>

	<h2 style="margin-top:24px;">Registration Goal</h2>
	<p>
		Use this to apply the current Default Registration Goal
		(<strong><?php echo (int) get_option( 'hmo_default_goal', 25 ); ?></strong>)
		to all <strong>future</strong> events that still have the old default stored. Past events are never changed.
	</p>
	<p>
		<button type="button" class="button button-secondary" id="hmo-reset-goals-btn">
			&#9654; Apply Current Default Goal to Future Events
		</button>
		<span id="hmo-reset-goals-status" style="margin-left:12px;font-size:13px;"></span>
	</p>

	<script>
	(function() {
		var ajaxUrl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce      = <?php echo wp_json_encode( wp_create_nonce( 'hmo_reset_goals' ) ); ?>;
		var btn        = document.getElementById('hmo-reset-goals-btn');
		var status     = document.getElementById('hmo-reset-goals-status');

		btn.addEventListener('click', function() {
			if ( ! confirm('This will overwrite the stored registration goal for all future events. Continue?') ) { return; }
			btn.disabled = true;
			btn.textContent = '⏳ Applying…';
			status.style.color = '#888';
			status.textContent = 'Updating…';

			var fd = new FormData();
			fd.append('action',      'hmo_reset_goals');
			fd.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					btn.disabled = false;
					btn.textContent = '✓ Apply Current Default Goal to Future Events';
					if (res.success) {
						status.style.color = '#007017';
						status.textContent = 'Done! ' + res.data.updated + ' future events updated to goal ' + res.data.goal + '.';
					} else {
						status.style.color = '#d63638';
						status.textContent = 'Error: ' + (res.data || 'Unknown error.');
					}
				})
				.catch(function() {
					btn.disabled = false;
					btn.textContent = '✓ Apply Current Default Goal to Future Events';
					status.style.color = '#d63638';
					status.textContent = 'Request failed. Please try again.';
				});
		});
	})();
	</script>

	<script>
	(function() {
		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'hmo_bulk_provision' ) ); ?>;
		var btn     = document.getElementById('hmo-bulk-provision-btn');
		var status  = document.getElementById('hmo-bulk-provision-status');

		btn.addEventListener('click', function() {
			btn.disabled = true;
			btn.textContent = '⏳ Provisioning…';
			status.style.color = '#888';
			status.textContent = 'Running — this may take a moment for large event lists…';

			var fd = new FormData();
			fd.append('action',      'hmo_bulk_provision');
			fd.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r) { return r.json(); })
				.then(function(res) {
					btn.disabled = false;
					btn.textContent = '✓ Provision Tasks for Future Events';
					if (res.success) {
						var d = res.data;
						status.style.color = '#007017';
						status.textContent =
							'Done! ' + d.total + ' future events scanned — ' +
							d.provisioned + ' newly provisioned, ' +
							d.already_done + ' already had tasks.';
					} else {
						status.style.color = '#d63638';
						status.textContent = 'Error: ' + (res.data || 'Unknown error.');
					}
				})
				.catch(function() {
					btn.disabled = false;
					btn.textContent = '✓ Provision Tasks for Future Events';
					status.style.color = '#d63638';
					status.textContent = 'Request failed. Please try again.';
				});
		});
	})();
	</script>

	<hr style="margin:24px 0;">

	<h2>Bulk Stage Completion</h2>
	<p>
		Mark all tasks in selected stages as <strong>complete</strong> for every event within the specified days window.
		Only <em>pending</em> tasks are changed; already-completed tasks are untouched.
		This action <strong>cannot be undone</strong> in bulk — proceed carefully.
	</p>
	<table class="form-table" style="max-width:520px;">
		<tr>
			<th><label>Days Out (&le;)</label></th>
			<td>
				<input type="number" id="hmo-bulk-complete-days" value="50" min="1" max="730" class="small-text">
				<p class="description">Complete tasks on events happening within this many days from today.</p>
			</td>
		</tr>
		<tr>
			<th><label>Stages to Complete</label></th>
			<td>
				<?php foreach ( HMO_Checklist_Templates::get_stages_option() as $s ) : ?>
				<label style="display:block;margin-bottom:4px;">
					<input type="checkbox" class="hmo-bulk-complete-stage"
						value="<?php echo esc_attr( $s['key'] ); ?>"
						<?php checked( in_array( $s['key'], array( 'event_setup', 'data_send_prep' ), true ) ); ?>>
					<?php echo esc_html( $s['label'] ); ?>
				</label>
				<?php endforeach; ?>
			</td>
		</tr>
	</table>
	<p>
		<button type="button" class="button button-secondary" id="hmo-bulk-complete-btn">
			&#9654; Mark Selected Stages Complete
		</button>
		<span id="hmo-bulk-complete-status" style="margin-left:12px;font-size:13px;"></span>
	</p>

	<script>
	(function() {
		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'hmo_bulk_complete_stages' ) ); ?>;
		var btn     = document.getElementById('hmo-bulk-complete-btn');
		var status  = document.getElementById('hmo-bulk-complete-status');

		btn.addEventListener('click', function() {
			var stages = Array.from( document.querySelectorAll('.hmo-bulk-complete-stage:checked') )
							   .map(function(cb){ return cb.value; });
			var days   = parseInt( document.getElementById('hmo-bulk-complete-days').value, 10 ) || 50;

			if ( stages.length === 0 ) {
				status.style.color = '#d63638';
				status.textContent = 'Please select at least one stage.';
				return;
			}

			var stageNames = Array.from( document.querySelectorAll('.hmo-bulk-complete-stage:checked') )
								  .map(function(cb){ return cb.closest('label').textContent.trim(); });

			if ( ! confirm(
				'This will mark ALL pending tasks in:\n\n  • ' + stageNames.join('\n  • ') +
				'\n\nComplete for every event within the next ' + days + ' days.\n\nThis cannot be undone in bulk. Continue?'
			) ) { return; }

			btn.disabled = true;
			btn.textContent = '⏳ Running…';
			status.style.color = '#888';
			status.textContent = 'Processing events…';

			var fd = new FormData();
			fd.append('action',      'hmo_bulk_complete_stages');
			fd.append('_ajax_nonce', nonce);
			fd.append('days_out',    days);
			stages.forEach(function(s){ fd.append('stages[]', s); });

			fetch(ajaxUrl, { method: 'POST', body: fd })
				.then(function(r){ return r.json(); })
				.then(function(res) {
					btn.disabled = false;
					btn.textContent = '&#9654; Mark Selected Stages Complete';
					if (res.success) {
						var d = res.data;
						status.style.color = '#007017';
						status.textContent =
							'Done! Scanned ' + d.events + ' events within ' + d.days_out + ' days — ' +
							d.affected_events + ' had pending tasks; ' +
							d.tasks_completed + ' task(s) marked complete.';
					} else {
						status.style.color = '#d63638';
						status.textContent = 'Error: ' + (res.data || 'Unknown error.');
					}
				})
				.catch(function() {
					btn.disabled = false;
					btn.textContent = '&#9654; Mark Selected Stages Complete';
					status.style.color = '#d63638';
					status.textContent = 'Request failed. Please try again.';
				});
		});
	})();
	</script>

	<hr style="margin:24px 0;">

	<h2>Event Detail Display</h2>
	<table class="form-table">
		<tr>
			<th><label for="hmo_hide_list_links">List Links Card</label></th>
			<td>
				<label>
					<input type="checkbox" id="hmo_hide_list_links" name="hmo_hide_list_links" value="1"
						<?php checked( 1, (int) get_option( 'hmo_hide_list_links', 0 ) ); ?>>
					Hide the "List Links" card on the Event Detail page
				</label>
				<p class="description">When checked, the Data List / Call List card is hidden for all users.</p>
			</td>
		</tr>
	</table>

	<?php submit_button( 'Save General Settings', 'primary', 'hmo_save_general' ); ?>
</form>

<!-- ======================================================================
     TAB: BUCKET ACCESS
     ====================================================================== -->
<?php elseif ( $active_tab === 'bucket-access' ) :
	$bucket_nonce  = wp_create_nonce( 'hmo_bucket_access' );
	$search_nonce  = wp_create_nonce( 'hmo_user_access' );
	$bridge_ba     = new HMO_Hostlinks_Bridge();
	$all_buckets   = $bridge_ba->get_marketers();
	$bucket_access = HMO_DB::get_all_bucket_access(); // keyed by marketer_id

	// Pre-load WP user data for all assigned users.
	$all_bucket_user_ids = array();
	foreach ( $bucket_access as $entry ) {
		$all_bucket_user_ids = array_merge( $all_bucket_user_ids, $entry['users'] );
	}
	$user_data_map = array();
	if ( ! empty( $all_bucket_user_ids ) ) {
		$fetched = get_users( array(
			'include' => array_unique( $all_bucket_user_ids ),
			'fields'  => array( 'ID', 'display_name', 'user_email' ),
		) );
		foreach ( $fetched as $u ) {
			$user_data_map[ (int) $u->ID ] = $u;
		}
	}
?>
<p>
	Assign WordPress users to event buckets. A user can be assigned to multiple buckets;
	a bucket can have multiple users. Administrators always see all events regardless of bucket assignment.
</p>

<?php if ( empty( $all_buckets ) ) : ?>
	<div class="notice notice-warning inline"><p>No active marketers (buckets) found in Hostlinks.</p></div>
<?php else : ?>

<div id="hmo-bucket-access-wrap">
<?php foreach ( $all_buckets as $bkt ) :
	$mid    = (int) $bkt->event_marketer_id;
	$bname  = $bkt->event_marketer_name;
	$uids   = $bucket_access[ $mid ]['users'] ?? array();
?>
<div class="hmo-bucket-row" id="hmo-bucket-row-<?php echo $mid; ?>">
	<div class="hmo-bucket-row__header">
		<strong class="hmo-bucket-row__name"><?php echo esc_html( $bname ); ?></strong>
		<small style="color:#8c8f94;margin-left:6px;">ID: <?php echo $mid; ?></small>
	</div>
	<div class="hmo-bucket-row__users" id="hmo-bucket-users-<?php echo $mid; ?>">
		<?php foreach ( $uids as $uid ) :
			$u = $user_data_map[ $uid ] ?? null;
			if ( ! $u ) { continue; }
		?>
		<span class="hmo-bucket-pill hmo-bucket-pill--assigned" id="hmo-bpill-<?php echo $mid; ?>-<?php echo (int) $uid; ?>">
			<?php echo esc_html( $u->display_name ); ?>
			<button type="button" class="hmo-bucket-pill__remove"
				data-marketer-id="<?php echo $mid; ?>"
				data-user-id="<?php echo (int) $uid; ?>"
				title="Remove user">×</button>
		</span>
		<?php endforeach; ?>
		<?php if ( empty( $uids ) ) : ?>
		<span class="hmo-bucket-empty" id="hmo-bucket-empty-<?php echo $mid; ?>">No users assigned.</span>
		<?php endif; ?>
	</div>
	<div class="hmo-bucket-row__add">
		<input type="text" class="hmo-bucket-user-search"
			placeholder="Search to add a user…"
			data-marketer-id="<?php echo $mid; ?>"
			data-bucket-name="<?php echo esc_attr( $bname ); ?>"
			autocomplete="off"
			style="width:260px;">
		<ul class="hmo-bucket-search-results" data-marketer-id="<?php echo $mid; ?>"
			style="list-style:none;margin:0;padding:0;max-width:360px;border:1px solid #ddd;border-top:none;display:none;background:#fff;position:absolute;z-index:200;"></ul>
	</div>
</div>
<?php endforeach; ?>
</div>

<style>
#hmo-bucket-access-wrap { max-width: 820px; }
.hmo-bucket-row { border: 1px solid #dcdcde; border-radius: 5px; padding: 12px 16px; margin-bottom: 12px; background: #fff; }
.hmo-bucket-row__header { margin-bottom: 8px; }
.hmo-bucket-row__users { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; min-height: 28px; align-items: center; }
.hmo-bucket-pill--assigned { display:inline-flex; align-items:center; gap:4px; background:hsl(199 89% 90%); color:hsl(199 89% 30%); border:1px solid hsl(199 60% 75%); border-radius:20px; padding:3px 10px 3px 12px; font-size:12px; font-weight:600; }
.hmo-bucket-pill__remove { background:none; border:none; cursor:pointer; font-size:15px; line-height:1; color:hsl(199 60% 45%); padding:0; }
.hmo-bucket-pill__remove:hover { color:#d63638; }
.hmo-bucket-empty { font-size:12px; color:#8c8f94; font-style:italic; }
.hmo-bucket-row__add { position:relative; }
</style>

<script>
(function() {
	var ajaxUrl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var baNonce    = <?php echo wp_json_encode( $bucket_nonce ); ?>;
	var sNonce     = <?php echo wp_json_encode( $search_nonce ); ?>;

	function removeUserPill(marketerId, userId) {
		var fd = new FormData();
		fd.append('action', 'hmo_remove_bucket_access');
		fd.append('_ajax_nonce', baNonce);
		fd.append('marketer_id', marketerId);
		fd.append('wp_user_id', userId);
		fetch(ajaxUrl, { method:'POST', body:fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					var pill = document.getElementById('hmo-bpill-' + marketerId + '-' + userId);
					if (pill) pill.remove();
					var container = document.getElementById('hmo-bucket-users-' + marketerId);
					if (container && !container.querySelector('.hmo-bucket-pill--assigned')) {
						var empty = document.createElement('span');
						empty.id = 'hmo-bucket-empty-' + marketerId;
						empty.className = 'hmo-bucket-empty';
						empty.textContent = 'No users assigned.';
						container.appendChild(empty);
					}
				}
			});
	}

	function addUserToBucket(marketerId, bucketName, user) {
		var fd = new FormData();
		fd.append('action', 'hmo_add_bucket_access');
		fd.append('_ajax_nonce', baNonce);
		fd.append('marketer_id', marketerId);
		fd.append('bucket_name', bucketName);
		fd.append('wp_user_id', user.id);
		fetch(ajaxUrl, { method:'POST', body:fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				if (res.success) {
					var container = document.getElementById('hmo-bucket-users-' + marketerId);
					var empty = document.getElementById('hmo-bucket-empty-' + marketerId);
					if (empty) empty.remove();
					var span = document.createElement('span');
					span.id = 'hmo-bpill-' + marketerId + '-' + user.id;
					span.className = 'hmo-bucket-pill hmo-bucket-pill--assigned';
					span.innerHTML = escHtml(res.data.name) +
						' <button type="button" class="hmo-bucket-pill__remove" data-marketer-id="' + marketerId + '" data-user-id="' + user.id + '" title="Remove user">\u00d7</button>';
					container.appendChild(span);
				}
			});
	}

	// Remove via click.
	document.addEventListener('click', function(e) {
		var btn = e.target.closest('.hmo-bucket-pill__remove');
		if (!btn) return;
		removeUserPill(parseInt(btn.dataset.marketerId, 10), parseInt(btn.dataset.userId, 10));
	});

	// Search and add.
	var timers = {};
	document.querySelectorAll('.hmo-bucket-user-search').forEach(function(input) {
		var marketerId  = parseInt(input.dataset.marketerId, 10);
		var bucketName  = input.dataset.bucketName;
		var resultsBox  = document.querySelector('.hmo-bucket-search-results[data-marketer-id="' + marketerId + '"]');

		input.addEventListener('input', function() {
			clearTimeout(timers[marketerId]);
			var q = input.value.trim();
			if (q.length < 2) { resultsBox.style.display = 'none'; return; }
			timers[marketerId] = setTimeout(function() {
				var fd = new FormData();
				fd.append('action', 'hmo_search_users');
				fd.append('_ajax_nonce', sNonce);
				fd.append('q', q);
				fetch(ajaxUrl, { method:'POST', body:fd })
					.then(function(r) { return r.json(); })
					.then(function(res) {
						resultsBox.innerHTML = '';
						if (!res.success || !res.data.length) { resultsBox.style.display='none'; return; }
						res.data.forEach(function(u) {
							// Skip already-assigned.
							if (document.getElementById('hmo-bpill-' + marketerId + '-' + u.id)) return;
							var li = document.createElement('li');
							li.style.cssText = 'padding:7px 12px;cursor:pointer;border-bottom:1px solid #eee;font-size:13px;';
							li.textContent = u.name + ' (' + u.email + ')';
							li.addEventListener('mousedown', function(e) {
								e.preventDefault();
								addUserToBucket(marketerId, bucketName, u);
								input.value = '';
								resultsBox.style.display = 'none';
							});
							li.addEventListener('mouseover', function(){ this.style.background='#f0f0f0'; });
							li.addEventListener('mouseout',  function(){ this.style.background=''; });
							resultsBox.appendChild(li);
						});
						if (resultsBox.children.length) {
							resultsBox.style.display = 'block';
						}
					});
			}, 280);
		});

		input.addEventListener('blur', function() {
			setTimeout(function() { resultsBox.style.display = 'none'; }, 200);
		});
	});

	function escHtml(s) {
		return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}
})();
</script>
<?php endif; ?>

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
			'dashboard_selector' => array( 'label' => 'Marketing Ops Selector', 'shortcode' => '[hmo_dashboard_selector]', 'field' => 'hmo_url_dashboard_selector' ),
			'dashboard'          => array( 'label' => 'Dashboard',              'shortcode' => '[hmo_dashboard]',          'field' => 'hmo_url_dashboard' ),
			'my_classes'         => array( 'label' => 'My Classes',             'shortcode' => '[hmo_my_classes]',         'field' => 'hmo_url_my_classes' ),
			'event_detail'       => array( 'label' => 'Event Detail',           'shortcode' => '[hmo_event_detail]',       'field' => 'hmo_url_event_detail' ),
			'task_editor'        => array( 'label' => 'Task Template Editor',   'shortcode' => '[hmo_task_editor]',        'field' => 'hmo_url_task_editor' ),
			'event_report'       => array( 'label' => 'Event Journey Report',   'shortcode' => '[hmo_event_report]',       'field' => 'hmo_url_event_report' ),
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

<!-- ── Report Viewer Users ────────────────────────────────────────────────── -->
<hr style="margin:32px 0;">
<h2>Event Journey Report — Allowed Viewers</h2>
<p>
	Users listed here can access the <code>[hmo_event_report]</code> shortcode to view event journey and task completion reports.
	Administrators always have access regardless of this list.
</p>

<form method="post" action="" id="hmo-rv-users-form">
	<?php wp_nonce_field( 'hmo_user_access' ); ?>
	<input type="hidden" id="hmo_report_viewer_ids" name="hmo_report_viewer_ids"
		value="<?php echo esc_attr( implode( ',', $report_viewer_ids ) ); ?>">

	<div style="max-width:560px;margin-bottom:8px;">
		<label for="hmo-rv-user-search" style="display:block;font-weight:600;margin-bottom:6px;">Search users to add</label>
		<div style="display:flex;gap:8px;">
			<input type="text" id="hmo-rv-user-search" placeholder="Type a name or email…"
				class="regular-text" autocomplete="off" style="flex:1;">
			<span id="hmo-rv-search-spinner" style="display:none;line-height:30px;color:#888;">Searching…</span>
		</div>
		<ul id="hmo-rv-search-results" style="
			list-style:none;margin:0;padding:0;max-width:560px;
			border:1px solid #ddd;border-top:none;display:none;
			background:#fff;position:relative;z-index:100;"></ul>
	</div>

	<table class="widefat striped" style="max-width:560px;margin-bottom:24px;" id="hmo-rv-users-table">
		<thead>
			<tr><th>Name</th><th>Email</th><th style="width:80px;"></th></tr>
		</thead>
		<tbody id="hmo-rv-users-tbody">
		<?php if ( empty( $report_viewer_users ) ) : ?>
			<tr id="hmo-rv-users-empty"><td colspan="3" style="color:#888;font-style:italic;">No report viewer users yet.</td></tr>
		<?php else : ?>
			<?php foreach ( $report_viewer_users as $u ) : ?>
			<tr id="hmo-rv-user-row-<?php echo (int) $u->ID; ?>">
				<td><?php echo esc_html( $u->display_name ); ?></td>
				<td><?php echo esc_html( $u->user_email ); ?></td>
				<td>
					<button type="button" class="button button-small hmo-rv-remove-user"
						data-id="<?php echo (int) $u->ID; ?>">Remove</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<p class="submit">
		<button type="submit" name="hmo_save_report_viewers" class="button button-primary">Save Report Viewer Users</button>
	</p>
</form>

<script>
(function() {
	var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'hmo_user_access' ) ); ?>;
	var rvIds    = <?php echo wp_json_encode( array_map( 'intval', $report_viewer_ids ) ); ?>;

	function syncRvIdsField() {
		document.getElementById('hmo_report_viewer_ids').value = rvIds.join(',');
	}

	var rvTimer;
	document.getElementById('hmo-rv-user-search').addEventListener('input', function() {
		clearTimeout( rvTimer );
		var q = this.value.trim();
		var $results = document.getElementById('hmo-rv-search-results');
		if ( q.length < 2 ) { $results.style.display = 'none'; return; }
		document.getElementById('hmo-rv-search-spinner').style.display = 'inline';
		rvTimer = setTimeout( function() {
			var fd = new FormData();
			fd.append('action','hmo_search_users');
			fd.append('q', q);
			fd.append('_ajax_nonce', nonce);
			fetch(ajaxUrl, { method:'POST', body: fd })
				.then(function(r){ return r.json(); })
				.then(function(res) {
					document.getElementById('hmo-rv-search-spinner').style.display = 'none';
					$results.innerHTML = '';
					if ( ! res.success || ! res.data.length ) { $results.style.display = 'none'; return; }
					res.data.forEach(function(u) {
						if ( rvIds.indexOf(u.id) !== -1 ) { return; }
						var li = document.createElement('li');
						li.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;';
						li.textContent = u.name + ' (' + u.email + ')';
						li.addEventListener('click', function() {
							rvIds.push(u.id);
							syncRvIdsField();
							var emptyRow = document.getElementById('hmo-rv-users-empty');
							if (emptyRow) emptyRow.remove();
							var tbody = document.getElementById('hmo-rv-users-tbody');
							var tr = document.createElement('tr');
							tr.id = 'hmo-rv-user-row-' + u.id;
							tr.innerHTML = '<td>' + u.name + '</td><td>' + u.email + '</td>' +
								'<td><button type="button" class="button button-small hmo-rv-remove-user" data-id="' + u.id + '">Remove</button></td>';
							tbody.appendChild(tr);
							$results.style.display = 'none';
							document.getElementById('hmo-rv-user-search').value = '';
						});
						$results.appendChild(li);
					});
					$results.style.display = $results.children.length ? 'block' : 'none';
				});
		}, 300 );
	});

	document.addEventListener('click', function(e) {
		if ( ! e.target.classList.contains('hmo-rv-remove-user') ) { return; }
		var id = parseInt( e.target.getAttribute('data-id'), 10 );
		rvIds = rvIds.filter(function(i){ return i !== id; });
		syncRvIdsField();
		var row = document.getElementById('hmo-rv-user-row-' + id);
		if (row) row.remove();
		if ( ! document.getElementById('hmo-rv-users-tbody').children.length ) {
			var tr = document.createElement('tr');
			tr.id = 'hmo-rv-users-empty';
			tr.innerHTML = '<td colspan="3" style="color:#888;font-style:italic;">No report viewer users yet.</td>';
			document.getElementById('hmo-rv-users-tbody').appendChild(tr);
		}
	});
})();
</script>

<!-- ── Marketing Admin Users ──────────────────────────────────────────────── -->
<hr style="margin:32px 0;">
<h2>Marketing Admins — Dashboard Access</h2>
<p>
	Users listed here will see the full <strong>Marketing Ops Dashboard</strong> when visiting the <code>[hmo_dashboard_selector]</code> page.
	Everyone else will see <strong>My Classes</strong>. WordPress administrators always have dashboard access regardless of this list.
</p>

<form method="post" action="" id="hmo-ma-users-form">
	<?php wp_nonce_field( 'hmo_user_access' ); ?>
	<input type="hidden" id="hmo_marketing_admin_ids" name="hmo_marketing_admin_ids"
		value="<?php echo esc_attr( implode( ',', $marketing_admin_ids ) ); ?>">

	<div style="max-width:560px;margin-bottom:8px;">
		<label for="hmo-ma-user-search" style="display:block;font-weight:600;margin-bottom:6px;">Search users to add</label>
		<div style="display:flex;gap:8px;">
			<input type="text" id="hmo-ma-user-search" placeholder="Type a name or email…"
				class="regular-text" autocomplete="off" style="flex:1;">
			<span id="hmo-ma-search-spinner" style="display:none;line-height:30px;color:#888;">Searching…</span>
		</div>
		<ul id="hmo-ma-search-results" style="
			list-style:none;margin:0;padding:0;max-width:560px;
			border:1px solid #ddd;border-top:none;display:none;
			background:#fff;position:relative;z-index:100;"></ul>
	</div>

	<table class="widefat striped" style="max-width:560px;margin-bottom:24px;" id="hmo-ma-users-table">
		<thead>
			<tr><th>Name</th><th>Email</th><th style="width:80px;"></th></tr>
		</thead>
		<tbody id="hmo-ma-users-tbody">
		<?php if ( empty( $marketing_admin_users ) ) : ?>
			<tr id="hmo-ma-users-empty"><td colspan="3" style="color:#888;font-style:italic;">No marketing admin users yet.</td></tr>
		<?php else : ?>
			<?php foreach ( $marketing_admin_users as $u ) : ?>
			<tr id="hmo-ma-user-row-<?php echo (int) $u->ID; ?>">
				<td><?php echo esc_html( $u->display_name ); ?></td>
				<td><?php echo esc_html( $u->user_email ); ?></td>
				<td>
					<button type="button" class="button button-small hmo-ma-remove-user"
						data-id="<?php echo (int) $u->ID; ?>">Remove</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<p class="submit">
		<button type="submit" name="hmo_save_marketing_admins" class="button button-primary">Save Marketing Admin Users</button>
	</p>
</form>

<script>
(function() {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'hmo_user_access' ) ); ?>;
	var maIds   = <?php echo wp_json_encode( array_map( 'intval', $marketing_admin_ids ) ); ?>;

	function syncMaIdsField() {
		document.getElementById('hmo_marketing_admin_ids').value = maIds.join(',');
	}

	var maTimer;
	document.getElementById('hmo-ma-user-search').addEventListener('input', function() {
		clearTimeout( maTimer );
		var q = this.value.trim();
		var $results = document.getElementById('hmo-ma-search-results');
		if ( q.length < 2 ) { $results.style.display = 'none'; return; }
		document.getElementById('hmo-ma-search-spinner').style.display = 'inline';
		maTimer = setTimeout( function() {
			var fd = new FormData();
			fd.append('action','hmo_search_users');
			fd.append('q', q);
			fd.append('_ajax_nonce', nonce);
			fetch(ajaxUrl, { method:'POST', body: fd })
				.then(function(r){ return r.json(); })
				.then(function(res) {
					document.getElementById('hmo-ma-search-spinner').style.display = 'none';
					$results.innerHTML = '';
					if ( ! res.success || ! res.data.length ) { $results.style.display = 'none'; return; }
					res.data.forEach(function(u) {
						if ( maIds.indexOf(u.id) !== -1 ) { return; }
						var li = document.createElement('li');
						li.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;';
						li.textContent = u.name + ' (' + u.email + ')';
						li.addEventListener('click', function() {
							maIds.push(u.id);
							syncMaIdsField();
							var emptyRow = document.getElementById('hmo-ma-users-empty');
							if (emptyRow) emptyRow.remove();
							var tbody = document.getElementById('hmo-ma-users-tbody');
							var tr = document.createElement('tr');
							tr.id = 'hmo-ma-user-row-' + u.id;
							tr.innerHTML = '<td>' + u.name + '</td><td>' + u.email + '</td>' +
								'<td><button type="button" class="button button-small hmo-ma-remove-user" data-id="' + u.id + '">Remove</button></td>';
							tbody.appendChild(tr);
							$results.style.display = 'none';
							document.getElementById('hmo-ma-user-search').value = '';
						});
						$results.appendChild(li);
					});
					$results.style.display = $results.children.length ? 'block' : 'none';
				});
		}, 300 );
	});

	document.addEventListener('click', function(e) {
		if ( ! e.target.classList.contains('hmo-ma-remove-user') ) { return; }
		var id = parseInt( e.target.getAttribute('data-id'), 10 );
		maIds = maIds.filter(function(i){ return i !== id; });
		syncMaIdsField();
		var row = document.getElementById('hmo-ma-user-row-' + id);
		if (row) row.remove();
		if ( ! document.getElementById('hmo-ma-users-tbody').children.length ) {
			var tr = document.createElement('tr');
			tr.id = 'hmo-ma-users-empty';
			tr.innerHTML = '<td colspan="3" style="color:#888;font-style:italic;">No marketing admin users yet.</td>';
			document.getElementById('hmo-ma-users-tbody').appendChild(tr);
		}
	});
})();
</script>

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
<!-- ======================================================================
     TAB: TOOLS LINKS
     ====================================================================== -->
<?php elseif ( $active_tab === 'tools' ) :
	$saved_tools = (array) get_option( 'hmo_tools_links', array() );
?>
<p>
	Add links that appear in the <strong>Tools</strong> card on every Event Detail page.
	Each link can have an optional icon image selected from the Media Library.
	The icon and link name are both clickable and open the URL in a new tab.
</p>

<style>
.hmo-tool-icon-cell { width: 90px; }
.hmo-tool-icon-wrap { display: flex; flex-direction: column; align-items: center; gap: 5px; }
.hmo-tool-icon-preview {
	width: 48px; height: 48px; border-radius: 6px;
	border: 1px solid #dcdcde; object-fit: contain;
	background: #f6f7f7; display: block;
}
.hmo-tool-icon-placeholder {
	width: 48px; height: 48px; border-radius: 6px;
	border: 1px dashed #c3c4c7; background: #f6f7f7;
	display: flex; align-items: center; justify-content: center;
	color: #c3c4c7; font-size: 20px; cursor: pointer;
}
.hmo-tool-icon-actions { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
.hmo-tool-icon-select { font-size: 11px !important; padding: 2px 6px !important; }
.hmo-tool-icon-remove { font-size: 11px !important; padding: 2px 6px !important; color: #d63638 !important; }
</style>

<form method="post" action="">
	<?php wp_nonce_field( 'hmo_save_tools' ); ?>

	<table class="widefat" id="hmo-tools-table" style="max-width:760px;margin-bottom:16px;">
		<thead>
			<tr>
				<th class="hmo-tool-icon-cell">Icon</th>
				<th>Link Name</th>
				<th>URL</th>
				<th style="width:50px;"></th>
			</tr>
		</thead>
		<tbody id="hmo-tools-tbody">
		<?php if ( empty( $saved_tools ) ) : ?>
			<tr id="hmo-tools-empty-row">
				<td colspan="4" style="color:#8c8f94;font-style:italic;padding:12px;">No links yet — add one below.</td>
			</tr>
		<?php else : ?>
			<?php foreach ( $saved_tools as $tool ) :
				$_icon_url = esc_attr( $tool['icon'] ?? '' );
			?>
			<tr class="hmo-tool-row">
				<td class="hmo-tool-icon-cell">
					<div class="hmo-tool-icon-wrap">
						<input type="hidden" name="hmo_tool_icon[]" class="hmo-tool-icon-input"
							value="<?php echo $_icon_url; ?>">
						<?php if ( $_icon_url ) : ?>
						<img src="<?php echo $_icon_url; ?>" class="hmo-tool-icon-preview" alt="">
						<?php else : ?>
						<div class="hmo-tool-icon-placeholder">+</div>
						<?php endif; ?>
						<div class="hmo-tool-icon-actions">
							<button type="button" class="button button-small hmo-tool-icon-select">
								<?php echo $_icon_url ? 'Change' : 'Select'; ?>
							</button>
							<?php if ( $_icon_url ) : ?>
							<button type="button" class="button button-small hmo-tool-icon-remove">Remove</button>
							<?php endif; ?>
						</div>
					</div>
				</td>
				<td>
					<input type="text" name="hmo_tool_name[]"
						value="<?php echo esc_attr( $tool['name'] ?? '' ); ?>"
						placeholder="Tool name" class="regular-text" style="width:100%;">
				</td>
				<td>
					<input type="url" name="hmo_tool_url[]"
						value="<?php echo esc_attr( $tool['url'] ?? '' ); ?>"
						placeholder="https://" class="regular-text" style="width:100%;">
				</td>
				<td style="text-align:center;vertical-align:middle;">
					<button type="button" class="button button-small hmo-remove-tool-row"
						title="Remove row">&times;</button>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<p>
		<button type="button" class="button" id="hmo-add-tool-row">+ Add Link</button>
	</p>

	<?php submit_button( 'Save Tools Links', 'primary', 'hmo_save_tools' ); ?>
</form>

<script>
(function() {
	var tbody   = document.getElementById('hmo-tools-tbody');
	var emptyId = 'hmo-tools-empty-row';

	/* ── WP Media frame (one shared instance) ─────────────────────── */
	var mediaFrame = null;
	var activeRow  = null;

	function openMediaPicker(row) {
		activeRow = row;
		if (!mediaFrame) {
			mediaFrame = wp.media({
				title:    'Select Tool Icon',
				button:   { text: 'Use this image' },
				library:  { type: 'image' },
				multiple: false
			});
			mediaFrame.on('select', function() {
				var att = mediaFrame.state().get('selection').first().toJSON();
				setIcon(activeRow, att.url);
			});
		}
		mediaFrame.open();
	}

	function setIcon(row, url) {
		row.querySelector('.hmo-tool-icon-input').value = url;
		var wrap = row.querySelector('.hmo-tool-icon-wrap');
		/* replace placeholder or existing img */
		var existing = wrap.querySelector('.hmo-tool-icon-preview, .hmo-tool-icon-placeholder');
		if (existing) existing.remove();
		var img = document.createElement('img');
		img.src = url;
		img.className = 'hmo-tool-icon-preview';
		img.alt = '';
		wrap.insertBefore(img, wrap.querySelector('.hmo-tool-icon-actions'));
		/* update buttons */
		var selectBtn = wrap.querySelector('.hmo-tool-icon-select');
		selectBtn.textContent = 'Change';
		var actions = wrap.querySelector('.hmo-tool-icon-actions');
		if (!actions.querySelector('.hmo-tool-icon-remove')) {
			var removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'button button-small hmo-tool-icon-remove';
			removeBtn.textContent = 'Remove';
			actions.appendChild(removeBtn);
		}
	}

	function clearIcon(row) {
		row.querySelector('.hmo-tool-icon-input').value = '';
		var wrap = row.querySelector('.hmo-tool-icon-wrap');
		var existing = wrap.querySelector('.hmo-tool-icon-preview');
		if (existing) existing.remove();
		if (!wrap.querySelector('.hmo-tool-icon-placeholder')) {
			var ph = document.createElement('div');
			ph.className = 'hmo-tool-icon-placeholder';
			ph.textContent = '+';
			wrap.insertBefore(ph, wrap.querySelector('.hmo-tool-icon-actions'));
		}
		var selectBtn = wrap.querySelector('.hmo-tool-icon-select');
		selectBtn.textContent = 'Select';
		var removeBtn = wrap.querySelector('.hmo-tool-icon-remove');
		if (removeBtn) removeBtn.remove();
	}

	/* ── Row builder for "+ Add Link" ─────────────────────────────── */
	function removeEmpty() {
		var e = document.getElementById(emptyId);
		if (e) e.remove();
	}

	function buildIconCell() {
		return '<td class="hmo-tool-icon-cell">' +
			'<div class="hmo-tool-icon-wrap">' +
				'<input type="hidden" name="hmo_tool_icon[]" class="hmo-tool-icon-input" value="">' +
				'<div class="hmo-tool-icon-placeholder">+</div>' +
				'<div class="hmo-tool-icon-actions">' +
					'<button type="button" class="button button-small hmo-tool-icon-select">Select</button>' +
				'</div>' +
			'</div>' +
		'</td>';
	}

	function addRow(name, url) {
		removeEmpty();
		var tr = document.createElement('tr');
		tr.className = 'hmo-tool-row';
		tr.innerHTML =
			buildIconCell() +
			'<td><input type="text" name="hmo_tool_name[]" value="' + escAttr(name || '') + '" placeholder="Tool name" class="regular-text" style="width:100%;"></td>' +
			'<td><input type="url"  name="hmo_tool_url[]"  value="' + escAttr(url  || '') + '" placeholder="https://"   class="regular-text" style="width:100%;"></td>' +
			'<td style="text-align:center;vertical-align:middle;"><button type="button" class="button button-small hmo-remove-tool-row" title="Remove row">&times;</button></td>';
		tbody.appendChild(tr);
	}

	document.getElementById('hmo-add-tool-row').addEventListener('click', function() {
		addRow('', '');
		tbody.lastElementChild.querySelector('input[name="hmo_tool_name[]"]').focus();
	});

	/* ── Event delegation ─────────────────────────────────────────── */
	document.addEventListener('click', function(e) {
		var row = e.target.closest('.hmo-tool-row');

		if (e.target.classList.contains('hmo-remove-tool-row')) {
			e.target.closest('tr').remove();
			if (!tbody.querySelector('.hmo-tool-row')) {
				var tr = document.createElement('tr');
				tr.id = emptyId;
				tr.innerHTML = '<td colspan="4" style="color:#8c8f94;font-style:italic;padding:12px;">No links yet — add one below.</td>';
				tbody.appendChild(tr);
			}
			return;
		}

		if (row && e.target.classList.contains('hmo-tool-icon-select')) {
			openMediaPicker(row);
			return;
		}

		if (row && e.target.classList.contains('hmo-tool-icon-remove')) {
			clearIcon(row);
			return;
		}

		if (row && e.target.classList.contains('hmo-tool-icon-placeholder')) {
			openMediaPicker(row);
			return;
		}
	});

	function escAttr(s) {
		return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}
})();
</script>

<!-- ======================================================================
     TAB: GWU PAGE SYNC
     ====================================================================== -->
<?php elseif ( $active_tab === 'page-sync' ) :
	$sync_status  = HMO_Page_Sync::get_config_status();
	$sync_nonce   = wp_create_nonce( 'hmo_page_sync_test' );
	$all_defined  = ! in_array( false, $sync_status, true );
?>

<h2>GWU Page Sync — Configuration Status</h2>
<p>
	When a new event is created in Hostlinks, Marketing Ops automatically creates a marketing page
	on <strong>grantwritingusa.com</strong> and saves the URL back to the event&#8217;s <em>WEB URL</em> field.
	This feature requires four constants in the <strong>subdomain&#8217;s</strong> <code>wp-config.php</code>.
</p>

<table class="widefat striped" style="max-width:540px;margin-bottom:20px;">
	<thead>
		<tr>
			<th>Constant</th>
			<th style="width:110px;">Status</th>
		</tr>
	</thead>
	<tbody>
	<?php
	$const_labels = array(
		'GWU_PRIMARY_API'           => 'Primary domain REST API URL',
		'GWU_API_USER'              => 'Application Password username',
		'GWU_API_PASS'              => 'Application Password',
		'GWU_EVENTS_PARENT_PAGE_ID' => 'Events parent page ID',
	);
	foreach ( $const_labels as $const => $label ) :
		$defined = $sync_status[ $const ] ?? false;
	?>
	<tr>
		<td>
			<code><?php echo esc_html( $const ); ?></code><br>
			<small style="color:#8c8f94;"><?php echo esc_html( $label ); ?></small>
		</td>
		<td>
			<?php if ( $defined ) : ?>
				<span style="color:#007017;font-weight:600;">&#10003; Defined</span>
				<?php if ( $const === 'GWU_PRIMARY_API' ) : ?>
					<br><small style="color:#555;"><?php echo esc_html( constant( $const ) ); ?></small>
				<?php elseif ( $const === 'GWU_API_USER' ) : ?>
					<br><small style="color:#555;"><?php echo esc_html( constant( $const ) ); ?></small>
				<?php elseif ( $const === 'GWU_EVENTS_PARENT_PAGE_ID' ) : ?>
					<br><small style="color:#555;">ID: <?php echo (int) constant( $const ); ?><?php echo ( (int) constant( $const ) === 0 ) ? ' (top-level pages)' : ''; ?></small>
				<?php endif; ?>
			<?php else : ?>
				<span style="color:#d63638;font-weight:600;">&#10007; Not set</span>
			<?php endif; ?>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<?php if ( $all_defined ) : ?>
<p>
	<button type="button" class="button button-secondary" id="hmo-test-page-sync-btn">
		&#9654; Test Connection to grantwritingusa.com
	</button>
	<span id="hmo-test-page-sync-status" style="margin-left:12px;font-size:13px;"></span>
</p>

<script>
(function() {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( $sync_nonce ); ?>;
	var btn     = document.getElementById('hmo-test-page-sync-btn');
	var status  = document.getElementById('hmo-test-page-sync-status');

	btn.addEventListener('click', function() {
		btn.disabled = true;
		btn.textContent = '\u23f3 Testing\u2026';
		status.style.color = '#888';
		status.textContent = 'Connecting\u2026';

		var fd = new FormData();
		fd.append('action',      'hmo_test_page_sync');
		fd.append('_ajax_nonce', nonce);

		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				btn.disabled = false;
				btn.textContent = '\u25b6 Test Connection to grantwritingusa.com';
				if (res.success) {
					status.style.color = '#007017';
					status.textContent = '\u2713 ' + res.data.message;
				} else {
					status.style.color = '#d63638';
					status.textContent = '\u2717 ' + (res.data || 'Unknown error.');
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = '\u25b6 Test Connection to grantwritingusa.com';
				status.style.color = '#d63638';
				status.textContent = 'Request failed. Please try again.';
			});
	});
})();
</script>
<?php else : ?>
<div class="notice notice-warning inline" style="max-width:540px;">
	<p>One or more constants are missing. Add all four to <code>wp-config.php</code> to enable page sync.</p>
</div>
<?php endif; ?>

<hr style="margin:28px 0;">

<h2>wp-config.php Setup</h2>
<p>
	Add the following four lines to <code>wp-config.php</code> on <strong>hostlinks.grantwritingusa.com</strong>
	(above <code>/* That&#8217;s all, stop editing! */</code>).
	The Application Password is generated in the <strong>grantwritingusa.com</strong> WP admin under
	<em>Users &rarr; event-automation &rarr; Profile &rarr; Application Passwords</em>.
</p>

<pre style="background:#f6f7f7;border:1px solid #dcdcde;padding:14px 16px;border-radius:4px;max-width:700px;overflow-x:auto;font-size:13px;line-height:1.7;"><?php echo esc_html(
"define( 'GWU_PRIMARY_API',           'https://grantwritingusa.com/wp-json/wp/v2' );
define( 'GWU_API_USER',              'event-automation' );
define( 'GWU_API_PASS',              'xxxx xxxx xxxx xxxx xxxx xxxx' ); // Application Password
define( 'GWU_EVENTS_PARENT_PAGE_ID',  0 ); // replace 0 with Events parent page ID if using one"
); ?></pre>

<hr style="margin:28px 0;">

<h2>How It Works</h2>
<ol>
	<li>A new event is saved in Hostlinks (manually or via Cvent import).</li>
	<li>The <code>hostlinks_event_created</code> action fires.</li>
	<li>Marketing Ops reads the full event record and builds a page title, slug, and HTML content from a standard template.</li>
	<li>A <code>POST /wp-json/wp/v2/pages</code> request is sent to grantwritingusa.com using the Application Password.</li>
	<li>The new page URL is saved back to the event&#8217;s <em>WEB URL</em> field in Hostlinks.</li>
	<li>The <code>[public_event_list]</code> shortcode on grantwritingusa.com immediately shows a working &#8220;details&#8221; link for the event.</li>
</ol>

<?php endif; ?>
</div>
