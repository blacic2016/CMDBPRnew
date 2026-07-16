<?php
declare(strict_types=1);

// Cargar configuración centralizada (incluye zona horaria)
require_once __DIR__ . '/wireless_config.php';

header('Content-Type: application/json');

/**
 * Obtiene los datos de la solicitud según el método HTTP
 */
function get_request_data(string $method): array {
    if ($method === 'GET') {
        return $_GET;
    }
    $data = file_get_contents('php://input');
    $decoded = json_decode($data, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }
    return [];
}

/**
 * Verifica si el usuario está autorizado usando hash de contraseñas
 */
function is_authorized(string $password): bool {
    if (empty($password)) {
        return false;
    }
    
    // Verificar rate limiting
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!check_rate_limit($clientIp)) {
        log_message('WARNING', 'Rate limit exceeded for authorization attempt', ['ip' => $clientIp]);
        return false;
    }
    
    $authorized = verify_admin_password($password);
    
    if ($authorized) {
        log_message('INFO', 'Successful authorization', ['ip' => $clientIp]);
    } else {
        log_message('WARNING', 'Failed authorization attempt', ['ip' => $clientIp]);
    }
    
    return $authorized;
}

/**
 * Valida y sanitiza datos de entrada
 */
function validate_and_sanitize(array $data, array $required_fields = []): array {
    $sanitized = [];
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[] = "Campo requerido faltante: {$field}";
        }
    }
    
    if (!empty($errors)) {
        throw new InvalidArgumentException(implode(', ', $errors));
    }
    
    return $sanitized;
}

// Conexión a la base de datos
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    log_message('ERROR', 'Database connection failed', ['error' => $mysqli->connect_error]);
    die(json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos.']));
}

// Establecer charset UTF-8
$mysqli->set_charset('utf8mb4');

