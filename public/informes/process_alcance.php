<?php
/**
 * process_alcance.php
 * Endpoint para el Informe de Alcance, Monitoreo por Equipo y Alertas de Plantilla
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/process_alcance.php
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/zabbix_api.php';
require_once __DIR__ . '/../../src/db.php';

if (php_sapi_name() !== 'cli') {
    require_login();
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_general_alcance';

try {
    $pdo = getPDO();

    switch ($action) {
        case 'get_general_alcance':
            // 1. Cargar mapeos de CMDB local
            $zbx_id_to_type = [];
            $ip_to_type = [];

            // sheet_ap
            $stmt = $pdo->query("SELECT zabbix_host_id, ip FROM sheet_ap");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['zabbix_host_id'])) $zbx_id_to_type[$row['zabbix_host_id']] = 'AP';
                if (!empty($row['ip'])) $ip_to_type[trim($row['ip'])] = 'AP';
            }

            // sheet_firewall
            $stmt = $pdo->query("SELECT zabbix_host_id, monitoring_access_ip FROM sheet_firewall");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['zabbix_host_id'])) $zbx_id_to_type[$row['zabbix_host_id']] = 'FIREWALL';
                if (!empty($row['monitoring_access_ip'])) $ip_to_type[trim($row['monitoring_access_ip'])] = 'FIREWALL';
            }

            // sheet_servers_virtuales
            $stmt = $pdo->query("SELECT zabbix_host_id, ip FROM sheet_servers_virtuales");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['zabbix_host_id'])) $zbx_id_to_type[$row['zabbix_host_id']] = 'SERVER VIRTUAL';
                if (!empty($row['ip'])) $ip_to_type[trim($row['ip'])] = 'SERVER VIRTUAL';
            }

            // sheet_servers_f_sicos
            try {
                $stmt = $pdo->query("SELECT direcci_n_ip FROM sheet_servers_f_sicos");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (!empty($row['direcci_n_ip'])) $ip_to_type[trim($row['direcci_n_ip'])] = 'SERVER FISICO';
                }
            } catch (Exception $e) {
                // Si falla por estructura de tabla, ignoramos
            }

            // sheet_equipos (Contiene ROUTER y SWITCH)
            $stmt = $pdo->query("SELECT zabbix_host_id, ip, tipo FROM sheet_equipos");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $type = (strtoupper($row['tipo'] ?? '') === 'ROUTER') ? 'ROUTER' : 'SWITCH';
                if (!empty($row['zabbix_host_id'])) $zbx_id_to_type[$row['zabbix_host_id']] = $type;
                if (!empty($row['ip'])) $ip_to_type[trim($row['ip'])] = $type;
            }

            // sheet_ups
            $stmt = $pdo->query("SELECT zabbix_host_id, direcci_n_ip FROM sheet_ups");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['zabbix_host_id'])) $zbx_id_to_type[$row['zabbix_host_id']] = 'UPS';
                if (!empty($row['direcci_n_ip'])) $ip_to_type[trim($row['direcci_n_ip'])] = 'UPS';
            }

            // 2. Obtener hosts desde Zabbix API
            $zbx_hosts = call_zabbix_api('host.get', [
                'output' => ['hostid', 'host', 'name', 'status', 'available', 'snmp_available'],
                'selectHostGroups' => ['groupid', 'name'],
                'selectParentTemplates' => ['templateid', 'name'],
                'selectInterfaces' => ['interfaceid', 'ip', 'type', 'main', 'available']
            ]);

            if (isset($zbx_hosts['error'])) {
                throw new Exception("Error al consultar Zabbix API: " . json_encode($zbx_hosts['error']));
            }

            $hosts = $zbx_hosts['result'] ?? [];

            // 3. Procesar y clasificar
            $types = ['ROUTER', 'SWITCH', 'SERVER VIRTUAL', 'SERVER FISICO', 'FIREWALL', 'AP', 'UPS', 'OTRO'];
            
            $by_type = [];
            foreach ($types as $t) {
                $by_type[$t] = ['total' => 0, 'monitored' => 0, 'unmonitored' => 0];
            }

            $by_method = [
                'ping' => 0,
                'ping_snmp' => 0,
                'ping_agent' => 0,
                'none' => 0
            ];

            $group_type_state_map = [];
            $hosts_detail = [];

            // Función interna de clasificación
            $classify = function($host) use ($zbx_id_to_type, $ip_to_type) {
                $hostid = $host['hostid'];
                if (isset($zbx_id_to_type[$hostid])) {
                    return $zbx_id_to_type[$hostid];
                }
                if (isset($host['interfaces'])) {
                    foreach ($host['interfaces'] as $iface) {
                        $ip = trim($iface['ip'] ?? '');
                        if ($ip && isset($ip_to_type[$ip])) {
                            return $ip_to_type[$ip];
                        }
                    }
                }

                $name = strtolower($host['name'] ?? '');
                $hostname = strtolower($host['host'] ?? '');
                
                $groups = [];
                if (isset($host['hostgroups'])) {
                    foreach ($host['hostgroups'] as $g) {
                        $groups[] = strtolower($g['name'] ?? '');
                    }
                }
                $templates = [];
                if (isset($host['parentTemplates'])) {
                    foreach ($host['parentTemplates'] as $t) {
                        $templates[] = strtolower($t['name'] ?? '');
                    }
                }
                
                $all_text = $name . ' ' . $hostname . ' ' . implode(' ', $groups) . ' ' . implode(' ', $templates);

                if (strpos($all_text, 'firewall') !== false || strpos($all_text, 'fortinet') !== false || strpos($all_text, 'fortigate') !== false || strpos($all_text, 'fw-') !== false || strpos($all_text, 'fw_') !== false) {
                    return 'FIREWALL';
                }
                if (strpos($all_text, 'router') !== false || strpos($all_text, 'routing') !== false || strpos($all_text, 'rt-') !== false || strpos($all_text, 'rt_') !== false) {
                    return 'ROUTER';
                }
                if (strpos($all_text, 'ap-') !== false || strpos($all_text, 'ap_') !== false || strpos($all_text, 'wireless') !== false || strpos($all_text, 'unifi') !== false || strpos($all_text, 'aruba') !== false || strpos($all_text, 'access point') !== false || strpos($all_text, 'wifi') !== false) {
                    return 'AP';
                }
                if (strpos($all_text, 'switch') !== false || strpos($all_text, 'switching') !== false || strpos($all_text, 'sw-') !== false || strpos($all_text, 'sw_') !== false) {
                    return 'SWITCH';
                }
                if (strpos($all_text, 'vmware') !== false || strpos($all_text, 'virtual') !== false || strpos($all_text, 'hyper-v') !== false || strpos($all_text, 'proxmox') !== false || strpos($all_text, 'srv-v') !== false || strpos($all_text, 'srv_v') !== false) {
                    return 'SERVER VIRTUAL';
                }
                if (strpos($all_text, 'server') !== false || strpos($all_text, 'linux') !== false || strpos($all_text, 'windows') !== false || strpos($all_text, 'srv-') !== false || strpos($all_text, 'srv_') !== false) {
                    return 'SERVER FISICO';
                }
                if (strpos($all_text, 'ups') !== false || strpos($all_text, 'apc') !== false || strpos($all_text, 'bateria') !== false) {
                    return 'UPS';
                }
                
                return 'OTRO';
            };

            $total_monitored = 0;
            $total_unmonitored = 0;

            foreach ($hosts as $host) {
                // Clasificar tipo
                $type = $classify($host);
                
                // Clasificar estado (status: 0 = monitored, 1 = unmonitored)
                $status_val = (int)($host['status'] ?? 0);
                $is_monitored = ($status_val === 0);
                $state_str = $is_monitored ? 'Monitoreado' : 'No Monitoreado';

                if ($is_monitored) {
                    $total_monitored++;
                    $by_type[$type]['monitored']++;
                } else {
                    $total_unmonitored++;
                    $by_type[$type]['unmonitored']++;
                }
                $by_type[$type]['total']++;

                // Clasificar método de monitoreo
                $method_str = 'Sin Monitoreo';
                if ($is_monitored) {
                    $has_agent = false;
                    $has_snmp = false;
                    if (isset($host['interfaces'])) {
                        foreach ($host['interfaces'] as $iface) {
                            if ((int)$iface['type'] === 1) $has_agent = true;
                            if ((int)$iface['type'] === 2) $has_snmp = true;
                        }
                    }
                    if ($has_snmp) {
                        $method_str = 'Ping y SNMP';
                        $by_method['ping_snmp']++;
                    } elseif ($has_agent) {
                        $method_str = 'Ping y Agente';
                        $by_method['ping_agent']++;
                    } else {
                        $method_str = 'Ping (ICMP)';
                        $by_method['ping']++;
                    }
                } else {
                    $by_method['none']++;
                }

                // Agrupación por Zabbix Host Group
                $host_groups = $host['hostgroups'] ?? [];
                if (empty($host_groups)) {
                    $host_groups = [['name' => 'Sin Grupo']];
                }

                foreach ($host_groups as $g) {
                    $gname = $g['name'] ?? 'Sin Grupo';
                    $key = $gname . '||' . $type . '||' . $state_str;
                    if (!isset($group_type_state_map[$key])) {
                        $group_type_state_map[$key] = [
                            'group' => $gname,
                            'type' => $type,
                            'state' => $state_str,
                            'count' => 0
                        ];
                    }
                    $group_type_state_map[$key]['count']++;
                }

                // Detalle del host
                $ips = [];
                if (isset($host['interfaces'])) {
                    foreach ($host['interfaces'] as $iface) {
                        if (!empty($iface['ip'])) $ips[] = $iface['ip'];
                    }
                }
                $ips_str = implode(', ', array_unique($ips));

                $glist = [];
                foreach (($host['hostgroups'] ?? []) as $g) {
                    $glist[] = $g['name'];
                }

                $hosts_detail[] = [
                    'hostid' => $host['hostid'],
                    'name' => $host['name'],
                    'ip' => $ips_str ?: 'N/A',
                    'type' => $type,
                    'method' => $method_str,
                    'status' => $state_str,
                    'groups' => implode(', ', $glist)
                ];
            }

            // Aplanar matriz agrupada
            $by_group_type_state = array_values($group_type_state_map);
            // Ordenar por grupo y tipo
            usort($by_group_type_state, function($a, $b) {
                if ($a['group'] === $b['group']) {
                    return strcmp($a['type'], $b['type']);
                }
                return strcmp($a['group'], $b['group']);
            });

            echo json_encode([
                'success' => true,
                'summary' => [
                    'total' => count($hosts),
                    'monitored' => $total_monitored,
                    'unmonitored' => $total_unmonitored
                ],
                'by_type' => $by_type,
                'by_method' => $by_method,
                'by_group_type_state' => $by_group_type_state,
                'hosts_detail' => $hosts_detail
            ]);
            break;

        case 'get_hosts_list':
            // Retorna solo una lista básica de hosts para el dropdown
            $zbx_hosts = call_zabbix_api('host.get', [
                'output' => ['hostid', 'name', 'host'],
                'sortfield' => 'name'
            ]);
            if (isset($zbx_hosts['error'])) throw new Exception("Error: " . json_encode($zbx_hosts['error']));
            echo json_encode(['success' => true, 'data' => $zbx_hosts['result'] ?? []]);
            break;

        case 'get_host_items_triggers':
            $hostid = $_GET['hostid'] ?? '';
            if (!$hostid) {
                throw new Exception("Falta parámetro hostid");
            }

            // 1. Obtener Items
            $items_resp = call_zabbix_api('item.get', [
                'hostids' => $hostid,
                'output' => ['itemid', 'name', 'key_', 'lastvalue', 'units', 'status'],
                'filter' => ['status' => 0], // Solo activos
                'sortfield' => 'name'
            ]);
            if (isset($items_resp['error'])) throw new Exception("Error items: " . json_encode($items_resp['error']));

            // 2. Obtener Triggers
            $triggers_resp = call_zabbix_api('trigger.get', [
                'hostids' => $hostid,
                'output' => ['triggerid', 'description', 'expression', 'priority', 'status', 'value'],
                'filter' => ['status' => 0], // Solo activos
                'sortfield' => 'description'
            ]);
            if (isset($triggers_resp['error'])) throw new Exception("Error triggers: " . json_encode($triggers_resp['error']));

            echo json_encode([
                'success' => true,
                'items' => $items_resp['result'] ?? [],
                'triggers' => $triggers_resp['result'] ?? []
            ]);
            break;

        case 'get_template_alerts':
            // 1. Obtener todos los hosts con sus plantillas para buscar la más usada
            $hosts_resp = call_zabbix_api('host.get', [
                'output' => ['hostid'],
                'selectParentTemplates' => ['templateid', 'name'],
                'monitored' => 1
            ]);
            if (isset($hosts_resp['error'])) throw new Exception("Error fetching hosts: " . json_encode($hosts_resp['error']));

            $template_counts = [];
            $template_names = [];
            foreach ($hosts_resp['result'] ?? [] as $h) {
                foreach ($h['parentTemplates'] ?? [] as $t) {
                    $tid = $t['templateid'];
                    $template_counts[$tid] = ($template_counts[$tid] ?? 0) + 1;
                    $template_names[$tid] = $t['name'];
                }
            }

            if (empty($template_counts)) {
                echo json_encode([
                    'success' => true,
                    'template_name' => 'Ninguno',
                    'host_count' => 0,
                    'triggers' => [],
                    'templates_list' => []
                ]);
                break;
            }

            arsort($template_counts);
            
            // Si el usuario especifica un template, usamos ese. Si no, el más usado.
            $selected_template_id = $_GET['templateid'] ?? key($template_counts);
            $selected_template_name = $template_names[$selected_template_id] ?? 'Desconocido';
            $selected_count = $template_counts[$selected_template_id] ?? 0;

            // Obtener todos los disparadores del template seleccionado
            $triggers_resp = call_zabbix_api('trigger.get', [
                'templateids' => $selected_template_id,
                'output' => ['triggerid', 'description', 'expression', 'priority', 'status'],
                'filter' => ['status' => 0], // Solo activos
                'sortfield' => 'description'
            ]);
            if (isset($triggers_resp['error'])) throw new Exception("Error triggers template: " . json_encode($triggers_resp['error']));

            // Obtener todos los hosts vinculados a esta plantilla
            $hosts_linked_resp = call_zabbix_api('host.get', [
                'templateids' => $selected_template_id,
                'output' => ['hostid', 'name', 'host'],
                'selectInterfaces' => ['ip', 'main'],
                'sortfield' => 'name'
            ]);
            if (isset($hosts_linked_resp['error'])) throw new Exception("Error hosts linked: " . json_encode($hosts_linked_resp['error']));

            $hosts_linked = [];
            foreach ($hosts_linked_resp['result'] ?? [] as $h) {
                $ips = [];
                foreach ($h['interfaces'] ?? [] as $iface) {
                    if (!empty($iface['ip'])) $ips[] = $iface['ip'];
                }
                $hosts_linked[] = [
                    'hostid' => $h['hostid'],
                    'name' => $h['name'],
                    'host' => $h['host'],
                    'ip' => implode(', ', array_unique($ips)) ?: 'N/A'
                ];
            }

            // Crear lista ordenada de templates para el selector
            $templates_list = [];
            foreach ($template_counts as $tid => $cnt) {
                $templates_list[] = [
                    'templateid' => $tid,
                    'name' => $template_names[$tid],
                    'count' => $cnt
                ];
            }

            echo json_encode([
                'success' => true,
                'templateid' => $selected_template_id,
                'template_name' => $selected_template_name,
                'host_count' => $selected_count,
                'triggers' => $triggers_resp['result'] ?? [],
                'hosts' => $hosts_linked,
                'templates_list' => $templates_list
            ]);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
