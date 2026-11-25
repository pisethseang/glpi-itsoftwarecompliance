<?php
if (!defined('GLPI_ROOT')) {
    return;
}

/** Read-only inventory analytics and administrator-owned policy rules. */
class PluginItsoftwarecomplianceCompliance
{
    private const TABLE = 'glpi_plugin_itsoftwarecompliance_rules';

    public static function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function url(string $page, array $params = []): string
    {
        global $CFG_GLPI;
        $root = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/');
        $url = $root . '/plugins/itsoftwarecompliance/front/' . $page;
        return $params ? $url . '?' . http_build_query($params) : $url;
    }

    public static function checkView(): void
    {
        Session::checkRight('computer', READ);
        Session::checkRight('software', READ);
    }

    public static function canManage(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function checkManage(): void
    {
        Session::checkRight('config', UPDATE);
    }

    public static function ensureInstalled(): void
    {
        global $DB;
        if (!$DB->tableExists(self::TABLE)) {
            throw new RuntimeException('Compliance rules table missing. Install the plugin from Setup > Plugins.');
        }
    }

    /** Build a safe entity restriction even when users switch GLPI active entities. */
    private static function entitySql(): string
    {
        $ids = array_values(array_unique(array_map('intval', Session::getActiveEntities())));
        $ids = array_filter($ids, static function ($id) { return $id >= 0; });
        if (!$ids) {
            return ' AND 1=0';
        }
        return ' AND c.entities_id IN (' . implode(',', $ids) . ')';
    }

    private static function departmentSql(int $ruleCategory = 0, int $filterCategory = 0): string
    {
        $sql = '';
        if ($ruleCategory > 0) {
            $sql .= ' AND u.usercategories_id = ' . $ruleCategory;
        }
        if ($filterCategory > 0) {
            $sql .= ' AND u.usercategories_id = ' . $filterCategory;
        }
        return $sql;
    }

    private static function computerFrom(int $ruleCategory = 0, int $filterCategory = 0): string
    {
        return ' FROM glpi_computers c'
            . ' LEFT JOIN glpi_users u ON u.id = c.users_id'
            . ' LEFT JOIN glpi_usercategories uc ON uc.id = u.usercategories_id'
            . ' WHERE c.is_deleted=0 AND c.is_template=0'
            . self::entitySql() . self::departmentSql($ruleCategory, $filterCategory);
    }

    private static function safeLike(string $term, bool $leftWildcard = false, bool $rightWildcard = false): string
    {
        global $DB;
        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        return "'" . ($leftWildcard ? '%' : '') . $DB->escape($term) . ($rightWildcard ? '%' : '') . "'";
    }

    /** Correlated per-computer query uses glpi_items_softwareversions.items_id index. */
    private static function installedExists(array $rule, bool $checkVersion): string
    {
        global $DB;
        $mode = (string)($rule['match_mode'] ?? 'contains');
        $pattern = (string)$rule['software_pattern'];
        if ($mode === 'exact') {
            $titleTest = "s.name = '" . $DB->escape($pattern) . "'";
        } elseif ($mode === 'prefix') {
            $titleTest = 's.name LIKE ' . self::safeLike($pattern, false, true) . " ESCAPE '!'";
        } else {
            $titleTest = 's.name LIKE ' . self::safeLike($pattern, true, true) . " ESCAPE '!'";
        }
        $versionTest = '';
        if ($checkVersion && trim((string)$rule['required_version']) !== '') {
            $versionTest = " AND sv.name = '" . $DB->escape((string)$rule['required_version']) . "'";
        }
        return 'EXISTS (SELECT 1 FROM glpi_items_softwareversions isv'
            . ' INNER JOIN glpi_softwareversions sv ON sv.id = isv.softwareversions_id'
            . ' INNER JOIN glpi_softwares s ON s.id = sv.softwares_id'
            . " WHERE isv.itemtype = 'Computer' AND isv.items_id = c.id"
            . ' AND isv.is_deleted=0 AND s.is_deleted=0'
            . ' AND ' . $titleTest . $versionTest . ')';
    }

    private static function fetchOne(string $sql): array
    {
        global $DB;
        $res = $DB->doQuery($sql);
        return $DB->fetchAssoc($res) ?: [];
    }

    private static function fetchAll(string $sql): array
    {
        global $DB;
        $rows = [];
        $res = $DB->doQuery($sql);
        while ($row = $DB->fetchAssoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public static function departments(): array
    {
        // Only IDs and display names; access to assets is still limited by active GLPI entities.
        return self::fetchAll('SELECT id, name FROM glpi_usercategories ORDER BY name ASC LIMIT 1000');
    }

    public static function categoryName(int $id, array $categories): string
    {
        if ($id === 0) {
            return 'All departments';
        }
        foreach ($categories as $category) {
            if ((int)$category['id'] === $id) {
                return (string)$category['name'];
            }
        }
        return 'Unknown category #' . $id;
    }

    public static function rules(bool $activeOnly = false): array
    {
        self::ensureInstalled();
        $where = $activeOnly ? ' WHERE is_active=1' : '';
        return self::fetchAll('SELECT * FROM ' . self::TABLE . $where . ' ORDER BY is_active DESC, id DESC LIMIT ' . ($activeOnly ? 30 : 100));
    }

    private static function activeRuleCount(): int
    {
        $row = self::fetchOne('SELECT COUNT(*) AS total FROM ' . self::TABLE . ' WHERE is_active=1');
        return (int)($row['total'] ?? 0);
    }

    public static function rule(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $row = self::fetchOne('SELECT * FROM ' . self::TABLE . ' WHERE id=' . $id . ' LIMIT 1');
        return $row ?: null;
    }

    public static function saveRule(array $input): int
    {
        global $DB;
        self::checkManage();
        self::ensureInstalled();
        $id = max(0, (int)($input['id'] ?? 0));
        $name = trim((string)($input['name'] ?? ''));
        $pattern = trim((string)($input['software_pattern'] ?? ''));
        $mode = (string)($input['match_mode'] ?? 'contains');
        $version = trim((string)($input['required_version'] ?? ''));
        $category = max(0, (int)($input['usercategories_id'] ?? 0));
        if ($name === '' || $pattern === '' || strlen($name) > 180 || strlen($pattern) > 180 || strlen($version) > 120) {
            throw new InvalidArgumentException('Rule name and application pattern are required (maximum 180 characters).');
        }
        if (!in_array($mode, ['exact', 'prefix', 'contains'], true)) {
            throw new InvalidArgumentException('Invalid software matching mode.');
        }
        if ($category > 0 && !self::fetchOne('SELECT id FROM glpi_usercategories WHERE id=' . $category . ' LIMIT 1')) {
            throw new InvalidArgumentException('Unknown department category.');
        }
        if ($id === 0 && self::activeRuleCount() >= 30) {
            throw new InvalidArgumentException('Maximum 30 active rules. Disable an existing rule before creating another.');
        }
        $now = date('Y-m-d H:i:s');
        $data = [
            'name' => $name,
            'software_pattern' => $pattern,
            'match_mode' => $mode,
            'required_version' => $version,
            'usercategories_id' => $category,
            'date_mod' => $now,
        ];
        if ($id > 0) {
            if (!self::rule($id)) {
                throw new InvalidArgumentException('Rule not found.');
            }
            $DB->update(self::TABLE, $data, ['id' => $id]);
            return $id;
        }
        $data['is_active'] = 1;
        $data['date_creation'] = $now;
        $DB->insert(self::TABLE, $data);
        return (int)$DB->insertId();
    }

    public static function setEnabled(int $id, bool $enabled): void
    {
        global $DB;
        self::checkManage();
        if (!self::rule($id)) {
            throw new InvalidArgumentException('Rule not found.');
        }
        if ($enabled && self::activeRuleCount() >= 30) {
            throw new InvalidArgumentException('Maximum 30 active rules. Disable an existing rule first.');
        }
        $DB->update(self::TABLE, ['is_active' => $enabled ? 1 : 0, 'date_mod' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    /** Permanently delete one plugin-owned compliance policy. GLPI inventory is untouched. */
    public static function deleteRule(int $id): void
    {
        global $DB;
        self::checkManage();
        self::ensureInstalled();
        if ($id < 1 || self::rule($id) === null) {
            throw new InvalidArgumentException('Compliance rule not found. It may already have been deleted.');
        }
        $DB->delete(self::TABLE, ['id' => $id]);
    }

    /**
     * Read-only draft policy inspection. Called by the rule editor only, never on Overview.
     * All results are restricted to installed software on computers in active entities.
     * No rule is saved or changed by this method.
     */
    public static function previewRule(array $draft, bool $withStats = false): array
    {
        global $DB;
        self::checkManage();
        self::ensureInstalled();
        $pattern = trim((string)($draft['software_pattern'] ?? ''));
        $mode = (string)($draft['match_mode'] ?? 'contains');
        $version = trim((string)($draft['required_version'] ?? ''));
        $category = max(0, (int)($draft['usercategories_id'] ?? 0));
        if (strlen($pattern) < 2 || strlen($pattern) > 180 || strlen($version) > 120) {
            throw new InvalidArgumentException('Enter 2–180 characters in the software pattern.');
        }
        if (!in_array($mode, ['contains', 'prefix', 'exact'], true)) {
            throw new InvalidArgumentException('Invalid matching mode.');
        }
        if ($category && !self::fetchOne('SELECT id FROM glpi_usercategories WHERE id=' . $category . ' LIMIT 1')) {
            throw new InvalidArgumentException('Invalid department category.');
        }
        if ($mode === 'exact') {
            $titleTest = "s.name = '" . $DB->escape($pattern) . "'";
        } elseif ($mode === 'prefix') {
            $titleTest = 's.name LIKE ' . self::safeLike($pattern, false, true) . " ESCAPE '!'";
        } else {
            $titleTest = 's.name LIKE ' . self::safeLike($pattern, true, true) . " ESCAPE '!'";
        }
        // Bound list: first matching titles only. The JOIN prevents exposing titles
        // that are installed exclusively in inaccessible entities.
        $sql = 'SELECT s.name AS software_name '
             . 'FROM glpi_softwares s WHERE s.is_deleted=0 AND ' . $titleTest
             . ' AND EXISTS (SELECT 1 FROM glpi_softwareversions sv '
             . 'INNER JOIN glpi_items_softwareversions isv ON isv.softwareversions_id=sv.id '
             . "  AND isv.itemtype='Computer' AND isv.is_deleted=0 "
             . 'INNER JOIN glpi_computers c ON c.id=isv.items_id AND c.is_deleted=0 AND c.is_template=0 '
             . 'LEFT JOIN glpi_users u ON u.id=c.users_id '
             . 'WHERE sv.softwares_id=s.id' . self::entitySql()
             . ($category ? ' AND u.usercategories_id=' . $category : '')
             . ') GROUP BY s.name ORDER BY s.name ASC LIMIT 12';
        $titles = [];
        foreach (self::fetchAll($sql) as $row) {
            $titles[] = (string)$row['software_name'];
        }
        $result = ['titles' => $titles, 'truncated' => count($titles) === 12];
        if ($withStats) {
            $rule = [
                'software_pattern' => $pattern,
                'match_mode' => $mode,
                'required_version' => $version,
                'usercategories_id' => $category,
            ];
            $result['stats'] = self::ruleStats($rule);
        }
        return $result;
    }

    public static function totalComputers(int $filterCategory = 0): int
    {
        $row = self::fetchOne('SELECT COUNT(*) AS total' . self::computerFrom(0, $filterCategory));
        return (int)($row['total'] ?? 0);
    }

    /** Counts evaluations, not unique PCs across different software rules. */
    public static function ruleStats(array $rule, int $filterCategory = 0): array
    {
        $ruleCategory = (int)$rule['usercategories_id'];
        $title = self::installedExists($rule, false);
        $requiredVersion = trim((string)$rule['required_version']) !== '';
        $accepted = $requiredVersion ? self::installedExists($rule, true) : $title;
        $mismatch = $requiredVersion ? "COALESCE(SUM(CASE WHEN ($title) AND NOT ($accepted) THEN 1 ELSE 0 END),0)" : '0';
        $sql = 'SELECT COUNT(*) AS checked,'
            . " COALESCE(SUM(CASE WHEN NOT ($title) THEN 1 ELSE 0 END),0) AS missing,"
            . " $mismatch AS wrong_version,"
            . " COALESCE(SUM(CASE WHEN ($accepted) THEN 1 ELSE 0 END),0) AS compliant"
            . self::computerFrom($ruleCategory, $filterCategory);
        $result = self::fetchOne($sql);
        return [
            'checked' => (int)($result['checked'] ?? 0),
            'missing' => (int)($result['missing'] ?? 0),
            'wrong_version' => (int)($result['wrong_version'] ?? 0),
            'compliant' => (int)($result['compliant'] ?? 0),
        ];
    }

    private static function findingWhere(array $rule, string $status): string
    {
        $title = self::installedExists($rule, false);
        $accepted = self::installedExists($rule, true);
        if ($status === 'missing') {
            return ' AND NOT (' . $title . ')';
        }
        if ($status === 'version') {
            return trim((string)$rule['required_version']) === ''
                ? ' AND 1=0' : ' AND (' . $title . ') AND NOT (' . $accepted . ')';
        }
        // A rule with no version requirement accepts any installation of the name.
        return ' AND NOT (' . $accepted . ')';
    }

    private static function searchWhere(string $search): string
    {
        if ($search === '') {
            return '';
        }
        $pattern = self::safeLike(substr($search, 0, 80), true, true);
        return " AND (c.name LIKE $pattern ESCAPE '!' OR c.serial LIKE $pattern ESCAPE '!')";
    }

    public static function exceptionsCount(array $rule, string $status, int $category = 0, string $search = ''): int
    {
        $where = self::computerFrom((int)$rule['usercategories_id'], $category)
            . self::findingWhere($rule, $status) . self::searchWhere($search);
        $row = self::fetchOne('SELECT COUNT(*) AS total' . $where);
        return (int)($row['total'] ?? 0);
    }

    public static function exceptionsPage(array $rule, string $status, int $category, string $search, int $offset, int $limit): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(1000, $limit));
        $where = self::computerFrom((int)$rule['usercategories_id'], $category)
            . self::findingWhere($rule, $status) . self::searchWhere($search);
        $title = self::installedExists($rule, false);
        $versionLabel = trim((string)$rule['required_version']) === ''
            ? "'Not detected'"
            : "CASE WHEN ($title) THEN 'Version does not match' ELSE 'Not detected' END";
        return self::fetchAll(
            'SELECT c.id, c.name, c.serial, c.last_inventory_update, '
            . "COALESCE(NULLIF(CONCAT_WS(' ', u.firstname, u.realname), ''), u.name, '') AS assigned_user, "
            . "COALESCE(uc.name, 'Unassigned') AS department, "
            . "$versionLabel AS finding"
            . $where . ' ORDER BY c.name ASC, c.id ASC'
            . ' LIMIT ' . $limit . ' OFFSET ' . $offset
        );
    }

    /** One lightweight grouped query; this is a device distribution, not a compliance score. */
    public static function departmentPopulation(int $filterCategory = 0, int $limit = 8): array
    {
        $limit = max(1, min(15, $limit));
        return self::fetchAll(
            "SELECT COALESCE(NULLIF(uc.name, ''), 'No user category') AS department, "
            . 'COUNT(*) AS computers' . self::computerFrom(0, $filterCategory)
            . " GROUP BY uc.id, uc.name ORDER BY computers DESC, department ASC LIMIT " . $limit
        );
    }

    /** Inventory timing is not a software-compliance verdict. */
    public static function inventoryReadiness(int $category = 0): array
    {
        $row = self::fetchOne(
            "SELECT COUNT(*) AS total, "
            . "COALESCE(SUM(CASE WHEN c.last_inventory_update IS NOT NULL "
            . "AND c.last_inventory_update >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END),0) AS recent, "
            . "COALESCE(SUM(CASE WHEN c.last_inventory_update IS NOT NULL "
            . "AND c.last_inventory_update < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END),0) AS stale, "
            . "COALESCE(SUM(CASE WHEN c.last_inventory_update IS NULL THEN 1 ELSE 0 END),0) AS unknown"
            . self::computerFrom(0, $category)
        );
        return ['total'=>(int)($row['total']??0), 'recent'=>(int)($row['recent']??0),
                'stale'=>(int)($row['stale']??0), 'unknown'=>(int)($row['unknown']??0)];
    }

    /** The 30-day freshness filter uses the actual recorded inventory timestamp. */
    private static function inventoryAgeWhere(string $age): string
    {
        switch ($age) {
            case 'recent':
                return ' AND c.last_inventory_update IS NOT NULL AND c.last_inventory_update >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            case 'stale':
                return ' AND c.last_inventory_update IS NOT NULL AND c.last_inventory_update < DATE_SUB(NOW(), INTERVAL 30 DAY)';
            case 'unknown':
                return ' AND c.last_inventory_update IS NULL';
            default:
                return '';
        }
    }

    /** Computers page always paginates on the server; checks one rule only if selected. */
    private static function computerListWhere(?array $rule, int $category, string $search, string $status, string $inventoryAge = 'all'): string
    {
        $where = self::computerFrom($rule ? (int)$rule['usercategories_id'] : 0, $category)
            . self::searchWhere($search) . self::inventoryAgeWhere($inventoryAge);
        if (!$rule) {
            return $where;
        }
        $name = self::installedExists($rule, false);
        $valid = self::installedExists($rule, true);
        if ($status === 'compliant') {
            return $where . ' AND (' . $valid . ')';
        }
        if ($status === 'missing') {
            return $where . ' AND NOT (' . $name . ')';
        }
        if ($status === 'version') {
            return $where . (trim((string)$rule['required_version']) === ''
                ? ' AND 1=0' : ' AND (' . $name . ') AND NOT (' . $valid . ')');
        }
        return $where;
    }

    public static function computerListCount(?array $rule, int $category, string $search, string $status, string $inventoryAge = 'all'): int
    {
        $row = self::fetchOne('SELECT COUNT(*) AS total'
            . self::computerListWhere($rule, $category, $search, $status, $inventoryAge));
        return (int)($row['total'] ?? 0);
    }

    public static function computerListPage(?array $rule, int $category, string $search, string $status, int $offset, int $limit = 25, string $inventoryAge = 'all'): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(100, $limit));
        $verdict = "'Select a rule'";
        if ($rule) {
            $name = self::installedExists($rule, false);
            $valid = self::installedExists($rule, true);
            $verdict = "CASE WHEN ($valid) THEN 'Compliant' "
                . "WHEN NOT ($name) THEN 'Not detected' ELSE 'Version mismatch' END";
        }
        return self::fetchAll('SELECT c.id, c.name, c.serial, c.last_inventory_update, DATEDIFF(NOW(), c.last_inventory_update) AS days_since_inventory, '
            . "COALESCE(NULLIF(CONCAT_WS(' ', u.firstname, u.realname), ''), u.name, '') AS assigned_user, "
            . "COALESCE(NULLIF(uc.name, ''), 'No user category') AS department, "
            . "$verdict AS verdict"
            . self::computerListWhere($rule, $category, $search, $status, $inventoryAge)
            . ' ORDER BY c.name ASC, c.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
    }

    public static function header(string $tab, string $caption = ''): void
    {
        $items = [
            'overview' => ['Overview', 'ti-layout-dashboard', 'overview.php'],
            'rules' => ['Compliance Rules', 'ti-list-check', 'rules.php'],
            'computers' => ['Computers', 'ti-device-desktop', 'computers.php'],
            'exceptions' => ['Findings', 'ti-alert-triangle', 'exceptions.php'],
        ];
        echo '<main class="itc-wrap">';
        echo '<div class="itc-head"><div class="itc-identity">';
        echo '<span class="itc-brand-icon"><i class="ti ti-shield-check"></i></span>';
        echo '<div><h2>IT Software Compliance</h2><p class="itc-muted">'
            . self::h($caption ?: 'Application deployment and required-version coverage') . '</p></div>';
        echo '</div><span class="itc-live-label"><i class="ti ti-database me-1"></i> Current GLPI inventory</span></div>';
        echo '<nav class="itc-tabs" aria-label="Compliance sections">';
        foreach ($items as $id => $item) {
            echo '<a class="' . ($tab === $id ? 'active' : '') . '" href="' . self::h(self::url($item[2]))
                . '"' . ($tab === $id ? ' aria-current="page"' : '') . '>'
                . '<i class="ti ' . self::h($item[1]) . '"></i><span>' . self::h($item[0]) . '</span></a>';
        }
        echo '</nav>';
    }

    public static function footer(): void
    {
        echo '</main>';
    }

    public static function stylesheet(): void
    {
        global $CFG_GLPI;
        $url = rtrim((string)($CFG_GLPI['root_doc'] ?? ''), '/') . '/plugins/itsoftwarecompliance/css/compliance.css';
        echo '<link rel="stylesheet" href="' . self::h($url) . '?v=043">';
    }

    public static function categoryOptions(array $categories, int $current): string
    {
        $output = '<option value="0">All departments</option>';
        foreach ($categories as $row) {
            $id = (int)$row['id'];
            $output .= '<option value="' . $id . '"' . ($id === $current ? ' selected' : '') . '>' . self::h($row['name']) . '</option>';
        }
        return $output;
    }
}
