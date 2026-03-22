=== Hostlinks Marketing Ops ===
Contributors: digitalsolution
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
License: GPLv2

== Description ==

Companion plugin for Hostlinks that adds marketer dashboards, per-event checklist workflow,
countdown / days-left views, marketer-only access to assigned events, and data / call list tracking.

Requires the Hostlinks plugin to be installed and active.

== Features ==

* Dashboard showing all events with stage, open tasks, days left, and risk highlighting
* Per-event checklist with 6 workflow stages and ~30 tasks
* Marketer-only filtering — marketers see only their assigned classes
* Task complete / incomplete tracking with optional completion notes
* Data list and call list status + Google Sheets URL tracking
* Activity log for every task and metadata change
* REST API endpoints for all checklist operations
* Risk highlighting: red, yellow, green based on days left and open task count

== Workflow Stages ==

1. Event Setup
2. Data Send Prep
3. 60-Day Marketing
4. 30-Day Marketing
5. Final Prep
6. Completion

== Installation ==

1. Ensure the Hostlinks plugin is installed and active.
2. Upload this plugin folder to /wp-content/plugins/.
3. Activate via the WordPress Plugins screen.
4. Tables are created and checklist templates are seeded automatically on activation.

== Changelog ==

= 1.4.5 =
* Kanban: SortableJS drag-and-drop, drag anywhere except title row, full-column drop zones.

= 1.0.0 =
* Initial Phase 1 release.
