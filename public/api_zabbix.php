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

    case 'get_interfaces_data':
        $hostid = $_GET['hostid'] ?? '';
        if (!$hostid) exit(json_encode(['success'=>false, 'error'=>'Missing hostid']));

        // BROAD SEARCH: Fetch items that commonly represent network interfaces
        $resp = call_zabbix_api('item.get', [
            'hostids' => $hostid,
            'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units'],
            'search' => [
                'key_' => ['net.if.*', 'ifHC*', 'ifIn*', 'ifOut*', 'ifOperStatus*', 'ifAlias*']
            ],
            'searchWildcardsEnabled' => true,
            'searchByAny' => true,
            'filter' => ['status' => 0]
        ]);

        if (isset($resp['error'])) {
            // If Zabbix fails, try to load from local DB as fallback
            $stmt = getPDO()->prepare("SELECT * FROM host_interfaces WHERE hostid = ?");
            $stmt->execute([$hostid]);
            $local_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($local_data)) {
                echo json_encode(['success' => true, 'data' => $local_data, 'source' => 'db_fallback']);
            } else {
                echo json_encode(['success' => false, 'error' => $resp['error']]);
            }
            exit;
        }

        $items = $resp['result'];
        $interfaces = [];

        foreach ($items as $item) {
            $attr = ''; $index = '';
            
            // Pattern 1: net.if.X[index]
            if (preg_match('/^net\.if\.(in|out|status|name|alias|vlan)\[([^\]]+)\]/i', $item['key_'], $matches)) {
                $attr = strtolower($matches[1]);
                $if_key = $matches[2];
                $parts = explode('.', $if_key);
                $index = end($parts);
            }
            // Pattern 2: ifX[index] (e.g., ifInOctets[1] or ifAlias[GigabitEthernet1])
            elseif (preg_match('/^(if(?:HC)?(?:In|Out|OperStatus|Alias|Name|Vlan))\[([^\]]+)\]/i', $item['key_'], $matches)) {
                $key_name = strtolower($matches[1]);
                $index = $matches[2];
                
                if (strpos($key_name, 'in') !== false) $attr = 'in';
                elseif (strpos($key_name, 'out') !== false) $attr = 'out';
                elseif (strpos($key_name, 'status') !== false) $attr = 'status';
                elseif (strpos($key_name, 'alias') !== false) $attr = 'alias';
                elseif (strpos($key_name, 'name') !== false) $attr = 'name';
                elseif (strpos($key_name, 'vlan') !== false) $attr = 'vlan';
            }

            if ($attr && $index) {
                // Clean index (remove quotes if present)
                $index = trim($index, '"\'');
                
                if (!isset($interfaces[$index])) {
                    $interfaces[$index] = [
                        'hostid' => $hostid,
                        'interface_index' => $index,
                        'interface_name' => '', 
                        'alias' => '',
                        'interface_type' => 'Other', // Default
                        'bits_sent' => 0,
                        'bits_received' => 0,
                        'vlan' => '',
                        'status' => 'Unknown',
                        'connected_hostid' => null
                    ];
                }

                // Try to extract name and alias from the item name if possible
                if (preg_match('/Interface\s+([^\(\:]+)(?:\(([^\)]+)\))?/i', $item['name'], $nameMatches)) {
                    if (empty($interfaces[$index]['interface_name'])) {
                        $interfaces[$index]['interface_name'] = trim($nameMatches[1]);
                    }
                    if (empty($interfaces[$index]['alias']) && isset($nameMatches[2])) {
                        $interfaces[$index]['alias'] = trim($nameMatches[2]);
                    }
                }

                if (!empty($interfaces[$index]['interface_name'])) {
                    if (preg_match('/^([a-zA-Z]+)/', $interfaces[$index]['interface_name'], $typeMatch)) {
                        $interfaces[$index]['interface_type'] = $typeMatch[1];
                    }
                }

                switch ($attr) {
                    case 'name': $interfaces[$index]['interface_name'] = $item['lastvalue'] ?: $interfaces[$index]['interface_name']; break;
                    case 'alias': $interfaces[$index]['alias'] = $item['lastvalue'] ?: $interfaces[$index]['alias']; break;
                    case 'in': $interfaces[$index]['bits_received'] = (float)$item['lastvalue']; break;
                    case 'out': $interfaces[$index]['bits_sent'] = (float)$item['lastvalue']; break;
                    case 'status': 
                        $val = (int)$item['lastvalue'];
                        $interfaces[$index]['status'] = ($val === 1) ? 'Up' : (($val === 2) ? 'Down' : 'Unknown');
                        break;
                    case 'vlan': $interfaces[$index]['vlan'] = $item['lastvalue']; break;
                }
            }
        }

        $pdo = getPDO();
        // UPSERT into host_interfaces to persist data
        foreach ($interfaces as $iface) {
            if (empty($iface['interface_name'])) $iface['interface_name'] = "Interface " . $iface['interface_index'];
            
            $stmt = $pdo->prepare("INSERT INTO host_interfaces (hostid, interface_index, interface_name, interface_type, alias, vlan, status, bits_received, bits_sent) 
                                   VALUES (:hostid, :if_idx, :if_name, :if_type, :alias, :vlan, :status, :in, :out)
                                   ON DUPLICATE KEY UPDATE 
                                     interface_name = VALUES(interface_name),
                                     interface_type = VALUES(interface_type),
                                     alias = VALUES(alias),
                                     vlan = VALUES(vlan),
                                     status = VALUES(status),
                                     bits_received = VALUES(bits_received),
                                     bits_sent = VALUES(bits_sent)");
            $stmt->execute([
                ':hostid' => $iface['hostid'],
                ':if_idx' => $iface['interface_index'],
                ':if_name' => $iface['interface_name'],
                ':if_type' => $iface['interface_type'] ?? 'Other',
                ':alias' => $iface['alias'],
                ':vlan' => $iface['vlan'],
                ':status' => $iface['status'],
                ':in' => $iface['bits_received'],
                ':out' => $iface['bits_sent']
            ]);
        }

        // LOAD EVERYTHING FROM DB (now updated) TO GET CONNECTIONS, sorted by type and name
        $stmt = $pdo->prepare("SELECT * FROM host_interfaces WHERE hostid = ? ORDER BY interface_type ASC, interface_name ASC");
        $stmt->execute([$hostid]);
        $final_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch connected host names
        $connected_hostids = array_filter(array_column($final_data, 'connected_hostid'));
        if (!empty($connected_hostids)) {
            $h_resp = call_zabbix_api('host.get', [
                'hostids' => array_unique($connected_hostids),
                'output' => ['hostid', 'name']
            ]);
            if (!isset($h_resp['error'])) {
                $h_names = array_column($h_resp['result'], 'name', 'hostid');
                foreach ($final_data as &$iface) {
                    if ($iface['connected_hostid']) {
                        $iface['connected_host_name'] = $h_names[$iface['connected_hostid']] ?? 'Unknown Host';
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'data' => $final_data]);
        break;

    case 'save_interface_connection':
        $hostid = $_POST['hostid'] ?? '';
        $interface_index = $_POST['interface_name'] ?? ''; // From UI, it sends the index
        $connected_hostid = $_POST['connected_hostid'] ?? '';

        if (!$hostid || !$interface_index || !$connected_hostid) {
            exit(json_encode(['success' => false, 'error' => 'Missing parameters']));
        }

        $stmt = getPDO()->prepare("UPDATE host_interfaces SET connected_hostid = ? WHERE hostid = ? AND interface_index = ?");
        $success = $stmt->execute([$connected_hostid, $hostid, $interface_index]);

        echo json_encode(['success' => $success]);
        break;

    case 'delete_interface_connection':
        $hostid = $_POST['hostid'] ?? '';
        $interface_index = $_POST['interface_name'] ?? '';

        if (!$hostid || !$interface_index) {
            exit(json_encode(['success' => false, 'error' => 'Missing parameters']));
        }

        $stmt = getPDO()->prepare("UPDATE host_interfaces SET connected_hostid = NULL WHERE hostid = ? AND interface_index = ?");
        $success = $stmt->execute([$hostid, $interface_index]);

        echo json_encode(['success' => $success]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
