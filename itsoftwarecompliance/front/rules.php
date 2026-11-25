<?php
include('../../../inc/includes.php');
PluginItsoftwarecomplianceCompliance::checkView();
PluginItsoftwarecomplianceCompliance::ensureInstalled();
$h = ['PluginItsoftwarecomplianceCompliance', 'h'];
$canManage = PluginItsoftwarecomplianceCompliance::canManage();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    PluginItsoftwarecomplianceCompliance::checkManage();
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save') {
            PluginItsoftwarecomplianceCompliance::saveRule($_POST);
            $success = 'Compliance rule saved.';
        } elseif ($action === 'toggle') {
            PluginItsoftwarecomplianceCompliance::setEnabled((int)($_POST['id'] ?? 0), (int)($_POST['enabled'] ?? 0) === 1);
            $success = 'Compliance rule status updated.';
        } elseif ($action === 'delete') {
            if ((string)($_POST['confirm_delete'] ?? '') !== '1') {
                throw new InvalidArgumentException('Deletion was not confirmed.');
            }
            PluginItsoftwarecomplianceCompliance::deleteRule((int)($_POST['id'] ?? 0));
            // PRG: prevent a refresh from accidentally resubmitting a destructive POST.
            header('Location: ' . PluginItsoftwarecomplianceCompliance::url('rules.php', ['deleted' => 1]), true, 303);
            exit;
        } else {
            throw new InvalidArgumentException('Unknown action.');
        }
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$categories = PluginItsoftwarecomplianceCompliance::departments();
$rules = PluginItsoftwarecomplianceCompliance::rules();
$ruleSearch = substr(trim((string)($_GET['q'] ?? '')), 0, 80);
$ruleState = (string)($_GET['state'] ?? 'all');
if (!in_array($ruleState, ['all', 'active', 'disabled'], true)) { $ruleState = 'all'; }
$visibleRules = array_values(array_filter($rules, static function ($rule) use ($ruleSearch, $ruleState) {
    if ($ruleState === 'active' && !(int)$rule['is_active']) { return false; }
    if ($ruleState === 'disabled' && (int)$rule['is_active']) { return false; }
    return $ruleSearch === '' || stripos((string)$rule['name'] . ' ' . (string)$rule['software_pattern'], $ruleSearch) !== false;
}));
$editing = $canManage ? PluginItsoftwarecomplianceCompliance::rule(max(0, (int)($_GET['edit'] ?? 0))) : null;
$showEditor = $canManage && ($editing !== null || isset($_GET['new']) || $error !== '');
$draft = $editing ?? (($error !== '' && ($_POST['action'] ?? '') === 'save') ? $_POST : []);
Html::header('IT Software Compliance - Rules', $_SERVER['PHP_SELF'], 'tools', 'PluginItsoftwarecomplianceDashboard', 'rules');
PluginItsoftwarecomplianceCompliance::stylesheet();
PluginItsoftwarecomplianceCompliance::header('rules', 'Configure which applications and exact versions IT requires');
?>
<div class="itc-v4-pagehead">
  <div><span class="itc-v4-kicker">POLICY MANAGEMENT</span><h3>Compliance rules</h3><p>Create approved software requirements and review their deployment coverage.</p></div>
  <?php if ($canManage): ?><a class="btn btn-primary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php',['new'=>1])) ?>"><i class="ti ti-plus me-1"></i> Add rule</a><?php endif; ?>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= $h($error) ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="alert alert-success"><?= $h($success) ?></div><?php endif; ?>
