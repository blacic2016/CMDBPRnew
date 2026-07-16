<?php
declare(strict_types=1); // Habilita el modo estricto para una mejor comprobación de tipos

// === CONFIGURACIÓN ===
define('ZABBIX_API_URL', 'http://172.32.1.50/zabbix/api_jsonrpc.php');
define('ZABBIX_API_TOKEN', '6cee3c04f2e648a0343598474e2ce3d77f3e7ba640fe5a48295d8ff30fefda1d');

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
        'id' => 1 // ID de la petición, útil para correlacionar peticiones/respuestas
    ];

    $ch = curl_init(ZABBIX_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Devuelve la transferencia como una cadena de texto
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload)); // Datos a enviar en la petición POST

    // *** CAMBIO CLAVE PARA ZABBIX 7.2: Autenticación por cabecera Bearer ***
    // Zabbix 7.0+ recomienda usar la cabecera 'Authorization: Bearer <token>'
    // en lugar del parámetro 'auth' en el payload JSON.
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . ZABBIX_API_TOKEN // Token de API en la cabecera
    ]);

    $result = curl_exec($ch);

    // --- Manejo de errores de cURL ---
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("Error cURL al realizar la petición a Zabbix: " . $error_msg);
    }
    curl_close($ch);

    $decoded = json_decode($result, true);

    // --- Manejo de errores de decodificación JSON ---
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error al decodificar la respuesta JSON de Zabbix: " . json_last_error_msg() . ". Respuesta original: " . $result);
    }

    // --- Manejo de errores de la API de Zabbix (si la respuesta contiene un campo 'error') ---
    if (isset($decoded['error'])) {
        throw new Exception("Error de la API de Zabbix: " . $decoded['error']['message'] . " (Código: " . $decoded['error']['code'] . ")");
    }

    return $decoded['result'] ?? []; // Devuelve el array 'result' o un array vacío si no existe
}

try {
    // === Paso 1: Obtener ítems aruba.client.* (sin filtro de tiempo inicial) ===
    $items = zabbix_api_request('item.get', [
        'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock'],
        'search' => ['key_' => 'aruba.client.'],
        'sortfield' => 'name',
        'limit' => 10000 // Un límite alto para asegurar que se obtengan todos los ítems relevantes
    ]);

    // === Paso 2: Obtener ítems de MAC (aruba.mcaddress.cliente) (sin filtro de tiempo inicial) ===
    $mac_items = zabbix_api_request('item.get', [
        'output' => ['itemid', 'name', 'key_', 'lastvalue', 'lastclock'],
        'search' => ['key_' => 'aruba.mcaddress.cliente'],
        'sortfield' => 'name',
        'limit' => 10000
    ]);

    // Unimos todos los ítems obtenidos
    $items = array_merge($items, $mac_items);

    // === Paso 3: Agrupar datos por SNMPINDEX ===
    $data_by_index = [];

    foreach ($items as $item) {
        // Extrae el SNMPINDEX de la clave del ítem (ej. 'aruba.client.ip.address[123]' -> '123')
        if (preg_match('/\[(.*?)\]/', $item['key_'], $matches)) {
            $index = $matches[1];
            $timestamp = intval($item['lastclock'] ?? 0); // Asegura que lastclock es un entero
            $fecha_formateada = $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : '';

            // Inicializa la entrada para el SNMPINDEX si no existe
            if (!isset($data_by_index[$index])) {
                $data_by_index[$index] = [
                    'snmpindex' => $index, // Temporalmente el índice original
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
                    'uptime' => '',
                    'lastdate' => $fecha_formateada // Fecha de la última actualización
                ];
            }

            $key = $item['key_'];
            $val = $item['lastvalue'];

            // Asigna el valor al campo correspondiente basándose en la clave del ítem
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
            } elseif (str_starts_with($key, 'aruba.client.uptime')) {
                $data_by_index[$index]['uptime'] = $val;
                // Actualiza lastdate con el timestamp del uptime si es el más reciente
                $data_by_index[$index]['lastdate'] = $fecha_formateada;
            }
        }
    }

    // === Paso 4: Filtrar equipos sin MAC o IP cliente ===
    // Aseguramos que solo se incluyan entradas completas
    $datos_filtrados = array_filter($data_by_index, function ($item) {
        return !empty($item['mac']) && !empty($item['ip_cliente']);
    });

    // === Paso 5: Reemplazar snmpindex por un número secuencial y limpiar el array ===
    // 'array_values' reindexa el array numéricamente, y luego asignamos el índice secuencial.
    $datos_finales = array_values($datos_filtrados);
    foreach ($datos_finales as $i => &$item) {
        $item['snmpindex'] = $i + 1; // Asigna un índice secuencial comenzando desde 1
    }
    unset($item); // Rompe la referencia del último elemento para evitar efectos secundarios

    // === Información adicional en la respuesta ===
    $response = [
        'data' => $datos_finales,
        'total_items_procesados' => count($items), // Número total de ítems antes del filtrado por MAC/IP
        'total_clientes_encontrados' => count($datos_finales), // Número de clientes únicos con MAC y IP
        'generado_en' => date('Y-m-d H:i:s')
    ];

    // === Paso 6: Responder en JSON ===
    echo json_encode($response, JSON_PRETTY_PRINT); // JSON_PRETTY_PRINT para una salida más legible

} catch (Exception $e) {
    // Captura cualquier excepción lanzada por la función zabbix_api_request
    http_response_code(500); // Establece el código de estado HTTP a 500 (Error Interno del Servidor)
    echo json_encode(['error' => $e->getMessage()]);
}