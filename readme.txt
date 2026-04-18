=== Hostlinks Marketing Ops ===
Contributors: digitalsolution
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
License: GPLv2

A companion plugin for Hostlinks that gives the marketing team a full workflow
management system: dashboards, per-event checklists, marketer bucket access,
task template editing, a journey report, a radius-based Maps reach tool, and more.

Requires the Hostlinks plugin to be installed and active.

== Description ==

Hostlinks Marketing Ops sits alongside the core Hostlinks plugin and provides
everything the marketing operations team needs to track and manage events from
assignment through completion. It also includes standalone marketing tools
(Maps radius lookup) accessible from the event detail page.

Each event moves through six configurable workflow stages. Tasks are provisioned
automatically from a master template when an event is created in Hostlinks and
tracked per-event. Marketers see only the events assigned to their bucket.
Admins and Marketing Admins get full visibility, reporting, and configuration tools.

Any user who can access an event in Marketing Ops may complete or reopen checklist
tasks on that event. WordPress Administrators and Marketing Admins retain broader
configuration powers and visibility across events.

== Shortcodes ==

[hmo_dashboard_selector]
  Landing-page selector; routes users to the full dashboard or their My Classes
  view depending on their role.

[hmo_dashboard]
  Full Marketing Ops dashboard — all events, filterable by stage, risk, date
  window, and marketer bucket.

[hmo_my_classes]
  Marketer-facing view; shows only events assigned to the logged-in user's
  buckets.

[hmo_event_detail]
  Per-event detail page: checklist with stage progress, task completion,
  per-task notes, event stats in the header, and an Insights panel (venue,
  links, contacts, hotels). Right column holds a Call List card, a Tools card
  (global links from settings), and a free-form Notes card.

[hmo_task_editor]
  Master task template editor. Add, rename, reorder, and delete stages and
  tasks (including subtasks). Changes automatically sync to all future events
  (adds new tasks, renames labels, removes deleted pending tasks while preserving
  completed ones).

[hmo_event_report]
  Event Journey Report for Marketing Admins and Report Viewers. Filterable by
  year, month, and marketer bucket. Shows registration count, days remaining,
  stage progress, and a full task breakdown for the selected event.

[display_maps_tool]
  Radius-based US population lookup tool. Enter a city, select a radius (25–500 mi),
  and get a county-level list with population and net migration data. Includes
  Copy List and Download CSV actions. Accessible to Hostlinks users only.
  Opens in a new tab from the event detail Tools card with automatic event context.

== User Roles & Access ==

WordPress Administrator
  Full access to all shortcodes, admin settings, task editor, reports, bulk
  tools, Maps tool, and list management. Always able to edit registration goals.

Marketing Admin
  Access to the task editor, event journey report, and all header navigation
  links. Can edit registration goals if enabled in Settings → General.
  Assigned via HMO Settings → Marketing Admins list.

Task Editor
  Can add/edit/delete tasks and stages in the master template. Assigned via
  HMO Settings → Task Editors.

Report Viewer
  Can access the Event Journey Report. Assigned via HMO Settings → Report
  Viewers.

Marketer / Hostlinks User (Bucket User)
  Sees only events assigned to their bucket(s) via the My Classes view.
  Can access the Maps tool. Can edit registration goals if enabled in Settings → General.
  Assigned via HMO Settings → Bucket Access (many-to-many: one user can hold
  multiple buckets; one bucket can be shared by multiple users).

== Workflow Stages ==

Stages are fully configurable in the Task Template Editor. The default set is:

  1. Event Setup
  2. Data Send Prep
  3. 60-Day Marketing
  4. 30-Day Marketing
  5. Final Prep
  6. Completion

== Task Auto-Provisioning ==

When a new event is created in Hostlinks — either via "Add New Event" or the
"New CVENT Events" importer — the plugin fires the custom action
`hostlinks_event_created` which triggers HMO to immediately provision the full
task checklist from the master template and set the open-task count. No manual
steps required.

Template changes sync automatically:
  - New top-level tasks are added to all future (non-past) events.
  - Renamed task labels update on all events.
  - Deleted tasks are removed only if still pending (completed tasks are preserved).
  - A "Recount All" button in Settings recalculates open-task counts across all
    provisioned future events without touching task statuses.

== Tools Card (Event Detail) ==

The Tools card on the event detail right column displays a configurable 2×2 grid
of global links set in HMO Settings → Tools Links. Each link can have:
  - A name
  - A URL
  - An optional icon image (selected from the WordPress Media Library)

When a Tools link points to the Maps tool page, the event ID and name are
automatically appended as URL parameters so the Maps tool knows its context.

== Maps Tool ([display_maps_tool]) ==

A local-first radius lookup tool. All data is cached in MySQL — no live API calls
at query time.

