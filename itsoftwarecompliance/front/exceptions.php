<?php
include('../../../inc/includes.php');
PluginItsoftwarecomplianceCompliance::checkView();
PluginItsoftwarecomplianceCompliance::ensureInstalled();
$h = ['PluginItsoftwarecomplianceCompliance', 'h'];
$categories = PluginItsoftwarecomplianceCompliance::departments();
$rules = PluginItsoftwarecomplianceCompliance::rules(true);
$ruleId = max(0, (int)($_GET['rule'] ?? 0));
if (!$ruleId && $rules) {
    $ruleId = (int)$rules[0]['id'];
}
$chosen = null;
foreach ($rules as $rule) {
    if ((int)$rule['id'] === $ruleId) {
        $chosen = $rule;
        break;
    }
}
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'missing', 'version'], true)) {
    $status = 'all';
}
$category = max(0, (int)($_GET['department'] ?? 0));
$validCategories = array_map(static fn($r) => (int)$r['id'], $categories);
if ($category > 0 && !in_array($category, $validCategories, true)) {
    $category = 0;
}
$search = substr(trim((string)($_GET['search'] ?? '')), 0, 80);
$page = max(1, min(100000, (int)($_GET['page'] ?? 1)));
$perPage = 25;
$export = (string)($_GET['export'] ?? '') === 'csv';
$total = $chosen ? PluginItsoftwarecomplianceCompliance::exceptionsCount($chosen, $status, $category, $search) : 0;
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = $chosen ? PluginItsoftwarecomplianceCompliance::exceptionsPage($chosen, $status, $category, $search, $export ? 0 : (($page - 1) * $perPage), $export ? 1000 : $perPage) : [];

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="IT_Software_Compliance_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store');
    $stream = fopen('php://output', 'w');
    fputcsv($stream, ['Computer ID', 'Computer Name', 'Serial', 'Department', 'Assigned User', 'Last Inventory', 'Finding', 'Required Application', 'Approved Version']);
    foreach ($rows as $row) {
        $out = [
            $row['id'], $row['name'], $row['serial'], $row['department'], $row['assigned_user'],
            $row['last_inventory_update'], $row['finding'], $chosen['name'] ?? '', $chosen['required_version'] ?? '',
        ];
        // Prevent spreadsheet formula injection in CSV exports.
        foreach ($out as &$v) {
            $v = (string)$v;
            if (preg_match('/^\s*[=+@\-]/u', $v)) {
                $v = "'" . $v;
            }
        }
        unset($v);
        fputcsv($stream, $out);
    }
    fclose($stream);
    return;
}

