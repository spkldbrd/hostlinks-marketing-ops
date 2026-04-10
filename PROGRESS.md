# Hostlinks Marketing Ops — Development Progress

This document summarises the work done on the Marketing Ops sister plugin to Hostlinks,
organised by feature area and release. It is intended as a human-readable reference for
the development team and future AI agents.

---

## What the Plugin Is

Marketing Ops extends the Hostlinks event-management platform with a full workflow layer
for the marketing team and a suite of standalone marketing tools. It lives at the
`hmo/v1` REST namespace (checklist/workflow) and `wp_ajax_hmo_maps_*` AJAX actions
(Maps tool). It uses eight custom database tables and exposes seven shortcodes.
It does not modify any Hostlinks core tables.

**Core concept:** each event is owned by a marketer "bucket." Users are assigned to
buckets (many-to-many). Tasks are provisioned automatically from a master template when
a new Hostlinks event is created. Progress is tracked per task. Admins and Marketing
Admins get cross-team visibility and reporting. Standalone tools (Maps) are accessible
from the event detail page via the Tools card.

---

## Architecture Overview

| Layer | Files |
|---|---|
| Plugin entry & constants | `hostlinks-marketing-ops.php` |
| Bootstrap / hook registration | `includes/class-hmo-bootstrap.php` |
| DB schema & migrations | `includes/class-hmo-db.php` |
| Maps DB schema | `includes/class-hmo-maps-db.php` |
| Hostlinks data bridge | `includes/class-hmo-hostlinks-bridge.php` |
| Access control | `includes/class-hmo-access-service.php` |
| Checklist logic | `includes/class-hmo-checklist-service.php`, `class-hmo-checklist-templates.php` |
| Dashboard data | `includes/class-hmo-dashboard-service.php` |
| Countdown / risk | `includes/class-hmo-countdown-service.php` |
| Maps AJAX service | `includes/class-hmo-maps-service.php` |
| Page URL registry | `includes/class-hmo-page-urls.php` |
| REST API | `includes/class-hmo-rest.php` |
| Shortcodes | `includes/class-hmo-shortcodes.php` |
| Front-end views | `shortcode/views/` |
| Auto-updater | `includes/class-hmo-updater.php` |
| Admin settings UI | `admin/views/settings.php` |
| Front-end CSS | `assets/css/frontend.css` |
| Maps JS | `assets/js/maps-tool.js` |
| Bundled data files | `assets/data/` |

---

## Feature Areas

### 1. Dashboards (`[hmo_dashboard]`, `[hmo_my_classes]`)

The main dashboard shows all active events in a table with risk highlighting,
days-left countdown, open task count, registration count vs. goal, current stage,
and marketer assignment. Used by admins and Marketing Admins.

`[hmo_my_classes]` shows the same table filtered to events matching the logged-in
user's bucket assignments.

**Filters available on both pages:**
- Trouble Only (high-risk events)
- Next 30 Days
- Upcoming
- Past Events
- Marketer Bucket

All four quick-filters are mutually exclusive (v1.5.5): selecting one clears the others.

**Header navigation bar** (v1.5.5+):
- Left: "Marketing Ops" title + Task Management link + Reports link
  (Task Management and Reports visible to Marketing Admins and WP admins only)
- Right: "Return to Hostlinks" link

**Performance (v1.5.7):** eliminated a severe N+1 query storm. The dashboard was
calling `get_event()` inside a loop for each event (~1,476 redundant queries for 738
events). Fixed by reading `eve_paid + eve_free` directly from the loaded event object
and computing countdown from date string without an extra DB call. Also removed
`delete_site_transient('update_plugins')` from `bust_cache_on_page_load()` which was
triggering a full WordPress plugin update HTTP request on every settings page load.

---

### 2. Event Detail (`[hmo_event_detail]`)

The main per-event working page for marketers. Loads via `?event_id={id}` on the
shortcode page.

**Header bar:** days-left pill, event title, back-to-dashboard link.
**Stat strip:** location, marketer, event date, registrations / goal (editability
controlled by role + Settings checkboxes), open task count, current stage selector.

