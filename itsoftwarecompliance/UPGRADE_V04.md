# IT Software Compliance v0.4.0 — GLPI 12 UAT

**Author:** Piseth SEANG

## Existing installation

1. Back up the entire GLPI database and the current `itsoftwarecompliance` plugin directory.
2. Extract the ZIP with the folder named exactly `itsoftwarecompliance`.
3. Replace the plugin code in your GLPI 12 test environment; for a bind-mounted Docker volume, update the host directory instead.
4. Restart the GLPI container to clear cached code. Under **Setup > Plugins**, update if GLPI prompts.
5. Force reload the browser (`Cmd + Shift + R` on macOS).

**No new plugin database table or column is introduced.** The existing `glpi_plugin_itsoftwarecompliance_rules` records remain intact. Do not uninstall an existing copy as a way to upgrade it.

## Verification plan

- Open **Overview**; check compliance score, counts, application search, and clickable inventory health counts.
- Open **Rules**, confirm saved rules; choose **Add rule** to display the new editor.
- Type at least three characters in software name to see inventory suggestions; choose one to use exact matching.
- Click **Preview rule** and check up to 12 matching titles and the evaluation counts. No data is changed by a preview.
- Save a test rule, verify findings/computers, edit and disable/enable it (with administrator permission).
- Check **Computers** and **Findings** still filter and paginate, and test CSV export.
- Use an account without `config` UPDATE to ensure the rule editor and preview are inaccessible.
- Confirm GLPI light and dark themes, and check browser network requests to `/plugins/itsoftwarecompliance/js/...` and `/plugins/itsoftwarecompliance/front/rule_preview.php`.

## Performance and security

- The Overview computes rule aggregates exactly as v0.3.0 and filters them in the browser. The overview search adds **no SQL queries**.
- Autocomplete waits 350 ms, requires at least three characters, cancels stale requests and returns up to 12 distinct installed titles within the active GLPI entity scope.
- Exact compliance counts for a *draft* rule are only computed after an authorized user clicks Preview.
- Rule Preview requires GLPI software/computer READ plus configuration UPDATE; GET is read-only, and input is validated and SQL-escaped.
- The preview lists a maximum of 12 software names; the evaluated counts include all matching installations in the selected scope, not just those names.
- On large GLPI datasets, very broad contains-text searches may still be slower. Prefer a full product title with exact matching where practical.

## Rollback

Restore the previous v0.3.0 plugin code directory and restart GLPI. No database rollback is required for the UI update unless you intentionally changed rules during testing.

## Limits

- UAT code and local syntax / mocked-SQL checks only; not yet tested inside the user's running GLPI 12 container.
- The Rule Preview UI uses existing GLPI-installed software records; it cannot prove an application is actually absent.
- No history, automated remediation, ticket generation or scheduled scans are included in this version.