Html::header('IT Software Compliance - Findings', $_SERVER['PHP_SELF'], 'tools', 'PluginItsoftwarecomplianceDashboard', 'exceptions');
PluginItsoftwarecomplianceCompliance::stylesheet();
PluginItsoftwarecomplianceCompliance::header('exceptions', 'Review missing installations and version mismatches');
$query = ['rule' => $ruleId, 'department' => $category, 'status' => $status, 'search' => $search];
$resetUrl = PluginItsoftwarecomplianceCompliance::url('exceptions.php');
$visible = count($rows);
$fromRow = $total > 0 ? (($page - 1) * $perPage + 1) : 0;
$toRow = $fromRow > 0 ? $fromRow + $visible - 1 : 0;
$versionLabel = trim((string)($chosen['required_version'] ?? ''));
$ruleScope = $chosen ? PluginItsoftwarecomplianceCompliance::categoryName((int)$chosen['usercategories_id'], $categories) : '';
$filtersUsed = (int)($category !== 0) + (int)($status !== 'all') + (int)($search !== '');
$now = time();
?>
<section class="itc-findings-v43" aria-label="Compliance findings investigation workspace">
  <header class="itc-f43-titlebar">
    <div class="itc-f43-title-copy">
      <div class="itc-f43-kicker"><i class="ti ti-shield-exclamation" aria-hidden="true"></i> COMPLIANCE INVESTIGATIONS</div>
      <h3>Findings workspace</h3>
      <p>Investigate missing installations, review version exceptions, and open the correct GLPI computer record.</p>
    </div>
    <div class="itc-f43-title-actions">
      <a href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>" class="btn btn-outline-secondary btn-sm"><i class="ti ti-settings me-1" aria-hidden="true"></i> Compliance rules</a>
      <?php if ($chosen): ?>
        <a href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php', array_merge($query, ['export' => 'csv']))) ?>" class="btn btn-primary btn-sm"><i class="ti ti-download me-1" aria-hidden="true"></i> Export CSV</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$chosen): ?>
    <div class="itc-f43-empty"><i class="ti ti-shield-check" aria-hidden="true"></i><strong>No active compliance rules</strong><p>Create or enable a rule to investigate software compliance.</p><a class="btn btn-primary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('rules.php')) ?>">Open Compliance Rules</a></div>
  <?php else: ?>
    <div class="itc-f43-overview" aria-label="Selected rule and findings summary">
      <div class="itc-f43-policy">
        <span class="itc-f43-policy-symbol"><i class="ti ti-app-window" aria-hidden="true"></i></span>
        <div class="itc-f43-policy-body">
          <span class="itc-f43-overline">CURRENT POLICY</span>
          <strong title="<?= $h($chosen['name']) ?>"><?= $h($chosen['name']) ?></strong>
          <div class="itc-f43-policy-tags">
            <span><i class="ti ti-category" aria-hidden="true"></i> <?= $h($ruleScope) ?></span>
            <span><i class="ti ti-git-compare" aria-hidden="true"></i> <?= $h(ucfirst((string)$chosen['match_mode'])) ?> match</span>
            <span><i class="ti ti-versions" aria-hidden="true"></i> <?= $versionLabel === '' ? 'Any version' : 'Approved: ' . $h($versionLabel) ?></span>
          </div>
        </div>
      </div>
      <div class="itc-f43-overview-numbers">
        <div class="itc-f43-count"><span>Matching computers</span><strong><?= number_format($total) ?></strong><small>Current filters</small></div>
        <div class="itc-f43-count"><span>Shown now</span><strong><?= number_format($visible) ?></strong><small>Of <?= number_format($total) ?> results</small></div>
      </div>
    </div>

    <section class="itc-f43-filter-card" aria-label="Refine findings">
      <div class="itc-f43-filter-heading">
        <div><h4><i class="ti ti-adjustments-horizontal" aria-hidden="true"></i> Refine investigation</h4><p>Filters run against GLPI inventory only when you apply them.</p></div>
        <span class="itc-f43-filter-counter"><?= $filtersUsed ?> active filter<?= $filtersUsed === 1 ? '' : 's' ?></span>
      </div>
      <form action="<?= $h($resetUrl) ?>" method="get" role="search" class="itc-f43-form">
        <div class="itc-f43-field itc-f43-field-rule"><label for="itc-f43-rule">Application rule</label><select class="form-select" name="rule" id="itc-f43-rule">
          <?php foreach ($rules as $rule): ?><option value="<?= (int)$rule['id'] ?>" <?= (int)$rule['id'] === $ruleId ? 'selected' : '' ?>><?= $h($rule['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="itc-f43-field"><label for="itc-f43-department">Department</label><select class="form-select" name="department" id="itc-f43-department"><?= PluginItsoftwarecomplianceCompliance::categoryOptions($categories, $category) ?></select></div>
        <div class="itc-f43-field"><label for="itc-f43-status">Finding type</label><select class="form-select" name="status" id="itc-f43-status">
          <?php foreach (['all' => 'All findings', 'missing' => 'Not detected', 'version' => 'Version mismatch'] as $key => $label): ?><option value="<?= $h($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= $h($label) ?></option><?php endforeach; ?>
        </select></div>
        <div class="itc-f43-field itc-f43-field-search"><label for="itc-f43-search">Computer name or serial</label><div class="itc-f43-searchbox"><i class="ti ti-search" aria-hidden="true"></i><input class="form-control" name="search" id="itc-f43-search" maxlength="80" value="<?= $h($search) ?>" placeholder="e.g. VBL-IT-PC01"></div></div>
        <div class="itc-f43-form-actions"><button class="btn btn-primary" type="submit"><i class="ti ti-filter me-1" aria-hidden="true"></i> Apply filters</button><a href="<?= $h($resetUrl) ?>" class="btn btn-outline-secondary">Reset</a></div>
      </form>
    </section>

    <section class="itc-f43-results" aria-label="Affected computers">
      <div class="itc-f43-results-head">
        <div class="itc-f43-results-intro">
          <span class="itc-f43-results-icon"><i class="ti ti-device-desktop-analytics" aria-hidden="true"></i></span>
          <div><h4>Affected computers</h4><p><?= number_format($fromRow) ?>–<?= number_format($toRow) ?> of <?= number_format($total) ?> · Sorted by computer name</p></div>
        </div>
        <button type="button" class="itc-f43-density" id="itc-f43-density" aria-pressed="false" title="Switch to compact rows"><i class="ti ti-list-details" aria-hidden="true"></i> Compact rows</button>
      </div>
      <nav class="itc-f43-segments" aria-label="Quick finding filters">
        <?php foreach (['all' => ['All findings','ti-list'], 'missing' => ['Not detected','ti-circle-x'], 'version' => ['Version mismatch','ti-git-compare']] as $key => $item): ?>
          <a href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php', array_merge($query, ['status' => $key, 'page' => 1]))) ?>" class="<?= $status === $key ? 'is-active' : '' ?>" <?= $status === $key ? 'aria-current="page"' : '' ?>><i class="ti <?= $h($item[1]) ?>" aria-hidden="true"></i> <?= $h($item[0]) ?></a>
        <?php endforeach; ?>
      </nav>
      <?php if (!$rows): ?>
        <div class="itc-f43-empty"><i class="ti ti-search-off" aria-hidden="true"></i><strong>No matching computers</strong><p>Try clearing the filters, or choose a different compliance rule.</p><a class="btn btn-outline-secondary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php', ['rule' => $ruleId])) ?>">Clear filters</a></div>
      <?php else: ?>
        <div class="itc-f43-table-scroll"><table class="itc-f43-table">
          <thead><tr><th scope="col" class="itc-f43-number-col">#</th><th scope="col">Computer</th><th scope="col">Assigned user &amp; department</th><th scope="col">Inventory confidence</th><th scope="col">Finding</th><th scope="col">Suggested next step</th><th scope="col" class="itc-f43-review-col">Review</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $i => $row):
                $isMissing = (string)$row['finding'] === 'Not detected';
                $assetUrl = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/') . '/front/computer.form.php?id=' . (int)$row['id'];
                $rawDate = trim((string)($row['last_inventory_update'] ?? ''));
                $timestamp = $rawDate !== '' ? strtotime($rawDate) : false;
                $hasDate = $timestamp !== false && $timestamp !== null;
                $daysAgo = $hasDate ? (int)floor(($now - $timestamp) / 86400) : null;
                $stale = $hasDate && ($now - $timestamp) > 30 * 86400;
                $missingDate = $daysAgo === null;
                $confidence = $missingDate ? 'Unknown' : ($stale ? 'Stale' : 'Recent');
                $confidenceClass = $missingDate ? 'unknown' : ($stale ? 'stale' : 'recent');
                $confidenceDetail = $missingDate ? 'No inventory date' : ($daysAgo <= 0 ? 'Inventoried today' : ($stale && $daysAgo === 30 ? 'Over 30 days ago' : $daysAgo . ' days ago'));
                $nextStep = $missingDate || $stale ? 'Refresh GLPI inventory' : ($isMissing ? 'Verify application install' : 'Review approved version');
            ?>
              <tr>
                <td class="itc-f43-row-number"><?= number_format($fromRow + $i) ?></td>
                <td><div class="itc-f43-device"><span class="itc-f43-device-icon"><i class="ti ti-device-desktop" aria-hidden="true"></i></span><div class="itc-f43-device-label"><a href="<?= $h($assetUrl) ?>" class="itc-f43-hostname"><?= $h($row['name'] ?: 'Computer #' . $row['id']) ?></a><span><?= $h($row['serial'] ?: 'No serial recorded') ?></span></div></div></td>
                <td><div class="itc-f43-person"><strong><?= $h($row['assigned_user'] ?: 'Unassigned') ?></strong><span><?= $h($row['department'] ?: 'No user category') ?></span></div></td>
                <td><div class="itc-f43-confidence"><span class="itc-f43-confidence-tag <?= $confidenceClass ?>"><i class="ti <?= $missingDate ? 'ti-help-circle' : ($stale ? 'ti-clock-exclamation' : 'ti-circle-check') ?>" aria-hidden="true"></i> <?= $h($confidence) ?></span><small><?= $h($confidenceDetail) ?></small></div></td>
                <td><span class="itc-f43-finding <?= $isMissing ? 'missing' : 'version' ?>"><span class="itc-f43-dot" aria-hidden="true"></span><?= $isMissing ? 'Not detected' : 'Version mismatch' ?></span></td>
                <td><span class="itc-f43-next-step"><?= $h($nextStep) ?></span></td>
                <td class="itc-f43-review-cell"><a class="itc-f43-inspect" href="<?= $h($assetUrl) ?>" data-itc-inspect data-hostname="<?= $h($row['name'] ?: 'Computer #' . $row['id']) ?>" data-id="<?= (int)$row['id'] ?>" data-serial="<?= $h($row['serial'] ?: 'Not recorded') ?>" data-user="<?= $h($row['assigned_user'] ?: 'Unassigned') ?>" data-department="<?= $h($row['department'] ?: 'No user category') ?>" data-inventory="<?= $h($rawDate ?: 'No inventory date') ?>" data-confidence="<?= $h($confidence) ?>" data-confidence-detail="<?= $h($confidenceDetail) ?>" data-finding="<?= $isMissing ? 'Not detected' : 'Version mismatch' ?>" data-next-step="<?= $h($nextStep) ?>" data-rule="<?= $h($chosen['name']) ?>" data-pattern="<?= $h($chosen['software_pattern']) ?>" data-version="<?= $h($versionLabel === '' ? 'Any version' : $versionLabel) ?>" data-url="<?= $h($assetUrl) ?>" aria-label="Inspect compliance finding for <?= $h($row['name'] ?: 'computer ' . $row['id']) ?>"><i class="ti ti-layout-sidebar-right-expand" aria-hidden="true"></i> Inspect</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
      <footer class="itc-f43-results-footer">
        <span>Showing <strong><?= number_format($fromRow) ?>–<?= number_format($toRow) ?></strong> of <strong><?= number_format($total) ?></strong> computers <span class="itc-f43-footer-muted">· 25 per page</span></span>
        <div class="itc-f43-paging">
          <?php if ($page > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php', array_merge($query, ['page' => $page - 1]))) ?>"><i class="ti ti-chevron-left" aria-hidden="true"></i> Previous</a><?php endif; ?>
          <span>Page <?= number_format($page) ?> of <?= number_format($pages) ?></span>
          <?php if ($page < $pages): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $h(PluginItsoftwarecomplianceCompliance::url('exceptions.php', array_merge($query, ['page' => $page + 1]))) ?>">Next <i class="ti ti-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
        </div>
      </footer>
    </section>
    <p class="itc-f43-disclaimer"><i class="ti ti-info-circle" aria-hidden="true"></i> Findings reflect GLPI's saved inventory, not live computer state. <strong>Not detected</strong> does not prove software is absent. For computers with old or unknown inventory data, refresh inventory before taking action. CSV export is limited to the first 1,000 results.</p>
  <?php endif; ?>
</section>

<div class="itc-f43-backdrop" id="itc-f43-backdrop" hidden></div>
<aside class="itc-f43-inspector" id="itc-f43-inspector" role="dialog" aria-modal="true" aria-labelledby="itc-f43-inspector-title" hidden>
  <div class="itc-f43-inspector-head">
    <div><span class="itc-f43-overline">INVESTIGATION DETAILS</span><h3 id="itc-f43-inspector-title">Computer finding</h3></div>
    <button type="button" class="itc-f43-inspector-close" id="itc-f43-inspector-close" aria-label="Close inspection panel"><i class="ti ti-x" aria-hidden="true"></i></button>
  </div>
  <div class="itc-f43-inspector-content">
    <div class="itc-f43-inspector-device"><span class="itc-f43-inspector-device-icon"><i class="ti ti-device-desktop" aria-hidden="true"></i></span><div><strong id="itc-f43-detail-hostname">—</strong><span id="itc-f43-detail-serial">—</span></div></div>
    <div class="itc-f43-inspector-section"><h4>Compliance result</h4><div class="itc-f43-inspector-result"><span id="itc-f43-detail-finding">—</span><small id="itc-f43-detail-rule">—</small></div><dl class="itc-f43-inspector-dl"><div><dt>Software match</dt><dd id="itc-f43-detail-pattern">—</dd></div><div><dt>Approved version</dt><dd id="itc-f43-detail-version">—</dd></div></dl></div>
    <div class="itc-f43-inspector-section"><h4>Inventory evidence</h4><dl class="itc-f43-inspector-dl"><div><dt>Last inventory</dt><dd id="itc-f43-detail-inventory">—</dd></div><div><dt>Inventory confidence</dt><dd id="itc-f43-detail-confidence">—</dd></div><div><dt>Assigned user</dt><dd id="itc-f43-detail-user">—</dd></div><div><dt>Department</dt><dd id="itc-f43-detail-department">—</dd></div></dl></div>
    <div class="itc-f43-inspector-next"><i class="ti ti-bulb" aria-hidden="true"></i><div><strong>Suggested next step</strong><p id="itc-f43-detail-next">—</p></div></div>
    <p class="itc-f43-inspector-note">This is an inventory-based finding. Verify the device before making changes.</p>
  </div>
  <div class="itc-f43-inspector-foot"><a href="#" id="itc-f43-detail-open" class="btn btn-primary"><i class="ti ti-external-link me-1" aria-hidden="true"></i> Open computer in GLPI</a><button type="button" class="btn btn-outline-secondary" id="itc-f43-detail-done">Close</button></div>
</aside>
<?php
$jsUrl = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/itsoftwarecompliance/js/findings.js?v=043';
echo '<script defer src="' . $h($jsUrl) . '"></script>';
PluginItsoftwarecomplianceCompliance::footer();
Html::footer();