**Registration goal editing (v1.11.4):** WordPress administrators can always edit.
Marketing Admins and Hostlinks Users can each be independently granted edit access
via checkboxes in Settings → General → "Allow Goal Editing". When unchecked for a
role, the input field and Save button are hidden but the current count stays visible.
The REST endpoint `POST /hmo/v1/events/{id}/goal` enforces the same role check
server-side via `require_manager()`.

**Left column — Checklist:**
- Tasks grouped by stage in collapsible accordions
- Current stage opens automatically
- Each task: checkbox, label, description, "Completed by" line, per-task note
- Subtasks from the template editor rendered as indented bullet list under parent (v1.6.2)
- Accordion header shows open task count and completion percentage

**Right column (top to bottom):**
1. **Call List card** (v1.6.4) — status (Not Set / Set) with View button (opens
   Google Sheet in new tab). Update button reveals inline URL input with Save/Cancel.
   URL stored per-event in `hmo_event_ops.call_list_url`.
2. **Tools card** (v1.7.x) — 2×2 grid of global links configured in Settings →
   Tools Links. Each link: name, URL, optional icon image from WP Media Library.
   When a link points to the Maps tool page, event ID and name are auto-appended
   as `?hmo_eid=&hmo_ename=` query params. Links open in new tab.
3. **Notes card** (v1.6.1) — free-form per-event note. Saves to
   `hmo_event_ops.event_note` column. REST handler returns HTTP 500 on DB failure.
4. **Insights panel** — read-only Hostlinks data:
   - Venue / address
   - Event Links: 2-column grid (Email & PDF, Info & Registration)
   - Event Contacts: 2-column grid, wraps to additional rows for 3+ contacts
   - Recommended Hotels: 2-column grid, wraps for 3+ hotels

**Layout rule (v1.6.5):** all right-column panels MUST live inside
`.hmo-detail-col-side-wrap` (a `flex-column` container). The outer layout is a
`1fr 1fr` CSS grid — it must always see exactly 2 direct children (left col and
the side wrap). Adding panels directly to the grid breaks the layout.

---

### 3. Task Template Editor (`[hmo_task_editor]`)

Drag-and-drop editor for the master checklist provisioned to every event.

- Add, rename, reorder, delete stages
- Add, rename, reorder, delete tasks within each stage
- Add subtasks under any task (collapsible sub-list in the editor)
- Subtask count badge on parent task row
- Drag-and-drop reordering (SortableJS) within stages

**Template sync to existing events (v1.7.2):** when a task is saved, the plugin
automatically syncs changes to all provisioned future events:
- New top-level tasks are added (not subtasks — those display from the template at
  render time via `get_subtasks_by_task_keys()`)
- Renamed labels update across all events
- Deleted tasks are removed only if their status is still pending (completed tasks
  are preserved)

**Recount All button (v1.7.2):** in Settings, recalculates `open_tasks` count for
all provisioned future events without touching individual task statuses. Safe to run
at any time.

Access: WordPress administrators and users in HMO Settings → Task Editors.

**Header bar** (v1.5.6+): "← Return to Marketing Ops" in the blue header.

---

### 4. Event Journey Report (`[hmo_event_report]`)

Read-only report for Marketing Admins and Report Viewers. Full task and stage
breakdown for one selected event.

**Filters (left of event selector):**
- Year (defaults to current year)
- Month (defaults to current month)
- Bucket / Marketer (defaults to All Buckets) — v1.6.0

Changing any filter resets the event selection and reloads the filtered event list.

**Report content:**
- Summary header: location, marketer, event date, registrations, days remaining
- Stage progress: visual progress bars per stage
- Full task table: label, status, completed by, completion date, note

**Access:** WordPress administrators, Marketing Admins (v1.5.4+), Report Viewers.

**Bug fixed (v1.5.4):** event dropdown was empty because SQL referenced `eve_name`
(non-existent column). Fixed to `COALESCE(cvent_event_title, eve_location, '')`.

**Header bar** (v1.5.6+): "← Return to Marketing Ops" in the blue header.

---

### 5. Task Auto-Provisioning (v1.7.3)

When a new event is created in Hostlinks — either via "Add New Event" or the
"New CVENT Events" importer — Hostlinks fires the custom action
`do_action('hostlinks_event_created', $event_id)`.