// ----------------------------------------------------
// LÓGICA PRINCIPAL DEL INVENTARIO API
// ----------------------------------------------------

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $data = get_request_data($method);
    $response = ['success' => false, 'message' => 'Método de solicitud no válido.'];
    
    log_message('INFO', 'API request received', ['method' => $method, 'endpoint' => 'inventario_api']);

    switch ($method) {
        
        // OBTENER DATOS DEL INVENTARIO Y FILTROS (GET)
        case 'GET':
            // Usar prepared statements para filtros
            $where_clauses = [];
            $params = [];
            $types = '';
            
            if (!empty($data['unidad'])) {
                $where_clauses[] = "unidad = ?";
                $params[] = $data['unidad'];
                $types .= 's';
            }
            if (!empty($data['sub_unidad'])) {
                $where_clauses[] = "sub_unidad = ?";
                $params[] = $data['sub_unidad'];
                $types .= 's';
            }
            if (!empty($data['estado_actual'])) {
                $where_clauses[] = "estado_actual = ?";
                $params[] = $data['estado_actual'];
                $types .= 's';
            }
            
            $where = empty($where_clauses) ? '' : ' WHERE ' . implode(' AND ', $where_clauses);
            
            $sql_data = "SELECT id, nro, ip, fecha, ciudad, unidad, sub_unidad, nombre_equipo, hostname, observaciones, app_seguridad, nessus, aranda, hx_v35_31_28, dominio, estado_actual, fecha_creacion, fecha_actualizacion FROM " . DB_TABLE . $where . " ORDER BY id DESC";
            
            $stmt = $mysqli->prepare($sql_data);
            if ($stmt) {
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                $stmt->execute();
                $result_data = $stmt->get_result();
                
                $inventario_data = [];
                while ($row = $result_data->fetch_assoc()) {
                    $inventario_data[] = $row;
                }
                $stmt->close();
            } else {
                throw new Exception('Error en la preparación de la consulta: ' . $mysqli->error);
            }

            // Obtener filtros únicos usando prepared statements
            $unidades_unicas = [];
            $stmt_unidad = $mysqli->prepare("SELECT DISTINCT unidad FROM " . DB_TABLE . " WHERE unidad IS NOT NULL AND unidad != '' AND estado_actual = 'ACTIVO' ORDER BY unidad");
            if ($stmt_unidad) {
                $stmt_unidad->execute();
                $result_unidad = $stmt_unidad->get_result();
                while ($row = $result_unidad->fetch_assoc()) {
                    $unidades_unicas[] = $row['unidad'];
                }
                $stmt_unidad->close();
            }
            
            $sub_unidades_unicas = [];
            $stmt_sub = $mysqli->prepare("SELECT DISTINCT sub_unidad FROM " . DB_TABLE . " WHERE sub_unidad IS NOT NULL AND sub_unidad != '' AND estado_actual = 'ACTIVO' ORDER BY sub_unidad");
            if ($stmt_sub) {
                $stmt_sub->execute();
                $result_sub = $stmt_sub->get_result();
                while ($row = $result_sub->fetch_assoc()) {
                    $sub_unidades_unicas[] = $row['sub_unidad'];
                }
                $stmt_sub->close();
            }
            
            $response = [
                'success' => true,
                'data' => $inventario_data,
                'filters' => [
                    'unidad' => $unidades_unicas,
                    'sub_unidad' => $sub_unidades_unicas,
                ]
            ];
            log_message('INFO', 'GET request successful', ['records_returned' => count($inventario_data)]);
            break;

        // CREAR NUEVO REGISTRO (POST)
        case 'POST':
            if (!isset($data['password_admin']) || !is_authorized($data['password_admin'])) {
                http_response_code(401);
                $response = ['success' => false, 'message' => 'Código de autorización incorrecto.'];
                break;
            }
            
            // Validar campos requeridos
            $required_fields = ['ip', 'unidad', 'sub_unidad', 'nombre_equipo'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => "Campo requerido faltante: {$field}"];
                    break 2;
                }
            }
            
            $getValueOrNull = function($key) use ($data) {
                $value = trim((string)($data[$key] ?? ''));
                return $value === '' ? null : $value;
            };

            $nro = $getValueOrNull('nro');
            $ip = trim($data['ip']);
            $fecha = $getValueOrNull('fecha');
            $ciudad = $getValueOrNull('ciudad');
            $unidad = trim($data['unidad']);
            $sub_unidad = trim($data['sub_unidad']);
            $nombre_equipo = trim($data['nombre_equipo']);
            $hostname = $getValueOrNull('hostname');
            $observaciones = $getValueOrNull('observaciones');
            $app_seguridad = $getValueOrNull('app_seguridad');
            $nessus = $getValueOrNull('nessus');
            $aranda = $getValueOrNull('aranda');
            $hx_v35_31_28 = $getValueOrNull('hx_v35_31_28');
            $dominio = $getValueOrNull('dominio');
            
            $current_date = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO " . DB_TABLE . " (nro, ip, fecha, ciudad, unidad, sub_unidad, nombre_equipo, hostname, observaciones, app_seguridad, nessus, aranda, hx_v35_31_28, dominio, estado_actual, fecha_creacion, fecha_actualizacion) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVO', ?, ?)";
            
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssssssssssss", 
                    $nro, $ip, $fecha, $ciudad, $unidad, $sub_unidad, $nombre_equipo, $hostname, $observaciones, $app_seguridad, 
                    $nessus, $aranda, $hx_v35_31_28, $dominio, $current_date, $current_date
                );
                
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Equipo creado con éxito.', 'id' => $mysqli->insert_id];
                    log_message('INFO', 'Equipment created successfully', ['id' => $mysqli->insert_id, 'ip' => $ip]);
                } else {
                    log_message('ERROR', 'SQL Error in POST', ['error' => $stmt->error]);
                    $response = ['success' => false, 'message' => 'Error al crear equipo: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                log_message('ERROR', 'SQL preparation failed in POST', ['error' => $mysqli->error]);
                $response = ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $mysqli->error];
            }
            break;

        // ACTUALIZAR REGISTRO EXISTENTE (PUT)
        case 'PUT':
            if (!isset($data['password_admin']) || !is_authorized($data['password_admin'])) {
                http_response_code(401);
                $response = ['success' => false, 'message' => 'Código de autorización incorrecto.'];
                break;
            }
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                http_response_code(400); 
                $response = ['success' => false, 'message' => 'ID de registro (id) faltante o inválido para actualizar.'];
                break;
            }
            
            $id = (int)$data['id'];
            
            // Validar campos requeridos
            $required_fields = ['ip', 'unidad', 'sub_unidad', 'nombre_equipo'];
            foreach ($required_fields as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    $response = ['success' => false, 'message' => "Campo requerido faltante: {$field}"];
                    break 2;
                }
            }
            
            $ip = trim($data['ip']);
            $unidad = trim($data['unidad']);
            $sub_unidad = trim($data['sub_unidad']);
            $nombre_equipo = trim($data['nombre_equipo']);
            $observaciones = isset($data['observaciones']) ? trim($data['observaciones']) : null;
            
            $current_date = date('Y-m-d H:i:s');

            $sql = "UPDATE " . DB_TABLE . " SET 
                    ip=?, unidad=?, sub_unidad=?, nombre_equipo=?, observaciones=?, fecha_actualizacion=? 
                    WHERE id=?";
            
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ssssssi", 
                    $ip, $unidad, $sub_unidad, $nombre_equipo, $observaciones, $current_date, $id
                );
                
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response = ['success' => true, 'message' => 'Equipo actualizado con éxito.', 'fecha_actualizacion' => $current_date];
                        log_message('INFO', 'Equipment updated successfully', ['id' => $id]);
                    } else {
                        $response = ['success' => true, 'message' => 'Actualización enviada, no se detectaron cambios en los datos.'];
                        log_message('INFO', 'Equipment update with no changes', ['id' => $id]);
                    }
                } else {
                    log_message('ERROR', 'SQL Error in PUT', ['error' => $stmt->error, 'id' => $id]);
                    $response = ['success' => false, 'message' => 'Error al actualizar equipo: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                log_message('ERROR', 'SQL preparation failed in PUT', ['error' => $mysqli->error]);
                $response = ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $mysqli->error];
            }
            break;

        // DESACTIVAR REGISTRO (DELETE)
        case 'DELETE':
            if (!isset($data['password_admin']) || !is_authorized($data['password_admin'])) {
                http_response_code(401);
                $response = ['success' => false, 'message' => 'Código de autorización incorrecto.'];
                break;
            }
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                http_response_code(400);
                $response = ['success' => false, 'message' => 'ID de registro (id) faltante o inválido.'];
                break;
            }
            
            $id = (int)$data['id'];
            $current_date = date('Y-m-d H:i:s');
            $nuevo_estado = 'DESACTIVADO';
            
            $sql = "UPDATE " . DB_TABLE . " SET estado_actual=?, fecha_actualizacion=? WHERE id=?";
            
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                if (false === $stmt->bind_param("ssi", $nuevo_estado, $current_date, $id)) {
                    log_message('ERROR', 'Bind param failed in DELETE', ['error' => $stmt->error]);
                    $response = ['success' => false, 'message' => 'Error de vinculación (DELETE): ' . $stmt->error];
                } elseif ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response = ['success' => true, 'message' => 'Equipo desactivado con éxito.'];
                        log_message('INFO', 'Equipment deactivated successfully', ['id' => $id]);
                    } else {
                        $response = ['success' => false, 'message' => 'Equipo no encontrado o ya estaba DESACTIVADO.'];
                        log_message('WARNING', 'Equipment deactivation failed - not found or already deactivated', ['id' => $id]);
                    }
                } else {
                    log_message('ERROR', 'SQL Error in DELETE', ['error' => $stmt->error, 'id' => $id]);
                    $response = ['success' => false, 'message' => 'Error al desactivar equipo: ' . $stmt->error];
                }
                $stmt->close();
            } else {
                log_message('ERROR', 'SQL preparation failed in DELETE', ['error' => $mysqli->error]);
                $response = ['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $mysqli->error];
            }
            break;

        default:
            http_response_code(405);
            $response = ['success' => false, 'message' => 'Método no permitido.'];
            log_message('WARNING', 'Invalid HTTP method', ['method' => $method]);
            break;
    }
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    $response = ['success' => false, 'message' => $e->getMessage()];
    log_message('WARNING', 'Validation error', ['error' => $e->getMessage()]);
} catch (\Exception $e) {
    http_response_code(500);
    $response = ['success' => false, 'message' => 'Error interno del servidor.'];
    log_message('ERROR', 'Unhandled exception', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
} finally {
    $mysqli->close();
    echo json_encode($response, JSON_PRETTY_PRINT);
}
?>
