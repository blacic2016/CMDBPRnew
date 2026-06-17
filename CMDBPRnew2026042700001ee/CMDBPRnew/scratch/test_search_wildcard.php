<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/zabbix_api.php';

$keyword = "*SISTEMA*";
$params = [
    'output' => ['groupid', 'name'],
    'search' => ['name' => $keyword],
    'searchWildcardsEnabled' => true,
    'sortfield' => 'name'
];
$resp = call_zabbix_api('hostgroup.get', $params);

echo "Search for: $keyword\n";
print_r($resp);
