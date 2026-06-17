<?php
/**
 * process_grupos.php
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/process_grupos.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/zabbix_api.php';

if (php_sapi_name() !== 'cli') {
    require_login();
}

header('Content-Type: application/json');

if (!defined('ICMP_TRIGGER_NAME_SEARCH')) {
    define('ICMP_TRIGGER_NAME_SEARCH', 'Unavailable by ICMP ping');
}

/**
 * Función adaptada para realizar peticiones usando la API general de Zabbix.
 */
function zabbix_api_request(string $method, array $params = []): array {
    $resp = call_zabbix_api($method, $params);
    if (isset($resp['error'])) {
        throw new Exception($resp['error']);
    }
    return $resp['result'] ?? [];
}

/**
 * Formatea un número de segundos en un string legible.
 */
function format_time(int $seconds): string {
    $d = floor($seconds / 86400);
    $h = floor(($seconds % 86400) / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;

    $parts = [];
    if ($d > 0) $parts[] = "{$d}d";
    if ($h > 0) $parts[] = "{$h}h";
    if ($m > 0) $parts[] = "{$m}m";
    if ($s > 0 || empty($parts)) $parts[] = "{$s}s"; 
    
    return implode(' ', $parts);
}

// Obtener datos enviados// Get raw POST data
$input = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
$request_data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input: ' . json_last_error_msg()]);
    exit;
}

$start_date_str = $request_data['startDate'] ?? ''; 
$end_date_str = $request_data['endDate'] ?? '';   

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$start_date_str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$end_date_str)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas de inicio o fin inválidas (esperado YYYY-MM-DD).']);
    exit;
}

