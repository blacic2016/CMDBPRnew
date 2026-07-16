<?php
declare(strict_types=1);

// Cargar configuración centralizada
require_once __DIR__ . '/wireless_config.php';

header('Content-Type: application/json');

/**
 * Ejecutar peticiones a la API de Zabbix
 *
 * @param string $method El método de la API de Zabbix a llamar (ej. 'item.get').
 * @param array $params Los parámetros para el método de la API.
 * @return array El resultado de la petición de la API.
 * @throws Exception Si la petición cURL falla, la decodificación JSON falla o la API de Zabbix devuelve un error.
 */
function zabbix_api_request(string $method, array $params = []): array
{
    $payload = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        'id' => 1
    ];

    $ch = curl_init(ZABBIX_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . ZABBIX_API_TOKEN
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        $error_code = curl_errno($ch);
        curl_close($ch);
        log_message('ERROR', 'Zabbix API cURL error', ['error' => $error_msg, 'code' => $error_code, 'method' => $method]);
        throw new Exception("Error cURL al realizar la petición a Zabbix: " . $error_msg);
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($result, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message('ERROR', 'Zabbix API JSON decode error', ['error' => json_last_error_msg(), 'method' => $method]);
        throw new Exception("Error al decodificar la respuesta JSON de Zabbix: " . json_last_error_msg());
    }

    if (isset($decoded['error'])) {
        log_message('ERROR', 'Zabbix API error response', [
            'error' => $decoded['error']['message'],
            'code' => $decoded['error']['code'],
            'method' => $method
        ]);
        throw new Exception("Error de la API de Zabbix: " . $decoded['error']['message'] . " (Código: " . $decoded['error']['code'] . ")");
    }

    if ($http_code !== 200) {
        log_message('WARNING', 'Zabbix API non-200 HTTP response', ['http_code' => $http_code, 'method' => $method]);
    }

    return $decoded['result'] ?? [];
}

/**
 * Obtiene información de la base de datos `inventario_equipos` basada en el hostname.
 * 
 * CORREGIDO: Ahora busca por hostname_zabbix en lugar de nombre del cliente.
 *
 * @param string $hostname El hostname del equipo a buscar (nombre del host en Zabbix).
 * @return array Los datos del inventario o un array vacío si no se encuentra.
 */
function get_inventory_data(string $hostname): array
{
    if (empty($hostname)) {
        return [];
    }
    
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    if ($mysqli->connect_errno) {
        log_message('ERROR', 'Database connection failed in get_inventory_data', [
            'error' => $mysqli->connect_error,
            'hostname' => $hostname
        ]);
        return [];
    }

    $mysqli->set_charset('utf8mb4');

    // Buscar por hostname (nombre del host en Zabbix)
    // También intentamos buscar por nombre_equipo como fallback
    $stmt = $mysqli->prepare("SELECT nombre_equipo, sub_unidad, unidad FROM " . DB_TABLE . " WHERE hostname = ? OR nombre_equipo = ? LIMIT 1");
    if (!$stmt) {
        log_message('ERROR', 'SQL preparation failed in get_inventory_data', [
            'error' => $mysqli->error,
            'hostname' => $hostname
        ]);
        $mysqli->close();
        return [];
    }

    $stmt->bind_param("ss", $hostname, $hostname);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    if ($row = $result->fetch_assoc()) {
        $data = $row;
        log_message('DEBUG', 'Inventory data found', ['hostname' => $hostname, 'data' => $data]);
    }

    $stmt->close();
    $mysqli->close();

    return $data;
}

// === Calcular timestamp de hace una hora ===
$una_hora_atras = time() - 3600;

