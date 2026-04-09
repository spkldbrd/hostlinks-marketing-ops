<?php
/**
 * Front-end view for [display_maps_tool].
 * JavaScript loaded as external enqueued file (assets/js/maps-tool.js).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$_maps_task_url   = HMO_Page_URLs::get_task_editor();
$_maps_report_url = HMO_Page_URLs::get_event_report();
$_maps_is_mgr     = current_user_can( 'manage_options' ) || HMO_Access_Service::current_user_is_marketing_admin();
?>

<div class="hostlinks-page hmo-frontend hmo-maps-page">

<!-- Blue header bar -->
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

	<!-- ── Two-column: Controls (left) + Stat cards (right) ────────── -->
	<div class="hmo-maps-main">

		<!-- Left: always visible -->
		<div class="hmo-maps-left">
			<p class="hmo-maps-intro">
				Begin typing your city and choose a location from the suggestions,<br>
				then set a radius and click Lookup.<br>
				<small>Counties are included if their population center falls within the selected radius.</small>
			</p>

			<div class="hmo-maps-location-wrap">
				<label for="hmo-maps-location" class="hmo-maps-field-label">
					Select center location (enter city or county name):
				</label>
				<input type="text" id="hmo-maps-location" class="hmo-maps-input"
					placeholder="e.g. Denver, CO" autocomplete="off" aria-autocomplete="list"
					aria-haspopup="listbox" aria-controls="hmo-maps-suggestions">
				<ul id="hmo-maps-suggestions" class="hmo-maps-suggestions" role="listbox"
					style="display:none;" aria-label="City suggestions"></ul>
			</div>

			<div class="hmo-maps-radius-wrap">
				<div class="hmo-maps-radius-header">
					<label for="hmo-maps-radius" class="hmo-maps-field-label">Set radius size in miles:</label>
					<strong id="hmo-maps-radius-val" class="hmo-maps-radius-num">100</strong>
				</div>
				<input type="range" id="hmo-maps-radius" class="hmo-maps-slider"
					min="25" max="500" step="25" value="100">
			</div>

			<div>
				<button type="button" id="hmo-maps-lookup-btn" class="hmo-maps-btn hmo-maps-btn--inline">Lookup</button>
			</div>
		</div>

		<!-- Right: always visible; --pending state until first lookup -->
		<div class="hmo-maps-right hmo-maps-right--pending" id="hmo-maps-right">
			<div class="hmo-maps-stat-grid">
				<div class="hmo-maps-stat-card">
					<div class="hmo-maps-stat-label">Counties</div>
					<div class="hmo-maps-stat-value" id="hmo-maps-county-count">—</div>
					<div class="hmo-maps-stat-sub">within radius</div>
				</div>
				<div class="hmo-maps-stat-card hmo-maps-stat-card--soon">
					<span>coming soon</span>
				</div>
			</div>

			<!-- Action bar lives in the right column, below the cards -->
			<div id="hmo-maps-action-bar" class="hmo-maps-action-bar">
				<div class="hmo-maps-action-row">
					<span class="hmo-maps-action-icon" aria-hidden="true">&#9993;</span>
					<span class="hmo-maps-action-desc">use this button to copy/paste the county list into your send request.</span>
					<button type="button" id="hmo-maps-copy-btn" class="hmo-maps-copy-btn">
						&#9112; Copy List
					</button>
				</div>
				<div class="hmo-maps-action-row">
					<span class="hmo-maps-action-icon" aria-hidden="true">&#128202;</span>
					<span class="hmo-maps-action-desc">use this button to download a CSV file to work in sheets/excel.</span>
					<button type="button" id="hmo-maps-export-btn" class="hmo-maps-export-btn">
						&#8659; Download CSV
					</button>
				</div>
			</div>
		</div>

	</div><!-- .hmo-maps-main -->

	<!-- ── Feedback ─────────────────────────────────────────────────── -->
	<div id="hmo-maps-error"   class="hmo-maps-error"   style="display:none;"></div>
	<div id="hmo-maps-spinner" class="hmo-maps-spinner" style="display:none;" aria-label="Loading">
		<span class="hmo-maps-spinner-dot"></span>
		<span class="hmo-maps-spinner-dot"></span>
		<span class="hmo-maps-spinner-dot"></span>
	</div>

	<!-- ── Results table ────────────────────────────────────────────── -->
	<div id="hmo-maps-results" class="hmo-maps-results" style="display:none;">
		<div class="hmo-maps-summary-meta" id="hmo-maps-summary-meta"></div>
		<h3 class="hmo-maps-results-title">County Detail</h3>
		<div class="hmo-maps-table-wrap">
			<table class="hmo-maps-table" id="hmo-maps-table">
				<thead>
					<tr>
						<th data-col="state_abbr"     class="sortable">State <span class="sort-icon">&#8597;</span></th>
						<th data-col="county_name"    class="sortable">County <span class="sort-icon">&#8597;</span></th>
						<th data-col="pop_2025"       class="sortable">Population <span class="sort-icon">&#8597;</span></th>
						<th data-col="netmig_2025"    class="sortable">Net Migration <span class="sort-icon">&#8597;</span></th>
						<th data-col="distance_miles" class="sortable">Distance (mi) <span class="sort-icon">&#8597;</span></th>
					</tr>
				</thead>
				<tbody id="hmo-maps-tbody"></tbody>
			</table>
		</div>
	</div>

</div><!-- .hmo-maps-wrap -->

</div><!-- .hmo-maps-page -->
