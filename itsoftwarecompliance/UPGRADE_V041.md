# IT Software Compliance v0.4.1: Delete Compliance Rule

Author: Piseth SEANG | Target: GLPI 12.0.x

## Added
- Rules list: per-row **Delete** button next to Edit and Enable/Disable.
- The delete button is shown only to users with GLPI configuration UPDATE permission.
- A confirmation dialog identifies the rule; cancel causes no change.
- Server checks authorization and rule existence before deleting that policy row.
- After delete, browser returns to the Rules list with a success message.

## What is NOT deleted
- GLPI computers, installed software, other compliance rules, inventory history.
- The plugin's database table or GLPI users.

## Warning
Deletion of a compliance rule is permanent and any findings calculated from it will no longer appear. Export or record the rule before deleting it if you may need it later. There is no undo or recycle bin in this version.

## Test
1. Back up the v0.4.0 plugin and the GLPI database.
2. Copy the new itsoftwarecompliance folder into GLPI 12 plugins directory and restart GLPI if needed.
3. Go to Tools > IT Software Compliance > Compliance Rules.
4. Check the Delete button beside an existing rule.
5. Test **Cancel** (rule stays). Then test **OK** on a disposable rule (rule removed).
6. Check Overview and Findings after deletion.

No schema changes are required; saved rules remain intact until deliberately deleted.
