<?php
include('../../../inc/includes.php');
PluginItsoftwarecomplianceCompliance::checkView();
PluginItsoftwarecomplianceCompliance::ensureInstalled();
$h = ['PluginItsoftwarecomplianceCompliance', 'h'];
$categories = PluginItsoftwarecomplianceCompliance::departments();
$allowedCategories = array_map(static fn($row) => (int)$row['id'], $categories);
$category = max(0, (int)($_GET['department'] ?? 0));
if ($category && !in_array($category, $allowedCategories, true)) { $category = 0; }
$rules = PluginItsoftwarecomplianceCompliance::rules(true);
$ruleId = max(0, (int)($_GET['rule'] ?? 0));
$selected = null;
foreach ($rules as $item) {
    if ((int)$item['id'] === $ruleId) { $selected = $item; break; }
}
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'compliant', 'missing', 'version'], true) || !$selected) { $status = 'all'; }
$inventoryAge = (string)($_GET['inventory'] ?? 'all');
if (!in_array($inventoryAge, ['all', 'recent', 'stale', 'unknown'], true)) { $inventoryAge = 'all'; }
$search = substr(trim((string)($_GET['search'] ?? '')), 0, 80);
$page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
$perPage = 25;
$total = PluginItsoftwarecomplianceCompliance::computerListCount($selected, $category, $search, $status, $inventoryAge);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = PluginItsoftwarecomplianceCompliance::computerListPage($selected, $category, $search, $status, ($page-1)*$perPage, $perPage, $inventoryAge);
$query = ['department'=>$category,'rule'=>$selected ? $selected['id'] : 0, 'status'=>$status, 'search'=>$search, 'inventory'=>$inventoryAge];
Html::header('IT Software Compliance - Computers', $_SERVER['PHP_SELF'], 'tools', 'PluginItsoftwarecomplianceDashboard', 'computers');
PluginItsoftwarecomplianceCompliance::stylesheet();
PluginItsoftwarecomplianceCompliance::header('computers', 'Inspect computers and verify software requirements per device');
?>
<div class="itc-section-head"><div><h3>Computer inventory</h3><p class="itc-muted">Browse devices in your active GLPI entities, or select one compliance rule to view their status.</p></div>
    <span class="itc-section-label"><i class="ti ti-device-desktop"></i> <?= number_format($total) ?> matching computers</span></div>
<form class="itc-toolbar itc-filter-grid" action="<?= $h(PluginItsoftwarecomplianceCompliance::url('computers.php')) ?>" method="get">
    <div><label for="itc-c-dept">Department</label><select name="department" id="itc-c-dept" class="form-select"><?= PluginItsoftwarecomplianceCompliance::categoryOptions($categories,$category) ?></select></div>
    <div><label for="itc-c-rule">Application requirement</label><select name="rule" id="itc-c-rule" class="form-select"><option value="0">All computers (no rule)</option>
        <?php foreach ($rules as $rule): ?><option value="<?= (int)$rule['id'] ?>" <?= $selected && (int)$rule['id']===(int)$selected['id'] ? 'selected' : '' ?>><?= $h($rule['name']) ?></option><?php endforeach; ?>
        </select></div>
    <div><label for="itc-c-status">Rule status</label><select name="status" id="itc-c-status" class="form-select">
        <?php foreach (['all'=>'All statuses','compliant'=>'Compliant','missing'=>'Not detected','version'=>'Wrong version'] as $key=>$label): ?><option value="<?= $key ?>" <?= $status===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
    </select></div>
    <div><label for="itc-c-inventory">Last inventory</label><select name="inventory" id="itc-c-inventory" class="form-select">
        <?php foreach (['all'=>'Any inventory age','recent'=>'Within 30 days','stale'=>'Over 30 days','unknown'=>'No inventory date'] as $key=>$label): ?><option value="<?= $h($key) ?>" <?= $inventoryAge===$key?'selected':'' ?>><?= $h($label) ?></option><?php endforeach; ?>
    </select></div>
    <div><label for="itc-c-search">Name or serial</label><input class="form-control" name="search" id="itc-c-search" maxlength="80" value="<?= $h($search) ?>" placeholder="Search hostname or serial"></div>
    <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i> Search</button>
    <a class="btn btn-outline-secondary" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('computers.php')) ?>">Reset</a>
</form>
<?php if ($inventoryAge !== 'all'): ?>
<div class="itc-active-filter"><i class="ti ti-calendar-stats"></i>
    <span>Inventory filter: <strong><?= $h(['recent'=>'Within 30 days','stale'=>'Over 30 days','unknown'=>'No recorded inventory date'][$inventoryAge]) ?></strong></span>
    <a href="<?= $h(PluginItsoftwarecomplianceCompliance::url('computers.php',array_merge($query,['inventory'=>'all','page'=>1]))) ?>">Clear filter <i class="ti ti-x"></i></a>
</div>
<?php endif; ?>
<div class="itc-panel itc-panel-table">
    <div class="itc-panel-head"><div><h3><i class="ti ti-list-details"></i> Matching computers</h3><div class="itc-muted"><?= $selected ? 'Showing only computers eligible for: '.$h($selected['name']) : 'Select an application requirement to calculate software status.' ?></div></div>
        <span class="itc-muted">25 results per page</span></div>
    <?php if (!$rows): ?><div class="itc-empty compact">No computers match the selected filters.</div>
    <?php else: ?><div class="itc-table-wrap"><table class="itc-table"><thead><tr><th>Computer</th><th>Department</th><th>Assigned user</th><th>Last inventory</th><th>Software check</th></tr></thead><tbody>
        <?php foreach ($rows as $row): $verdict=(string)$row['verdict']; $tone=$verdict==='Compliant'?'good':($verdict==='Not detected'?'bad':($verdict==='Version mismatch'?'warn':'neutral')); ?>
        <tr>
            <td><a class="itc-link" href="<?= $h(rtrim((string)$CFG_GLPI['root_doc'],'/') . '/front/computer.form.php?id=' . (int)$row['id']) ?>"><i class="ti ti-device-desktop me-1"></i><?= $h($row['name'] ?: 'Computer #'.$row['id']) ?></a><div class="itc-muted itc-code"><?= $h($row['serial']) ?></div></td>
            <td><?= $h($row['department']) ?></td>
            <td><?= $h($row['assigned_user']) ?></td>
            <td><span class="itc-date"><?= $h($row['last_inventory_update'] ?: 'Unknown / never') ?></span>
                <?php if ($row['days_since_inventory'] !== null): ?><div class="itc-muted"><?= number_format(max(0, (int)$row['days_since_inventory'])) ?> days ago</div><?php endif; ?></td>
            <td><span class="itc-pill <?= $tone ?>"><?= $h($verdict==='Select a rule' ? 'Not evaluated' : $verdict) ?></span></td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
    <div class="itc-paging"><div class="itc-muted">Page <?= number_format($page) ?> / <?= number_format($pages) ?> · <?= number_format($total) ?> computers</div><div class="itc-actions">
        <?php if ($page>1): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('computers.php',array_merge($query,['page'=>$page-1]))) ?>">Previous</a><?php endif; ?>
        <?php if ($page<$pages): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('computers.php',array_merge($query,['page'=>$page+1]))) ?>">Next</a><?php endif; ?>
    </div></div>
</div>
<div class="itc-disclaimer"><i class="ti ti-info-circle"></i><span>Compliance is evaluated against one selected rule and current GLPI inventory. If the rule targets a specific department, computers outside that department are excluded. This plugin does not install or remove software.</span></div>
<?php PluginItsoftwarecomplianceCompliance::footer(); Html::footer(); ?>
