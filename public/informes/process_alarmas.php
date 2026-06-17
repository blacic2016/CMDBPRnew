<?php
/**
 * process_alarmas.php
 * Ubicación: /var/www/html/VILASECA/CMDBPRnew/public/informes/process_alarmas.php
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

try {
    // --- 1. Procesamiento de Fechas ---
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

    // --- 2. Obtener Grupos Autorizados y Filtrar ---
    $hostgroups_file = __DIR__ . '/hostgroups.txt';
    $allowed_groups = [];
    if (file_exists($hostgroups_file)) {
        $allowed_groups = array_filter(array_map('trim', file($hostgroups_file)));
    }

    if (empty($allowed_groups)) {
        throw new Exception("No hay grupos de host configurados en hostgroups.txt.");
    }

    // Filtrar grupo seleccionado
    $groups_to_query = $allowed_groups;
    if (!empty($selected_hostgroup_name)) {
        if (!in_array($selected_hostgroup_name, $allowed_groups)) {
            throw new Exception("El grupo de host seleccionado no está autorizado.");
        }
        $groups_to_query = [$selected_hostgroup_name];
    }

    $group_ids = [];
    $host_groups_response = zabbix_api_request('hostgroup.get', [
        'output' => ['groupid', 'name'],
        'filter' => ['name' => $groups_to_query]
    ]);

    foreach ($host_groups_response as $group) {
        $group_ids[] = $group['groupid'];
    }

    if (empty($group_ids)) {
        throw new Exception("Ninguno de los grupos autorizados fue encontrado en Zabbix.");
    }

    // --- 3. Obtener Hosts Monitoreados ---
    $hosts_response = zabbix_api_request('host.get', [
        'output' => ['hostid', 'name'],
        'groupids' => $group_ids,
        'monitored' => 1
    ]);

    $valid_hosts = [];
    foreach ($hosts_response as $host) {
        $valid_hosts[$host['hostid']] = $host['name'];
    }

    if (empty($valid_hosts)) {
        throw new Exception("No se encontraron hosts activos en los grupos seleccionados.");
    }

    // --- 4. Obtener Alarmas (Eventos de Problema) ---
    $events_response = zabbix_api_request('event.get', [
        'output' => ['eventid', 'objectid', 'name', 'severity', 'clock'],
        'selectHosts' => ['hostid', 'name'],
        'hostids' => array_keys($valid_hosts),
        'time_from' => $from_timestamp,
        'time_till' => $to_timestamp,
        'value' => 1, // Sólo alarmas que se dispararon (Problem state)
        'sortfield' => 'clock',
        'sortorder' => 'DESC'
    ]);

    // --- 5. Procesamiento y Agrupación ---
    $total_alarmas = count($events_response);
    $hosts_alarm_count = [];
    $severity_count = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $alarm_types = [];
    $lista_alarmas = [];

    foreach ($events_response as $ev) {
        $severity = (int)$ev['severity'];
        $name = $ev['name'];
        $clock = (int)$ev['clock'];
        $date = date('d-m-Y H:i:s', $clock);
        
        $host_name = 'Desconocido';
        $host_id = '0';
        if (!empty($ev['hosts'])) {
            $host_id = $ev['hosts'][0]['hostid'];
            $host_name = $ev['hosts'][0]['name'];
        }

        // Sumar severidad general
        if (isset($severity_count[$severity])) {
            $severity_count[$severity]++;
        }

        // Agrupar por host
        if (!isset($hosts_alarm_count[$host_id])) {
            $hosts_alarm_count[$host_id] = [
                'hostid' => $host_id,
                'name' => $host_name,
                'total' => 0,
                'severities' => [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
            ];
        }
        $hosts_alarm_count[$host_id]['total']++;
        $hosts_alarm_count[$host_id]['severities'][$severity]++;

        // Agrupar por tipo de alarma (name)
        if (!isset($alarm_types[$name])) {
            $alarm_types[$name] = [
                'name' => $name,
                'count' => 0,
                'severity' => $severity
            ];
        }
        $alarm_types[$name]['count']++;

        // Fila de detalle
        $lista_alarmas[] = [
            'eventid' => $ev['eventid'],
            'hostid' => $host_id,
            'host' => $host_name,
            'severity' => $severity,
            'name' => $name,
            'date' => $date
        ];
    }

    // Ordenar hosts por total de alarmas descendente
    uasort($hosts_alarm_count, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    $hosts_alarm_count = array_values($hosts_alarm_count);

    // Ordenar tipos de alarma por cantidad de ocurrencias descendente
    uasort($alarm_types, function($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    $alarm_types = array_values($alarm_types);

    // --- 6. Formatear y Enviar Respuesta ---
    $resumen_general = [
        'total_alarmas' => $total_alarmas,
        'total_equipos_afectados' => count($hosts_alarm_count),
        'por_severidad' => $severity_count,
        'fecha_inicio_analisis' => $from_date->format('d-m-Y'),
        'fecha_fin_analisis' => $to_date->format('d-m-Y')
    ];

    $final_output = [
        'resumen_general' => $resumen_general,
        'por_equipo' => $hosts_alarm_count,
        'por_tipo_alarma' => $alarm_types,
        'lista_alarmas' => $lista_alarmas
    ];

    // Guardar en la carpeta resultado para auditoría
    $output_dir = __DIR__ . "/resultado";
    if (!is_dir($output_dir)) {
        @mkdir($output_dir, 0777, true);
    }
    $nombre_archivo = $output_dir . "/alarmas_" . date('Ymd_His') . ".json";
    @file_put_contents($nombre_archivo, json_encode($final_output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    echo json_encode($final_output);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al generar el informe de alarmas: ' . $e->getMessage()]);
}
?>
