
# IT Software Compliance for GLPI 12

**Version:** 0.4.3 (UAT)  
**Author:** Piseth SEANG  
**GLPI compatibility:** 12.0.0–12.0.99  
**License declared by plugin:** GPLv3+

IT Software Compliance is a custom GLPI plugin for checking recorded software installations against IT-defined application policies. It helps administrators understand application coverage, review missing or mismatched installations, and identify computers with stale inventory data—all within GLPI.

> **Important:** Results reflect GLPI's stored inventory, not a live endpoint scan. “Not detected” does not prove an application is absent. Investigate missing or stale inventory before taking remediation action.

## Features

| Area | Capabilities |
| --- | --- |
| **Overview** | Rule-check pass rate, computers in scope, passing evaluations, findings, inventory freshness, and application coverage ordered by findings. |
| **Compliance Rules** | Create, preview, edit, enable/disable, search, filter, or delete software policy rules. |
| **Computers** | Browse computers and assigned users; filter by department, policy, result, computer name/serial, and last inventory age. |
| **Findings** | Investigate software not detected or exact-version mismatches; filter, inspect, open computer records, and export CSV. |

### Highlights of v0.4.3

- Refreshed **Findings workspace** with a summary of the current policy and filtered results.
- Quick filters for **all findings**, **not detected**, and **version mismatch**.
- Inventory confidence labels based on each computer's last successful inventory: **Recent** (0–30 days), **Stale** (over 30 days), or **Unknown** (no date).
- Read-only **Inspect** panel with a direct link to the computer in GLPI; it reuses loaded report rows rather than issuing an additional database request.
- Compact-table toggle, responsive styling, accessible keyboard/focus behavior, and **Esc** to close the Inspect panel.
- Existing rule-evaluation queries and plugin database schema are unchanged in the v0.4.3 upgrade.

## How compliance is evaluated

A rule consists of:

| Field | Meaning |
| --- | --- |
| **Name** | Friendly policy name, such as `Cisco Secure Endpoint`. |
| **Software name / pattern** | Name matched against software recorded in GLPI. |
| **Matching** | **Exact name**, **Starts with**, or **Contains text**. |
| **Approved version** | Optional **exact** version string. Leave empty to accept any detected version. |
| **Department** | Optional assigned-user **GLPI User Category**, or all departments. |
| **Status** | Enabled or disabled. Only active rules are evaluated. |

**Rule outcomes:**

- **Passing / Compliant:** GLPI records a matching software installation, with the exact required version if specified.
- **Not detected:** No matching installation record is found for the computer.
- **Version mismatch:** The software name matches but no installed version matches the rule's required version.

**Important counting rule:** the Overview pass rate is calculated from **rule evaluations**, not unique computers. One computer can appear in multiple checks or findings.

**Department scope:** “Department” means the assigned computer user's **User Category** (`glpi_computers.users_id` → `glpi_users.usercategories_id`). It is not necessarily the asset's GLPI location or group. Computers without assigned users can be included when the scope is **All departments**.

## Requirements

- GLPI **12.0.x**, within the plugin's declared range **12.0.0–12.0.99**.
- PHP, database, and other dependencies required by the installed GLPI 12 server.
- Permission to install GLPI plugins for setup.
- Existing computer and installed-software records in GLPI for meaningful results.

**Not compatible with GLPI 10.** This build is intended for UAT validation before production use.

## Installation (Docker Desktop / Docker)

The installed plugin directory must be named exactly **`itsoftwarecompliance`**.

### 1. Back up GLPI

Back up the GLPI database and, if upgrading, the existing plugin directory. A database backup is important because configured rules are stored in a plugin-owned table.

For an existing Docker container named `glpi`, you can back up the plugin files:

```bash
mkdir -p ./glpi-plugin-backups
docker cp glpi:/var/www/html/plugins/itsoftwarecompliance ./glpi-plugin-backups/itsoftwarecompliance-before-update
```

Skip the `docker cp` backup command if the plugin has not yet been installed.

### 2. Get the source

Download the repository ZIP from:

https://github.com/pisethseang/glpi-itsoftwarecompliance

Extract the download and find the nested **`itsoftwarecompliance/`** directory. Do not copy the outer repository folder into GLPI's plugin directory.

### 3. Install the directory

From a terminal in the directory containing `itsoftwarecompliance/`:

```bash
docker cp ./itsoftwarecompliance glpi:/var/www/html/plugins/
docker restart glpi
```

For an **upgrade**, replace the contents of `/var/www/html/plugins/itsoftwarecompliance/` with the updated plugin files, retaining the installed plugin directory name. If `/var/www/html/plugins` is bind-mounted, update the **host-mounted directory** rather than relying on `docker cp`.

> The shown path assumes your GLPI document root is `/var/www/html`. Confirm it in your container if the image uses a different layout.

### 4. Enable in GLPI

1. Open **Setup → Plugins** (or the installed-plugins page in your GLPI deployment).
2. Find **IT Software Compliance**.
3. On first installation, select **Install**, then **Enable**.
4. On upgrade, apply an **Update** action if GLPI offers one; do **not** uninstall merely to upgrade.
5. Open **Tools → IT Software Compliance**.
6. Hard-refresh the browser if old styles are cached.

