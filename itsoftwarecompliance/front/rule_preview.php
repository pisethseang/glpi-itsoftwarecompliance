<?php
/** Read-only, explicitly permission-checked draft policy preview. */
include('../../../inc/includes.php');
PluginItsoftwarecomplianceCompliance::checkView();
PluginItsoftwarecomplianceCompliance::checkManage();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'GET required.']);
        return;
    }
    $operation = (string)($_GET['operation'] ?? 'titles');
    if (!in_array($operation, ['titles', 'preview'], true)) {
        throw new InvalidArgumentException('Invalid preview operation.');
    }
    $result = PluginItsoftwarecomplianceCompliance::previewRule([
        'software_pattern' => $_GET['software_pattern'] ?? '',
        'match_mode' => $_GET['match_mode'] ?? 'contains',
        'required_version' => $_GET['required_version'] ?? '',
        'usercategories_id' => $_GET['usercategories_id'] ?? 0,
    ], $operation === 'preview');
    echo json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[IT Software Compliance] Preview failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Preview unavailable. Check the GLPI PHP error log.']);
}
