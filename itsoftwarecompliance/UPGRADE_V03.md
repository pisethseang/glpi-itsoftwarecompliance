# IT Software Compliance v0.3.0 — upgrade notes

Target: **GLPI 12.0.x**. Upgrade from the working **v0.2.0** package. This is a UAT build and needs runtime verification against your own GLPI 12 instance.

## What's new

- Refreshed styling to inherit GLPI's primary color, surface and text variables; compact native-looking cards and tabs.
- New **IT action center** uses already-calculated counts, with direct links to computers whose last inventory is older than 30 days and computers with no recorded inventory date.
- New **inventory-age filter** on the Computers page: Any, Within 30 days, Over 30 days, No inventory date. Works with existing department, rule, rule-status and hostname filters.
- The computer list displays days since last inventory alongside the timestamp.
- **Rules** page now supports a name/pattern search, Active/Disabled state filter, and a View Findings link for each enabled rule.
- Application coverage is sorted by affected counts, so the most urgent issues appear first.
- A data-quality reminder flags potentially stale findings without labeling devices automatically noncompliant.

## Not changed

- Plugin database schema and existing rules. No migration or destructive database action.
- Permission requirements, entity scoping, software name matching and exact version check logic.
- Bounded queries and server-side pagination. The new Overview cards reuse existing aggregates and do not issue extra per-rule or per-computer SQL queries.
- CSV export, installation behavior and original four pages.
- No automatic remediation, scheduling, ticket creation or historical trends.

## Upgrade

1. Back up your GLPI database and current `itsoftwarecompliance` plugin folder.
2. Replace the files in `/var/www/html/plugins/itsoftwarecompliance/` with this ZIP's `itsoftwarecompliance/` folder, or update the bind-mounted host folder instead.
3. Restart GLPI and hard-refresh the browser to clear stylesheet caches.
4. Visit **Setup > Plugins** and use Update if offered; do not uninstall, since existing rule data should remain intact.
5. In **Overview**, click Stale inventory and No inventory date. Check the computers shown match GLPI's last inventory timestamps.
6. In **Compliance Rules**, search and filter policies, then click Findings for an active rule.
7. Verify **Findings** and CSV export continue to work.

## Rollback

Restore the backed-up v0.2.0 plugin folder and restart the GLPI container. There is no v0.3.0 schema migration to roll back.

## Data note

“Not detected” means not present in current GLPI inventory, not proof of missing software. Freshness uses `glpi_computers.last_inventory_update`, **not** `date_mod`. User Category is the department source. The pass rate measures **rule checks**, not unique computers.
