<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/permissions_helper.php';
require_once __DIR__ . '/../../src/zabbix_api.php';

require_login();
if (!has_module_access('snmp')) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

header('Content-Type: application/json');

$response = call_zabbix_api('apiinfo.version', []);
echo json_encode($response, JSON_PRETTY_PRINT);
