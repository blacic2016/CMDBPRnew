<?php
/**
 * process_report.php
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/process_report.php
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

// Get raw POST data
$input = file_get_contents(php_sapi_name() === 'cli' ? 'php://stdin' : 'php://input');
$request_data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input: ' . json_last_error_msg()]);
    exit;
}

$start_date_str = $request_data['startDate'] ?? ''; 
$end_date_str = $request_data['endDate'] ?? '';   
$selected_hostgroup_name = $request_data['hostgroup'] ?? '';
$selected_hosts = $request_data['hosts'] ?? [];

// Validación de formato de fecha YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$start_date_str)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fecha de inicio inválida o faltante (esperado YYYY-MM-DD).']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$end_date_str)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fecha de fin inválida o faltante (esperado YYYY-MM-DD).']);
    exit;
}

if (empty($selected_hostgroup_name)) {
    http_response_code(400);
    echo json_encode(['error' => 'No se ha seleccionado un grupo de host.']);
    exit;
}

try {
    // --- 1. Procesamiento de Fechas y Rango ---
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

    $interval = new DateInterval('P1D');
    $period = new DatePeriod($from_date->setTime(0, 0, 0), $interval, (clone $to_date)->modify('+1 second'));

    // --- 2. Obtener Hosts y Triggers Relevantes ---
    $group_id_selected = null;
    $host_groups_response = zabbix_api_request('hostgroup.get', [
        'output' => ['groupid', 'name'],
        'filter' => ['name' => $selected_hostgroup_name]
    ]);

    foreach ($host_groups_response as $group) {
        if ($group['name'] === $selected_hostgroup_name) {
            $group_id_selected = $group['groupid'];
            break;
        }
    }

    if ($group_id_selected === null) {
        throw new Exception("El grupo de host '$selected_hostgroup_name' no fue encontrado en Zabbix.");
    }

    $hosts_response = zabbix_api_request('host.get', [
        'output' => ['hostid', 'name'],
        'groupids' => [$group_id_selected],
        'selectParentTemplates' => ['templateid', 'name'], 
        'monitored' => 1
    ]);
    
    $valid_hosts = [];
    foreach ($hosts_response as $host) {
        if (isset($host['host']) && $host['host'] === $host['name'] && !empty($host['templateid'])) {
            continue;
        }
        if (!empty($selected_hosts) && !in_array('all', $selected_hosts)) {
            if (!in_array($host['hostid'], $selected_hosts)) {
                continue;
            }
        }
        $valid_hosts[$host['hostid']] = $host['name'];
    }

    if (empty($valid_hosts)) {
        throw new Exception("No se encontraron hosts monitoreados y activos en el grupo '$selected_hostgroup_name'.");
    }

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

    $triggers_response = zabbix_api_request('trigger.get', [
        'output' => ['triggerid', 'description'],
        'search' => ['description' => ICMP_TRIGGER_NAME_SEARCH],
        'selectHosts' => ['hostid', 'name'],
        'hostids' => array_keys($valid_hosts),
        'filter' => ['status' => 0]
    ]);
    
    $trigger_ids_to_fetch_events = array_column($triggers_response, 'triggerid');

    // --- 3. Obtener Eventos de Problema ---
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
            'limit' => 50000
        ]);

        foreach ($all_events_response as $event) {
            $eventos_por_trigger[$event['objectid']][] = $event;
        }
    }

    // --- 4. Calcular Disponibilidad por Host y Día ---
    $host_data = [];
    $host_summary = [];
    $eventos = [];
    
    $total_hosts_analyzed = 0;
    $sum_ok_general = 0.0;
    $sum_problem_general = 0.0;

    foreach ($valid_hosts as $host_id => $host_name) {
        $host_triggers = array_filter($triggers_response, fn($t) => 
            isset($t['hosts'][0]['hostid']) && $t['hosts'][0]['hostid'] == $host_id
        );

        if (empty($host_triggers)) {
             $days_in_analysis = $to_date->diff($from_date->setTime(0, 0, 0))->days + 1;
             $host_summary[] = [
                'host' => $host_name,
                'ip' => $host_ips[$host_id] ?? 'N/A',
                'porcentaje_ok_total' => 100.0,
                'porcentaje_problem_total' => 0.0
            ];
            $sum_ok_general += 100.0;
            $total_hosts_analyzed++;
            
            foreach ($period as $date) {
                $day_start = $date->setTime(0, 0, 0)->getTimestamp();
                $host_data[] = [
                    'hostid' => $host_id,
                    'host' => $host_name,
                    'ip' => $host_ips[$host_id] ?? 'N/A',
                    'trigger' => 'Sin trigger ICMP',
                    'date' => date('d-m-Y', $day_start),
                    'problems' => 0.0,
                    'ok' => 100.0
                ];
            }
            continue;
        }

        $trigger_id_for_host = $host_triggers[array_key_first($host_triggers)]['triggerid'];
        $trigger_description_for_host = $host_triggers[array_key_first($host_triggers)]['description'];

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
            $daily_events_for_host = $eventos_por_trigger[$trigger_id_for_host] ?? [];

            $current_host_event_for_day = $last_open_event_global;
            $daily_events_for_day = array_filter($daily_events_for_host, fn($e) => $e['clock'] >= $day_start && $e['clock'] <= $day_end);
            usort($daily_events_for_day, fn($a, $b) => $a['clock'] <=> $b['clock']);
            
            if ($current_host_event_for_day !== null && $current_host_event_for_day['fin'] === 'Continúa' && $current_host_event_for_day['start_timestamp'] < $day_start) {
                $problem_start_for_day_calc = $day_start;
                
                $closing_event = array_filter($daily_events_for_day, fn($e) => (int)$e['value'] === 0 && $e['objectid'] == $trigger_id_for_host);
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

            foreach ($daily_events_for_day as $event) {
                $event_time = (int)$event['clock'];
                $state = (int)$event['value']; 

                if ($state == 1) { 
                    if ($current_host_event_for_day === null) {
                        $current_host_event_for_day = [
                            'eventid' => $event['eventid'],
                            'inicio' => date('Y-m-d H:i:s', $event_time),
                            'start_timestamp' => $event_time,
                            'valor_inicial' => 1,
                            'fin' => 'Continúa',
                            'duracion' => 'Calculando...',
                            'fecha' => date('d-m-Y', $event_time),
                            'trigger' => $trigger_description_for_host,
                            'host' => $host_name,
                            'hostid' => $host_id,
                            'triggerid' => $event['objectid']
                        ];
                        $last_open_event_global = $current_host_event_for_day;
                    }
                } elseif ($state == 0 && $current_host_event_for_day !== null && $current_host_event_for_day['triggerid'] == $event['objectid']) { 
                    $current_host_event_for_day['fin'] = date('Y-m-d H:i:s', $event_time);
                    $current_host_event_for_day['end_timestamp'] = $event_time;
                    $current_host_event_for_day['valor_final'] = 0;
                    $duration = $event_time - $current_host_event_for_day['start_timestamp'];
                    $current_host_event_for_day['duracion'] = format_time($duration);
                    
                    $eventos[] = $current_host_event_for_day;
                    
                    $problem_start_for_day_calc = max($current_host_event_for_day['start_timestamp'], $day_start);
                    $problem_end_for_day_calc = min($event_time, $day_end);
                    
                    if ($problem_end_for_day_calc > $problem_start_for_day_calc) {
                        if ($current_host_event_for_day['start_timestamp'] >= $day_start) {
                            $problem_time_day += ($problem_end_for_day_calc - $problem_start_for_day_calc);
                        }
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

            $host_data[] = [
                'hostid' => $host_id,
                'host' => $host_name,
                'ip' => $host_ips[$host_id] ?? 'N/A',
                'trigger' => $trigger_description_for_host,
                'date' => date('d-m-Y', $day_start),
                'problems' => round($problem_percentage, 4),
                'ok' => round($ok_percentage, 4)
            ];
            $sum_problem_host += $problem_percentage;
            $sum_ok_host += $ok_percentage;
            $count_days_host++;
        }

        if ($count_days_host > 0) {
            $host_summary[] = [
                'host' => $host_name,
                'ip' => $host_ips[$host_id] ?? 'N/A',
                'porcentaje_ok_total' => round($sum_ok_host / $count_days_host, 4),
                'porcentaje_problem_total' => round($sum_problem_host / $count_days_host, 4)
            ];
            $sum_problem_general += $sum_problem_host / $count_days_host;
            $sum_ok_general += $sum_ok_host / $count_days_host;
            $total_hosts_analyzed++;
        }
    }

    // --- 5. Cálculo del Resumen General ---
    $days_in_analysis = $from_date->diff($to_date)->days + 1;
    $total_seconds_analysis_period = $days_in_analysis * 86400;

    $avg_ok_general = $total_hosts_analyzed > 0 ? $sum_ok_general / $total_hosts_analyzed : 0.0;
    $avg_problem_general = $total_hosts_analyzed > 0 ? $sum_problem_general / $total_hosts_analyzed : 0.0;

    foreach ($host_summary as &$summary) {
        $summary['tiempo_ok_estimado'] = format_time(round(($summary['porcentaje_ok_total'] / 100) * $total_seconds_analysis_period));
        $summary['tiempo_problem_estimado'] = format_time(round(($summary['porcentaje_problem_total'] / 100) * $total_seconds_analysis_period));
    }
    unset($summary);

    $resumen = [
        'total_hosts_analizados' => $total_hosts_analyzed,
        'promedio_ok' => round($avg_ok_general, 4),
        'promedio_problem' => round($avg_problem_general, 4),
        'fecha_inicio_analisis' => $from_date->format('d-m-Y'),
        'fecha_fin_analisis' => $to_date->format('d-m-Y'),
        'dias_analizados' => $days_in_analysis,
        'tiempo_total_periodo_segundos' => $total_seconds_analysis_period,
        'tiempo_total_periodo_formateado' => format_time($total_seconds_analysis_period),
        'tiempo_promedio_ok_estimado' => format_time(round(($avg_ok_general / 100) * $total_seconds_analysis_period)),
        'tiempo_promedio_problem_estimado' => format_time(round(($avg_problem_general / 100) * $total_seconds_analysis_period)),
        'por_host' => $host_summary
    ];

    // --- 6. Guardar y Enviar Respuesta ---
    $output_dir = __DIR__ . "/resultado";
    if (!is_dir($output_dir)) {
        @mkdir($output_dir, 0777, true);
    }

    $nombre_archivo = $output_dir . "/disponibilidad_" . date('Ymd_His') . ".json";
    $final_output = [
        'resumen_general' => $resumen,
        'detalle_diario_por_host' => $host_data,
        'eventos_de_problema' => $eventos
    ];

    @file_put_contents($nombre_archivo, json_encode($final_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode($final_output);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['error' => 'Error al generar el informe: ' . $e->getMessage()]);
}
?>