Data sources (bundled files in assets/data/):
  - CenPop2020_Mean_CO.txt — 2020 Census Centers of Population (population-weighted
    county centroids; updated every 10 years with each decennial Census).
  - 2024_Gaz_counties_national.txt — 2024 Census Gazetteer (geographic county
    centroids; alternative source).
  - co-est2025-alldata.csv — Census PEP Vintage 2025 population and net migration
    estimates (updated annually each spring).

Centroid source is selectable in Settings → Maps → Centroid Source. Population-
weighted centroids (recommended) place county centers where people actually live,
producing marketing-relevant radius counts that closely match reference tools like
Stats America Big Radius.

Geocoding:
  - Primary: Google Places API autocomplete (requires API key in settings).
  - Fallback: Nominatim (OpenStreetMap) server-side geocoding.
  - When autocomplete is used, the resolved lat/lng is sent directly with the
    AJAX request — no server-side geocoder round-trip needed.

Maps page navigation: the page opens in a new browser tab from the Tools card.
The header shows a "× Close Tab" button (calls window.close()) when opened as a
fresh tab, or "← Return to Hostlinks" when navigated to directly.

Future features flagged in Settings (not yet implemented):
  - Census API Key: for automatic annual data pulls from the Census Bureau API.
  - Sync Frequency: to schedule WP Cron auto-refresh when Census API is wired up.

== Database Tables ==

Workflow tables:
  wp_hmo_event_ops          — one row per event; stage, goal, task count, call list URL,
                              event note, and other workflow metadata.
  wp_hmo_event_tasks        — one row per task per event; status, completion tracking,
                              and per-task notes.
  wp_hmo_event_task_items   — sub-checklist items (reserved for future use).
  wp_hmo_checklist_templates — master stage and task definitions including subtasks.
  wp_hmo_bucket_access      — many-to-many: marketer bucket ↔ WordPress user.
  wp_hmo_event_activity     — activity log: task completions, stage changes, goal updates.

Maps tables:
  wp_hmo_maps_county_centroids — FIPS, state_abbr, county_name, lat, lng
                                 (~3,221 rows: 3,143 US counties + 78 PR municipios).
  wp_hmo_maps_county_stats     — FIPS, state_name, county_name, pop_2025, netmig_2025,
                                 synced_at.

DB schema version is managed via HMO_DB_VERSION constant and `hmo_db_version` in
wp_options. Migrations run automatically when the version constant advances.

== REST API ==

All checklist interactions use the WordPress REST API under the `hmo/v1` namespace.
Maps tool interactions use wp-admin/admin-ajax.php AJAX actions.

REST endpoints:
  GET  /hmo/v1/dashboard                  Dashboard row data
  GET  /hmo/v1/events/{id}/checklist      Event checklist
  POST /hmo/v1/events/{id}/stage          Update workflow stage
  POST /hmo/v1/events/{id}/lists          Save call list URL
  POST /hmo/v1/events/{id}/goal           Update registration goal (role-gated)
  POST /hmo/v1/events/{id}/event-note     Save event-level note
  POST /hmo/v1/tasks/{id}/complete        Mark task complete (with optional note)
  POST /hmo/v1/tasks/{id}/incomplete      Revert task to pending
  POST /hmo/v1/tasks/{id}/note            Save per-task completion note

AJAX actions (Maps tool):
  wp_ajax_hmo_maps_init_centroids         Parse centroid file and upsert to DB (admin)
  wp_ajax_hmo_maps_sync_stats             Parse PEP CSV and upsert to DB (admin)
  wp_ajax_hmo_maps_lookup                 Geocode + Haversine radius query (logged-in users)
  wp_ajax_nopriv_hmo_maps_lookup          Same, for non-logged-in (access-gated inside handler)

== Installation ==

1. Ensure the Hostlinks plugin is installed and active.
2. Upload this plugin folder to /wp-content/plugins/.
3. Activate via the WordPress Plugins screen.
4. Tables are created and checklist templates are seeded automatically.
5. Visit Marketing Ops → Settings to assign user roles and configure pages.
6. For the Maps tool: visit Settings → Maps, choose Centroid Source, click
   "Initialize Centroids" then "Sync Stats Now". Add your Google Maps API key
   for autocomplete. Set the Maps tool page URL in Settings → Page Links.

== Auto-Updates ==

The plugin checks for updates via GitHub Releases (repository: spkldbrd/hostlinks-marketing-ops).
The WordPress admin will show an update notice when a new release is available.
A push to main triggers a GitHub Actions workflow that builds and publishes the zip.

== Changelog ==

= 1.11.14 =
* Stability: public REST endpoints (public-events, past-events) use a short-lived
  response cache and a light per-IP rate limit to reduce load from anonymous clients.
