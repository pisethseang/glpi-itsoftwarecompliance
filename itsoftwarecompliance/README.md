**Current test build: v0.4.3 (Piseth SEANG).** The Findings page includes a read-only investigation drawer and inventory-freshness indicators. See `UPGRADE_V043.md`.

> **Version 0.4.0 · Author: Piseth SEANG · GLPI 12 UAT**

## v0.4.0 update

See [UPGRADE_V03.md](UPGRADE_V03.md) for the latest GLPI-themed UI, Action Center, inventory freshness drill-downs and rule filtering. Existing rules and schema are unchanged.

# IT Software Compliance — GLPI 12

Version 0.2.0 (UAT candidate). See `UPGRADE_V02.md` for installation and changes. This is a **separate custom plugin**, installed at:

`/var/www/html/plugins/itsoftwarecompliance`

It does not modify existing GLPI inventory or your other plugins.

## Main functions

1. **Overview:** visual rule-check score (evaluations, not unique PCs), application coverage, inventory freshness, department distribution, and priority findings. Each rule shows distinct computers checked, passing, missing, mismatched, and coverage.
2. **Compliance Rules:** administrators with GLPI `config` / `UPDATE` permission add, edit, disable, or enable rules. A rule defines a software name pattern, exact/prefix/contains match mode, optional **exact** required version, and an optional assigned-user **User Category** scope.
3. **Findings:** filter by application rule, category, finding type, computer name or serial. Paginate at 25 records. Open GLPI computer details or export up to 1,000 matching rows to CSV.

4. **Computers:** new paginated computer browser with optional per-rule compliance status and filters.

Read-only inventory: no agent installation, software installation, ticket creation, or other changes to GLPI core data. Only `glpi_plugin_itsoftwarecompliance_rules` is created and written.

## GLPI version & requirements

- Target: GLPI **12.0.x**, PHP/DB extensions required by GLPI itself.
- This package is **not compatible with GLPI 10**. It uses GLPI 12's typed `CommonGLPI::$rightname` and `DBmysql::doQuery` API.
- Intended for administrator-led evaluation before deployment. No live GLPI 12 Docker container was available during build.

## First install (Docker Desktop)

Back up your GLPI database before installing any plugin. Extract the zip **without changing the folder name**:

```bash
# In the directory containing this ZIP
unzip -oq itsoftwarecompliance_GLPI12_v0.2.0_UAT.zip -d /tmp/glpi-compliance-install

docker cp /tmp/glpi-compliance-install/itsoftwarecompliance \
  glpi:/var/www/html/plugins/

docker restart glpi
```

Open GLPI **Setup > Plugins** (or Marketplace > Installed plugins), click **Install**, then **Enable** for **IT Software Compliance**. Navigate to **Tools > IT Software Compliance**.

If you deploy as a Docker bind-mounted plugin directory, copy the files into the bind-mounted host directory instead of `docker cp`.

Create a simple rule first:

- Rule name: `Cisco Secure Endpoint`
- Software pattern: `Cisco Secure Endpoint`
- Matching: `Contains text` (or exact where appropriate)
- Required version: blank initially
- Department: `All departments`

Then test Overview, Computers, Findings, CSV export, and a second rule with a version requirement.

## What “department” means

The filter uses `glpi_computers.users_id` → `glpi_users.usercategories_id` → `glpi_usercategories` (the **assigned user's GLPI User Category**). That category is not always the same as the computer's Location or Group. Devices without assigned users are included in **All departments**, but not category-specific scopes.

## Coverage interpretation

- **Passing:** A matching software title is recorded, and the exact requested version is recorded when the rule requires one.
- **Not detected:** No matching software installation is recorded for that GLPI computer.
- **Version mismatch:** A matching title is recorded, but none of its installed versions matches the required version.
- **Findings:** Counts of rule evaluations, **not a de-duplicated count of computers** (one computer may fail multiple rules).
- **GLPI Agent:** Depending on how your inventory is collected, an agent may be reporting successfully even if the agent's package does not appear in Software inventory. Verify discovered application titles before enabling an agent compliance rule.
- **Coverage is inventory-based:** a missing installation record can mean incomplete or stale inventory. This plugin does not assess application usage, vulnerability status, or real-time endpoint health.

## Performance decisions

- Overview executes an aggregate SQL query per active rule (at most **30** active rules).
- Uses `EXISTS` subqueries scoped to each GLPI computer and the `items_id` index instead of loading every installation into PHP.
- Computer details are loaded **only on demand**, 25 rows per page.
- CSV export is capped at **1,000** rows per request.
- No JavaScript dependency or polling/cron job.
- Rules are limited to 30 active and 100 displayed to avoid slow, unbounded overview queries.

For large GLPI installations test response time in UAT; highly generic `contains` rules may still be expensive depending on database size and indexing.

## Access control

- View requires both `computer` READ and `software` READ.
- Rule configuration requires `config` UPDATE.
- Computer queries restrict results to `Session::getActiveEntities()`; the current GLPI active-entity selector is respected.
- All inputs are validated and/or escaped; exported values are neutralized against spreadsheet formula injection.
- GLPI 12 handles CSRF with same-origin Fetch Metadata protection; forms use POST.

## Rollback

1. Disable **IT Software Compliance** in Setup > Plugins.
2. Remove only the `itsoftwarecompliance` plugin folder to revert the code. Do **not** uninstall GLPI or modify your existing plugins.
3. The plugin's uninstall hook intentionally preserves its policy-rules table. Export/back up the DB before manual deletion if ever required.

## If you see “An unexpected error occurred”

After reproducing the problem run:

```bash
docker exec glpi sh -c 'tail -100 /var/www/html/files/_log/php-errors.log'
```

Send error lines mentioning `itsoftwarecompliance`. An Apache warning or unrelated IMAP message is not enough to diagnose a plugin failure.

## Files

- `setup.php`, `hook.php`: GLPI plugin metadata, bootstrapping and schema install
- `inc/dashboard.class.php`: GLPI menu registration
- `inc/compliance.class.php`: policy validation, scope, SQL analytics, escaping
- `front/overview.php`, `front/rules.php`, `front/exceptions.php`: pages
- `public/css/compliance.css`: GLPI-compatible styling

## Explicitly not included in v0.1.0

- Ticket creation, AD/GPO deployment, automatic remediation, background scans, inventory sync, warranty/license compliance, custom plugin profile rights, or per-computer eligibility exception lists.
- Future GLPI major versions beyond the 12.0.x series have not been evaluated.

## New in v0.2.0

The advanced Overview is an aggregated **rule evaluation** dashboard, not a unique-device compliance score. It uses small live GLPI queries and does not record historical trends. The Computers page checks at most one rule on 25 visible devices per page. Read `UPGRADE_V02.md` before installing on a live server.
