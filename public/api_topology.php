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
    
    // Extract hostnames to find relationships
    $hostnames = array_map(function($h) { return $h['name']; }, $res_hosts['result']);
    $hostnames = array_unique($hostnames);
    
    $links = [];
    if (!empty($hostnames)) {
        $pdo = getPDO();
        // Construct placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($hostnames), '?'));
        
        $sql = "SELECT ci_origen_servicio_hostname as source, ci_destino_infraestructura_hostname as target, relaci_n as type 
                FROM sheet_relaciones 
                WHERE ci_origen_servicio_hostname IN ($placeholders) 
                   OR ci_destino_infraestructura_hostname IN ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        // We pass the hostnames twice because we have two IN clauses
        $stmt->execute(array_merge($hostnames, $hostnames));
        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true, 
        'hosts' => $res_hosts['result'],
        'links' => $links
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no definida']);
