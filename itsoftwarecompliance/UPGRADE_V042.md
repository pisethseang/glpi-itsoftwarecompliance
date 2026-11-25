# v0.4.2 — Findings table refresh

1. Back up the working `itsoftwarecompliance` directory on Docker Desktop/UAT and GLPI database before the upgrade.
2. Replace the plugin files from this ZIP. Keep the installed rules and plugin database table.
3. Restart the GLPI container if necessary, then hard-refresh the browser.
4. Open Tools → IT Software Compliance → Findings. Test All findings, Not detected, Version mismatch, rule/department/hostname filters, paginated results, computer links, and CSV export.
5. Compare a known rule and its result count against v0.4.1; no compliance SQL is changed.
6. If problems appear, restore the v0.4.1 plugin directory without touching the rules table.

Release scope: **Findings page presentation only**. No new tables, queries, scheduled checks, or inventory changes.
