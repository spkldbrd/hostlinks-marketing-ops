<?php
/**
 * Front-end view for [display_maps_tool].
 *
 * Variables available in scope (passed from HMO_Shortcodes::render_maps_tool):
 *   $access  — HMO_Access_Service instance
 *   $nonce   — wp_create_nonce('hmo_maps_lookup')
 *   $ajax_url — admin_url('admin-ajax.php')
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! HMO_Access_Service::can_view_shortcode( 'display_maps_tool' ) ) {
	echo '<p class="hmo-access-denied">Access denied. Please log in with a Hostlinks account to use this tool.</p>';
	return;
}
?>

<div class="hmo-maps-wrap" id="hmo-maps-wrap">

	<!-- ── Controls ────────────────────────────────────────────────────── -->
	<div class="hmo-maps-controls">
		<div class="hmo-maps-control-group">
			<label for="hmo-maps-location" class="hmo-maps-label">City, State</label>
			<input type="text" id="hmo-maps-location" class="hmo-maps-input"
				placeholder="e.g. Denver, CO" autocomplete="off">
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
	var ajaxUrl  = <?php echo wp_json_encode( $ajax_url ); ?>;
	var nonce    = <?php echo wp_json_encode( $nonce ); ?>;

	var btnLookup  = document.getElementById('hmo-maps-lookup-btn');
	var btnExport  = document.getElementById('hmo-maps-export-btn');
	var inputLoc   = document.getElementById('hmo-maps-location');
	var sliderRad  = document.getElementById('hmo-maps-radius');
	var radVal     = document.getElementById('hmo-maps-radius-val');
	var errBox     = document.getElementById('hmo-maps-error');
	var spinner    = document.getElementById('hmo-maps-spinner');
	var summary    = document.getElementById('hmo-maps-summary');
	var results    = document.getElementById('hmo-maps-results');
	var tbody      = document.getElementById('hmo-maps-tbody');

	var currentData = [];
	var sortCol     = 'distance_miles';
	var sortDir     = 1; // 1 = asc, -1 = desc

	// Radius slider display
	sliderRad.addEventListener('input', function() {
		radVal.textContent = this.value;
	});

	// Enter key in location field triggers lookup
	inputLoc.addEventListener('keydown', function(e) {
		if (e.key === 'Enter') btnLookup.click();
	});

	// Lookup
	btnLookup.addEventListener('click', function() {
		var location = inputLoc.value.trim();
		if (!location) {
			showError('Please enter a city and state.');
			return;
		}
		clearResults();
		showSpinner(true);
		hideError();

		var fd = new FormData();
		fd.append('action',   'hmo_maps_lookup');
		fd.append('nonce',    nonce);
		fd.append('location', location);
		fd.append('radius',   sliderRad.value);

		fetch(ajaxUrl, { method: 'POST', body: fd })
			.then(function(r) { return r.json(); })
			.then(function(res) {
				showSpinner(false);
				if (!res.success) {
					showError(res.data || 'An error occurred.');
					return;
				}
				renderResults(res.data);
			})
			.catch(function() {
				showSpinner(false);
				showError('Request failed. Please try again.');
			});
	});

	function renderResults(data) {
		currentData = data.counties || [];

		// Summary cards
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
			var av = a[sortCol];
			var bv = b[sortCol];
			if (!isNaN(parseFloat(av)) && !isNaN(parseFloat(bv))) {
				return sortDir * (parseFloat(av) - parseFloat(bv));
			}
			return sortDir * String(av).localeCompare(String(bv));
		});

		var rows = sorted.map(function(c) {
			var netmigClass = c.netmig_2025 >= 0 ? 'hmo-netmig-pos' : 'hmo-netmig-neg';
			return '<tr>' +
				'<td>' + esc(c.state_abbr) + '</td>' +
				'<td>' + esc(c.county_name) + '</td>' +
				'<td class="hmo-num">' + formatNum(c.pop_2025) + '</td>' +
				'<td class="hmo-num ' + netmigClass + '">' + formatNetmig(c.netmig_2025) + '</td>' +
				'<td class="hmo-num">' + parseFloat(c.distance_miles).toFixed(1) + '</td>' +
				'</tr>';
		});
		tbody.innerHTML = rows.join('');
	}

	// Column sorting
	document.getElementById('hmo-maps-table').addEventListener('click', function(e) {
		var th = e.target.closest('th[data-col]');
		if (!th || !currentData.length) return;
		var col = th.getAttribute('data-col');
		if (sortCol === col) {
			sortDir *= -1;
		} else {
			sortCol = col;
			sortDir = (col === 'distance_miles') ? 1 : -1;
		}
		// Update icons
		document.querySelectorAll('#hmo-maps-table th.sortable').forEach(function(el) {
			var icon = el.querySelector('.sort-icon');
			if (el === th) {
				icon.innerHTML = sortDir === 1 ? '&#8593;' : '&#8595;';
			} else {
				icon.innerHTML = '&#8597;';
			}
		});
		renderTable();
	});

	// CSV Export
	btnExport.addEventListener('click', function() {
		if (!currentData.length) return;
		var cols = ['state_abbr','county_name','pop_2025','netmig_2025','distance_miles'];
		var header = ['State','County','Population','Net Migration','Distance (mi)'];
		var lines = [header.join(',')];
		currentData.forEach(function(c) {
			lines.push(cols.map(function(k) {
				var v = c[k];
				if (typeof v === 'string' && (v.includes(',') || v.includes('"'))) {
					v = '"' + v.replace(/"/g, '""') + '"';
				}
				return v;
			}).join(','));
		});
		var blob = new Blob([lines.join('\n')], { type: 'text/csv' });
		var url  = URL.createObjectURL(blob);
		var a    = document.createElement('a');
		a.href     = url;
		a.download = 'maps-results.csv';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	});

	// Helpers
	function clearResults() {
		summary.style.display = 'none';
		results.style.display = 'none';
		tbody.innerHTML = '';
		currentData = [];
	}
	function showSpinner(show) {
		spinner.style.display = show ? '' : 'none';
	}
	function showError(msg) {
		errBox.textContent = msg;
		errBox.style.display = '';
	}
	function hideError() {
		errBox.style.display = 'none';
	}
	function esc(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}
	function formatNum(n) {
		return parseInt(n, 10).toLocaleString();
	}
	function formatNetmig(n) {
		var v = parseInt(n, 10);
		return (v >= 0 ? '+' : '') + v.toLocaleString();
	}
})();
</script>