* Maintenance: complete uninstall cleanup for all HMO tables, options, page-template
  keys, and hmo_* transients; settings POST handling moved to HMO_Settings_Form_Handler;
  Settings screen split into tab partials under admin/views/settings/.
* Shortcodes: single registry from HMO_Shortcodes::register() drives access labels,
  asset detection, and shortcode registration together.
* Maps and checklist: hardened centroid validation, maps lookup when a Google key is
  set, safer list-metadata updates when both call_list_url and call_list_urls are
  posted; shared bulk-complete SQL for REST and AJAX.
* Releases: Requires PHP 8.0 in plugin metadata and updater payload; documented
  Windows zip pitfalls; CI rejects zip entries containing backslashes.

= 1.11.5 =
* Maps tool: added population-weighted centroid support (2020 Census Centers of
  Population file CenPop2020_Mean_CO.txt). New "Centroid Source" dropdown in
  Settings → Maps lets you choose Geographic (2024 Gazetteer) or Population-
  Weighted (2020 Census). Initialize Centroids now passes the selected source to
  the importer. Population-weighted centroids place county centers where people
  actually live, producing county counts that closely match Stats America Big Radius.
* Maps tool: Data Status table now shows which centroid source is currently loaded.
* Maps tool: fixed UTF-8 BOM stripping from CenPop file header row.
* Settings: Census API Key and Sync Frequency fields marked as "Future Feature"
  with explanatory descriptions for planned automatic annual Census data updates.

= 1.11.4 =
* Settings: added "Allow Goal Editing" checkboxes to General tab — independently
  grant Marketing Admins and/or Hostlinks Users the ability to edit the registration
  goal on event pages. Input field and Save button are hidden for unchecked roles;
  the goal count remains visible. WP Administrators can always edit.

= 1.11.3 =
* Maps tool: left and right cards now match height — align-items changed to stretch and left panel set to flex: 1.

= 1.11.2 =
* Maps tool: event context awareness — when Maps is opened from an event's Tools card, the event name and ID are passed as URL params, displayed as a pill badge in the Maps header bar, and made available in hmoMapsConfig.eventId / eventName for future features.

= 1.11.1 =
* Maps tool: replaced header nav bar with isolated close-tab UX — shows "× Close Tab" (calls window.close()) when opened as a new tab, falls back to "← Return to Hostlinks" when navigated to directly. Removes Task Management and Reports links from Maps header to keep the tool focused and users anchored to their event.

= 1.11.0 =
* Maps tool: full layout redesign — two-column layout (controls left, stats/actions right), right column always visible with greyed pending state until first lookup, stat cards (Counties + coming soon placeholder), Copy List and Download CSV action rows moved into right column below cards, Lookup button smaller and left-aligned, radius slider at 70% width, action rows right-aligned with icon-text-button order, Copy List button fixed width during text swap.

= 1.10.8 =
* Maps tool: reverted PlaceAutocompleteElement attempt — it is a web component that cannot wrap an existing input, so it silently broke autocomplete. Back to legacy Autocomplete class which remains fully functional.

= 1.10.7 =
* Maps tool: county name suffix (County, Parish, Borough, etc.) is now stripped in the results table, CSV export, and Copy List — all three show "Denver, CO" format consistently.

= 1.10.6 =
* Maps tool: fixed Copy List button on HTTP sites — navigator.clipboard is only available on HTTPS; now falls back to execCommand copy before attempting the Clipboard API.
* Maps tool: updated Google Places autocomplete to use PlaceAutocompleteElement (new API) with legacy Autocomplete as fallback, eliminating the deprecation console warning.

= 1.10.5 =
* Maps tool: added "Copy List" button that copies all result counties to clipboard as plain text in "City, ST" format (one per line, county suffixes stripped).

= 1.10.4 =
* Maps tool: added display_maps_tool to SHORTCODES registry so frontend.css (blue header bar and all HMO styles) is properly enqueued on the Maps page.

= 1.10.3 =
* Maps tool: removed county map (deferred for future release).
* Maps tool: fixed slider flicker — radius value is now a fixed-width flex sibling of the label, not inline text, so digit count changes (75→100) never cause a layout reflow.

= 1.10.2 =
* Maps tool: redesigned layout to vertical stack (Big Radius style) — location input full width, radius slider below labeled value.
* Maps tool: fixed slider flicker caused by layout reflow when value width changes; radius number now uses tabular-nums with fixed min-width.
* Maps tool: added interactive US county map (D3 v7 + us-atlas TopoJSON) — counties within the selected radius highlight red after each lookup.

= 1.10.1 =
* Maps tool: fixed inline JavaScript syntax error caused by WordPress the_content filter entity-encoding && operators — moved all JS to external enqueued file (assets/js/maps-tool.js).
* Maps tool: switched city autocomplete from Nominatim to Google Places API for fast, accurate partial-text matching; Google Maps API key field added to Maps settings tab.
* Maps tool: Nominatim autocomplete retained as automatic fallback when no Google key is configured.
* Cleaned up all debug instrumentation and temporary debug files from v1.10.0 investigation.

