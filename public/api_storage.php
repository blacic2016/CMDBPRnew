<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zabbix_api.php';

header('Content-Type: application/json');

if (!current_user_id()) {
    echo json_encode(['success' => false, 'error' => 'No session']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_storage_analysis':
        $groupid = $_GET['groupid'] ?? null;
        $hostid = $_GET['hostid'] ?? null;

        $params = [
            'output' => ['hostid', 'name'],
            'selectInventory' => ['os'],
            'monitored' => true
        ];
        if ($groupid && $groupid != 'all') $params['groupids'] = (is_array($groupid) ? $groupid : explode(',', $groupid));
        if ($hostid && $hostid != 'all') $params['hostids'] = (is_array($hostid) ? $hostid : explode(',', $hostid));

        $resp = call_zabbix_api('host.get', $params);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'error' => $resp['error']]);
            break;
        }
        $hosts = $resp['result'];
        
        $report_data = [];
        $total_storage_bytes = 0;
        $total_used_bytes = 0;

        foreach ($hosts as $h) {
            $items_resp = call_zabbix_api('item.get', [
                'hostids' => $h['hostid'],
                // Búsqueda MASIVA para no dejar ningún filesystem fuera
                'search' => [ 'key_' => ['vfs', 'vmware', 'hrStorage', 'storage'] ],
                'searchByAny' => true,
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units']
            ]);

            if (isset($items_resp['error'])) continue;
            $items = $items_resp['result'];

            $filesystems = [];
            $detected_platform = 'Zabbix Agent';

            foreach ($items as $item) {
                $mount = ''; $mode = ''; $key = $item['key_'];
                $nameLow = strtolower($item['name']);

                // --- DETERMINACIÓN DE PLATAFORMA (BROAD) ---
                if (strpos($key, 'vmware.vm') !== false) {
                    $detected_platform = 'VMware Guest';
                } else if (strpos($key, 'vmware.datastore') !== false) {
                    $detected_platform = 'VMware Datastore';
                } else if (strpos($key, 'hrStorage') !== false && $detected_platform === 'Zabbix Agent') {
                    $detected_platform = 'SNMP';
                }

                // --- 1. EXTRACCIÓN DE PUNTO DE MONTAJE Y MODO ---
                
                // Zabbix Agent Standard (vfs.fs.size, vfs.fs.dependent.size, etc)
                if (preg_match('/vfs\.fs\.(?:dependent\.)?(?:size\.)?(?:size)?\[(?:["\']?)([^"\'\],]+)(?:["\']?),(.*)\]/', $key, $m)) {
                    $mount = $m[1]; $mode = trim($m[2], '"\' ');
                }
                // VMware VM Guest OS (vCenter) - Soporta 3 o 4 parámetros
                else if (preg_match('/vmware\.vm\.vfs\.fs\.size\[.*?,.*?,(?:["\']?)([^"\'\],]+)(?:["\']?),?(.*)?\]/', $key, $m)) {
                    $mount = $m[1]; $mode = trim($m[2] ?? 'total', '"\' ');
                }
                // VMware Datastore
                else if (preg_match('/vmware\.datastore\.size\[.*?,(?:["\']?)([^"\'\],]+)(?:["\']?),?(.*)?\]/', $key, $m)) {
                    $mount = $m[1]; $mode = trim($m[2] ?? 'total', '"\' ');
                }
                // SNMP (hrStorage) - hrStorageSize[Index] or hrStorageSize.Index
                else if (strpos($key, 'hrStorage') !== false) {
                    $mount = (preg_match('/\[(?:["\']?)([^"\'\],]+)/', $key, $m)) ? $m[1] : (preg_match('/\.(\d+)$/', $key, $m2) ? $m2[1] : '');
                }
                
                // Fallback: Si no hay mount pero el nombre parece descriptivo
                if ($mount === '') {
                    if (preg_match('#on\s+([/\\\\A-Za-z0-9._:-]+)$#i', $item['name'], $m)) $mount = $m[1];
                    else if (preg_match('/\((([A-Z]:)|(\/.*?))\)/i', $item['name'], $m)) $mount = $m[1];
                    else if (preg_match('/discovery:\s+.*:\s+(.*)$/i', $item['name'], $m)) $mount = trim(explode(':', $m[1])[0]);
                    else if (strpos($nameLow, 'fs ') !== false || strpos($nameLow, 'disco ') !== false || strpos($nameLow, 'mount ') !== false) {
                        if (preg_match('/([A-Z]:)/i', $item['name'], $m)) $mount = $m[1];
                    }
                }

                if ($mount !== '') {
                    // --- 2. NORMALIZACIÓN DE MOUNT POINT ---
                    $mount = trim(str_replace(['"', '\''], '', $mount));
                    $mount = rtrim($mount, '\\/'); 
                    if ($mount === '' || $mount === '"') $mount = '/';
                    
                    // Asegurar que C: sea igual a (C:) o c:
                    $mountKey = (preg_match('/^[A-Za-z]:$/', $mount)) ? strtoupper($mount) : $mount;

                    if (!isset($filesystems[$mountKey])) {
                        $filesystems[$mountKey] = ['mount' => $mount, 'total' => 0, 'used' => 0, 'free' => 0, 'pused' => 0, 'pfree' => 0];
                    }
                    
                    $val = (float)$item['lastvalue']; 
                    $u = strtolower($item['units']);

                    // Clasificación inteligente por Key y Nombre
                    $isFreePct = (strpos($mode, 'pfree') !== false || strpos($nameLow, 'free %') !== false || strpos($nameLow, 'free in %') !== false || strpos($nameLow, 'libre %') !== false);
                    $isPct     = ($u == '%' || $mode == 'pused' || strpos($mode, 'util') !== false || strpos($nameLow, 'utilization') !== false || strpos($nameLow, 'utilización') !== false || strpos($nameLow, 'uso %') !== false || strpos($nameLow, 'utilizada %') !== false);
                    $isTotal   = (strpos($mode, 'total') !== false || strpos($nameLow, 'total') !== false || strpos($nameLow, 'capacidad') !== false);
                    $isUsed    = (strpos($mode, 'used') !== false  || strpos($nameLow, 'used') !== false  || strpos($nameLow, 'usado') !== false);
                    $isFree    = (strpos($mode, 'free') !== false  || strpos($nameLow, 'free') !== false  || strpos($nameLow, 'libre') !== false);

                    if ($isFreePct) $filesystems[$mountKey]['pfree'] = $val;
                    else if ($isPct) $filesystems[$mountKey]['pused'] = $val;
                    else if ($isTotal) $filesystems[$mountKey]['total'] = $val;
                    else if ($isUsed) $filesystems[$mountKey]['used'] = $val;
                    else if ($isFree) $filesystems[$mountKey]['free'] = $val;
                    else if ($mode == '' && $filesystems[$mountKey]['total'] == 0) $filesystems[$mountKey]['total'] = $val;
                }
            }

            $host_fs = []; $host_total = 0; $host_used = 0;
            foreach ($filesystems as $m => $fs) {
                // Matemática de Reconstrucción (Crucial para VMware y Agent Dependent)
                if ($fs['total'] == 0) {
                    if ($fs['free'] > 0 && $fs['pfree'] > 0) $fs['total'] = ($fs['free'] / ($fs['pfree'] / 100));
                    else if ($fs['used'] > 0 && $fs['pused'] > 0) $fs['total'] = ($fs['used'] / ($fs['pused'] / 100));
                    else if ($fs['free'] > 0 && $fs['used'] > 0) $fs['total'] = $fs['used'] + $fs['free'];
                }

                if ($fs['used'] == 0) {
                    if ($fs['total'] > 0 && $fs['free'] > 0) $fs['used'] = $fs['total'] - $fs['free'];
                    else if ($fs['total'] > 0 && $fs['pused'] > 0) $fs['used'] = ($fs['total'] * $fs['pused']) / 100;
                    else if ($fs['total'] > 0 && $fs['pfree'] > 0) $fs['used'] = $fs['total'] - ($fs['total'] * $fs['pfree'] / 100);
                }

                if ($fs['pused'] == 0) {
                    if ($fs['total'] > 0 && $fs['used'] > 0) $fs['pused'] = round(($fs['used'] / $fs['total']) * 100, 1);
                    else if ($fs['pfree'] > 0) $fs['pused'] = 100 - $fs['pfree'];
                }
                
                // Filtro de Calidad: Incluir si logramos determinar la capacidad total
                if ($fs['total'] > 0) { 
                    $host_total += $fs['total']; $host_used += $fs['used'];
                    $fs['growth_rate'] = $fs['total'] * 0.00018; 
                    $free_b = $fs['total'] - $fs['used'];
                    $fs['days_until_full'] = $fs['growth_rate'] > 0 ? floor($free_b / $fs['growth_rate']) : 999;
                    $host_fs[] = $fs;
                }
            }

            if (!empty($host_fs)) {
                $report_data[] = [
                    'hostid' => $h['hostid'], 'name' => $h['name'],
                    'platform' => $detected_platform,
                    'os' => $h['inventory']['os'] ?? 'Unknown',
                    'total_space' => $host_total, 'used_space' => $host_used,
                    'usage_pct' => $host_total > 0 ? round(($host_used/$host_total)*100, 1) : 0,
                    'filesystems' => $host_fs
                ];
                $total_storage_bytes += $host_total; $total_used_bytes += $host_used;
            }
        }

        echo json_encode(['success' => true, 'data' => $report_data, 'summary' => ['total_storage' => $total_storage_bytes, 'total_used' => $total_used_bytes, 'avg_growth' => $total_storage_bytes * 0.0002]]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
