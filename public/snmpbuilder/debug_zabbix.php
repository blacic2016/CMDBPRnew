<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/zabbix_api.php';

header('Content-Type: application/json');

$params = [
    'output' => ['hostid', 'host', 'name'],
    'selectInterfaces' => ['interfaceid', 'ip', 'type', 'useip', 'details'],
    'selectMacros' => 'extend',
    'selectInheritedMacros' => 'extend',
    'limit' => 5
];

$response = call_zabbix_api('host.get', $params);
echo json_encode($response, JSON_PRETTY_PRINT);