= 1.10.0 =
* New Maps tool ([display_maps_tool]): radius-based US population and net migration lookup using local MySQL cache.
* Admin settings tab for Maps: Initialize Centroids, Sync Stats Now, Census API key, sync frequency.
* Blue header bar with nav links on Maps tool page.
* Maps tool listed in Page Links settings.

= 1.6.7 =
* Refactored event notes to store in hmo_event_ops.event_note column (proper relational storage).
* upsert_event_ops() now returns bool so callers can detect DB write failures.
* save_event_note REST handler returns HTTP 500 on failure instead of always returning 200.

= 1.6.6 =
* Interim fix: stored event notes in wp_options per-event to unblock users while
  root cause was investigated. Superseded by 1.6.7.

= 1.6.5 =
* Fixed right-column layout bug: Insights panel was appearing in the left column.
  Root cause: the 2-column grid had multiple direct children, causing a 2x2 auto-flow.
  Fix: wrapped Call List, Notes, and Insights in a single flex-column container.

= 1.6.4 =
* Replaced the full-width List Links panel (2 lists + dropdowns + raw URL fields)
  with a compact Call List card in the event detail right column.
* Card shows "Not Set" or "Set" status with a View arrow button (opens Google Sheet).
* Click Update to reveal an inline URL input; Save/Cancel controls.
* Call list URL is stored per-event (was already per-event in hmo_event_ops).
* Fixed Notes panel using wrong event ID field ($event->id → $event->eve_id).

= 1.6.3 =
* Insights panel space efficiency: Event Links, Event Contacts, and Recommended
  Hotels now render in a 2-column grid side by side.
* Contact and hotel cards wrap to additional rows for 3rd/4th entries.

= 1.6.2 =
* Fixed subtasks added via the Task Template Editor not appearing on event checklists.
* Root cause: template subtasks (hmo_checklist_templates with parent_id > 0) were
  never fetched for the event detail view.
* Added get_subtasks_by_task_keys() — single JOIN query to load all subtasks for a
  set of parent task_keys.
* Subtasks are attached to each event task and rendered as a nested bullet list.

= 1.6.1 =
* Added per-event Notes card to the event detail right column (above Insights).
* Notes are tied to the event and visible only on the event detail page.

= 1.6.0 =
* Event Journey Report: added Bucket filter dropdown (defaults to All Buckets).
* Bucket filter narrows the event selector by assigned marketer.

= 1.5.9 =
* Event Journey Report: added Year and Month filter dropdowns.
* Filters default to the current month and immediately update the event selector.

= 1.5.8 =
* Event Journey Report now renders full-width (removed max-width: 960px constraint).

= 1.5.7 =
* Performance: eliminated N+1 query storm on dashboard/My Classes page load.
  Root cause: get_dashboard_rows() called get_event() in a loop for each event,
  causing ~1,476 redundant SELECT queries for 738 events on a cache miss.
  Fix: registration count read directly from event object properties; days-left
  calculated from eve_start date string without an additional DB call.
* Performance: removed delete_site_transient('update_plugins') from bust_cache_on_page_load()
  which was forcing a full WordPress plugin update check on every settings visit.

= 1.5.6 =
* Task Management and Reports header links now visible only to Marketing Admins
  and WordPress administrators.
* Added "← Return to Marketing Ops" header bar to the Task Template Editor page.
* Added "← Return to Marketing Ops" header bar to the Event Journey Report page.

= 1.5.5 =
* Dashboard and My Classes: Trouble Only, Next 30 Days, Upcoming, and Past Events
  filters are now mutually exclusive (clicking one clears the others).
* Added Task Management and Reports navigation links to the blue header bar on
  Dashboard and My Classes pages.

= 1.5.4 =
* Fixed Event Journey Report dropdown showing no events.
  Root cause: SQL was referencing a non-existent eve_name column; fixed to use
  COALESCE(cvent_event_title, eve_location, '').
* Marketing Admins can now access the Event Journey Report
  (added check in current_user_can_view_reports()).

= 1.4.9 =
* Kanban: cross-column pull clone (source card stays put); hide in-list
  sortable-drag; calmer hover/cursor during drag.

= 1.4.8 =
* Kanban: hide in-list drag source + restyle ghost slot; grabbing cursor during
  drag.

= 1.4.7 =
* Kanban: Sortable forceFallback + fallbackOnBody so drags escape overflow
  (cross-column drops).

= 1.4.6 =
* Kanban: opt-in debug logging via localStorage or query string flag.

= 1.4.5 =
* Kanban: SortableJS drag-and-drop across columns.

= 1.0.0 =
* Initial Phase 1 release.