HMO hooks into this action via `HMO_Checklist_Service::auto_provision_event()`:
1. Checks the event is not already provisioned.
2. Inserts all tasks from `hmo_checklist_templates` (top-level tasks only — subtasks
   display from the template at render time).
3. Counts open tasks and writes to `hmo_event_ops.open_tasks`.
4. The event checklist is immediately available when the marketer first visits the
   event detail page.

The Hostlinks plugin must call `do_action('hostlinks_event_created', $event_id)` in
both the "Add New Event" and "CVENT import" save handlers. This is a cross-plugin
contract — if that action is not fired, auto-provisioning will not occur and the
marketer must manually trigger it by visiting the event detail page.

---

### 6. Tools Card & Settings (v1.7.x)

**Settings → Tools Links tab:** global list of links shown in the Tools card on every
event detail page. Each entry has:
- Name (required)
- URL (required)
- Icon (optional — WP Media Library image selector, stores attachment URL)

Stored as a serialised array in `hmo_tools_links` wp_option.

**Front-end rendering:** 2×2 CSS grid (`grid-template-columns: 1fr 1fr`). Each
item is an `<a>` tag with optional `<img>` icon to the left of the link name.
No bullet points, no trailing arrows.

**Maps tool awareness:** if a link URL's path matches the Maps tool page path
(from `HMO_Page_URLs::get_maps_tool()`), the event's `eve_id` and title are
appended as `hmo_eid` and `hmo_ename` query parameters automatically.

---

### 7. Maps Tool (`[display_maps_tool]`)

A standalone radius-based US county lookup tool using local MySQL data.

#### Data pipeline

```
assets/data/CenPop2020_Mean_CO.txt  ──┐
                                       ├─► [Initialize Centroids] ──► hmo_maps_county_centroids
assets/data/2024_Gaz_counties_national.txt ─┘

assets/data/co-est2025-alldata.csv  ──────► [Sync Stats Now] ──► hmo_maps_county_stats
```

**Centroid sources (v1.11.5):**
- `CenPop2020_Mean_CO.txt` — 2020 Census Centers of Population. 3,221 rows
  (3,143 US counties + 78 Puerto Rico municipios). Population-weighted: places the
  county center where residents actually live. **Recommended for marketing reach.**
  Updates every 10 years (next: ~2031 after the 2030 Census).
- `2024_Gaz_counties_national.txt` — 2024 Census Gazetteer. Tab-delimited.
  Uses `INTPTLAT`/`INTPTLONG` (geographic center of land area). Can produce different
  counts for large counties where population is concentrated in one corner.

Centroid source is selected in Settings → Maps → Centroid Source dropdown. After
changing the setting, click "Initialize Centroids" to reload. Rollback: switch back
to Geographic and re-initialize.

**Stats file:** `co-est2025-alldata.csv` — Census PEP Vintage 2025. County-level
population estimates and net migration. Filter: only `SUMLEV=050` rows. FIPS built
from `STATE` (2-digit) + `COUNTY` (3-digit). Updates annually each spring.

#### Lookup workflow (AJAX)

1. User types a city → Google Places autocomplete resolves to lat/lng.
2. User clicks Lookup → JS sends `lat`, `lng`, `location`, `radius` via AJAX POST
   to `wp_ajax_hmo_maps_lookup`.
3. Server runs Haversine query against `hmo_maps_county_centroids` JOIN
   `hmo_maps_county_stats`. Uses `ROUND(..., 1) AS distance_miles` and
   `HAVING distance_miles <= %d`.
4. Results returned as JSON: county list, total pop, total net migration, count.
5. JS renders stat cards (county count), results table, enables action buttons.

**Haversine formula (MySQL):**
```sql
3958.8 * ACOS(
  LEAST(1.0,
    COS(RADIANS(center_lat)) * COS(RADIANS(c.lat))
    * COS(RADIANS(c.lng) - RADIANS(center_lng))
    + SIN(RADIANS(center_lat)) * SIN(RADIANS(c.lat))
  )
)
```
Earth radius: 3958.8 miles. `LEAST(1.0, ...)` guard prevents `ACOS` domain errors
from floating-point precision.

