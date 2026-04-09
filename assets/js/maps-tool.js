/**
 * Marketing Maps tool — front-end logic.
 * Injected data: hmoMapsConfig  (ajaxUrl, nonce, hasGoogleKey)
 * Dependencies:  D3 v7, topojson-client (enqueued by render_maps_tool)
 */
(function() {
	var ajaxUrl   = hmoMapsConfig.ajaxUrl;
	var nonce     = hmoMapsConfig.nonce;
	var useGoogle = hmoMapsConfig.hasGoogleKey;

	// ── DOM refs ─────────────────────────────────────────────────────────
	var btnLookup  = document.getElementById('hmo-maps-lookup-btn');
	var btnExport  = document.getElementById('hmo-maps-export-btn');
	var btnCopy    = document.getElementById('hmo-maps-copy-btn');
	var inputLoc   = document.getElementById('hmo-maps-location');
	var sliderRad  = document.getElementById('hmo-maps-radius');
	var radVal     = document.getElementById('hmo-maps-radius-val');
	var errBox     = document.getElementById('hmo-maps-error');
	var spinner    = document.getElementById('hmo-maps-spinner');
	var rightPanel = document.getElementById('hmo-maps-right');
	var results    = document.getElementById('hmo-maps-results');
	var tbody      = document.getElementById('hmo-maps-tbody');
	var acList     = document.getElementById('hmo-maps-suggestions');

	if (!btnLookup || !inputLoc) { return; }

	// ── State ─────────────────────────────────────────────────────────────
	var currentData = [];
	var sortCol     = 'distance_miles';
	var sortDir     = 1;
	var pinnedLat   = null;
	var pinnedLng   = null;

	// ── County name formatter ──────────────────────────────────────────────
	// Strips trailing geographic suffixes so "Denver County" → "Denver".
	var _suffixes = [
		' City and Borough', ' Census Area', ' Municipality',
		' Municipio', ' Borough', ' Parish', ' County'
	];
	function countyLabel(name) {
		for (var i = 0; i < _suffixes.length; i++) {
			if (name.slice(-_suffixes[i].length) === _suffixes[i]) {
				return name.slice(0, -_suffixes[i].length);
			}
		}
		return name;
	}

	// ── Radius slider ─────────────────────────────────────────────────────
	// Update only the number span — no surrounding DOM changes → no flicker.
	sliderRad.addEventListener('input', function() {
		radVal.textContent = this.value;
	});

	// ── Lookup ────────────────────────────────────────────────────────────
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

	// ── Results rendering ─────────────────────────────────────────────────
	function renderResults(data) {
		currentData = data.counties || [];

		document.getElementById('hmo-maps-county-count').textContent = data.count.toLocaleString();
		document.getElementById('hmo-maps-summary-meta').textContent =
			data.location + ' \u00b7 ' + data.radius + '-mile radius';

		rightPanel.classList.remove('hmo-maps-right--pending');
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
				'<td>' + esc(countyLabel(c.county_name)) + '</td>' +
				'<td class="hmo-num">' + formatNum(c.pop_2025) + '</td>' +
				'<td class="hmo-num ' + cls + '">' + formatNetmig(c.netmig_2025) + '</td>' +
				'<td class="hmo-num">' + parseFloat(c.distance_miles).toFixed(1) + '</td>' +
				'</tr>';
		}).join('');
	}

	// ── Column sorting ────────────────────────────────────────────────────
	document.getElementById('hmo-maps-table').addEventListener('click', function(e) {
		var th = e.target.closest('th[data-col]');
		if (!th || !currentData.length) return;
		var col = th.getAttribute('data-col');
		sortDir = (sortCol === col) ? sortDir * -1 : (col === 'distance_miles' ? 1 : -1);
		sortCol = col;
		document.querySelectorAll('#hmo-maps-table th.sortable').forEach(function(el) {
			el.querySelector('.sort-icon').innerHTML =
				el === th ? (sortDir === 1 ? '\u2191' : '\u2193') : '\u2195';
		});
		renderTable();
	});

	// ── Copy List ─────────────────────────────────────────────────────────
	// Copies results as plain text: "Denver, CO" (one per line, suffix stripped)
	btnCopy.addEventListener('click', function() {
		if (!currentData.length) return;

		var text = currentData.map(function(c) {
			return countyLabel(c.county_name) + ', ' + c.state_abbr;
		}).join('\n');

		var btn = this;

		function markCopied() {
			btn.textContent = '\u2713 Copied!';
			btn.classList.add('hmo-maps-copy-btn--done');
			setTimeout(function() {
				btn.innerHTML = '&#9112; Copy List';
				btn.classList.remove('hmo-maps-copy-btn--done');
			}, 2000);
		}

		function execFallback() {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0;pointer-events:none;';
			document.body.appendChild(ta);
			ta.focus(); ta.select();
			try { document.execCommand('copy'); } catch(e) {}
			document.body.removeChild(ta);
			markCopied();
		}

		// navigator.clipboard only exists in secure contexts (HTTPS).
		// Fall back to execCommand for HTTP sites.
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(markCopied).catch(execFallback);
		} else {
			execFallback();
		}
	});

	// ── CSV Export ────────────────────────────────────────────────────────
	btnExport.addEventListener('click', function() {
		if (!currentData.length) return;
		var header = ['State','County','Population','Net Migration','Distance (mi)'];
		var lines  = [header.join(',')].concat(currentData.map(function(c) {
			var row = [
				c.state_abbr,
				countyLabel(c.county_name),
				c.pop_2025,
				c.netmig_2025,
				parseFloat(c.distance_miles).toFixed(1)
			];
			return row.map(function(v) {
				v = String(v);
				if (v.includes(',') || v.includes('"')) {
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

	// ════════════════════════════════════════════════════════════════════
	// Autocomplete — Google Places (preferred) or Nominatim (fallback)
	// ════════════════════════════════════════════════════════════════════

	function initGoogleAutocomplete() {
		var ac = new google.maps.places.Autocomplete(inputLoc, {
			types:                 ['(cities)'],
			componentRestrictions: { country: 'us' },
			fields:                ['geometry', 'name', 'address_components']
		});
		if (acList) acList.style.display = 'none';

		ac.addListener('place_changed', function() {
			var place = ac.getPlace();
			if (!place.geometry || !place.geometry.location) { return; }
			pinnedLat = place.geometry.location.lat();
			pinnedLng = place.geometry.location.lng();
			runLookup();
		});

		inputLoc.addEventListener('input', function() {
			pinnedLat = null;
			pinnedLng = null;
		});
	}

	function initNominatimAutocomplete() {
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

		var acTimer  = null;
		var acItems  = [];
		var acActive = -1;

		inputLoc.addEventListener('input', function() {
			pinnedLat = null; pinnedLng = null;
			var q = this.value.trim();
			clearTimeout(acTimer);
			if (q.length < 2) { acHide(); return; }
			acTimer = setTimeout(function() {
				var nomUrl = 'https://nominatim.openstreetmap.org/search' +
					'?q=' + encodeURIComponent(q) +
					'&format=json&limit=7&countrycodes=us&addressdetails=1';
				fetch(nomUrl)
				.then(function(r) { return r.json(); })
				.then(function(data) {
					var seen = {}, items = [];
					(data || []).forEach(function(item) {
						var addr  = item.address || {};
						var city  = addr.city || addr.town || addr.village || addr.hamlet ||
						            addr.suburb || addr.neighbourhood || addr.borough;
						var state = addr.state || '';
						var abbr  = STATE_ABBR[state] || state;
						if (!city || !abbr) return;
						var label = city + ', ' + abbr;
						if (seen[label]) return;
						seen[label] = true;
						items.push({ label: label, lat: parseFloat(item.lat), lng: parseFloat(item.lon) });
					});
					acShow(items.slice(0, 5));
				})
				.catch(function() { acHide(); });
			}, 320);
		});

		function acShow(items) {
			acItems = items; acActive = -1;
			acList.innerHTML = '';
			if (!items.length) { acHide(); return; }
			items.forEach(function(item, i) {
				var li = document.createElement('li');
				li.textContent = item.label;
				li.className   = 'hmo-maps-suggestion-item';
				li.setAttribute('role', 'option');
				li.addEventListener('mousedown', function(e) { e.preventDefault(); acCommit(i); });
				acList.appendChild(li);
			});
			acList.style.display = '';
		}

		function acHide() {
			acList.style.display = 'none';
			acList.innerHTML = '';
			acItems = []; acActive = -1;
		}

		function acCommit(i) {
			var item = acItems[i]; if (!item) return;
			inputLoc.value = item.label;
			pinnedLat = item.lat; pinnedLng = item.lng;
			acHide(); runLookup();
		}

		function acHighlight(i) {
			acList.querySelectorAll('.hmo-maps-suggestion-item').forEach(function(el, idx) {
				el.classList.toggle('hmo-maps-suggestion-item--active', idx === i);
			});
		}

		inputLoc.addEventListener('keydown', function(e) {
			var len = acItems.length;
			if (e.key === 'Enter') {
				if (acActive >= 0 && len) { e.preventDefault(); acCommit(acActive); } else { runLookup(); }
				return;
			}
			if (!len) return;
			if (e.key === 'ArrowDown')  { e.preventDefault(); acActive = Math.min(acActive + 1, len - 1); acHighlight(acActive); }
			else if (e.key === 'ArrowUp')   { e.preventDefault(); acActive = Math.max(acActive - 1, 0); acHighlight(acActive); }
			else if (e.key === 'Escape')    { acHide(); }
		});

		document.addEventListener('click', function(e) { if (e.target !== inputLoc) acHide(); });
	}

	// Boot autocomplete.
	if (useGoogle && typeof google !== 'undefined' && google.maps && google.maps.places) {
		initGoogleAutocomplete();
	} else if (useGoogle) {
		var _poll = setInterval(function() {
			if (typeof google !== 'undefined' && google.maps && google.maps.places) {
				clearInterval(_poll); initGoogleAutocomplete();
			}
		}, 100);
	} else {
		initNominatimAutocomplete();
	}

	// ── Helpers ───────────────────────────────────────────────────────────
	function clearResults() {
		rightPanel.classList.add('hmo-maps-right--pending');
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
