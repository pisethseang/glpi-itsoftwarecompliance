<?php
/** The only schema change is the plugin-owned rules table. */
function plugin_itsoftwarecompliance_install(): bool
{
    global $DB;
    $table = 'glpi_plugin_itsoftwarecompliance_rules';
    if (!$DB->tableExists($table)) {
        $charset = DBConnection::getDefaultCharset();
        $collation = DBConnection::getDefaultCollation();
        $pk = DBConnection::getDefaultPrimaryKeySignOption();
        $sql = "CREATE TABLE `$table` (
            `id` int $pk NOT NULL AUTO_INCREMENT,
            `name` varchar(180) NOT NULL,
            `software_pattern` varchar(180) NOT NULL,
            `match_mode` varchar(16) NOT NULL DEFAULT 'contains',
            `required_version` varchar(120) NOT NULL DEFAULT '',
            `usercategories_id` int NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 1,
            `date_creation` datetime DEFAULT NULL,
            `date_mod` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `is_active` (`is_active`),
            KEY `usercategories_id` (`usercategories_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$charset COLLATE=$collation";
        $DB->doQuery($sql);
    }
    return true;
}

/** Preserve policy rules on uninstall; remove only when explicitly requested by an administrator. */
function plugin_itsoftwarecompliance_uninstall(): bool
{
    return true;
}