**Geocoding fallback:** if no lat/lng is sent (user typed without selecting from
autocomplete), the server geocodes via Nominatim (OpenStreetMap) as a fallback.

#### Front-end layout

Two-column layout:
- **Left card** (~55% width): city input, radius slider (70% width), Lookup button.
- **Right card** (~42% width): Results (Counties stat + Send Count placeholder),
  action bar (Copy List, Download CSV) — greyed until first lookup.
- Below both cards: full-width county detail table.

Navigation: opens in a new browser tab from the event detail Tools card.
- Fresh tab (no prior history): shows "× Close Tab" button — calls `window.close()`.
- Direct URL / bookmarked: shows "← Return to Hostlinks" link.
- Event context pill badge in header when `hmo_eid` / `hmo_ename` URL params present.

#### County name formatting

Suffixes stripped in the results table, CSV export, and Copy List:
County, Parish, Borough, Census Area, Municipality, City and Borough,
Municipio, District, Island, city (independent city, Virginia).

#### Known methodology differences vs. Stats America Big Radius

Stats America uses a different centroid dataset. After switching to population-
weighted centroids, Denver CO counts are nearly identical. Some boundary-case
discrepancies remain for specific cities (Santa Barbara CA, Washington DC) due to
different geocoded starting points and centroid vintages. This is expected and
inherent to any centroid-based radius tool.

#### Settings (Maps tab)

| Field | Option key | Notes |
|---|---|---|
| Page Heading | `hmo_maps_page_heading` | Displayed in blue header bar. Default: "Marketing Maps". |
| Centroid Source | `hmo_maps_centroid_source` | `geographic` or `population_weighted`. Controls Initialize Centroids. |
| Google Maps API Key | `hmo_maps_google_api_key` | Required for Places autocomplete. Enable Places API in Google Cloud Console. |
| Census API Key | `hmo_maps_census_api_key` | **Future feature.** Not used. For planned annual auto-update via Census API. |
| Sync Frequency | `hmo_maps_sync_frequency` | **Future feature.** Not used. For planned WP Cron scheduling when Census API is wired. |
| Centroids initialized | `hmo_maps_centroids_initialized` | Timestamp, set by Initialize Centroids button. |
| Centroid source stored | `hmo_maps_centroid_source` | Written by Initialize Centroids; shown in Data Status. |
| Stats last synced | `hmo_maps_last_sync` | Timestamp, set by Sync Stats Now button. |

---

### 8. Access Control

Three layers:

**Layer 1 — Shortcode gate** (`hmo_shortcode_access_modes` option):
Each shortcode set to `public`, `logged_in`, or `approved_viewers`.
`HMO_Access_Service::SHORTCODES` constant lists all registered shortcodes —
this also controls whether `frontend.css` is enqueued on the page.
`display_maps_tool` must be in this list or the blue header bar won't load.

**Layer 2 — Role-based capability:**
- WP admin (`manage_options`): full access everywhere, always can edit goals.
- Marketing Admin: task editor, reports, header nav links, optional goal editing.
- Task Editor: task template editor only.
- Report Viewer: event journey report only.
- Hostlinks User / Bucket User: My Classes, event detail, Maps tool, optional goal editing.

**Layer 3 — Bucket-based event filtering:**
`hmo_bucket_access` maps marketer IDs (Hostlinks) to WordPress user IDs.
Many-to-many. Assigned via HMO Settings → Bucket Access.

---

### 9. Database & Migrations

Tables created by `HMO_DB::create_tables()` and `HMO_Maps_DB::create_tables()`
using `dbDelta()`. Migrations run when `HMO_DB_VERSION` constant advances.

`HMO_DB::maybe_upgrade()` forward-only migrations:
- v1.3: added `completed_by_user_id`, `completed_at`, `completion_note` to `hmo_event_tasks`
- v1.3.4: added `event_note` to `hmo_event_ops`
- v1.3.6: `HMO_Maps_DB::create_tables()` called — creates Maps tables if not present

Maps tables have `UNIQUE KEY (fips)` so re-running Initialize Centroids / Sync Stats
is safe (uses `ON DUPLICATE KEY UPDATE`).

