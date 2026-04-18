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
		<tr>
			<th scope="row">Allow Goal Editing</th>
			<td>
				<fieldset>
					<label>
						<input type="checkbox" name="hmo_goal_edit_marketing_admin" value="1"
							<?php checked( 1, get_option( 'hmo_goal_edit_marketing_admin', 0 ) ); ?>>
						Marketing Admins can edit the registration goal on event pages
					</label><br>
					<label style="margin-top:6px;display:inline-block;">
						<input type="checkbox" name="hmo_goal_edit_hostlinks_user" value="1"
							<?php checked( 1, get_option( 'hmo_goal_edit_hostlinks_user', 0 ) ); ?>>
						Hostlinks Users can edit the registration goal on event pages
					</label>
					<p class="description">When unchecked, the goal count is still displayed but the input field and Save button are hidden for that role. WordPress Administrators can always edit.</p>
				</fieldset>
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
