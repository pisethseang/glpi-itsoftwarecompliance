# IT Software Compliance 0.2.0 — GLPI 12 UAT

## New screens

- **Overview:** responsive KPI cards, aggregate rule-check pass rate, application coverage progress bars, count breakdown, inventory freshness, department computer population, and priority findings.
- **Compliance Rules:** original rule editor and enable/disable controls, styled to match GLPI's Tabler/Bootstrap interface.
- **Computers:** new paginated (25 per page), entity-aware computer browser with assigned user, category, last inventory, optional one-rule status, and hostname/serial search.
- **Findings:** previously labeled `Non-Compliant PCs`, now visibly labeled `Findings`. Existing URL `/front/exceptions.php`, rule filter, CSV export, and drill-down preserved. CSV cap clarified to 1,000 rows to match the existing SQL limit.

## Metrics — important distinctions

- **Overall compliance score** = passing rule evaluations / all rule evaluations in selected scope. A device evaluated against four rules contributes up to four checks. This is **not** the percentage of unique compliant computers.
- **Findings** = total not-detected + wrong-version rule evaluations. A computer may be counted more than once.
- **Inventory readiness** reads `glpi_computers.last_inventory_update` (within 30 days / over 30 days / missing). It is **not** the asset modification date, and it is separate from application compliance.
- **Department** is the assigned user's GLPI User Category, not a company HR department or GLPI asset Location.
- **No history**: trends, alerts, schedules and automatic GLPI ticket creation are **not** included. There is no synthetic historical data or background polling.

## Upgrade from v0.1.1

1. Back up the **GLPI database** and the installed `itsoftwarecompliance` plugin directory.
2. Replace only the plugin code with the `itsoftwarecompliance` directory from this ZIP (keep the exact folder name).
3. Restart the GLPI Docker container or clear PHP opcache as appropriate.
4. In GLPI Setup > Plugins, click **Update** if offered, then ensure the plugin is enabled.
5. Log out/in if the new Computers menu item does not appear; GLPI stores menus in the user session.
6. Hard-refresh the browser so it loads `compliance.css?v=020`.
7. Test Overview, Compliance Rules, Computers (with/without rule), Findings, CSV, and department restrictions.

### Docker Desktop example

Run from the directory containing the ZIP:

```bash
mkdir -p /tmp/itc-v02
unzip -oq itsoftwarecompliance_GLPI12_v0.2.0_UAT.zip -d /tmp/itc-v02
docker cp glpi:/var/www/html/plugins/itsoftwarecompliance ./itsoftwarecompliance-v011-backup
docker cp /tmp/itc-v02/itsoftwarecompliance/. glpi:/var/www/html/plugins/itsoftwarecompliance/
docker restart glpi
```

If the plugin directory is bind-mounted, update the host mount, not the in-container copy.

## Database preservation

This version **does not change the schema**. Existing rows in `glpi_plugin_itsoftwarecompliance_rules` are preserved. The install hook is idempotent and creates the rules table only if absent. It does not write to GLPI computer or software inventory.

## Compatibility and verification

- Intended for GLPI 12.0.x (same version bounds as v0.1.1), not GLPI 10.
- PHP source files have been syntax checked, HTML was exercised with a local GLPI API/DB mock, and screenshots were inspected at desktop viewport. This is **not** a live GLPI 12 runtime or MariaDB integration test.
- SQL remains bounded by 30 active rules; all computer lists use database-side pagination. No cron jobs or JavaScript auto-refresh.

## If you see an unexpected error

```bash
docker exec glpi sh -c 'tail -100 /var/www/html/files/_log/php-errors.log'
```

Test in Docker Desktop/UAT before installing on LIVE. To roll back, restore the saved v0.1.1 plugin directory without touching the rules table.