<?php if (($_GET['deleted'] ?? '') === '1'): ?><div class="alert alert-success" role="status">Compliance rule deleted. GLPI computer and software inventory was not changed.</div><?php endif; ?>
<?php if ($showEditor): ?>
<section class="itc-v4-rule-editor" id="rule-editor">
  <div class="itc-v4-panel-head itc-v4-rule-heading"><div><h3><i class="ti ti-list-check me-1"></i><?= $editing ? 'Edit compliance rule' : 'Create compliance rule' ?></h3><p>Preview matching software names and affected computers before saving.</p></div><a class="btn btn-outline-secondary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>"><i class="ti ti-x me-1"></i> Close</a></div>
  <div class="itc-v4-editor-layout">
    <form class="itc-v4-rule-form" method="post" action="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>" id="itc-rule-form">
      <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($draft['id'] ?? 0) ?>">
      <div class="itc-v4-form-group"><label class="form-label" for="rule-name">Rule name</label><input class="form-control" id="rule-name" name="name" required maxlength="180" value="<?= $h($draft['name'] ?? '') ?>" placeholder="e.g. Cisco Secure Endpoint"><small>Describe the requirement so your IT team can recognize it.</small></div>
      <div class="itc-v4-form-group"><label class="form-label" for="rule-pattern">Software name / pattern</label>
        <div class="itc-v4-autocomplete"><input class="form-control" id="rule-pattern" name="software_pattern" required maxlength="180" autocomplete="off" value="<?= $h($draft['software_pattern'] ?? '') ?>" placeholder="Type 365, Cisco, Chrome..." aria-describedby="rule-pattern-help"><div id="itc-rule-suggestions" class="itc-v4-suggestions" role="listbox" hidden></div></div>
        <small id="rule-pattern-help">Find matching GLPI software names. Selecting a suggestion uses exact-name matching.</small>
      </div>
      <div class="itc-v4-rule-pair">
        <div class="itc-v4-form-group"><label class="form-label" for="rule-mode">Name matching</label><select class="form-select" id="rule-mode" name="match_mode"><?php foreach (['contains'=>'Contains text','exact'=>'Exact name','prefix'=>'Starts with'] as $mode=>$label): ?><option value="<?= $h($mode) ?>" <?= ($draft['match_mode'] ?? 'contains') === $mode ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?></select></div>
        <div class="itc-v4-form-group"><label class="form-label" for="rule-version">Approved version</label><input class="form-control" id="rule-version" name="required_version" maxlength="120" value="<?= $h($draft['required_version'] ?? '') ?>" placeholder="Any version"><small>Exact version match; blank accepts any.</small></div>
      </div>
      <div class="itc-v4-form-group"><label class="form-label" for="rule-dept">Department scope</label><select class="form-select" id="rule-dept" name="usercategories_id"><?= PluginItsoftwarecomplianceCompliance::categoryOptions($categories,(int)($draft['usercategories_id'] ?? 0)) ?></select><small>Uses the assigned user's GLPI User Category.</small></div>
      <div class="itc-v4-editor-buttons"><button type="button" id="itc-preview-trigger" class="btn btn-outline-primary"><i class="ti ti-flask me-1"></i> Preview rule</button><button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i> <?= $editing ? 'Update rule' : 'Save rule' ?></button></div>
    </form>
    <aside class="itc-v4-preview" id="itc-rule-preview" data-url="<?= $h(PluginItsoftwarecomplianceCompliance::url('rule_preview.php')) ?>" aria-live="polite">
      <div class="itc-v4-preview-title"><span class="itc-v4-rule-icon"><i class="ti ti-flask"></i></span><div><strong>Rule preview</strong><small>Read-only · uses current GLPI inventory</small></div></div>
      <div id="itc-preview-content" class="itc-v4-preview-placeholder"><i class="ti ti-scan"></i><strong>Test before you save</strong><p>Click <b>Preview rule</b> to see matching installed software names and how many computers would pass or need review.</p></div>
      <div class="itc-v4-preview-foot"><i class="ti ti-info-circle"></i> Preview does not create, update, or enable rules. It may take longer than name search because it evaluates the draft rule.</div>
    </aside>
  </div>