`upsert_event_ops()` returns `bool` — always check it in REST handlers.

---

### 10. Auto-Updates

`HMO_Updater` checks GitHub Releases for the latest tag.
Repository: `spkldbrd/hostlinks-marketing-ops`.
A push to `main` triggers `.github/workflows/release-zip.yml` which:
1. Parses `HMO_VERSION` from the plugin header.
2. Builds `hostlinks-marketing-ops.zip` with top-level `hostlinks-marketing-ops/` folder.
3. Creates a GitHub Release for that version or uploads (--clobber) to an existing one.

Excluded from zip: `.git`, `.github`, `.cursor`.

---

## Release History

| Version | Date | Summary |
|---|---|---|
| 1.11.5 | 2026-03-21 | Maps: population-weighted centroids (CenPop2020); Centroid Source setting; BOM fix; future-feature labels |
| 1.11.4 | 2026-03-21 | Settings: configurable registration goal editing per role |
| 1.11.3 | 2026-03-21 | Maps: equal card height (align-items stretch) |
| 1.11.2 | 2026-03-21 | Maps: event context awareness via URL params + pill badge |
| 1.11.1 | 2026-03-21 | Maps: isolated close-tab navigation (× Close Tab / ← Return to Hostlinks) |
| 1.11.0 | 2026-03-21 | Maps: full two-column layout redesign with stat cards + action bar |
| 1.10.8 | 2026-03-21 | Maps: revert PlaceAutocompleteElement (web component, broke input); back to legacy Autocomplete |
| 1.10.7 | 2026-03-21 | Maps: county suffix stripping in table, CSV, and Copy List |
| 1.10.6 | 2026-03-21 | Maps: Copy List HTTP fallback (execCommand); PlaceAutocompleteElement attempt |
| 1.10.5 | 2026-03-21 | Maps: Copy List button (clipboard, County ST format) |
| 1.10.4 | 2026-03-21 | Maps: added display_maps_tool to SHORTCODES — fixes missing frontend.css |
| 1.10.3 | 2026-03-21 | Maps: remove D3 map; fix slider flicker with fixed-width flex sibling |
| 1.10.2 | 2026-03-21 | Maps: vertical stack layout; slider flicker fix; D3 county map (removed in 1.10.3) |
| 1.10.1 | 2026-03-21 | Maps: externalize JS to fix WordPress content-filter entity-encoding bug; Google Places autocomplete |
| 1.10.0 | 2026-03-21 | Maps tool initial build: local data pipeline, Haversine query, AJAX, admin settings |
| 1.7.3  | 2026-03-21 | Task auto-provisioning on Hostlinks event creation (hook-based) |
| 1.7.2  | 2026-03-21 | Recount All button; template-change sync to existing future events |
| 1.7.1  | 2026-03-21 | Tools card: remove bullets/arrows; 2×2 grid layout |
| 1.7.0  | 2026-03-21 | Tools card: WP Media Library icon selector; icon displayed on front-end |
| 1.6.9  | 2026-03-21 | Split Call List row into two cards: Call List (left) + Tools (right) with settings |
| 1.6.8  | 2026-03-21 | readme.txt and PROGRESS.md created |
| 1.6.7  | 2026-03-21 | Event notes: proper DB column storage + real HTTP 500 error handling |
| 1.6.6  | 2026-03-21 | Interim: wp_options note storage (superseded by 1.6.7) |
| 1.6.5  | 2026-03-21 | Fix: Insights in left column (grid layout — wrap right panels in flex-column) |
| 1.6.4  | 2026-03-21 | Call List compact card; remove old List Links panel |
| 1.6.3  | 2026-03-21 | Insights: 2-column grid for links, contacts, hotels |
| 1.6.2  | 2026-03-21 | Fix: template subtasks not visible on event checklist |
| 1.6.1  | 2026-03-21 | Per-event Notes card in right column |
| 1.6.0  | 2026-03-21 | Event Journey Report: Bucket filter |
| 1.5.9  | 2026-03-21 | Event Journey Report: Year/Month filters |
| 1.5.8  | 2026-03-21 | Event Journey Report: full-width layout |
| 1.5.7  | 2026-03-21 | Performance: N+1 fix; remove aggressive update cache bust |
| 1.5.6  | 2026-03-21 | Return to Marketing Ops on Task Editor + Report; restrict nav links to admins |
| 1.5.5  | 2026-03-21 | Mutually exclusive filters; Task Management + Reports header nav |
| 1.5.4  | 2026-03-21 | Fix report dropdown; Marketing Admins can access report |
| 1.4.9  | —          | Kanban drag-and-drop refinements |
| 1.0.0  | —          | Initial release |

