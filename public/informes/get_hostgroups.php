<?php
/**
 * get_hostgroups.php
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/get_hostgroups.php
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/zabbix_api.php';
require_login();

header('Content-Type: application/json');

try {
    $response = call_zabbix_api('hostgroup.get', [
        'output' => ['name'],
        'with_monitored_hosts' => true,
        'sortfield' => 'name'
    ]);

    if (isset($response['error'])) {
        throw new Exception($response['error']);
    }

    $host_group_names = [];
    if (isset($response['result']) && is_array($response['result'])) {
        foreach ($response['result'] as $group) {
            $host_group_names[] = $group['name'];
        }
    }

    echo json_encode($host_group_names);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener hostgroups desde Zabbix: ' . $e->getMessage()]);
}
?>
