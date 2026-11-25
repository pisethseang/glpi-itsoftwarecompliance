<?php
include('../../../inc/includes.php');
PluginItsoftwarecomplianceCompliance::checkView();
PluginItsoftwarecomplianceCompliance::ensureInstalled();
$h = ['PluginItsoftwarecomplianceCompliance', 'h'];
$categories = PluginItsoftwarecomplianceCompliance::departments();
$category = max(0, (int)($_GET['department'] ?? 0));
$allowed = array_map(static fn($row) => (int)$row['id'], $categories);
if ($category && !in_array($category, $allowed, true)) { $category = 0; }
$rules = PluginItsoftwarecomplianceCompliance::rules(true);
$items = [];
$checks = ['checked'=>0,'compliant'=>0,'missing'=>0,'wrong_version'=>0];
foreach ($rules as $rule) {
    $stats = PluginItsoftwarecomplianceCompliance::ruleStats($rule, $category);
    $items[] = ['rule'=>$rule,'stats'=>$stats];
    foreach ($checks as $key=>$_) { $checks[$key] += $stats[$key]; }
}
$computers = PluginItsoftwarecomplianceCompliance::totalComputers($category);
$inventory = PluginItsoftwarecomplianceCompliance::inventoryReadiness($category);
$departments = PluginItsoftwarecomplianceCompliance::departmentPopulation($category, 6);
$findings = $checks['missing'] + $checks['wrong_version'];
$rate = $checks['checked'] ? round(100 * $checks['compliant'] / $checks['checked'], 1) : null;
$ranked = $items;
usort($ranked, static function ($a, $b) {
    return (($b['stats']['missing'] + $b['stats']['wrong_version']) <=> ($a['stats']['missing'] + $a['stats']['wrong_version']))
        ?: strcmp((string)$a['rule']['name'], (string)$b['rule']['name']);
});
$priority = array_slice(array_filter($ranked, static fn($entry) => ($entry['stats']['missing'] + $entry['stats']['wrong_version']) > 0), 0, 4);
$aged = $inventory['stale'] + $inventory['unknown'];
Html::header('IT Software Compliance', $_SERVER['PHP_SELF'], 'tools', 'PluginItsoftwarecomplianceDashboard', 'overview');
PluginItsoftwarecomplianceCompliance::stylesheet();
PluginItsoftwarecomplianceCompliance::header('overview', 'Policy-driven software compliance and inventory readiness');
?>
<div class="itc-v4-pagehead">
  <div><span class="itc-v4-kicker">SOFTWARE ASSURANCE</span><h3>Compliance overview</h3><p>Live inventory checks against your approved software requirements.</p></div>
  <div class="itc-v4-page-actions">
    <a class="btn btn-outline-primary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php',['department'=>$category])) ?>"><i class="ti ti-alert-triangle me-1"></i> View findings</a>
    <?php if (PluginItsoftwarecomplianceCompliance::canManage()): ?><a class="btn btn-primary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php',['new'=>1])) ?>"><i class="ti ti-plus me-1"></i> New rule</a><?php endif; ?>
  </div>
</div>
<form class="itc-toolbar itc-v4-filter" method="get" action="<?= $h(PluginItsoftwarecomplianceCompliance::url('overview.php')) ?>">
  <div class="itc-control"><label for="itc-overview-dept">Department (User Category)</label><select id="itc-overview-dept" name="department" class="form-select"><?= PluginItsoftwarecomplianceCompliance::categoryOptions($categories,$category) ?></select></div>
  <button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1"></i> Apply</button>
  <a class="btn btn-outline-secondary" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('overview.php')) ?>">Reset</a>
  <span class="itc-v4-scope"><i class="ti ti-lock-check"></i> Respects your active GLPI entities</span>
