<?php
	$maps_api_key          = get_option( 'hmo_maps_census_api_key', '' );
	$maps_google_api_key   = get_option( 'hmo_maps_google_api_key', '' );
	$maps_frequency        = get_option( 'hmo_maps_sync_frequency', 'monthly' );
	$maps_page_heading     = get_option( 'hmo_maps_page_heading', '' );
	$maps_centroid_source  = get_option( 'hmo_maps_centroid_source', 'geographic' );
	$maps_init_date        = get_option( 'hmo_maps_centroids_initialized', '' );
	$maps_sync_date        = get_option( 'hmo_maps_last_sync', '' );
	$centroid_count        = HMO_Maps_DB::centroids_count();
	$stats_count           = HMO_Maps_DB::stats_count();
?>

<h2 style="margin-top:0;">Maps Tool Settings</h2>

<div style="background:hsl(199 60% 96%);border:1px solid hsl(199 50% 78%);border-radius:7px;padding:14px 18px;margin-bottom:22px;display:flex;align-items:flex-start;gap:14px;">
	<span style="font-size:1.4rem;line-height:1;margin-top:2px;">&#128506;</span>
	<div>
		<strong>Shortcode:</strong> <code>[display_maps_tool]</code><br>
		<span style="font-size:0.88rem;color:hsl(215 20% 38%);">
			Place this shortcode on any WordPress page to display the Maps radius-lookup tool.
			Only Hostlinks users with access will see the tool; others receive an &ldquo;Access Denied&rdquo; message.
		</span>
		<?php
		$maps_page_url = HMO_Page_URLs::get_maps_tool();
		if ( $maps_page_url ) : ?>
		<br><a href="<?php echo esc_url( $maps_page_url ); ?>" target="_blank" style="font-size:0.88rem;">
			&#8599; Open Maps Tool page
		</a>
		<?php else : ?>
		<br><span style="font-size:0.82rem;color:hsl(0 60% 45%);">Page URL not set — add it in the <a href="<?php echo esc_url( add_query_arg( 'tab', 'page-links', menu_page_url( 'hostlinks-marketing-ops', false ) ) ); ?>">Page Links</a> tab.</span>
		<?php endif; ?>
	</div>
</div>

<p>
	Configure the radius-lookup tool and load the geographic and demographic data into the database.
	The tool uses the bundled Gazetteer and Census PEP data files — no live API calls are needed for initialization or stats.
	City autocomplete uses <strong>Google Places API</strong> (paste your key below). The Lookup button also works without autocomplete
	by falling back to OpenStreetMap geocoding.
</p>

