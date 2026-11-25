<?php
if (!defined('GLPI_ROOT')) {
    return;
}

class PluginItsoftwarecomplianceDashboard extends CommonGLPI
{
    public static string $rightname = 'computer';

    public static function getTypeName($nb = 0)
    {
        return 'IT Software Compliance';
    }

    public static function getMenuContent()
    {
        if (!Session::haveRight('computer', READ) || !Session::haveRight('software', READ)) {
            return false;
        }
        $base = '/plugins/itsoftwarecompliance/front/';
        return [
            'title' => 'IT Software Compliance',
            'page' => $base . 'overview.php',
            'icon' => 'ti ti-shield-check',
            'options' => [
                'overview' => ['title' => 'Overview', 'page' => $base . 'overview.php', 'icon' => 'ti ti-layout-dashboard'],
                'rules' => ['title' => 'Compliance Rules', 'page' => $base . 'rules.php', 'icon' => 'ti ti-list-check'],
                'computers' => ['title' => 'Computers', 'page' => $base . 'computers.php', 'icon' => 'ti ti-device-desktop'],
                'exceptions' => ['title' => 'Findings', 'page' => $base . 'exceptions.php', 'icon' => 'ti ti-alert-triangle'],
            ],
        ];
    }
}