</section>
<?php elseif (!$canManage): ?>
<div class="itc-note mb-3">Your account can view rules and findings. Creating or changing a rule requires GLPI configuration update permission.</div>
<?php endif; ?>
<div class="itc-v4-panel itc-v4-rules-list">
  <div class="itc-rule-topline"><div><h3 class="h3 mb-1"><i class="ti ti-shield-check me-2"></i>Configured rules</h3><p class="itc-muted mb-0"><?= count($visibleRules) ?> shown of <?= count($rules) ?> · quick access to software findings</p></div></div>
  <form class="itc-toolbar itc-rule-toolbar" method="get" action="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>">
    <div><label for="itc-rule-search">Find rule</label><input id="itc-rule-search" name="q" class="form-control" maxlength="80" value="<?= $h($ruleSearch) ?>" placeholder="Application or rule name"></div>
    <div><label for="itc-rule-status">Policy status</label><select class="form-select" id="itc-rule-status" name="state">
        <option value="all" <?= $ruleState==='all'?'selected':'' ?>>All statuses</option><option value="active" <?= $ruleState==='active'?'selected':'' ?>>Active only</option><option value="disabled" <?= $ruleState==='disabled'?'selected':'' ?>>Disabled only</option>
    </select></div>
    <button class="btn btn-primary" type="submit"><i class="ti ti-search me-1"></i> Find</button>
    <a class="btn btn-outline-secondary" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>">Reset</a>
  </form>
  <?php if (!$rules): ?><p class="itc-muted">No compliance rules configured yet.</p>
  <?php elseif (!$visibleRules): ?><div class="itc-empty compact">No rules match these filters.</div>
  <?php else: ?><div class="itc-table-wrap"><table class="itc-table">
    <thead><tr><th>Rule</th><th>Pattern</th><th>Version</th><th>Department</th><th>Status</th><th>View</th><?php if ($canManage): ?><th>Action</th><?php endif; ?></tr></thead><tbody>
    <?php foreach ($visibleRules as $rule): ?>
    <tr><td><strong><?= $h($rule['name']) ?></strong></td><td><?= $h($rule['software_pattern']) ?><div class="itc-muted"><?= $h($rule['match_mode']) ?></div></td>
      <td><?= $h($rule['required_version'] ?: 'Any') ?></td><td><?= $h(PluginItsoftwarecomplianceCompliance::categoryName((int)$rule['usercategories_id'], $categories)) ?></td>
      <td><span class="itc-pill <?= $rule['is_active'] ? 'good' : 'warn' ?>"><?= $rule['is_active'] ? 'Active' : 'Disabled' ?></span></td>
      <td><?php if ((int)$rule['is_active']): ?><a class="itc-rule-view" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php',['rule'=>$rule['id']])) ?>">Findings <i class="ti ti-arrow-up-right"></i></a><?php else: ?><span class="itc-muted">—</span><?php endif; ?></td>
      <?php if ($canManage): ?><td><div class="itc-actions">
        <a class="btn btn-outline-primary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php', ['edit'=>$rule['id']])) ?>">Edit</a>
        <form method="post" action="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>">
          <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
          <input type="hidden" name="enabled" value="<?= $rule['is_active'] ? 0 : 1 ?>">
          <button type="submit" class="btn btn-outline-secondary btn-sm"><?= $rule['is_active'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <form method="post" action="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>"
              data-rule-name="<?= $h($rule['name']) ?>"
              onsubmit="return window.confirm('Permanently delete compliance rule: ' + this.dataset.ruleName + '\n\nThis removes the policy only. GLPI computers and installed software will NOT be deleted.\n\nContinue?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">
          <input type="hidden" name="confirm_delete" value="1">
          <button type="submit" class="btn btn-outline-danger btn-sm" title="Permanently delete this compliance rule" aria-label="Delete rule <?= $h($rule['name']) ?>"><i class="ti ti-trash me-1" aria-hidden="true"></i> Delete</button>
        </form>
      </div></td><?php endif; ?>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</div>
<div class="itc-note mt-3"><b>Matching examples:</b> a “contains” rule for <span class="itc-code">365</span> matches any software name containing that text, but does not match “Office Professional Plus” unless your inventory name contains “365”. For version rules, the inventory version must exactly equal the version entered. No apps are installed or removed by this plugin.</div>
<?php
if ($showEditor) {
    $script = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/itsoftwarecompliance/js/rule-preview.js?v=040';
    echo '<script defer src="' . $h($script) . '"></script>';
}
PluginItsoftwarecomplianceCompliance::footer();
Html::footer();
?>