try {
    log_message('INFO', 'Starting data retrieval from Zabbix', ['timestamp_filter' => $una_hora_atras]);
    
    // === Paso 1 a 3: Obtener ítems de Zabbix ===
    $items = zabbix_api_request('item.get', [
        'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'hostid'],
        'search' => ['key_' => 'aruba.client.'],
        'sortfield' => 'name',
        'limit' => 10000,
        'filter' => [
            'lastclock' => $una_hora_atras . ':' . time()
        ]
    ]);

    $mac_items = zabbix_api_request('item.get', [
        'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'hostid'],
        'search' => ['key_' => 'aruba.mcaddress.cliente'],
        'sortfield' => 'name',
        'limit' => 10000,
        'filter' => [
            'lastclock' => $una_hora_atras . ':' . time()
        ]
    ]);

    $throughput_items = zabbix_api_request('item.get', [
        'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock', 'hostid'],
        'search' => ['key_' => 'aruba.client.troughput'],
        'sortfield' => 'name',
        'limit' => 10000,
        'filter' => [
            'lastclock' => $una_hora_atras . ':' . time()
        ]
    ]);

    // Unimos todos los ítems obtenidos
    $items = array_merge($items, $mac_items, $throughput_items);
    
    log_message('INFO', 'Zabbix items retrieved', [
        'total_items' => count($items),
        'client_items' => count($items) - count($mac_items) - count($throughput_items),
        'mac_items' => count($mac_items),
        'throughput_items' => count($throughput_items)
    ]);

    // === Obtener nombres de hosts de Zabbix para luego buscar en la BD ===
    $host_ids = array_unique(array_column($items, 'hostid'));
    $hosts_zabbix = zabbix_api_request('host.get', [
        'output' => ['hostid', 'name'],
        'hostids' => $host_ids,
    ]);
    
    // Mapeamos los IDs de host a sus nombres para una búsqueda más rápida.
    $host_names_map = array_column($hosts_zabbix, 'name', 'hostid');
    
    log_message('DEBUG', 'Host names mapped', ['total_hosts' => count($host_names_map)]);

    // === Paso 4: Agrupar datos por SNMPINDEX ===
    $data_by_index = [];

    foreach ($items as $item) {
        if (preg_match('/\[(.*?)\]/', $item['key_'], $matches)) {
            $index = $matches[1];
            $timestamp = intval($item['lastclock'] ?? 0);
            // Convertir timestamp de UTC a zona horaria de Ecuador
            $fecha_formateada = $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '';

            if (!isset($data_by_index[$index])) {
                $data_by_index[$index] = [
                    'snmpindex' => $index,
                    'mac' => '',
                    'ip_cliente' => '',
                    'ip_ap' => '',
                    'nombre' => '',
                    'os' => '',
                    'snr' => '',
                    'rx_bytes' => '',
                    'tx_bytes' => '',
                    'rx_rate' => '',
                    'tx_rate' => '',
                    'rx_throughput' => '',
                    'tx_throughput' => '',
                    'uptime' => '',
                    'lastdate' => $fecha_formateada,
                    'hostname_zabbix' => $host_names_map[$item['hostid']] ?? 'Desconocido',
                    'nombre_equipo' => '',
                    'sub_unidad' => '',
                    'unidad' => ''
                ];
            }

            $key = $item['key_'];
            $val = $item['lastvalue'];

            if (str_starts_with($key, 'aruba.mcaddress.cliente')) {
                $data_by_index[$index]['mac'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.ip.address')) {
                $data_by_index[$index]['ip_cliente'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.ap.ip.address')) {
                $data_by_index[$index]['ip_ap'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.name')) {
                $data_by_index[$index]['nombre'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.operating.system')) {
                $data_by_index[$index]['os'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.snr')) {
                $data_by_index[$index]['snr'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.rx.data.bytes')) {
                $data_by_index[$index]['rx_bytes'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.tx.data.bytes')) {
                $data_by_index[$index]['tx_bytes'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.rx.rate')) {
                $data_by_index[$index]['rx_rate'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.tx.rate')) {
                $data_by_index[$index]['tx_rate'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.rx.troughput')) {
                $data_by_index[$index]['rx_throughput'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.tx.troughput')) {
                $data_by_index[$index]['tx_throughput'] = $val;
            } elseif (str_starts_with($key, 'aruba.client.uptime')) {
                $data_by_index[$index]['uptime'] = $val;
                $data_by_index[$index]['lastdate'] = $fecha_formateada;
            }
        }
    }

    // === CORREGIDO: COTEJAR datos con la base de datos usando hostname_zabbix ===
    $inventory_matches = 0;
    foreach ($data_by_index as &$item) {
        if (!empty($item['hostname_zabbix']) && $item['hostname_zabbix'] !== 'Desconocido') {
            // CORRECCIÓN: Buscar por hostname_zabbix en lugar de nombre del cliente
            $inventory_data = get_inventory_data($item['hostname_zabbix']);
            if (!empty($inventory_data)) {
                $item['nombre_equipo'] = $inventory_data['nombre_equipo'] ?? '';
                $item['sub_unidad'] = $inventory_data['sub_unidad'] ?? '';
                $item['unidad'] = $inventory_data['unidad'] ?? '';
                $inventory_matches++;
            }
        }
    }
    unset($item);
    
    log_message('INFO', 'Inventory data matched', ['matches' => $inventory_matches, 'total_items' => count($data_by_index)]);

    // === Paso 5 y 6: Filtrar y Reindexar ===
    $datos_filtrados = array_filter($data_by_index, function ($item) {
        return !empty($item['mac']) && !empty($item['ip_cliente']);
    });

    $datos_finales = array_values($datos_filtrados);
    foreach ($datos_finales as $i => &$item) {
        $item['snmpindex'] = $i + 1;
    }
    unset($item);

    // === Paso 7: Responder en JSON ===
    $response = [
        'data' => $datos_finales,
        'timestamp_filtro' => $una_hora_atras,
        'fecha_filtro' => date('Y-m-d H:i:s', $una_hora_atras),
        'total_items_procesados' => count($items),
        'total_clientes_encontrados' => count($datos_finales),
        'inventory_matches' => $inventory_matches,
        'generado_en' => date('Y-m-d H:i:s'),
        'zona_horaria' => date_default_timezone_get()
    ];

    log_message('INFO', 'Data retrieval completed successfully', [
        'total_clients' => count($datos_finales),
        'inventory_matches' => $inventory_matches
    ]);

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    log_message('ERROR', 'Unhandled exception in obtener_datos', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    echo json_encode(['error' => 'Error interno del servidor. Consulte los logs para más detalles.']);
}
?>
