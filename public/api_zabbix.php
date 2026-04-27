<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zabbix_api.php';
require_once __DIR__ . '/../src/db.php';

header('Content-Type: application/json');

if (!current_user_id()) {
    echo json_encode(['success' => false, 'error' => 'No session']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'get_keywords':
        $res = getPDO()->query("SELECT keyword FROM zabbix_keywords ORDER BY keyword ASC");
        echo json_encode(['success' => true, 'data' => $res->fetchAll(PDO::FETCH_COLUMN)]);
        break;

    case 'get_groups':
        $keyword = $_GET['keyword'] ?? '';
        $params = [
            'output' => ['groupid', 'name'],
            'sortfield' => 'name'
        ];
        if ($keyword) {
            $params['search'] = ['name' => '*' . $keyword . '*'];
            $params['searchWildcardsEnabled'] = true;
        }
        $resp = call_zabbix_api('hostgroup.get', $params);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'error' => $resp['error']]);
        } else {
            echo json_encode(['success' => true, 'data' => $resp['result']]);
        }
        break;

    case 'get_templates':
        $resp = call_zabbix_api('template.get', [
            'output' => ['templateid', 'name'],
            'sortfield' => 'name'
        ]);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'error' => $resp['error']]);
        } else {
            echo json_encode(['success' => true, 'data' => $resp['result']]);
        }
        break;

    case 'get_hosts':
        $groupids = $_GET['groupids'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $params = [
            'output' => ['hostid', 'host', 'name', 'status', 'available', 'snmp_available', 'ipmi_available', 'jmx_available'],
            'selectHostGroups' => 'extend',
            'selectParentTemplates' => ['templateid', 'name'],
            'selectTags' => 'extend',
            'selectMacros' => 'extend',
            'selectInventory' => 'extend',
            'selectInterfaces' => ['interfaceid', 'ip', 'type', 'main', 'port', 'useip', 'available'],
            'sortfield' => 'name'
        ];

        if ($groupids) {
            $params['groupids'] = explode(',', $groupids);
        }
        if ($search) {
            $params['search'] = ['name' => $search, 'host' => $search];
            $params['searchByAny'] = true;
        }

        // --- Soporte para Filtro de Tags ---
        $tags_json = $_GET['tags'] ?? '';
        if ($tags_json) {
            $tags_data = json_decode($tags_json, true);
            if (is_array($tags_data)) {
                $params['tags'] = $tags_data;
                $params['evaltype'] = (int)($_GET['evaltype'] ?? 0); // 0 = AND/OR, 2 = AND
            }
        }

        $resp = call_zabbix_api('host.get', $params);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'error' => $resp['error']]);
        } else {
            $hosts = $resp['result'];
            
            // --- Procesar Disponibilidad para Zabbix 6.x+ ---
            foreach ($hosts as &$h) {
                $h['available'] = (int)($h['available'] ?? 0);
                $h['snmp_available'] = (int)($h['snmp_available'] ?? 0);
                
                if (isset($h['interfaces'])) {
                    foreach ($h['interfaces'] as $iface) {
                        // Type 1 = Agent, Type 2 = SNMP
                        if ($iface['type'] == 1 && $h['available'] == 0) {
                            $h['available'] = (int)$iface['available'];
                        }
                        if ($iface['type'] == 2) {
                            $h['snmp_available'] = (int)$iface['available'];
                        }
                    }
                }
            }

            // --- Soporte para Verificaciones de Estado (Ping) ---
            $with_checks = $_GET['with_checks'] ?? '';
            if (strpos($with_checks, 'icmp') !== false && !empty($hosts)) {
                $hostids = array_column($hosts, 'hostid');
                // Buscamos el ítem de ping (comúnmente icmpping)
                $items_resp = call_zabbix_api('item.get', [
                    'hostids' => $hostids,
                    'search' => ['key_' => 'icmpping'],
                    'output' => ['hostid', 'lastvalue', 'key_'],
                    'filter' => ['status' => 0] // Solo items habilitados
                ]);
                
                if (!isset($items_resp['error'])) {
                    $ping_map = [];
                    foreach ($items_resp['result'] as $item) {
                        // Priorizamos icmpping exacto si hay varios similares
                        if ($item['key_'] == 'icmpping' || !isset($ping_map[$item['hostid']])) {
                            $ping_map[$item['hostid']] = $item['lastvalue'];
                        }
                    }
                    foreach ($hosts as &$h) {
                        if (isset($ping_map[$h['hostid']])) {
                            $h['icmp_ping'] = $ping_map[$h['hostid']];
                        } else {
                            $h['icmp_ping'] = null; // No tiene item de ping
                        }
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => $hosts]);
        }
        break;

    case 'get_host_details':
        $hostid = $_GET['hostid'] ?? '';
        if (!$hostid) exit(json_encode(['success'=>false, 'error'=>'Missing hostid']));

        $resp = call_zabbix_api('host.get', [
            'hostids' => $hostid,
            'output' => 'extend',
            'selectGroups' => 'extend',
            'selectParentTemplates' => 'extend',
            'selectTags' => 'extend',
            'selectMacros' => 'extend',
            'selectInventory' => 'extend',
            'selectInterfaces' => 'extend'
        ]);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'error' => $resp['error']]);
        } else {
            echo json_encode(['success' => true, 'data' => $resp['result'][0] ?? null]);
        }
        break;

    case 'update_hosts_bulk':
        $hostids = $_POST['hostids'] ?? [];
        $templates = $_POST['templates'] ?? null; // array of templateids
        $groups = $_POST['groups'] ?? null; // array of groupids
        $inventory_mode = $_POST['inventory_mode'] ?? null; // 1 = automatic

        if (empty($hostids)) exit(json_encode(['success'=>false, 'error'=>'No hosts selected']));

        $results = []; $errors = [];
        foreach ($hostids as $hid) {
            $data = ['hostid' => $hid];
            if ($templates !== null) $data['templates'] = array_map(fn($id) => ['templateid' => $id], $templates);
            if ($groups !== null) $data['groups'] = array_map(fn($id) => ['groupid' => $id], $groups);
            if ($inventory_mode !== null) $data['inventory_mode'] = (int)$inventory_mode;

            $resp = call_zabbix_api('host.update', $data);
            if (isset($resp['error'])) $errors[] = "Host $hid: " . $resp['error'];
            else $results[] = $hid;
        }

        echo json_encode([
            'success' => empty($errors),
            'updated' => count($results),
            'errors' => $errors
        ]);
        break;

    case 'delete_hosts':
        $hostids = $_POST['hostids'] ?? [];
        if (empty($hostids)) exit(json_encode(['success'=>false, 'error'=>'No hosts selected']));

        $resp = call_zabbix_api('host.delete', $hostids);
        if (isset($resp['error'])) {
            echo json_encode(['success' => false, 'error' => $resp['error']]);
        } else {
            echo json_encode(['success' => true, 'deleted' => count($hostids)]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
