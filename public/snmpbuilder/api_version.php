<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/zabbix_api.php';

header('Content-Type: application/json');

$response = call_zabbix_api('apiinfo.version', []);
echo json_encode($response, JSON_PRETTY_PRINT);
