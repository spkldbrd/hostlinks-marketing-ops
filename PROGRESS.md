# Hostlinks Marketing Ops — Development Progress

This document summarises the work done on the Marketing Ops sister plugin to Hostlinks,
organised by feature area and release. It is intended as a human-readable reference for
the development team.

---

## What the Plugin Is

Marketing Ops extends the Hostlinks event-management platform with a full workflow layer
for the marketing team. It lives at the `hmo/v1` REST namespace, uses six custom database
tables, and exposes six shortcodes. It does not modify any Hostlinks core tables.

**Core concept:** each event is owned by a marketer "bucket." Users are assigned to buckets
(many-to-many). Tasks are provisioned from a master template when a marketer first visits
an event. Progress is tracked per task. Admins and Marketing Admins get cross-team visibility
and reporting.

---

## Architecture Overview

| Layer | Files |
|---|---|
| DB schema & migrations | `includes/class-hmo-db.php` |
| Hostlinks data bridge | `includes/class-hmo-hostlinks-bridge.php` |
| Access control | `includes/class-hmo-access-service.php` |
| Checklist logic | `includes/class-hmo-checklist-service.php`, `class-hmo-checklist-templates.php` |
| Dashboard data | `includes/class-hmo-dashboard-service.php` |
| Countdown/risk | `includes/class-hmo-countdown-service.php` |
| REST API | `includes/class-hmo-rest.php` |
| Shortcodes | `includes/class-hmo-shortcodes.php` |
| Front-end views | `shortcode/views/` |
| Auto-updater | `includes/class-hmo-updater.php` |
| Admin UI | `admin/` |

---

## Feature Areas

### 1. Dashboards (`[hmo_dashboard]`, `[hmo_my_classes]`)

The main dashboard shows all active events in a table with risk highlighting,
days-left countdown, open task count, registration count vs. goal, current stage,
and marketer assignment. It is used by admins and Marketing Admins.

`[hmo_my_classes]` shows the same table but filtered to events matching the
logged-in user's bucket assignments.

**Filters available on both pages:**
- Trouble Only (high-risk events)
- Next 30 Days
- Upcoming
- Past Events
- Marketer Bucket

All four quick-filters are mutually exclusive: selecting one clears the others.

**Header navigation bar** (v1.5.5+):
- Title: "Marketing Ops"
- Left side: Task Management link, Reports link (visible to Marketing Admins and WP admins only)
- Right side: "Return to Hostlinks" link

**Performance history:**
In v1.5.7, a severe N+1 query problem was fixed. The dashboard was calling `get_event()` inside
a loop for every event to retrieve registration counts and event dates — ~1,476 redundant queries
for a site with 738 events. Fixed by reading `eve_paid + eve_free` directly from the already-loaded
event object and adding `get_days_left_from_date(string)` to bypass the DB for countdown
calculations. Also removed an aggressive `delete_site_transient('update_plugins')` call that was
triggering a full WordPress plugin update HTTP check on every admin page load.

---

### 2. Event Detail (`[hmo_event_detail]`)

The main per-event working page for marketers. It loads via `?event_id={id}` on the
page where the shortcode is placed.

**Header bar:** Days-left pill, event title, back to dashboard link. Stat strip: location,
marketer, event date, registrations (editable goal), open task count, current stage selector.

**Left column — Checklist:**
- Tasks grouped by stage in collapsible accordions
- The current stage opens automatically
- Each task: checkbox, label, description, "Completed by" line, per-task note textarea + Save
- Subtasks (from the template editor) displayed as an indented bullet list under the parent task (v1.6.2)
- Accordion header shows open task count and completion percentage

**Right column (top to bottom):**
1. **Call List card** (v1.6.4) — compact card showing status (Not Set / Set) with a View
   arrow button that opens the Google Sheet. Update button reveals an inline URL input.
2. **Notes card** (v1.6.1) — free-form per-event note visible only on this page. Saves
   to `event_note` column on `hmo_event_ops`. REST handler returns real HTTP 500 on failure.
3. **Insights panel** — read-only data from Hostlinks:
   - Venue / address
   - Event Links: 2-column grid (Email & PDF, Info & Registration)
   - Event Contacts: 2-column grid, wraps for 3+ contacts
   - Recommended Hotels: 2-column grid, wraps for 3+ hotels

**Layout fix (v1.6.5):** The two-column grid (`1fr 1fr`) auto-flowed into a 2×2 grid when
there were more than 2 right-column panels. Fixed by wrapping all right-side panels in a
`flex-column` container so the grid always sees exactly 2 direct children.

---

### 3. Task Template Editor (`[hmo_task_editor]`)

A drag-and-drop editor for the master checklist that gets provisioned to every event.

- Add, rename, reorder, and delete stages
- Add, rename, reorder, and delete tasks within each stage
- Add subtasks under any task (collapsible sub-list)
- Subtask badge shows count on the parent task row
- Drag-and-drop reordering (SortableJS) within stages

Changes to the template are reflected automatically on every event checklist the next
time it loads — no re-provisioning needed. Subtasks are fetched via a JOIN on
`hmo_checklist_templates.parent_id` matched by `task_key`.

Access: WordPress administrators and users listed in HMO Settings → Task Editors.

**Header bar** (v1.5.6+): "← Return to Marketing Ops" link in the blue header.

