<?php
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
		<?php foreach ( HMO_Access_Service::get_shortcodes() as $key => $label ) :
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