<form method="post" action="">
	<?php wp_nonce_field( 'hmo_save_maps' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="hmo_maps_page_heading">Page Heading</label></th>
			<td>
				<input type="text" id="hmo_maps_page_heading" name="hmo_maps_page_heading"
					value="<?php echo esc_attr( $maps_page_heading ); ?>" class="regular-text"
					placeholder="Marketing Maps">
				<p class="description">Text displayed in the blue header bar on the Maps tool page. Defaults to <strong>Marketing Maps</strong> if left blank.</p>
			</td>
		</tr>
		<tr>
			<th><label for="hmo_maps_centroid_source">Centroid Source</label></th>
			<td>
				<select id="hmo_maps_centroid_source" name="hmo_maps_centroid_source">
					<option value="geographic"         <?php selected( $maps_centroid_source, 'geographic' ); ?>>Geographic (2024 Gazetteer — land-area center)</option>
					<option value="population_weighted"<?php selected( $maps_centroid_source, 'population_weighted' ); ?>>Population-Weighted (2020 Census — where people live)</option>
				</select>
				<p class="description">
					Controls which centroid is used when you click <strong>Initialize Centroids</strong>.<br>
					<strong>Geographic</strong> uses the geographic center of each county's land area (2024 Census Gazetteer).<br>
					<strong>Population-Weighted</strong> uses the center of where the county's population actually lives (2020 Census Centers of Population) — recommended for marketing reach analysis.<br>
					After changing this setting, save and then click <strong>Initialize Centroids</strong> to reload the data.
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="hmo_maps_google_api_key">Google Maps API Key</label></th>
			<td>
				<input type="text" id="hmo_maps_google_api_key" name="hmo_maps_google_api_key"
					value="<?php echo esc_attr( $maps_google_api_key ); ?>" class="regular-text">
				<p class="description">
					Required for city autocomplete. Paste your Google Maps Platform key here —
					enable <strong>Places API</strong> in your Google Cloud Console.
					<?php if ( empty( $maps_google_api_key ) ) : ?>
					<br><span style="color:#d63638;">&#9888; No key set — autocomplete will fall back to basic search.</span>
					<?php else : ?>
					<br><span style="color:#00a32a;">&#10003; Key saved.</span>
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="hmo_maps_census_api_key">Census API Key</label></th>
			<td>
				<input type="text" id="hmo_maps_census_api_key" name="hmo_maps_census_api_key"
					value="<?php echo esc_attr( $maps_api_key ); ?>" class="regular-text">
				<p class="description">
					<strong style="color:#b45309;">&#9201; Future Feature</strong> — when implemented, this key will enable automatic annual updates of county population and migration data directly from the Census Bureau API, eliminating the need to manually replace the bundled CSV each spring. Census population estimates update once per year (released ~March/April). Obtain a free key at <a href="https://api.census.gov/data/key_signup.html" target="_blank" rel="noopener">api.census.gov</a>.
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="hmo_maps_sync_frequency">Sync Frequency</label></th>
			<td>
				<select id="hmo_maps_sync_frequency" name="hmo_maps_sync_frequency">
					<option value="monthly"   <?php selected( $maps_frequency, 'monthly' ); ?>>Monthly</option>
					<option value="quarterly" <?php selected( $maps_frequency, 'quarterly' ); ?>>Quarterly</option>
					<option value="annually"  <?php selected( $maps_frequency, 'annually' ); ?>>Annually</option>
				</select>
				<p class="description">
					<strong style="color:#b45309;">&#9201; Future Feature</strong> — when the Census API integration is built, this will control how often WordPress automatically pulls fresh population and migration data in the background via WP Cron. <em>Annually</em> is recommended since Census estimates only update once per year. No automatic sync occurs yet.
				</p>
			</td>
		</tr>
	</table>
	<?php submit_button( 'Save Maps Settings', 'primary', 'hmo_save_maps' ); ?>
</form>

<hr style="margin:28px 0;">

<h2>Data Status</h2>
<table class="widefat" style="max-width:580px;margin-bottom:20px;">
	<tbody>
		<tr>
			<th style="width:220px;">County Centroids</th>
			<td>
				<?php if ( $centroid_count > 0 ) : ?>
					<span style="color:#007017;font-weight:600;">&#10003; <?php echo number_format( $centroid_count ); ?> counties loaded</span>
					<?php
					$_src_label = ( get_option( 'hmo_maps_centroid_source', 'geographic' ) === 'population_weighted' )
						? 'Population-Weighted (2020 Census)'
						: 'Geographic (2024 Gazetteer)';
					?>
					<br><small style="color:#555;">Source: <?php echo esc_html( $_src_label ); ?></small>
					<?php if ( $maps_init_date ) : ?>
					<br><small style="color:#888;">Last initialized: <?php echo esc_html( $maps_init_date ); ?></small>
					<?php endif; ?>
				<?php else : ?>
					<span style="color:#d63638;font-weight:600;">&#10007; Not initialized</span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th>Population Stats</th>
			<td>
				<?php if ( $stats_count > 0 ) : ?>
					<span style="color:#007017;font-weight:600;">&#10003; <?php echo number_format( $stats_count ); ?> counties synced</span>
					<?php if ( $maps_sync_date ) : ?>
					<br><small style="color:#888;">Last synced: <?php echo esc_html( $maps_sync_date ); ?></small>
					<?php endif; ?>
				<?php else : ?>
					<span style="color:#d63638;font-weight:600;">&#10007; Not synced</span>
				<?php endif; ?>
			</td>
		</tr>
	</tbody>
</table>

<p>
	<button type="button" class="button button-secondary" id="hmo-maps-init-btn">
		&#9654; Initialize Centroids
	</button>
	<span id="hmo-maps-init-status" style="margin-left:12px;font-size:13px;"></span>
</p>
<p style="margin-top:8px;">
	<button type="button" class="button button-secondary" id="hmo-maps-sync-btn">
		&#9654; Sync Stats Now
	</button>
	<span id="hmo-maps-sync-status" style="margin-left:12px;font-size:13px;"></span>
</p>

<p class="description" style="margin-top:12px;">
	<strong>Initialize Centroids</strong> — loads county centroids (~3,200 rows) using the <strong>Centroid Source</strong> selected above. Safe to re-run; switching sources requires re-running this.<br>
	<strong>Sync Stats</strong> — imports 2025 population and net migration data from the bundled Census PEP file. Re-run whenever you update the data file.
</p>

<script>
(function() {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var initNonce = <?php echo wp_json_encode( wp_create_nonce( 'hmo_maps_init_centroids' ) ); ?>;
	var syncNonce = <?php echo wp_json_encode( wp_create_nonce( 'hmo_maps_sync_stats' ) ); ?>;

	function runAction(btn, statusEl, action, nonce, label) {
		btn.disabled = true;
		btn.textContent = '⏳ Running…';
		statusEl.style.color = '#888';
		statusEl.textContent = 'Processing — this may take a moment…';

		var fd = new FormData();
		fd.append('action', action);
		fd.append('_ajax_nonce', nonce);

		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				btn.disabled = false;
				btn.textContent = '✓ ' + label;
				if (res.success) {
					statusEl.style.color = '#007017';
					statusEl.textContent = 'Done! ' + res.data.rows.toLocaleString() + ' rows processed.';
				} else {
					statusEl.style.color = '#d63638';
					statusEl.textContent = 'Error: ' + (res.data || 'Unknown error.');
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = '▶ ' + label;
				statusEl.style.color = '#d63638';
				statusEl.textContent = 'Request failed. Please try again.';
			});
	}

	document.getElementById('hmo-maps-init-btn').addEventListener('click', function() {
		var source = (document.getElementById('hmo_maps_centroid_source') || {}).value || 'geographic';
		var fd = new FormData();
		fd.append('action', 'hmo_maps_init_centroids');
		fd.append('_ajax_nonce', initNonce);
		fd.append('source', source);
		var btn = this;
		var statusEl = document.getElementById('hmo-maps-init-status');
		btn.disabled = true;
		btn.textContent = '⏳ Running…';
		statusEl.style.color = '#888';
		statusEl.textContent = 'Processing — this may take a moment…';
		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				btn.disabled = false;
				btn.textContent = '✓ Initialize Centroids';
				if (res.success) {
					statusEl.style.color = '#007017';
					statusEl.textContent = 'Done! ' + res.data.rows.toLocaleString() + ' rows processed (' + (res.data.source === 'population_weighted' ? 'population-weighted' : 'geographic') + ').';
				} else {
					statusEl.style.color = '#d63638';
					statusEl.textContent = 'Error: ' + (res.data || 'Unknown error.');
				}
			})
			.catch(function() {
				btn.disabled = false;
				btn.textContent = '▶ Initialize Centroids';
				statusEl.style.color = '#d63638';
				statusEl.textContent = 'Request failed. Please try again.';
			});
	});

	document.getElementById('hmo-maps-sync-btn').addEventListener('click', function() {
		runAction(this, document.getElementById('hmo-maps-sync-status'),
			'hmo_maps_sync_stats', syncNonce, 'Sync Stats Now');
	});
})();
</script>