</form>
<section class="itc-v4-metrics" aria-label="Overview statistics">
  <div class="itc-v4-metric"><div class="itc-v4-metric-label">Rule compliance <i class="ti ti-shield-check"></i></div><strong><?= $rate===null?'—':number_format($rate,1).'%' ?></strong><small><?= number_format($checks['compliant']) ?> of <?= number_format($checks['checked']) ?> checks passing</small></div>
  <div class="itc-v4-metric"><div class="itc-v4-metric-label">Computers in scope <i class="ti ti-device-desktop"></i></div><strong><?= number_format($computers) ?></strong><small>Across active GLPI entities</small></div>
  <div class="itc-v4-metric"><div class="itc-v4-metric-label">Passing checks <i class="ti ti-circle-check"></i></div><strong><?= number_format($checks['compliant']) ?></strong><small>Successful policy evaluations</small></div>
  <a class="itc-v4-metric itc-v4-metric-link" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php',['department'=>$category])) ?>"><div class="itc-v4-metric-label">Findings <i class="ti ti-alert-triangle"></i></div><strong><?= number_format($findings) ?></strong><small>Open affected computers <i class="ti ti-arrow-up-right"></i></small></a>
</section>
<div class="itc-v4-primary-grid">
  <section class="itc-v4-panel itc-v4-score-panel">
    <div class="itc-v4-panel-head"><div><h3>Policy compliance</h3><p>Passing rule evaluations</p></div><span class="itc-v4-chip"><?= count($rules) ?> active rule<?= count($rules)===1?'':'s' ?></span></div>
    <div class="itc-v4-score-content">
      <div class="itc-v4-gauge" style="--itc-progress:<?= $rate===null?0:max(0,min(100,$rate)) ?>%;"><div class="itc-v4-gauge-center"><strong><?= $rate===null?'—':number_format($rate,1).'%' ?></strong><small>Pass rate</small></div></div>
      <div class="itc-v4-score-key">
        <div><span class="itc-dot green"></span><span>Passing</span><strong><?= number_format($checks['compliant']) ?></strong></div>
        <div><span class="itc-dot red"></span><span>Not detected</span><strong><?= number_format($checks['missing']) ?></strong></div>
        <div><span class="itc-dot amber"></span><span>Version mismatch</span><strong><?= number_format($checks['wrong_version']) ?></strong></div>
        <p>One computer can be evaluated against multiple rules. This is not the percentage of unique compliant computers.</p>
      </div>
    </div>
  </section>
  <section class="itc-v4-panel itc-v4-inventory-panel">
    <div class="itc-v4-panel-head"><div><h3>Inventory health</h3><p>Last successful inventory · 30-day threshold</p></div><i class="ti ti-clock-check"></i></div>
    <div class="itc-v4-health">
      <?php foreach ([['recent','Within 30 days','green'],['stale','Over 30 days','amber'],['unknown','No inventory date','red']] as $group): [$key,$label,$tone]=$group; $pct=$computers ? min(100,round($inventory[$key]*100/$computers,1)) : 0; ?>
      <a class="itc-v4-health-row" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('computers.php',['department'=>$category,'inventory'=>$key])) ?>"><span class="itc-dot <?= $tone ?>"></span><span><?= $label ?></span><strong><?= number_format($inventory[$key]) ?></strong><i class="ti ti-chevron-right"></i><span class="itc-v4-health-track"><span class="<?= $tone ?>" style="width:<?= $pct ?>%"></span></span></a>
      <?php endforeach; ?>
    </div>
    <?php if ($aged): ?><p class="itc-v4-inline-note"><i class="ti ti-info-circle"></i> <?= number_format($aged) ?> computer(s) have stale or unknown inventory dates. Verify inventory freshness before interpreting missing software.</p><?php else: ?><p class="itc-v4-inline-note">Inventory timestamps are read from GLPI computer records.</p><?php endif; ?>
  </section>
