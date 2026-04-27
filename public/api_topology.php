<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zabbix_api.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_login();

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'get_groups') {
    // Return all groups as requested, so they can always be selected
    $res = call_zabbix_api('hostgroup.get', [
        'output' => ['groupid', 'name']
    ]);
    
    if (isset($res['error'])) {
        echo json_encode(['success' => false, 'error' => $res['error']]);
        exit;
    }
    
    echo json_encode(['success' => true, 'result' => $res['result']]);
    exit;
}

if ($action === 'get_topology') {
    $pais = $_GET['pais'] ?? '';
    $ciudad = $_GET['ciudad'] ?? '';
    $cliente = $_GET['cliente'] ?? '';
    $subgrupo = $_GET['subgrupo'] ?? '';

    // Search for all groups matching these keywords
    $search_params = [];
    if($pais !== 'N/A') $search_params[] = $pais;
    if($ciudad !== 'GENERAL') $search_params[] = $ciudad;
    if($cliente !== 'GENERAL') $search_params[] = $cliente;
    if($subgrupo && $subgrupo !== 'GENERAL') $search_params[] = $subgrupo;
    
    // We get all groups to filter them on our side by the combination of tokens
    $res_groups = call_zabbix_api('hostgroup.get', ['output' => ['groupid', 'name']]);
    
    $target_group_ids = [];
    foreach ($res_groups['result'] as $g) {
        $match = true;
        foreach ($search_params as $token) {
            if (stripos($g['name'], $token) === false) {
                $match = false;
                break;
            }
        }
        if ($match) $target_group_ids[] = $g['groupid'];
    }

    if (empty($target_group_ids)) {
        echo json_encode(['success' => true, 'hosts' => [], 'links' => []]);
        exit;
    }

    // 2. Get hosts for all these groups
    $res_hosts = call_zabbix_api('host.get', [
        'groupids' => $target_group_ids,
        'output' => ['hostid', 'host', 'name', 'status'],
        'selectInterfaces' => ['ip'],
        'selectInventory' => ['type', 'model', 'notes']
    ]);
    
    if (isset($res_hosts['error'])) {
        echo json_encode(['success' => false, 'error' => $res_hosts['error']]);
        exit;
    }
    
    $all_hosts = $res_hosts['result'];
    $fetched_ids = array_column($all_hosts, 'hostid');
    $queue = $fetched_ids;
    $processed_ports = [];
    $depth = 0;
    $max_depth = 3; // Limitar profundidad para evitar bucles o lentitud

    $pdo = getPDO();

    while (!empty($queue) && $depth < $max_depth) {
        $placeholders = implode(',', array_fill(0, count($queue), '?'));
        
        // Obtener interfaces para los hosts actuales en la cola
        $sql_if = "SELECT hostid, interface_name as name, status, connected_hostid FROM host_interfaces WHERE hostid IN ($placeholders)";
        $stmt_if = $pdo->prepare($sql_if);
        $stmt_if->execute($queue);
        $if_rows = $stmt_if->fetchAll(PDO::FETCH_ASSOC);

        $next_queue = [];
        foreach ($if_rows as $row) {
            $processed_ports[$row['hostid']][] = [
                'name' => $row['name'],
                'status' => strtolower($row['status']) == 'up' ? 1 : 2,
                'connected_hostid' => $row['connected_hostid']
            ];

            if ($row['connected_hostid'] && !in_array($row['connected_hostid'], $fetched_ids)) {
                $next_queue[] = $row['connected_hostid'];
                $fetched_ids[] = $row['connected_hostid'];
            }
        }

        if (!empty($next_queue)) {
            // Buscar datos de Zabbix para los nuevos equipos descubiertos
            $res_extra = call_zabbix_api('host.get', [
                'hostids' => $next_queue,
                'output' => ['hostid', 'host', 'name', 'status'],
                'selectInterfaces' => ['ip'],
                'selectInventory' => ['type', 'model', 'notes']
            ]);
            if (isset($res_extra['result'])) {
                foreach ($res_extra['result'] as $extra) {
                    $all_hosts[] = $extra;
                }
            }
        }

        $queue = $next_queue;
        $depth++;
    }

    // Unir puertos a los hosts correspondientes
    foreach ($all_hosts as &$h) {
        $h['ports'] = $processed_ports[$h['hostid']] ?? [];
    }

    // Extract hostnames to find relationships (Legacy fallback)
    $hostnames = array_map(function($h) { return $h['name']; }, $all_hosts);
    $hostnames = array_unique($hostnames);
    
    $links = [];
    if (!empty($hostnames)) {
        $placeholders_rel = implode(',', array_fill(0, count($hostnames), '?'));
        $sql_rel = "SELECT ci_origen_servicio_hostname as source, ci_destino_infraestructura_hostname as target, relaci_n as type 
                FROM sheet_relaciones 
                WHERE ci_origen_servicio_hostname IN ($placeholders_rel) 
                   OR ci_destino_infraestructura_hostname IN ($placeholders_rel)";
        
        $stmt_rel = $pdo->prepare($sql_rel);
        $stmt_rel->execute(array_merge($hostnames, $hostnames));
        $links = $stmt_rel->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'success' => true, 
        'hosts' => $all_hosts,
        'links' => $links
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no definida']);
