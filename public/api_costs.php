<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/zabbix_api.php';
require_once __DIR__ . '/../src/db.php';

set_time_limit(300); // Increase timeout for historical data processing
header('Content-Type: application/json');

if (!current_user_id()) {
    echo json_encode(['success' => false, 'error' => 'No session']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$pdo = getPDO();

switch ($action) {
    case 'save_rule':
        if (!has_role(['ADMIN', 'SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
        
        $id = $_POST['id'] ?? null;
        $apply_to = $_POST['apply_to'] ?? 'GLOBAL';
        $hostgroup_id = $_POST['hostgroup_id'] ?? null;
        $hostgroup_name = $_POST['hostgroup_name'] ?? null;
        $cpu_cost = (float)($_POST['cpu_cost'] ?? 0);
        $mem_cost = (float)($_POST['mem_cost'] ?? 0);
        $disk_cost = (float)($_POST['disk_cost'] ?? 0);
        $currency = $_POST['currency'] ?? 'USD';

        if ($id) {
            $stmt = $pdo->prepare("UPDATE zabbix_costs_rules SET apply_to = ?, hostgroup_id = ?, hostgroup_name = ?, cpu_cost_base = ?, mem_cost_base = ?, disk_cost_base = ?, currency = ? WHERE id = ?");
            $stmt->execute([$apply_to, $hostgroup_id, $hostgroup_name, $cpu_cost, $mem_cost, $disk_cost, $currency, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO zabbix_costs_rules (apply_to, hostgroup_id, hostgroup_name, cpu_cost_base, mem_cost_base, disk_cost_base, currency) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$apply_to, $hostgroup_id, $hostgroup_name, $cpu_cost, $mem_cost, $disk_cost, $currency]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'get_rules':
        $stmt = $pdo->query("SELECT * FROM zabbix_costs_rules ORDER BY apply_to DESC, hostgroup_name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    case 'delete_rule':
        if (!has_role(['ADMIN', 'SUPER_ADMIN'])) exit(json_encode(['success' => false, 'error' => 'Unauthorized']));
        $id = $_POST['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM zabbix_costs_rules WHERE id = ?");
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'calculate_costs':
        $hostgroup_id = $_GET['hostgroup_id'] ?? null;
        $year = $_GET['year'] ?? null;
        $months = $_GET['months'] ?? []; // Array of months 1-12
        if (!is_array($months) && !empty($months)) $months = explode(',', $months);

        // 1. Get rules from CMDB DB
        $rules_stmt = $pdo->query("SELECT * FROM zabbix_costs_rules ORDER BY apply_to DESC");
        $rules = $rules_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $global_rule = null;
        $group_rules = [];
        foreach ($rules as $r) {
            if ($r['apply_to'] === 'GLOBAL') $global_rule = $r;
            else $group_rules[$r['hostgroup_id']] = $r;
        }

        // 2. Fetch Hosts from Zabbix
        $params = [
            'output' => ['hostid', 'name', 'host'],
            'selectGroups' => ['groupid', 'name']
        ];
        if ($hostgroup_id && $hostgroup_id !== 'all') {
            $params['groupids'] = [$hostgroup_id];
        }

        $zbx_hosts = call_zabbix_api('host.get', $params);
        if (isset($zbx_hosts['error'])) {
            exit(json_encode(['success' => false, 'error' => $zbx_hosts['error']]));
        }

        $hosts = $zbx_hosts['result'];
        if (empty($hosts)) {
            exit(json_encode(['success' => true, 'data' => []]));
        }

        $hostids = array_column($hosts, 'hostid');
        
        // 3. Fetch Data (History/Trends or Last Value)
        $item_data = [];
        $is_historical = ($year && !empty($months));
        
        // Exact keys provided by USER + General Fallbacks
        $cpu_usage_keys = ['system.cpu.util', 'vmware.vm.cpu.usage.perf', 'cpu.util', 'cpu.usage', 'cpu.idle', 'perf_counter[\Processor(_Total)\% Processor Time]'];
        $mem_usage_keys = ['vm.memory.utilization', 'vm.memory.util', 'vmware.vm.memory.usage', 'memory.util', 'memory.size[pused]'];
        $disk_usage_keys = ['vfs.fs.util', 'pused', 'util', 'usage'];
        
        $capacity_keys = ['system.cpu.num', 'vmware.vm.cpu.num', 'vm.memory.size[total]', 'vmware.vm.memory.size', 'vfs.fs.size', 'vmware.vm.vfs.fs.size'];
        $all_search_keys = array_merge($cpu_usage_keys, $mem_usage_keys, $disk_usage_keys, $capacity_keys);

        if ($is_historical) {
            $items_resp = call_zabbix_api('item.get', [
                'hostids' => $hostids,
                'output' => ['itemid', 'hostid', 'key_', 'name', 'units'],
                'search' => ['key_' => $all_search_keys],
                'searchByAny' => true
            ]);
            
            if (!empty($items_resp['result'])) {
                $items_map = [];
                foreach ($items_resp['result'] as $it) {
                    $low_key = strtolower($it['key_']);
                    $low_name = strtolower($it['name']);
                    
                    $category = 'other';
                    if (strpos($low_key, 'cpu') !== false || strpos($low_name, 'cpu') !== false) $category = 'cpu';
                    elseif (strpos($low_key, 'memory') !== false || strpos($low_name, 'memory') !== false || strpos($low_key, 'mem') !== false) $category = 'mem';
                    elseif (strpos($low_key, 'fs') !== false || strpos($low_key, 'disk') !== false || strpos($low_name, 'disco') !== false) $category = 'disk';
                    
                    if ($category === 'other') continue;

                    $is_capacity = (strpos($low_key, 'total') !== false || strpos($low_key, 'num') !== false || (strpos($low_key, 'size') !== false && strpos($low_key, 'used') === false));
                    $is_idle = (strpos($low_key, 'idle') !== false || strpos($low_name, 'idle') !== false);
                    
                    $items_map[$it['itemid']] = ['hostid' => $it['hostid'], 'cat' => $category, 'is_cap' => $is_capacity, 'is_idle' => $is_idle, 'units' => $it['units']];
                }
                
                $itemids = array_keys($items_map);
                foreach ($months as $m) {
                    $time_from = strtotime("$year-$m-01 00:00:00");
                    $time_till = strtotime(date("Y-m-t 23:59:59", $time_from));
                    
                    $trends = call_zabbix_api('trend.get', [
                        'itemids' => $itemids,
                        'time_from' => $time_from,
                        'time_till' => $time_till,
                        'output' => ['itemid', 'value_avg', 'value_max']
                    ]);
                    
                    if (!empty($trends['result'])) {
                        foreach ($trends['result'] as $tr) {
                            $info = $items_map[$tr['itemid']];
                            $hostid = $info['hostid'];
                            $cat = $info['cat'];
                            $val = (float)$tr['value_avg'];
                            
                            if ($info['is_idle']) $val = 100 - $val;
                            
                            $sub = $info['is_cap'] ? 'cap' : 'usage';
                            if (!isset($item_data[$hostid][$cat][$sub])) $item_data[$hostid][$cat][$sub] = [];
                            $item_data[$hostid][$cat][$sub][] = $val;
                        }
                    }
                }
                // Reduction
                foreach ($item_data as $hid => $cats) {
                    foreach ($cats as $cat => $subs) {
                        foreach ($subs as $sub => $vals) {
                            $item_data[$hid][$cat][$sub] = array_sum($vals) / count($vals);
                        }
                    }
                }
            }
        } else {
            // Real-time
            $items_resp = call_zabbix_api('item.get', [
                'hostids' => $hostids,
                'output' => ['hostid', 'key_', 'name', 'lastvalue', 'units'],
                'search' => ['key_' => $all_search_keys],
                'searchByAny' => true
            ]);

            if (isset($items_resp['result'])) {
                foreach ($items_resp['result'] as $it) {
                    $low_key = strtolower($it['key_']);
                    $low_name = strtolower($it['name']);
                    $category = 'other';
                    if (strpos($low_key, 'cpu') !== false || strpos($low_name, 'cpu') !== false) $category = 'cpu';
                    elseif (strpos($low_key, 'memory') !== false || strpos($low_name, 'memory') !== false || strpos($low_key, 'mem') !== false) $category = 'mem';
                    elseif (strpos($low_key, 'fs') !== false || strpos($low_key, 'disk') !== false || strpos($low_name, 'disco') !== false) $category = 'disk';
                    if ($category === 'other') continue;

                    $is_capacity = (strpos($low_key, 'total') !== false || strpos($low_key, 'num') !== false || (strpos($low_key, 'size') !== false && strpos($low_key, 'used') === false));
                    $val = (float)$it['lastvalue'];
                    if (strpos($low_key, 'idle') !== false || strpos($low_name, 'idle') !== false) $val = 100 - $val;
                    
                    $sub = $is_capacity ? 'cap' : 'usage';
                    // For capacity, we want the most recent or highest?
                    if ($is_capacity) {
                        if (!isset($item_data[$it['hostid']][$category][$sub]) || $val > $item_data[$it['hostid']][$category][$sub]) {
                            $item_data[$it['hostid']][$category][$sub] = $val;
                        }
                    } else {
                        // For usage, we might have multiple items (different disks), we merge or take max?
                        if (!isset($item_data[$it['hostid']][$category][$sub]) || $val > $item_data[$it['hostid']][$category][$sub]) {
                            $item_data[$it['hostid']][$category][$sub] = $val;
                        }
                    }
                }
            }
        }

        // 4. Processing
        $report = [];
        foreach ($hosts as $h) {
            $hid = $h['hostid'];
            $cpu_usage = $item_data[$hid]['cpu']['usage'] ?? 0;
            $mem_usage = $item_data[$hid]['mem']['usage'] ?? 0;
            $disk_usage = $item_data[$hid]['disk']['usage'] ?? 0;

            $cpu_cap = $item_data[$hid]['cpu']['cap'] ?? 0;
            $mem_cap = $item_data[$hid]['mem']['cap'] ?? 0;
            $disk_cap = $item_data[$hid]['disk']['cap'] ?? 0;

            // Unit conversion for RAM/Disk (mostly B to GB)
            $mem_cap_gb = ($mem_cap > 10000) ? round($mem_cap / 1024 / 1024 / 1024, 2) : $mem_cap;
            $disk_cap_gb = ($disk_cap > 10000) ? round($disk_cap / 1024 / 1024 / 1024, 2) : $disk_cap;

            // Find applicable rule
            $active_rule = $global_rule;
            if (isset($h['groups']) && is_array($h['groups'])) {
                foreach ($h['groups'] as $g) {
                    if (isset($group_rules[$g['groupid']])) {
                        $active_rule = $group_rules[$g['groupid']];
                        break;
                    }
                }
            }

            // Calculations
            $cpu_total = 0; $mem_total = 0; $disk_total = 0;
            $currency = 'USD';
            $source = 'Sin Regla';

            if ($active_rule) {
                // Base rates from rule
                $cpu_rate = (float)$active_rule['cpu_cost_base'];
                $mem_rate = (float)$active_rule['mem_cost_base'];
                $disk_rate = (float)($active_rule['disk_cost_base'] ?? 0);

                // Total Capacity Cost per hour (Rate * Capacity)
                $cpu_cap_cost = $cpu_cap * $cpu_rate;
                $mem_cap_cost = $mem_cap_gb * $mem_rate;
                $disk_cap_cost = $disk_cap_gb * $disk_rate;

                // Used Cost (Percent of Capacity Cost)
                $cpu_used_cost = ($cpu_usage * $cpu_cap_cost) / 100;
                $mem_used_cost = ($mem_usage * $mem_cap_cost) / 100;
                $disk_used_cost = ($disk_usage * $disk_cap_cost) / 100;

                // Idle Cost
                $cpu_idle_cost = $cpu_cap_cost - $cpu_used_cost;
                $mem_idle_cost = $mem_cap_cost - $mem_used_cost;
                $disk_idle_cost = $disk_cap_cost - $disk_used_cost;
                
                $total_cap_hour = $cpu_cap_cost + $mem_cap_cost + $disk_cap_cost;
                $total_used_hour = $cpu_used_cost + $mem_used_cost + $disk_used_cost;
                $total_idle_hour = $cpu_idle_cost + $mem_idle_cost + $disk_idle_cost;

                // Calculate exact hours in selected period
                $total_hours_period = 0;
                if (!empty($months)) {
                    foreach ($months as $m) {
                        $days = cal_days_in_month(CAL_GREGORIAN, $m, $year);
                        $total_hours_period += ($days * 24);
                    }
                } else {
                    $total_hours_period = 730; // Default fallback (approx 1 month)
                }

                $total_proj = $total_cap_hour * $total_hours_period;
                $used_proj = $total_used_hour * $total_hours_period;
                $idle_proj = $total_idle_hour * $total_hours_period;

                $currency = $active_rule['currency'];
                $source = $active_rule['apply_to'] === 'GLOBAL' ? 'Global' : 'Grupo: ' . ($active_rule['hostgroup_name'] ?? 'Unknown Group');

                $report[] = [
                    'hostid' => $hid,
                    'name' => $h['name'],
                    'groups' => isset($h['groups']) ? array_column($h['groups'], 'name') : [],
                    'capacity' => [
                        'cpu' => round($cpu_cap, 0),
                        'mem' => $mem_cap_gb . ' GB',
                        'disk' => $disk_cap_gb . ' GB'
                    ],
                    'usage' => [
                        'cpu' => round($cpu_usage, 1),
                        'mem' => round($mem_usage, 1),
                        'disk' => round($disk_usage, 1)
                    ],
                    'costs' => [
                        'cpu' => ['cap' => round($cpu_cap_cost, 4), 'used' => round($cpu_used_cost, 4), 'idle' => round($cpu_idle_cost, 4)],
                        'mem' => ['cap' => round($mem_cap_cost, 4), 'used' => round($mem_used_cost, 4), 'idle' => round($mem_idle_cost, 4)],
                        'disk' => ['cap' => round($disk_cap_cost, 4), 'used' => round($disk_used_cost, 4), 'idle' => round($disk_idle_cost, 4)],
                        'total_hour' => ['cap' => round($total_cap_hour, 4), 'used' => round($total_used_hour, 4), 'idle' => round($total_idle_hour, 4)],
                        'total_period' => ['cap' => round($total_proj, 2), 'used' => round($used_proj, 2), 'idle' => round($idle_proj, 2)]
                    ],
                    'currency' => $currency,
                    'rule_source' => $source
                ];
            }
        }

        echo json_encode(['success' => true, 'is_historical' => $is_historical, 'data' => $report]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
