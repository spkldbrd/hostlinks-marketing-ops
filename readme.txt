=== Hostlinks Marketing Ops ===
Contributors: digitalsolution
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
License: GPLv2

A companion plugin for Hostlinks that gives the marketing team a full workflow
management system: dashboards, per-event checklists, marketer bucket access,
task template editing, a journey report, and more.

Requires the Hostlinks plugin to be installed and active.

== Description ==

Hostlinks Marketing Ops sits alongside the core Hostlinks plugin and provides
everything the marketing operations team needs to track and manage events from
assignment through completion.

Each event moves through six configurable workflow stages. Tasks are provisioned
from a master template and tracked per-event. Marketers see only the events
assigned to their bucket. Admins and Marketing Admins get full visibility,
reporting, and configuration tools.

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
  links, contacts, hotels). Right column also holds a Call List card and a
  free-form Notes card.

[hmo_task_editor]
  Master task template editor. Add, rename, reorder, and delete stages and
  tasks (including subtasks). Changes automatically reflect on every event's
  checklist the next time it loads.

[hmo_event_report]
  Event Journey Report for Marketing Admins and Report Viewers. Filterable by
  year, month, and marketer bucket. Shows registration count, days remaining,
  stage progress, and a full task breakdown for the selected event.

== User Roles & Access ==

WordPress Administrator
  Full access to all shortcodes, admin settings, task editor, reports, bulk
  tools, and list management.

Marketing Admin
  Access to the task editor, event journey report, and all header navigation
  links. Assigned via the HMO Settings → Marketing Admins list.

Task Editor
  Can add/edit/delete tasks and stages in the master template. Assigned via
  HMO Settings → Task Editors.

Report Viewer
  Can access the Event Journey Report. Assigned via HMO Settings → Report
  Viewers.

Marketer (Bucket User)
  Sees only events assigned to their bucket(s) via the My Classes view.
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

== Database Tables ==

  wp_hmo_event_ops       — one row per event; stage, goal, task count, call list URL,
                           event note, and other workflow metadata.
  wp_hmo_event_tasks     — one row per task per event; status, completion tracking,
                           and per-task notes.
  wp_hmo_event_task_items — sub-checklist items (future use).
  wp_hmo_checklist_templates — master stage and task definitions including subtasks.
  wp_hmo_bucket_access   — many-to-many: marketer bucket ↔ WordPress user.
  wp_hmo_event_activity  — activity log: task completions, stage changes, goal updates.

DB schema version is managed via `hmo_db_version` in wp_options. Migrations run
automatically on every page load via the `plugins_loaded` hook.

== REST API ==

All checklist interactions use the WordPress REST API under the `hmo/v1` namespace.

  GET  /hmo/v1/dashboard                          Dashboard row data
  GET  /hmo/v1/events/{id}/checklist              Event checklist
  POST /hmo/v1/events/{id}/stage                  Update workflow stage
  POST /hmo/v1/events/{id}/lists                  Save call list URL
  POST /hmo/v1/events/{id}/goal                   Update registration goal (managers)
  POST /hmo/v1/events/{id}/event-note             Save event-level note
  POST /hmo/v1/tasks/{id}/complete                Mark task complete (with optional note)
  POST /hmo/v1/tasks/{id}/incomplete              Revert task to pending
  POST /hmo/v1/tasks/{id}/note                    Save per-task completion note

== Installation ==

1. Ensure the Hostlinks plugin is installed and active.
2. Upload this plugin folder to /wp-content/plugins/.
3. Activate via the WordPress Plugins screen.
4. Tables are created and checklist templates are seeded automatically.
5. Visit Marketing Ops → Settings to assign user roles and configure pages.

== Auto-Updates ==

The plugin checks for updates via GitHub Releases (repository: spkldbrd/hostlinks-marketing-ops).
The WordPress admin will show an update notice when a new release is available.

== Changelog ==

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
