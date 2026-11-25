# IT Software Compliance 0.4.3 — Findings Workspace

Author: Piseth SEANG. GLPI 12.0.x UAT build.

## Scope
Only the Findings UI was redesigned (`front/exceptions.php`, `public/css/compliance.css`, `public/js/findings.js`). The stylesheet version was bumped to `043`; the plugin metadata version is `0.4.3`. All GLPI SQL and compliance calculations are unchanged.

## Improvements
- Policy summary and filtered result counts
- GLPI-style compact filter form and finding-type quick tabs
- Inventory confidence using the last successful inventory date already in each result (Recent: 0–30 days; Stale: >30 days; Unknown: no recorded inventory date)
- Manual suggested next action (refresh inventory first if stale/unknown)
- Accessible Inspect panel, opening without another database request, with a direct GLPI computer link
- Compact-row display toggle, responsive tables, accessible focus handling and escape-to-close

## Installation and validation
1. Back up the existing plugin directory and database before updating.
2. Replace only the `itsoftwarecompliance` directory with the contents of this ZIP. For bind mounts, replace files in the host mount.
3. Restart the GLPI container, then refresh the browser cache.
4. Verify Overview, Compliance Rules (including Delete), Computers, Findings status tabs, Inspect panel, CSV export and pagination.

## Performance and safety
- No additional database queries or tables.
- Inspect panel uses only rows returned by the existing paginated report.
- Filters are server-side and only execute on Apply.
- Export retains the existing 1,000-row cap and spreadsheet formula escaping.
- The inventory confidence label uses the date in each row, not a live agent check.
- `Not detected` is not definitive evidence that software is absent.

## Known limits
This package was not executed against your actual Docker GLPI 12 instance. Verify on Mac Docker Desktop/UAT before LIVE deployment.
