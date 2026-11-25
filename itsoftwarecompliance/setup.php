<?php
/** IT Software Compliance - GLPI 12 series. */
define('PLUGIN_ITSOFTWARECOMPLIANCE_VERSION', '0.4.3');
define('PLUGIN_ITSOFTWARECOMPLIANCE_MIN_GLPI', '12.0.0');
define('PLUGIN_ITSOFTWARECOMPLIANCE_MAX_GLPI', '12.0.99');

function plugin_version_itsoftwarecompliance(): array
{
    return [
        'name' => 'IT Software Compliance',
        'version' => PLUGIN_ITSOFTWARECOMPLIANCE_VERSION,
        'author' => 'Piseth SEANG',
        'license' => 'GPLv3+',
        'homepage' => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_ITSOFTWARECOMPLIANCE_MIN_GLPI,
                'max' => PLUGIN_ITSOFTWARECOMPLIANCE_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_itsoftwarecompliance_check_prerequisites(): bool
{
    return version_compare(GLPI_VERSION, PLUGIN_ITSOFTWARECOMPLIANCE_MIN_GLPI, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_ITSOFTWARECOMPLIANCE_MAX_GLPI, '<=');
}

function plugin_itsoftwarecompliance_check_config($verbose = false): bool
{
    return true;
}

function plugin_init_itsoftwarecompliance(): void
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['itsoftwarecompliance'] = true;
    Plugin::registerClass('PluginItsoftwarecomplianceDashboard');
    if (Session::haveRight('computer', READ) && Session::haveRight('software', READ)) {
        $PLUGIN_HOOKS['menu_toadd']['itsoftwarecompliance'] = [
            'tools' => 'PluginItsoftwarecomplianceDashboard',
        ];
    }
}
