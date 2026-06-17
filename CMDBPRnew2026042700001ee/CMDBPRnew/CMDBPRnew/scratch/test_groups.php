<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/zabbix_api.php';

$resp = call_zabbix_api('hostgroup.get', [
    'output' => ['name'],
    'limit' => 20
]);

echo "ZABBIX_API_URL: " . ZABBIX_API_URL . "\n";
print_r($resp);