---

## Known Good Patterns

**REST handlers:**
- Always check DB operation return values. Use `$success ? 200 : 500`, never hardcode 200.
- `upsert_event_ops()` returns `bool` — always check it.

**Event ID:**
- `$event->eve_id` is the Hostlinks event primary key. Never use `$event->id`
  in event-detail.php — that field does not exist on the Hostlinks result object.

**Right-column layout:**
- All side panels must be inside `.hmo-detail-col-side-wrap` (flex-column).
  The outer grid must always see exactly 2 direct children.

**Template subtasks:**
- Fetched at render time via `get_subtasks_by_task_keys()` — no per-event
  provisioning needed. Auto-provisioning only inserts top-level tasks.

**DB migrations:**
- Check with `SHOW COLUMNS` before `ALTER TABLE`.
- The version option updates at the end of `maybe_upgrade()` — if ALTER fails,
  the column will be missing even though the version appears current.
- Always design callers to handle a missing column gracefully (`COALESCE`, etc.).

**Maps tool — `display_maps_tool` in SHORTCODES:**
- This constant controls whether `frontend.css` loads. If `display_maps_tool` is
  missing from `HMO_Access_Service::SHORTCODES`, the blue header bar and all HMO
  styles will be absent from the Maps page.

**Maps tool — JavaScript:**
- All Maps JS must live in `assets/js/maps-tool.js` and be enqueued via
  `wp_enqueue_script`. Never put Maps JS inline inside `shortcode/views/maps.php`
  — WordPress `the_content` filter entity-encodes `&&` to `&#038;&#038;`, which
  breaks JavaScript syntax inside inline `<script>` blocks.

**Maps tool — centroid import:**
- Both centroid file parsers strip UTF-8 BOM from the first header column using
  `ltrim($header[0], "\xEF\xBB\xBF")`. This is required for the CenPop file.
- CenPop file uses comma delimiter. Gazetteer uses tab delimiter.
- CenPop has no state abbreviation column — derive from STATEFP using
  `HMO_Maps_Service::state_fips_map()`.
- FIPS from CenPop: `str_pad(STATEFP, 2, '0', LEFT) . str_pad(COUNTYFP, 3, '0', LEFT)`.
- FIPS from Gazetteer: `GEOID` column (already 5 digits).

**Maps tool — `wp_localize_script` config:**
- `hmoMapsConfig` is the global JS object. Current keys: `ajaxUrl`, `nonce`,
  `hasGoogleKey`, `eventId`, `eventName`.
- When adding new server-side config values needed client-side, add them here —
  never hardcode server values in `maps-tool.js`.

**Cross-plugin contract (Hostlinks ↔ HMO):**
- HMO auto-provisioning relies on `do_action('hostlinks_event_created', $event_id)`
  being fired in Hostlinks on both the "Add New Event" and CVENT import save paths.
- If Hostlinks changes its event-creation flow, this hook must be preserved or
  HMO auto-provisioning will silently stop working.

---

## Planned / Future Features

| Feature | Notes |
|---|---|
| Maps — Census API auto-update | Annual auto-pull of PEP data via Census API key + WP Cron (Sync Frequency setting is already stubbed). |
| Maps — Send Count (EmailCraft) | Query EmailCraft API with county list to count contactable email addresses. "Send Count" card on Maps page is already stubbed as "coming soon". Plan doc exists. |
| Maps — D3 county map | Interactive US county map highlighting results. Removed in v1.10.3, deferred. |
| Maps — population-weighted centroid auto-update | CenPop file will need to be replaced after the 2030 Census (~2031). |