---

### 4. Event Journey Report (`[hmo_event_report]`)

A read-only report for Marketing Admins and Report Viewers. Shows the full task and
stage breakdown for one selected event.

**Filters (left of event selector):**
- Year (defaults to current year)
- Month (defaults to current month)
- Bucket / Marketer (defaults to All Buckets) — added v1.6.0

Changing any filter resets the event selection and reloads the filtered event list.

**Report content** for the selected event:
- Summary header: location, marketer (ops), event date, registrations, days remaining
- Stage progress overview: visual progress bars per stage
- Full task table: task label, status, completed by, completion date, note

**Access:** WordPress administrators, Marketing Admins (added v1.5.4),
and users in HMO Settings → Report Viewers.

**Bug fixed in v1.5.4:** The event dropdown was empty because the SQL query referenced
`eve_name` (a non-existent column). Fixed to `COALESCE(cvent_event_title, eve_location, '')`.

**Header bar** (v1.5.6+): "← Return to Marketing Ops" link in the blue header.

---

### 5. Access Control

Three layers of access control:

**Layer 1 — Shortcode gate** (`hmo_shortcode_access_modes` option):
Each shortcode can be set to `public`, `logged_in`, or `approved_viewers`.

**Layer 2 — Role-based capability:**
- WordPress admin (`manage_options`): full access everywhere
- Marketing Admin: task editor, reports, header nav links. Assigned via HMO Settings.
- Task Editor: task template editor only. Assigned via HMO Settings.
- Report Viewer: event journey report only. Assigned via HMO Settings.

**Layer 3 — Bucket-based event filtering:**
The `hmo_bucket_access` table maps marketer IDs (from Hostlinks) to WordPress user IDs.
This is many-to-many: one user can see multiple buckets; one bucket can be shared.
Assigned via HMO Settings → Bucket Access.

---

### 6. Database & Migrations

Tables are created by `HMO_DB::create_tables()` using `dbDelta()` on activation and
on every `plugins_loaded` call when `hmo_db_version < HMO_DB_VERSION`.

`HMO_DB::maybe_upgrade()` handles forward-only migrations:
- v1.3 (added columns): `completed_by_user_id`, `completed_at`, `completion_note` on `hmo_event_tasks`
- v1.3.4 (added column): `event_note` on `hmo_event_ops`

`upsert_event_ops()` now returns `bool` so callers can detect failures. Previously it
returned `void` and all callers assumed success.

---

### 7. Auto-Updates

The plugin checks GitHub Releases for updates via `HMO_Updater`.
Repository: `spkldbrd/hostlinks-marketing-ops`.
WordPress admin shows a standard update notice when a new release tag is available.

---

## Release History (Recent)

| Version | Date       | Summary |
|---------|------------|---------|
| 1.6.7   | 2026-03-21 | Event notes: proper DB column storage + real error handling |
| 1.6.6   | 2026-03-21 | Interim: wp_options storage for notes (superseded by 1.6.7) |
| 1.6.5   | 2026-03-21 | Fix: Insights panel appearing in left column (grid layout bug) |
| 1.6.4   | 2026-03-21 | Call List compact card; remove old List Links panel; fix notes event-id bug |
| 1.6.3   | 2026-03-21 | Insights 2-column grid for links, contacts, hotels |
| 1.6.2   | 2026-03-21 | Fix: template subtasks now visible on event checklist |
| 1.6.1   | 2026-03-21 | Per-event Notes card in right column |
| 1.6.0   | 2026-03-21 | Event Journey Report: Bucket filter |
| 1.5.9   | 2026-03-21 | Event Journey Report: Year/Month filters |
| 1.5.8   | 2026-03-21 | Event Journey Report: full-width layout |
| 1.5.7   | 2026-03-21 | Performance: eliminate N+1 queries; remove aggressive update cache bust |
| 1.5.6   | 2026-03-21 | Return to Marketing Ops header on Task Editor and Report; restrict nav links to admins |
| 1.5.5   | 2026-03-21 | Mutually exclusive filters; Task Management + Reports header nav links |
| 1.5.4   | 2026-03-21 | Fix report dropdown; Marketing Admins can access report |
| 1.4.9   | —          | Kanban drag-and-drop refinements |
| 1.0.0   | —          | Initial release |

---

## Known Good Patterns

- **REST handlers** should always check the return value of DB operations and return
  the appropriate HTTP status. Use `$success ? 200 : 500`, never hardcode 200.
- **`upsert_event_ops()`** returns `bool` — always check it in REST handlers.
- **`$event->eve_id`** is the Hostlinks event primary key used throughout. Never use
  `$event->id` in event-detail.php — that field does not exist on the Hostlinks result object.
- **Right-column layout**: all side panels must live inside `.hmo-detail-col-side-wrap`
  (a flex-column container) so the outer grid always sees exactly 2 children.
- **Template subtasks** are fetched via `get_subtasks_by_task_keys()` at render time —
  no per-event provisioning needed for subtask display.
- **DB migrations**: always check with `SHOW COLUMNS` before attempting an `ALTER TABLE`.
  The version option is updated at the end of `maybe_upgrade()` — if ALTER fails, the
  column will be missing even though the version appears current. Always design callers
  to handle a missing column gracefully.
