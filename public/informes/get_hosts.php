<?php
/**
 * get_hosts.php
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/get_hosts.php
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/zabbix_api.php';
require_login();

header('Content-Type: application/json');

$hostgroup_name = $_GET['hostgroup'] ?? '';

if (empty($hostgroup_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Grupo de host no especificado.']);
    exit;
}

try {
    // 1. Obtener ID del grupo de hosts
    $host_groups_response = call_zabbix_api('hostgroup.get', [
        'output' => ['groupid', 'name'],
        'filter' => ['name' => $hostgroup_name]
    ]);

    if (isset($host_groups_response['error'])) {
        throw new Exception($host_groups_response['error']);
    }

    $group_id = null;
    if (isset($host_groups_response['result']) && is_array($host_groups_response['result'])) {
        foreach ($host_groups_response['result'] as $group) {
            if ($group['name'] === $hostgroup_name) {
                $group_id = $group['groupid'];
                break;
            }
        }
    }

    if ($group_id === null) {
        throw new Exception("Grupo de host no encontrado.");
    }

    // 2. Obtener hosts del grupo
    $hosts_response = call_zabbix_api('host.get', [
        'output' => ['hostid', 'name'],
        'groupids' => [$group_id],
        'selectParentTemplates' => ['templateid', 'name'], 
        'monitored' => 1,
        'sortfield' => 'name'
    ]);

    if (isset($hosts_response['error'])) {
        throw new Exception($hosts_response['error']);
    }

    $valid_hosts = [];
    if (isset($hosts_response['result']) && is_array($hosts_response['result'])) {
        foreach ($hosts_response['result'] as $host) {
            // Filtrar templates o elementos no deseados de la misma manera que process_report.php
            if (isset($host['host']) && $host['host'] === $host['name'] && !empty($host['templateid'])) {
                continue;
            }
            $valid_hosts[] = [
                'hostid' => $host['hostid'],
                'name' => $host['name']
            ];
        }
    }

    echo json_encode($valid_hosts);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener hosts desde Zabbix: ' . $e->getMessage()]);
}
?>