try {
    // --- 1. Cargar grupos de host directamente de Zabbix ---
    $host_groups_response = zabbix_api_request('hostgroup.get', [
        'output' => ['groupid', 'name'],
        'with_monitored_hosts' => true,
        'sortfield' => 'name'
    ]);

    if (empty($host_groups_response)) {
        throw new Exception("No se encontraron grupos de host activos en Zabbix.");
    }

    $group_ids = array_column($host_groups_response, 'groupid');

    // --- 2. Procesamiento de Fechas y Rango ---
    $from_date = DateTime::createFromFormat('Y-m-d', $start_date_str);
    $to_date = DateTime::createFromFormat('Y-m-d', $end_date_str);
    
    if ($from_date === false || $to_date === false) {
          throw new Exception("Rango de fechas inválido o malformado.");
    }
    
    $to_date->setTime(23, 59, 59);

    $today = new DateTime();
    if ($to_date > $today) {
        $to_date = $today;
    }
    
    if ($from_date > $to_date) {
        throw new Exception("La fecha de inicio no puede ser posterior a la fecha de fin.");
    }

    $from_timestamp = $from_date->getTimestamp();
    $to_timestamp = $to_date->getTimestamp();
    $days_in_analysis = $from_date->diff($to_date)->days + 1;

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($from_date->setTime(0, 0, 0), $interval, (clone $to_date)->modify('+1 second'));

    // --- 4. Obtener Hosts activos y monitoreados en estos grupos ---
    $hosts_response = zabbix_api_request('host.get', [
        'output' => ['hostid', 'name', 'host'],
        'groupids' => $group_ids,
        'selectHostGroups' => ['groupid', 'name'],
        'monitored' => 1
    ]);

    $valid_hosts = [];
    $host_groups_map = []; 
    
    foreach ($hosts_response as $host) {
        $host_id = $host['hostid'];
        $valid_hosts[$host_id] = $host['name'];
        $groups = $host['hostgroups'] ?? $host['groups'] ?? [];
        foreach ($groups as $g) {
            $h_g_id = $g['groupid'];
            if (in_array($h_g_id, $group_ids)) {
                $host_groups_map[$host_id][] = $h_g_id;
            }
        }
    }

    if (empty($valid_hosts)) {
        throw new Exception("No se encontraron hosts monitoreados en los grupos seleccionados.");
    }

    // --- 5. Obtener Interfaces (IPs) de los Hosts ---
    $interfaces_response = zabbix_api_request('hostinterface.get', [
        'output' => ['hostid', 'ip'],
        'hostids' => array_keys($valid_hosts)
    ]);
    
    $host_ips = [];
    foreach ($interfaces_response as $iface) {
        if (!isset($host_ips[$iface['hostid']])) {
            $host_ips[$iface['hostid']] = $iface['ip'];
        }
    }

    // --- 6. Obtener Triggers "ICMP Unavailable" ---
    $triggers_response = zabbix_api_request('trigger.get', [
        'output' => ['triggerid', 'description'],
        'search' => ['description' => ICMP_TRIGGER_NAME_SEARCH],
        'selectHosts' => ['hostid', 'name'],
        'hostids' => array_keys($valid_hosts),
        'filter' => ['status' => 0]
    ]);

    $host_triggers = [];
    foreach ($triggers_response as $t) {
        if (isset($t['hosts'][0]['hostid'])) {
            $h_id = $t['hosts'][0]['hostid'];
            $host_triggers[$h_id] = [
                'triggerid' => $t['triggerid'],
                'description' => $t['description']
            ];
        }
    }

    // --- 7. Obtener Eventos de Problema en lote ---
    $trigger_ids_to_fetch_events = array_column($triggers_response, 'triggerid');
    $eventos_por_trigger = [];
    
    if (!empty($trigger_ids_to_fetch_events)) {
        $all_events_response = zabbix_api_request('event.get', [
            'output' => ['eventid', 'clock', 'value', 'objectid'],
            'object' => 0, 
            'objectids' => $trigger_ids_to_fetch_events,
            'time_from' => $from_timestamp,
            'time_till' => $to_timestamp,
            'sortfield' => 'clock',
            'sortorder' => 'ASC',
            'limit' => 100000
        ]);

        foreach ($all_events_response as $event) {
            $eventos_por_trigger[$event['objectid']][] = $event;
        }
    }

    // --- 8. Calcular Disponibilidad por Host ---
    $host_results = [];
    $total_seconds_analysis_period = $to_timestamp - $from_timestamp;
    if ($total_seconds_analysis_period <= 0) {
        $total_seconds_analysis_period = 86400;
    }

    foreach ($valid_hosts as $host_id => $host_name) {
        $trigger_info = $host_triggers[$host_id] ?? null;

        if ($trigger_info === null) {
            $host_results[$host_id] = [
                'ok' => 100.0,
                'problem' => 0.0,
                'tiempo_ok' => $total_seconds_analysis_period,
                'tiempo_problem' => 0
            ];
            continue;
        }

        $trigger_id = $trigger_info['triggerid'];
        $daily_events = $eventos_por_trigger[$trigger_id] ?? [];

        $last_open_event_global = null;
        $sum_problem_host = 0.0;
        $sum_ok_host = 0.0;
        $count_days_host = 0;

        foreach ($period as $date) {
            $day_start = $date->setTime(0, 0, 0)->getTimestamp();
            $day_end_dt = (clone $date)->setTime(23, 59, 59);
            $day_end = min($day_end_dt->getTimestamp(), $to_timestamp);
            
            $total_time_day = $day_end - $day_start;
            if ($total_time_day <= 0) {
                continue;
            }

            $problem_time_day = 0.0;

            // Eventos del día
            $daily_events_for_day = array_filter($daily_events, fn($e) => $e['clock'] >= $day_start && $e['clock'] <= $day_end);
            usort($daily_events_for_day, fn($a, $b) => $a['clock'] <=> $b['clock']);
            
            // Carry-over
            if ($last_open_event_global !== null && $last_open_event_global['fin'] === 'Continúa' && $last_open_event_global['start_timestamp'] < $day_start) {
                $problem_start_for_day_calc = $day_start;
                $closing_event = array_filter($daily_events_for_day, fn($e) => (int)$e['value'] === 0);
                if (!empty($closing_event)) {
                    $first_close_time = (int)reset($closing_event)['clock'];
                    $problem_end_for_day_calc = min($first_close_time, $day_end);
                } else {
                    $problem_end_for_day_calc = $day_end;
                }
                
                if ($problem_end_for_day_calc > $problem_start_for_day_calc) {
                    $problem_time_day += ($problem_end_for_day_calc - $problem_start_for_day_calc);
                }
            }

            $current_host_event_for_day = $last_open_event_global;
            foreach ($daily_events_for_day as $event) {
                $event_time = (int)$event['clock'];
                $state = (int)$event['value']; 

                if ($state == 1) { 
                    if ($current_host_event_for_day === null) {
                        $current_host_event_for_day = [
                            'eventid' => $event['eventid'],
                            'start_timestamp' => $event_time,
                            'fin' => 'Continúa'
                        ];
                        $last_open_event_global = $current_host_event_for_day;
                    }
                } elseif ($state == 0 && $current_host_event_for_day !== null) { 
                    $current_host_event_for_day['fin'] = date('Y-m-d H:i:s', $event_time);
                    
                    $problem_start_for_day_calc = max($current_host_event_for_day['start_timestamp'], $day_start);
                    $problem_end_for_day_calc = min($event_time, $day_end);
                    
                    if ($problem_end_for_day_calc > $problem_start_for_day_calc) {
                        $problem_time_day += ($problem_end_for_day_calc - $problem_start_for_day_calc);
                    }
                    
                    $current_host_event_for_day = null;
                    $last_open_event_global = null;
                }
            }
            
            if ($current_host_event_for_day !== null && $current_host_event_for_day['fin'] === 'Continúa') {
                $problem_start_for_day_calc = max($current_host_event_for_day['start_timestamp'], $day_start);
                $problem_end_for_day_calc = $day_end;
                
                if ($problem_end_for_day_calc > $problem_start_for_day_calc) {
                    $problem_time_day += ($problem_end_for_day_calc - $problem_start_for_day_calc);
                }
                $last_open_event_global = $current_host_event_for_day;
            }

            $problem_time_day = min($problem_time_day, $total_time_day);
            $problem_percentage = ($total_time_day > 0) ? ($problem_time_day / $total_time_day) * 100 : 0.0;
            $ok_percentage = 100.0 - $problem_percentage;

            $sum_problem_host += $problem_percentage;
            $sum_ok_host += $ok_percentage;
            $count_days_host++;
        }

        $avg_ok = $count_days_host > 0 ? $sum_ok_host / $count_days_host : 100.0;
        $avg_prob = $count_days_host > 0 ? $sum_problem_host / $count_days_host : 0.0;

        $host_results[$host_id] = [
            'ok' => round($avg_ok, 4),
            'problem' => round($avg_prob, 4),
            'tiempo_ok' => round(($avg_ok / 100) * $total_seconds_analysis_period),
            'tiempo_problem' => round(($avg_prob / 100) * $total_seconds_analysis_period)
        ];
    }

    // --- 9. Agrupar por Host Group ---
    $groups_summary = [];
    $total_ok_sum = 0.0;
    $total_problem_sum = 0.0;
    $groups_with_hosts_count = 0;

    foreach ($host_groups_response as $group) {
        $g_id = $group['groupid'];
        $g_name = $group['name'];

        $g_hosts = [];
        foreach ($host_groups_map as $h_id => $g_ids) {
            if (in_array($g_id, $g_ids)) {
                $g_hosts[] = $h_id;
            }
        }

        $count_hosts = count($g_hosts);
        $avg_ok = 100.0;
        $avg_problem = 0.0;
        $tiempo_ok_sec = $total_seconds_analysis_period;
        $tiempo_problem_sec = 0;

        if ($count_hosts > 0) {
            $sum_ok = 0.0;
            $sum_problem = 0.0;
            foreach ($g_hosts as $h_id) {
                $sum_ok += $host_results[$h_id]['ok'];
                $sum_problem += $host_results[$h_id]['problem'];
            }
            $avg_ok = $sum_ok / $count_hosts;
            $avg_problem = $sum_problem / $count_hosts;
            $tiempo_ok_sec = ($avg_ok / 100) * $total_seconds_analysis_period;
            $tiempo_problem_sec = ($avg_problem / 100) * $total_seconds_analysis_period;

            $total_ok_sum += $avg_ok;
            $total_problem_sum += $avg_problem;
            $groups_with_hosts_count++;
        }

        $groups_summary[] = [
            'groupid' => $g_id,
            'name' => $g_name,
            'total_hosts' => $count_hosts,
            'porcentaje_ok' => round($avg_ok, 4),
            'porcentaje_problem' => round($avg_problem, 4),
            'tiempo_ok_formateado' => format_time((int)round($tiempo_ok_sec)),
            'tiempo_problem_formateado' => format_time((int)round($tiempo_problem_sec))
        ];
    }

    $avg_ok_general = $groups_with_hosts_count > 0 ? $total_ok_sum / $groups_with_hosts_count : 100.0;
    $avg_problem_general = $groups_with_hosts_count > 0 ? $total_problem_sum / $groups_with_hosts_count : 0.0;

    $resumen_general = [
        'total_grupos' => count($host_groups_response),
        'grupos_monitoreados' => $groups_with_hosts_count,
        'promedio_ok' => round($avg_ok_general, 4),
        'promedio_problem' => round($avg_problem_general, 4),
        'fecha_inicio_analisis' => $from_date->format('d-m-Y'),
        'fecha_fin_analisis' => $to_date->format('d-m-Y'),
        'dias_analizados' => $days_in_analysis,
        'tiempo_total_periodo_formateado' => format_time($total_seconds_analysis_period),
        'tiempo_promedio_ok_estimado' => format_time((int)round(($avg_ok_general / 100) * $total_seconds_analysis_period)),
        'tiempo_promedio_problem_estimado' => format_time((int)round(($avg_problem_general / 100) * $total_seconds_analysis_period))
    ];

    $final_output = [
        'resumen_general' => $resumen_general,
        'grupos' => $groups_summary
    ];

    // --- Guardar en carpeta resultado ---
    $output_dir = __DIR__ . "/resultado";
    if (!is_dir($output_dir)) {
        @mkdir($output_dir, 0777, true);
    }
    $nombre_archivo = $output_dir . "/disponibilidad_grupos_" . date('Ymd_His') . ".json";
    @file_put_contents($nombre_archivo, json_encode($final_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode($final_output);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar el informe: ' . $e->getMessage()]);
}
?>