## Quick start

1. Go to **Tools → IT Software Compliance → Compliance Rules**.
2. Select **Add rule**.
3. Enter a policy name, for example `Cisco Secure Endpoint`.
4. Enter a software name/pattern that matches the naming used by your GLPI inventory.
5. Choose a matching mode. Leave **Approved version** blank during an initial discovery test.
6. Select **All departments** or a specific assigned-user category.
7. Use **Preview rule** to inspect likely matches without saving changes.
8. Save and enable the rule, then review **Overview**, **Computers**, and **Findings**.

To check a fixed release requirement later, edit the rule and enter the **exact** recorded version.

## Permissions and data protection

| Action | Required GLPI permission |
| --- | --- |
| View the plugin, computers, results, and findings | `computer` **READ** and `software` **READ** |
| Create, edit, enable/disable, or permanently delete rules | `config` **UPDATE** |

Computer data is restricted to **active permitted GLPI entities**. Inputs are validated/escaped in the plugin, and CSV exports are protected against spreadsheet-formula injection.

The plugin reads GLPI computer and software inventory; it **does not install or uninstall applications**, initiate scans, modify GLPI computer/software records, create tickets, or perform automatic remediation.

The plugin creates and modifies only its own rules table on normal rule-management operations:

```text
glpi_plugin_itsoftwarecompliance_rules
```

**Deleting a rule is permanent.** Removing a rule does not delete GLPI computers or software; however, any findings derived from that rule will no longer appear. The plugin uninstall hook is designed to **preserve** the rules table rather than drop it automatically.

## Filters, freshness, and exports

- **Findings:** select a rule, filter by assigned-user category, finding type, computer name, or serial; investigate results in the **Inspect** panel or open the GLPI computer record.
- **Computers:** optionally select one rule and filter by result; filter inventory freshness as **within 30 days**, **over 30 days**, or **no inventory date**.
- **Inventory freshness:** uses the computer's `last_inventory_update` value, not a real-time agent health check.
- **Pagination:** 25 computers/findings per page.
- **CSV export:** up to **1,000 matching Findings rows** per request.
- **Rule limits:** at most **30 active** rules are evaluated in the Overview; the Rules list displays up to **100** rules.

For large inventories, test the query response time in UAT. Broad **Contains text** policies can require more work than narrow application-name matches.

## Upgrade notes

| Version | Change |
| --- | --- |
| **0.4.3** | Findings investigation workspace, freshness indicators, Inspect panel, compact mode, accessibility improvements. |
| **0.4.2** | Findings table presentation refresh. |
| **0.4.1** | Confirmed, permanent rule deletion for authorized administrators. |
| **0.4.0** | Refreshed GLPI-themed presentation and policy-management workflow. |
| **0.3.0** | Inventory-age filters, inventory action links, rule search/state filters, freshness awareness. |
| **0.2.0** | Expanded Overview analytics and paginated Computers view. |

See the versioned `UPGRADE_V*.md` files in the plugin directory for detailed upgrade and UAT notes.

## UAT verification checklist

- [ ] Plugin appears in GLPI with version **0.4.3** and opens under **Tools**.
- [ ] A reader with the two required READ permissions can see results but cannot manage rules.
- [ ] An authorized administrator can preview, add, edit, disable, and re-enable a disposable rule.
- [ ] **Overview** counts match the expected rule checks, not a count of unique computers.
- [ ] **Computers** filters and inventory-age categories return expected records.
- [ ] **Findings** tabs, filters, Inspect panel, computer links, and pagination work.
- [ ] Findings CSV export respects the current filters and the 1,000-row limit.
- [ ] Deleting a disposable test rule requires confirmation and leaves GLPI inventory untouched.
- [ ] Active-entity changes restrict visible data as expected.
- [ ] Existing saved rules remain after the upgrade.

## Troubleshooting

**Plugin missing under Tools:** check that it is enabled, the directory is named `itsoftwarecompliance`, and the signed-in user has both required READ permissions.

**Rules table missing:** finish plugin installation in **Setup → Plugins**. Do not manually create a replacement table without checking the plugin's installation hook.

**All computers show Not detected:** first verify that software installations are present in GLPI, the software-name pattern matches recorded titles, and the inventory is recent enough to trust.

**Unexpected error:** inspect your GLPI PHP error log. For the Docker layout used above:

```bash
docker exec glpi sh -c 'tail -100 /var/www/html/files/_log/php-errors.log'
```

Check your actual GLPI log path if it differs. Look for errors naming `itsoftwarecompliance`.

**Old styling after update:** refresh the browser cache and verify you replaced the correct Docker/bind-mounted files.

## Rollback

1. Back up your current database and plugin directory.
2. Disable the plugin in GLPI if the update is malfunctioning.
3. Restore the previous known-good `itsoftwarecompliance/` directory.
4. Restart the GLPI container and verify the pages again.
5. Preserve `glpi_plugin_itsoftwarecompliance_rules` to keep configured rules. Do not delete the rules table as part of a routine rollback.


