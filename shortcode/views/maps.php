<?php
/**
 * Front-end view for [display_maps_tool].
 *
 * Variables available in scope (passed from HMO_Shortcodes::render_maps_tool):
 *   $access  — HMO_Access_Service instance
 *   $nonce   — wp_create_nonce('hmo_maps_lookup')
 *   $ajax_url  — admin_url('admin-ajax.php')
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Access is checked by the shortcode renderer before this view is included.

$_maps_task_url   = HMO_Page_URLs::get_task_editor();
$_maps_report_url = HMO_Page_URLs::get_event_report();
$_maps_is_mgr     = current_user_can( 'manage_options' ) || HMO_Access_Service::current_user_is_marketing_admin();
?>

<div class="hostlinks-page hmo-frontend hmo-maps-page">

<!-- Blue header bar — same as Dashboard / My Classes -->
<div class="hmo-dashboard-header">
	<span class="hmo-dashboard-header__title">Marketing Maps</span>
	<nav class="hmo-header-nav">
		<a href="<?php echo esc_url( Hostlinks_Page_URLs::get_upcoming() ); ?>" class="hmo-header-nav__link">
			&larr; Return to Hostlinks
		</a>
		<?php if ( $_maps_task_url && $_maps_is_mgr ) : ?>
		<span class="hmo-header-nav__sep" aria-hidden="true">|</span>
		<a href="<?php echo esc_url( $_maps_task_url ); ?>" class="hmo-header-nav__link">Task Management</a>
		<?php endif; ?>
		<?php if ( $_maps_report_url && $_maps_is_mgr ) : ?>
		<span class="hmo-header-nav__sep" aria-hidden="true">|</span>
		<a href="<?php echo esc_url( $_maps_report_url ); ?>" class="hmo-header-nav__link">Reports</a>
		<?php endif; ?>
	</nav>
</div>

<div class="hmo-maps-wrap" id="hmo-maps-wrap">

	<!-- ── Controls ────────────────────────────────────────────────────── -->
	<div class="hmo-maps-controls">
		<div class="hmo-maps-control-group hmo-maps-location-wrap">
			<label for="hmo-maps-location" class="hmo-maps-label">City, State</label>
			<input type="text" id="hmo-maps-location" class="hmo-maps-input"
				placeholder="e.g. Denver, CO" autocomplete="off" aria-autocomplete="list"
				aria-haspopup="listbox" aria-controls="hmo-maps-suggestions">
			<ul id="hmo-maps-suggestions" class="hmo-maps-suggestions" role="listbox"
				style="display:none;" aria-label="City suggestions"></ul>
		</div>

		<div class="hmo-maps-control-group hmo-maps-slider-group">
			<label for="hmo-maps-radius" class="hmo-maps-label">
				Radius: <strong id="hmo-maps-radius-val">100</strong> miles
			</label>
			<input type="range" id="hmo-maps-radius" class="hmo-maps-slider"
				min="25" max="500" step="25" value="100">
		</div>

		<div class="hmo-maps-control-group hmo-maps-btn-group">
			<button type="button" id="hmo-maps-lookup-btn" class="hmo-maps-btn">
				Lookup
			</button>
		</div>
	</div>

	<div id="hmo-maps-error" class="hmo-maps-error" style="display:none;"></div>
	<div id="hmo-maps-spinner" class="hmo-maps-spinner" style="display:none;" aria-label="Loading">
		<span class="hmo-maps-spinner-dot"></span>
		<span class="hmo-maps-spinner-dot"></span>
		<span class="hmo-maps-spinner-dot"></span>
	</div>

	<!-- ── Summary Dashboard ───────────────────────────────────────────── -->
	<div id="hmo-maps-summary" class="hmo-maps-summary" style="display:none;">
		<div class="hmo-maps-summary-meta" id="hmo-maps-summary-meta"></div>
		<div class="hmo-maps-summary-cards">
			<div class="hmo-maps-summary-card">
				<div class="hmo-maps-card-label">Total Reach</div>
				<div class="hmo-maps-card-value" id="hmo-maps-total-pop">—</div>
				<div class="hmo-maps-card-sub">estimated population (2025)</div>
			</div>
			<div class="hmo-maps-summary-card">
				<div class="hmo-maps-card-label">Market Growth</div>
				<div class="hmo-maps-card-value" id="hmo-maps-total-netmig">—</div>
				<div class="hmo-maps-card-sub">net migration (2025)</div>
			</div>
			<div class="hmo-maps-summary-card">
				<div class="hmo-maps-card-label">Counties</div>
				<div class="hmo-maps-card-value" id="hmo-maps-county-count">—</div>
				<div class="hmo-maps-card-sub">within radius</div>
			</div>
		</div>
	</div>

	<!-- ── Results Table ───────────────────────────────────────────────── -->
	<div id="hmo-maps-results" class="hmo-maps-results" style="display:none;">
		<div class="hmo-maps-results-header">
			<h3 class="hmo-maps-results-title">Results</h3>
			<button type="button" id="hmo-maps-export-btn" class="hmo-maps-export-btn">
				&#8659; Download CSV
			</button>
		</div>
		<div class="hmo-maps-table-wrap">
			<table class="hmo-maps-table" id="hmo-maps-table">
				<thead>
					<tr>
						<th data-col="state_abbr" class="sortable">State <span class="sort-icon">&#8597;</span></th>
						<th data-col="county_name" class="sortable">County <span class="sort-icon">&#8597;</span></th>
						<th data-col="pop_2025" class="sortable">Population <span class="sort-icon">&#8597;</span></th>
						<th data-col="netmig_2025" class="sortable">Net Migration <span class="sort-icon">&#8597;</span></th>
						<th data-col="distance_miles" class="sortable">Distance (mi) <span class="sort-icon">&#8597;</span></th>
					</tr>
				</thead>
				<tbody id="hmo-maps-tbody"></tbody>
			</table>
		</div>
	</div>

</div><!-- .hmo-maps-wrap -->

<script>
(function() {
	var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
	var nonce   = <?php echo wp_json_encode( $nonce ); ?>;

	// ── DOM refs ────────────────────────────────────────────────────────
	var btnLookup = document.getElementById('hmo-maps-lookup-btn');
	var btnExport = document.getElementById('hmo-maps-export-btn');
	var inputLoc  = document.getElementById('hmo-maps-location');
	var sliderRad = document.getElementById('hmo-maps-radius');
	var radVal    = document.getElementById('hmo-maps-radius-val');
	var errBox    = document.getElementById('hmo-maps-error');
	var spinner   = document.getElementById('hmo-maps-spinner');
	var summary   = document.getElementById('hmo-maps-summary');
	var results   = document.getElementById('hmo-maps-results');
	var tbody     = document.getElementById('hmo-maps-tbody');
	var acList    = document.getElementById('hmo-maps-suggestions');

	// ── State ───────────────────────────────────────────────────────────
	var currentData = [];
	var sortCol     = 'distance_miles';
	var sortDir     = 1;

	// Geocoords captured from autocomplete selection.
	// When set, the server skips Nominatim and uses these directly.
	var pinnedLat = null;
	var pinnedLng = null;

	// ── Radius slider ───────────────────────────────────────────────────
	sliderRad.addEventListener('input', function() {
		radVal.textContent = this.value;
	});

	// ── Lookup ──────────────────────────────────────────────────────────
	function runLookup() {
		var location = inputLoc.value.trim();
		if (!location) { showError('Please enter a city and state.'); return; }
		clearResults();
		showSpinner(true);
		hideError();

		var fd = new FormData();
		fd.append('action',   'hmo_maps_lookup');
		fd.append('nonce',    nonce);
		fd.append('location', location);
		fd.append('radius',   sliderRad.value);

		// If we have pinned coords from autocomplete, send them so the
		// server can skip its own Nominatim round-trip entirely.
		if (pinnedLat !== null && pinnedLng !== null) {
			fd.append('lat', pinnedLat);
			fd.append('lng', pinnedLng);
		}

		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				showSpinner(false);
				if (!res.success) { showError(res.data || 'An error occurred.'); return; }
				renderResults(res.data);
			})
			.catch(function() {
				showSpinner(false);
				showError('Request failed. Please try again.');
			});
	}

	btnLookup.addEventListener('click', runLookup);

	// ── Results rendering ───────────────────────────────────────────────
	function renderResults(data) {
		currentData = data.counties || [];
		document.getElementById('hmo-maps-total-pop').textContent    = formatNum(data.total_pop);
		document.getElementById('hmo-maps-total-netmig').textContent = formatNetmig(data.total_netmig);
		document.getElementById('hmo-maps-county-count').textContent = data.count.toLocaleString();
		document.getElementById('hmo-maps-summary-meta').textContent =
			data.location + ' · ' + data.radius + '-mile radius';
		summary.style.display = '';
		results.style.display = '';
		renderTable();
	}

	function renderTable() {
		var sorted = currentData.slice().sort(function(a, b) {
			var av = a[sortCol], bv = b[sortCol];
			if (!isNaN(parseFloat(av)) && !isNaN(parseFloat(bv))) {
				return sortDir * (parseFloat(av) - parseFloat(bv));
			}
			return sortDir * String(av).localeCompare(String(bv));
		});
		tbody.innerHTML = sorted.map(function(c) {
			var cls = parseInt(c.netmig_2025) >= 0 ? 'hmo-netmig-pos' : 'hmo-netmig-neg';
			return '<tr>' +
				'<td>' + esc(c.state_abbr) + '</td>' +
				'<td>' + esc(c.county_name) + '</td>' +
				'<td class="hmo-num">' + formatNum(c.pop_2025) + '</td>' +
				'<td class="hmo-num ' + cls + '">' + formatNetmig(c.netmig_2025) + '</td>' +
				'<td class="hmo-num">' + parseFloat(c.distance_miles).toFixed(1) + '</td>' +
				'</tr>';
		}).join('');
	}

	// ── Column sorting ──────────────────────────────────────────────────
	document.getElementById('hmo-maps-table').addEventListener('click', function(e) {
		var th = e.target.closest('th[data-col]');
		if (!th || !currentData.length) return;
		var col = th.getAttribute('data-col');
		sortDir = (sortCol === col) ? sortDir * -1 : (col === 'distance_miles' ? 1 : -1);
		sortCol = col;
		document.querySelectorAll('#hmo-maps-table th.sortable').forEach(function(el) {
			el.querySelector('.sort-icon').innerHTML =
				el === th ? (sortDir === 1 ? '&#8593;' : '&#8595;') : '&#8597;';
		});
		renderTable();
	});

	// ── CSV Export ──────────────────────────────────────────────────────
	btnExport.addEventListener('click', function() {
		if (!currentData.length) return;
		var cols   = ['state_abbr','county_name','pop_2025','netmig_2025','distance_miles'];
		var header = ['State','County','Population','Net Migration','Distance (mi)'];
		var lines  = [header.join(',')].concat(currentData.map(function(c) {
			return cols.map(function(k) {
				var v = c[k];
				if (typeof v === 'string' && (v.includes(',') || v.includes('"'))) {
					v = '"' + v.replace(/"/g, '""') + '"';
				}
				return v;
			}).join(',');
		}));
		var blob = new Blob([lines.join('\n')], { type: 'text/csv' });
		var url  = URL.createObjectURL(blob);
		var a    = document.createElement('a');
		a.href = url; a.download = 'maps-results.csv';
		document.body.appendChild(a); a.click();
		document.body.removeChild(a); URL.revokeObjectURL(url);
	});

	// ── Autocomplete — Nominatim city typeahead ─────────────────────────
	// Each suggestion stores {label, lat, lng} so selecting one pins the
	// coordinates and lets the server skip geocoding entirely.

	var STATE_ABBR = {
		'Alabama':'AL','Alaska':'AK','Arizona':'AZ','Arkansas':'AR',
		'California':'CA','Colorado':'CO','Connecticut':'CT','Delaware':'DE',
		'Florida':'FL','Georgia':'GA','Hawaii':'HI','Idaho':'ID',
		'Illinois':'IL','Indiana':'IN','Iowa':'IA','Kansas':'KS',
		'Kentucky':'KY','Louisiana':'LA','Maine':'ME','Maryland':'MD',
		'Massachusetts':'MA','Michigan':'MI','Minnesota':'MN','Mississippi':'MS',
		'Missouri':'MO','Montana':'MT','Nebraska':'NE','Nevada':'NV',
		'New Hampshire':'NH','New Jersey':'NJ','New Mexico':'NM','New York':'NY',
		'North Carolina':'NC','North Dakota':'ND','Ohio':'OH','Oklahoma':'OK',
		'Oregon':'OR','Pennsylvania':'PA','Rhode Island':'RI','South Carolina':'SC',
		'South Dakota':'SD','Tennessee':'TN','Texas':'TX','Utah':'UT',
		'Vermont':'VT','Virginia':'VA','Washington':'WA','West Virginia':'WV',
		'Wisconsin':'WI','Wyoming':'WY','District of Columbia':'DC'
	};

	var acTimer   = null;
	var acItems   = []; // [{label, lat, lng}, ...]
	var acActive  = -1;

	// Clear pinned coords whenever the user edits the field manually
	inputLoc.addEventListener('input', function() {
		pinnedLat = null;
		pinnedLng = null;

		var q = this.value.trim();
		clearTimeout(acTimer);
		if (q.length < 2) { acHide(); return; }

		acTimer = setTimeout(function() {
			fetch(
				'https://nominatim.openstreetmap.org/search' +
				'?q=' + encodeURIComponent(q) +
				'&format=json&limit=7&countrycodes=us&addressdetails=1',
				{ headers: { 'User-Agent': 'HostlinksMarketingOps/1.0' } }
			)
			.then(function(r) { return r.json(); })
			.then(function(data) {
				var seen  = {};
				var items = [];
				(data || []).forEach(function(item) {
					var addr  = item.address || {};
					var city  = addr.city || addr.town || addr.village ||
								addr.hamlet || addr.county || item.name;
					var state = addr.state || '';
					var abbr  = STATE_ABBR[state] || state;
					if (!city || !abbr) return;
					var label = city + ', ' + abbr;
					if (seen[label]) return;
					seen[label] = true;
					items.push({ label: label, lat: parseFloat(item.lat), lng: parseFloat(item.lon) });
				});
				acShow(items);
			})
			.catch(function() { acHide(); });
		}, 320);
	});

	function acShow(items) {
		acItems  = items;
		acActive = -1;
		acList.innerHTML = '';
		if (!items.length) { acHide(); return; }
		items.forEach(function(item, i) {
			var li = document.createElement('li');
			li.textContent = item.label;
			li.className   = 'hmo-maps-suggestion-item';
			li.setAttribute('role', 'option');
			li.addEventListener('mousedown', function(e) {
				e.preventDefault();
				acCommit(i);
			});
			acList.appendChild(li);
		});
		acList.style.display = '';
	}

	function acHide() {
		acList.style.display = 'none';
		acList.innerHTML = '';
		acItems  = [];
		acActive = -1;
	}

	// Select item i: pin coords, set input label, hide list, fire lookup
	function acCommit(i) {
		var item = acItems[i];
		if (!item) return;
		inputLoc.value = item.label;
		pinnedLat = item.lat;
		pinnedLng = item.lng;
		acHide();
		runLookup(); // immediate — no extra button click needed
	}

	function acHighlight(i) {
		acList.querySelectorAll('.hmo-maps-suggestion-item').forEach(function(el, idx) {
			el.classList.toggle('hmo-maps-suggestion-item--active', idx === i);
		});
	}

	// Keyboard: arrows navigate, Enter commits or falls through to lookup
	inputLoc.addEventListener('keydown', function(e) {
		var len = acItems.length;
		if (e.key === 'Enter') {
			if (acActive >= 0 && len) { e.preventDefault(); acCommit(acActive); }
			else { runLookup(); }
			return;
		}
		if (!len) return;
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			acActive = Math.min(acActive + 1, len - 1);
			acHighlight(acActive);
		} else if (e.key === 'ArrowUp') {
			e.preventDefault();
			acActive = Math.max(acActive - 1, 0);
			acHighlight(acActive);
		} else if (e.key === 'Escape') {
			acHide();
		}
	});

	document.addEventListener('click', function(e) {
		if (e.target !== inputLoc) acHide();
	});

	// ── Helpers ─────────────────────────────────────────────────────────
	function clearResults() {
		summary.style.display = 'none';
		results.style.display = 'none';
		tbody.innerHTML = '';
		currentData = [];
	}
	function showSpinner(v) { spinner.style.display = v ? '' : 'none'; }
	function showError(msg) { errBox.textContent = msg; errBox.style.display = ''; }
	function hideError()    { errBox.style.display = 'none'; }
	function esc(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}
	function formatNum(n)    { return parseInt(n, 10).toLocaleString(); }
	function formatNetmig(n) { var v = parseInt(n,10); return (v>=0?'+':'') + v.toLocaleString(); }
})();
</script>

</div><!-- .hmo-maps-page -->
