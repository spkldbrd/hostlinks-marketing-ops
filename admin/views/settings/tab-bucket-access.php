<?php
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
