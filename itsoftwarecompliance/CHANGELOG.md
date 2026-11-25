## 0.4.3 — Advanced Findings Workspace (GLPI 12 UAT)

- Rebuilt only the Findings page into a focused investigation workspace.
- Added policy context, inventory confidence (Recent / Stale / Unknown), and suggested manual follow-up steps.
- Added an accessible client-side Inspect drawer using data already in the current page (no AJAX or additional SQL).
- Added a compact-row toggle, responsive filters, improved GLPI-colored status labels, and clearer CSV and pagination controls.
- Preserved findings query logic, entity restrictions, permissions, filters, CSV export (1,000-row cap), 25-row server pagination, and rule deletion.
- Author remains **Piseth SEANG**. No database migrations.

# IT Software Compliance v0.4.2 — Findings Table UI (GLPI 12)

- Rebuilt only **Findings** as a GLPI/Tabler-styled, responsive data table with numbered rows, asset links, readable statuses, and an Open action.
- Added compact summary cards, quick finding-status tabs, clearer filters, empty state, and page indicators.
- Preserved the existing server-side rule/department/search/status filters, 25-row pagination, CSV export (1,000-record cap), and read-only entity-aware queries.
- Updated the CSS asset cache version to 042; all styling is scoped to `.itc-findings-v42`.
- Author: **Piseth SEANG**. No database migrations or permission changes.

# IT Software Compliance v0.4.1 - Rules Delete Action (GLPI 12)

- Added a red **Delete** button to each Compliance Rules row, visible only to users with configuration UPDATE rights.
- Browser confirmation names the selected rule and warns that policy deletion cannot be undone.
- Server re-checks permissions, confirmation flag, and rule ID; deletion affects only the plugin-owned rules table.
- Redirects after deletion to prevent accidental duplicate POST submissions.
- No database migration, new table, or GLPI inventory changes.
- Author: **Piseth SEANG**.

## 0.4.0 — GLPI Native Experience (UAT)

- Author changed to **Piseth SEANG** in `setup.php` and plugin documentation.
- New Overview presentation with four concise KPIs, policy score, searchable rule coverage, inventory freshness drill-downs, priority findings, and department inventory.
- Rules page now opens the editor only when **Add rule** or **Edit** is selected; the existing rule list is easier to navigate.
- Added read-only **Rule Preview**: shows at most 12 matching software names in accessible entities and calculates checked, passing, missing, and version-mismatch computers on explicit request.
- Added bounded, 350 ms delayed software autocomplete (three characters minimum) using current GLPI inventory; choosing a title switches to exact-name matching.
- Added GLPI-themed responsive styles and keyboard-friendly controls; CSS/JS scoped to this plugin.
- Preserved `plugin_itsoftwarecompliance_install()` and the existing policy table. No schema migration.
- No scheduled jobs, automatic ticket creation, or live endpoint agents added.

# Changelog

## 0.3.0 (2026-11-21) — GLPI-native interface and operational drill-downs
- Updated GLPI color/theme variables and streamlined scorecards, forms and navigation.
- Added an IT Action Center with direct links to stale and never-inventoried computers.
- Added inventory freshness filters on the Computers page (within 30d, over 30d, unknown).
- Added days-since-inventory display for matching computer rows.
- Added policy search, status filters and Findings shortcuts to the Rules page.
- Sorted application coverage by affected computers to put urgent findings first.
- Maintained existing schema, rules, permissions, queries and report exports.

## 0.2.0 (2026-11-20) — GLPI-style advanced dashboard
- Redesigned Overview with compact GLPI/Tabler card styling and responsive layout.
- Added rule-check pass-rate, app coverage, status breakdown, inventory freshness and department distribution.
- Added a dedicated, server-paginated Computers page with optional per-rule device checks.
- Restyled Compliance Rules and Findings navigation; preserved existing rules and reporting logic.
- Clarified bounded Findings CSV export limit as 1,000 rows, consistent with the existing query limit.
- Updated CSS cache version to v020; no database schema migrations.
- No historical trends, scheduling, or automatic tickets in this release.

## 0.1.1 (2026-11-20) — GLPI 12 stylesheet fix
- Moved stylesheet to GLPI 12-supported `public/css` directory.
- Updated plugin-scoped GLPI-compatible CSS.
- No database or reporting logic changes.

## 0.1.0 (2026-11-20) — initial UAT candidate
- GLPI 12.0.x plugin metadata and three-page navigation.
- Plugin-owned rule table and entity-restricted software compliance checks.
- Exact / prefix / contains application matching and optional exact required version.
- Paginated missing / wrong-version device list and CSV export.