</div>
<section class="itc-v4-panel itc-v4-coverage-panel">
  <div class="itc-v4-panel-head"><div><h3>Required application coverage</h3><p>Rule-by-rule software coverage, ranked by findings</p></div><a href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>" class="itc-panel-link">Manage rules <i class="ti ti-arrow-right"></i></a></div>
  <div class="itc-v4-searchrow"><label class="itc-v4-searchbox"><i class="ti ti-search"></i><input type="search" id="itc-v4-coverage-search" placeholder="Search application rules..." aria-label="Search application rules"></label><span class="itc-muted">Showing <?= count($items) ?> active rules</span></div>
  <?php if (!$items): ?><div class="itc-empty"><i class="ti ti-list-check"></i><strong>No compliance rules configured</strong><span>Add a rule to begin checking installed software.</span><?php if (PluginItsoftwarecomplianceCompliance::canManage()): ?><a class="btn btn-primary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php',['new'=>1])) ?>">Add first rule</a><?php endif; ?></div>
  <?php else: ?><div id="itc-v4-coverage-results" class="itc-v4-coverage-list">
    <?php foreach ($ranked as $entry): $rule=$entry['rule'];$st=$entry['stats'];$percent=$st['checked']?round(100*$st['compliant']/$st['checked'],1):0;$bad=$st['missing']+$st['wrong_version']; ?>
      <div class="itc-v4-coverage-row" data-rule-name="<?= $h(strtolower((string)$rule['name'].' '.(string)$rule['software_pattern'])) ?>">
        <div class="itc-v4-coverage-main"><div class="itc-v4-rule-title"><span class="itc-v4-rule-icon"><i class="ti ti-apps"></i></span><div><strong><?= $h($rule['name']) ?></strong><small><?= $h(PluginItsoftwarecomplianceCompliance::categoryName((int)$rule['usercategories_id'],$categories)) ?></small></div></div><div class="itc-v4-coverage-number"><?= $st['checked']?number_format($percent,1).'%':'—' ?></div></div>
        <div class="itc-v4-coverage-progress"><span style="width:<?= $percent ?>%" class="<?= $percent>=90?'good':($percent>=70?'warn':'bad') ?>"></span></div>
        <div class="itc-v4-coverage-detail"><span><?= number_format($st['compliant']) ?> / <?= number_format($st['checked']) ?> rule checks passing</span><a href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php',['rule'=>(int)$rule['id'],'department'=>$category])) ?>"><?= number_format($bad) ?> finding<?= $bad===1?'':'s' ?> <i class="ti ti-arrow-up-right"></i></a></div>
      </div>
    <?php endforeach; ?>
  </div><p id="itc-v4-no-results" class="itc-v4-no-results" hidden>No application rules match your search.</p><?php endif; ?>
</section>
<div class="itc-v4-bottom-grid">
  <section class="itc-v4-panel"><div class="itc-v4-panel-head"><div><h3>Priority findings</h3><p>Rules with the most affected computers</p></div><a class="itc-panel-link" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php',['department'=>$category])) ?>">All findings <i class="ti ti-arrow-right"></i></a></div>
    <?php if (!$priority): ?><div class="itc-empty compact">No findings for active rules in this scope.</div><?php else: ?><div class="itc-v4-priorities"><?php foreach ($priority as $entry): $rule=$entry['rule'];$st=$entry['stats'];$total=$st['missing']+$st['wrong_version']; ?><a href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php',['rule'=>$rule['id'],'department'=>$category])) ?>"><span class="itc-v4-alert-icon"><i class="ti ti-alert-triangle"></i></span><span><strong><?= $h($rule['name']) ?></strong><small><?= number_format($st['missing']) ?> not detected · <?= number_format($st['wrong_version']) ?> wrong version</small></span><b><?= number_format($total) ?></b><i class="ti ti-chevron-right"></i></a><?php endforeach; ?></div><?php endif; ?>
  </section>
  <section class="itc-v4-panel"><div class="itc-v4-panel-head"><div><h3>Computer distribution</h3><p>By assigned user's GLPI User Category</p></div></div>
    <?php if (!$computers): ?><div class="itc-empty compact">No computers in the selected scope.</div><?php else: ?><div class="itc-department-list"><?php foreach ($departments as $row): $pop=(int)$row['computers'];$pct=$computers?min(100,100*$pop/$computers):0; ?><div class="itc-dept-row"><span><?= $h($row['department']) ?></span><div class="itc-progress-line"><span class="blue" style="width:<?= $pct ?>%"></span></div><strong><?= number_format($pop) ?></strong></div><?php endforeach; ?></div><?php endif; ?>
  </section>
</div>
<div class="itc-disclaimer"><i class="ti ti-info-circle"></i><span><strong>Inventory-based reporting:</strong> “Not detected” means there is no matching GLPI software installation record. This does not prove the application is absent. Version matching is exact, and one computer can contribute more than one rule evaluation.</span></div>
<?php
$script = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/itsoftwarecompliance/js/overview.js?v=040';
echo '<script defer src="' . $h($script) . '"></script>';
PluginItsoftwarecomplianceCompliance::footer();
Html::footer();
